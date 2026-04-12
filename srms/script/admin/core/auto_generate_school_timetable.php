<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../../"); exit; }
app_require_permission('academic.manage', '../school_timetable');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../school_timetable");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$year = (int)($_POST['year'] ?? date('Y'));
$generationScope = trim((string)($_POST['generation_scope'] ?? 'single_class'));
$sessionsPerDay = max(1, min(8, (int)($_POST['sessions_per_day'] ?? 6)));
$durationMinutes = max(30, min(180, (int)($_POST['duration_minutes'] ?? 40)));
$breakMinutes = max(0, min(60, (int)($_POST['break_minutes'] ?? 10)));
$firstStartTime = trim((string)($_POST['first_start_time'] ?? '08:00'));
$roomPrefix = trim((string)($_POST['room_prefix'] ?? 'Class Room'));
$clearExisting = (int)($_POST['clear_existing'] ?? 1) === 1;
$days = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['days'] ?? '')))));

if ($termId < 1 || $year < 2000 || empty($days)) {
	app_reply_redirect('danger', 'Missing timetable generation details.', '../school_timetable');
}

if (!in_array($generationScope, ['single_class', 'whole_school'], true)) {
	$generationScope = 'single_class';
}

if ($generationScope === 'single_class' && $classId < 1) {
	app_reply_redirect('danger', 'Select a class for single-class generation.', '../school_timetable?term_id=' . $termId);
}

function app_timetable_subject_weight(string $subjectName): int {
	$name = strtolower(trim($subjectName));
	$coreWords = ['math', 'mathematics', 'english', 'kiswahili', 'science', 'cre', 'social', 'language'];
	foreach ($coreWords as $word) {
		if (strpos($name, $word) !== false) {
			return 3;
		}
	}
	return 1;
}

function app_timetable_time_add(string $time, int $minutes): string {
	$normalized = trim($time);
	if (preg_match('/^\d{2}:\d{2}$/', $normalized)) {
		$normalized .= ':00';
	}
	$dt = new DateTime('1970-01-01 '.$normalized);
	$dt->modify("+{$minutes} minutes");
	return $dt->format('H:i:s');
}

function app_timetable_slot_conflict(array $candidate, array $existingRows): bool {
	foreach ($existingRows as $row) {
		if ($candidate['day_name'] !== $row['day_name']) {
			continue;
		}
		if ($candidate['start_time'] >= $row['end_time'] || $candidate['end_time'] <= $row['start_time']) {
			continue;
		}
		if ((int)$candidate['class_id'] === (int)$row['class_id']) {
			return true;
		}
		if ((int)$candidate['teacher_id'] > 0 && (int)$candidate['teacher_id'] === (int)$row['teacher_id']) {
			return true;
		}
		if ($candidate['room'] !== '' && $row['room'] !== '' && $candidate['room'] === $row['room']) {
			return true;
		}
	}
	return false;
}

function app_timetable_slot_key(array $slot): string {
	return (string)$slot['day_name'].'|'.(string)$slot['session_label'];
}

function app_timetable_fetch_assignments(PDO $conn, int $classId, int $termId, int $year): array {
	$stmt = $conn->prepare("SELECT ta.teacher_id, ta.subject_id, sb.name AS subject_name,
		concat_ws(' ', st.fname, st.lname) AS teacher_name
		FROM tbl_teacher_assignments ta
		JOIN tbl_subjects sb ON sb.id = ta.subject_id
		JOIN tbl_staff st ON st.id = ta.teacher_id
		WHERE ta.class_id = ? AND ta.term_id = ? AND ta.year = ? AND ta.status = 1
		ORDER BY sb.name, ta.id");
	$stmt->execute([$classId, $termId, $year]);

	$assignments = [];
	$seen = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$key = (int)$row['teacher_id'].'-'.(int)$row['subject_id'];
		if (isset($seen[$key])) {
			continue;
		}
		$seen[$key] = true;
		$assignments[] = $row;
	}
	return $assignments;
}

function app_timetable_build_for_class(PDO $conn, array $options, array &$existingRows): array {
	$classId = (int)$options['class_id'];
	$termId = (int)$options['term_id'];
	$year = (int)$options['year'];
	$days = $options['days'];
	$sessionsPerDay = (int)$options['sessions_per_day'];
	$durationMinutes = (int)$options['duration_minutes'];
	$breakMinutes = (int)$options['break_minutes'];
	$firstStartTime = (string)$options['first_start_time'];
	$roomPrefix = (string)$options['room_prefix'];
	$createdBy = (int)$options['created_by'];

	$assignments = app_timetable_fetch_assignments($conn, $classId, $termId, $year);
	if (empty($assignments)) {
		return ['created' => 0, 'status' => 'skipped'];
	}

	$insert = $conn->prepare("INSERT INTO tbl_school_timetable (term_id, class_id, subject_id, teacher_id, day_name, session_label, start_time, end_time, room, created_by)
		VALUES (?,?,?,?,?,?,?,?,?,?)");

	$weeklySlots = max(1, count($days) * $sessionsPerDay);
	$weightTotal = 0;
	foreach ($assignments as $assignment) {
		$weightTotal += app_timetable_subject_weight((string)$assignment['subject_name']);
	}
	$targets = [];
	$allocated = 0;
	foreach ($assignments as $idx => $assignment) {
		$weight = app_timetable_subject_weight((string)$assignment['subject_name']);
		$target = (int)floor(($weeklySlots * $weight) / max(1, $weightTotal));
		if ($target < 1) {
			$target = 1;
		}
		$targets[$idx] = $target;
		$allocated += $target;
	}
	while ($allocated < $weeklySlots) {
		foreach ($assignments as $idx => $assignment) {
			if ($allocated >= $weeklySlots) {
				break;
			}
			$targets[$idx]++;
			$allocated++;
		}
	}
	while ($allocated > $weeklySlots) {
		$changed = false;
		foreach ($assignments as $idx => $assignment) {
			if ($allocated <= $weeklySlots) {
				break;
			}
			if (($targets[$idx] ?? 1) > 1) {
				$targets[$idx]--;
				$allocated--;
				$changed = true;
			}
		}
		if (!$changed) {
			break;
		}
	}

	$lessonQueue = [];
	$remaining = $targets;
	while (count($lessonQueue) < $weeklySlots) {
		$progress = false;
		foreach ($assignments as $idx => $assignment) {
			if (($remaining[$idx] ?? 0) > 0) {
				$lessonQueue[] = $assignment;
				$remaining[$idx]--;
				$progress = true;
				if (count($lessonQueue) >= $weeklySlots) {
					break;
				}
			}
		}
		if (!$progress) {
			break;
		}
	}

	$created = 0;
	$roomNo = 1;
	$slotTemplate = [];
	for ($sessionIndex = 0; $sessionIndex < $sessionsPerDay; $sessionIndex++) {
		$minutesFromStart = $sessionIndex * ($durationMinutes + $breakMinutes);
		$startTime = app_timetable_time_add($firstStartTime, $minutesFromStart);
		$slotTemplate[] = [
			'session_label' => 'Session '.($sessionIndex + 1),
			'start_time' => $startTime,
			'end_time' => app_timetable_time_add($startTime, $durationMinutes),
		];
	}

	$allSlots = [];
	foreach ($slotTemplate as $slotMeta) {
		foreach ($days as $day) {
			$allSlots[] = [
				'day_name' => $day,
				'session_label' => $slotMeta['session_label'],
				'start_time' => $slotMeta['start_time'],
				'end_time' => $slotMeta['end_time'],
			];
		}
	}

	$usedSlotKeys = [];
	$subjectDailyCount = [];
	foreach ($lessonQueue as $assignment) {
		$bestSlot = null;
		$bestScore = PHP_INT_MAX;
		foreach ($allSlots as $slot) {
			$slotKey = app_timetable_slot_key($slot);
			if (isset($usedSlotKeys[$slotKey])) {
				continue;
			}

			$subjectId = (int)$assignment['subject_id'];
			$dailyLoad = (int)($subjectDailyCount[$subjectId][$slot['day_name']] ?? 0);
			if ($dailyLoad >= 2) {
				continue;
			}

			$candidate = [
				'day_name' => $slot['day_name'],
				'session_label' => $slot['session_label'],
				'start_time' => $slot['start_time'],
				'end_time' => $slot['end_time'],
				'room' => trim($roomPrefix.' '.$roomNo),
				'class_id' => $classId,
				'teacher_id' => (int)$assignment['teacher_id'],
			];

			if (app_timetable_slot_conflict($candidate, $existingRows)) {
				continue;
			}

			$spreadPenalty = $dailyLoad * 10;
			$dayLoad = 0;
			foreach ($subjectDailyCount as $subjectLoads) {
				$dayLoad += (int)($subjectLoads[$slot['day_name']] ?? 0);
			}
			$score = $spreadPenalty + $dayLoad;
			if ($score < $bestScore) {
				$bestScore = $score;
				$bestSlot = $candidate;
			}
		}

		if ($bestSlot === null) {
			throw new RuntimeException('Could not find a conflict-free timetable slot for ' . $assignment['subject_name'] . '.');
		}

		$insert->execute([
			$termId,
			$classId,
			(int)$assignment['subject_id'],
			(int)$assignment['teacher_id'],
			$bestSlot['day_name'],
			$bestSlot['session_label'],
			$bestSlot['start_time'],
			$bestSlot['end_time'],
			$bestSlot['room'],
			$createdBy
		]);
		$existingRows[] = $bestSlot;
		$usedSlotKeys[app_timetable_slot_key($bestSlot)] = true;
		$subjectId = (int)$assignment['subject_id'];
		if (!isset($subjectDailyCount[$subjectId])) {
			$subjectDailyCount[$subjectId] = [];
		}
		$subjectDailyCount[$subjectId][$bestSlot['day_name']] = (int)($subjectDailyCount[$subjectId][$bestSlot['day_name']] ?? 0) + 1;
		$created++;
		$roomNo = ($roomNo % max(1, $sessionsPerDay)) + 1;
	}

	return ['created' => $created, 'status' => 'generated'];
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_timetable_table($conn);
	if (!app_table_exists($conn, 'tbl_teacher_assignments')) {
		throw new RuntimeException('Teacher allocations are required before generating the school timetable.');
	}

	$stmt = $conn->prepare("SELECT day_name, start_time, end_time, room, class_id, teacher_id
		FROM tbl_school_timetable WHERE term_id = ?");
	$stmt->execute([$termId]);
	$existingRows = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$existingRows[] = [
			'day_name' => (string)$row['day_name'],
			'start_time' => substr((string)$row['start_time'], 0, 8),
			'end_time' => substr((string)$row['end_time'], 0, 8),
			'room' => (string)($row['room'] ?? ''),
			'class_id' => (int)$row['class_id'],
			'teacher_id' => (int)$row['teacher_id'],
		];
	}

	$classIds = [];
	if ($generationScope === 'whole_school') {
		$stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE term_id = ? AND year = ? AND status = 1 ORDER BY class_id");
		$stmt->execute([$termId, $year]);
		$classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		if (empty($classIds)) {
			throw new RuntimeException('No teacher allocations found for this term and year across the school.');
		}
	} else {
		$classIds = [$classId];
	}

	if ($clearExisting) {
		if ($generationScope === 'whole_school') {
			$stmt = $conn->prepare("DELETE FROM tbl_school_timetable WHERE term_id = ?");
			$stmt->execute([$termId]);
			$existingRows = [];
		} else {
			$stmt = $conn->prepare("DELETE FROM tbl_school_timetable WHERE class_id = ? AND term_id = ?");
			$stmt->execute([$classId, $termId]);
			$existingRows = array_values(array_filter($existingRows, function ($row) use ($classId) {
				return (int)$row['class_id'] !== (int)$classId;
			}));
		}
	}

	$conn->beginTransaction();
	$totalCreated = 0;
	$generatedClasses = 0;
	$skippedClasses = 0;
	foreach ($classIds as $targetClassId) {
		$result = app_timetable_build_for_class($conn, [
			'class_id' => (int)$targetClassId,
			'term_id' => $termId,
			'year' => $year,
			'days' => $days,
			'sessions_per_day' => $sessionsPerDay,
			'duration_minutes' => $durationMinutes,
			'break_minutes' => $breakMinutes,
			'first_start_time' => $firstStartTime,
			'room_prefix' => $roomPrefix,
			'created_by' => (int)$account_id,
		], $existingRows);

		$totalCreated += (int)$result['created'];
		if (($result['status'] ?? '') === 'generated') {
			$generatedClasses++;
		} else {
			$skippedClasses++;
		}
	}

	if ($generatedClasses < 1) {
		throw new RuntimeException('No class could be generated. Ensure teacher allocations exist for the selected term and year.');
	}

	$conn->commit();
	if ($generationScope === 'whole_school') {
		app_reply_redirect('success', 'Whole-school timetable generated: '.$totalCreated.' lesson slot(s) across '.$generatedClasses.' class(es)'.($skippedClasses > 0 ? ', '.$skippedClasses.' class(es) skipped (no allocations).' : '').'.', '../school_timetable?term_id='.$termId);
	}
	app_reply_redirect('success', 'School timetable generated for '.$totalCreated.' lesson slot(s).', '../school_timetable?class_id='.$classId.'&term_id='.$termId);
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$redirect = '../school_timetable?term_id='.$termId;
	if ($generationScope !== 'whole_school' && $classId > 0) {
		$redirect = '../school_timetable?class_id='.$classId.'&term_id='.$termId;
	}
	app_reply_redirect('danger', 'Failed to generate school timetable: '.$e->getMessage(), $redirect);
}
