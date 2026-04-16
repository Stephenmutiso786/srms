<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}
app_require_permission('exams.manage', '../exam_timetable');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../exam_timetable");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$examId = (int)($_POST['exam_id'] ?? 0);
$startDate = trim((string)($_POST['start_date'] ?? ''));
$sessionsPerDay = max(1, min(6, (int)($_POST['sessions_per_day'] ?? 2)));
$durationMinutes = max(30, min(240, (int)($_POST['duration_minutes'] ?? 120)));
$breakMinutes = max(0, min(120, (int)($_POST['break_minutes'] ?? 30)));
$firstStartTime = trim((string)($_POST['first_start_time'] ?? '08:00'));
$roomsRaw = trim((string)($_POST['rooms'] ?? ''));
$rooms = array_values(array_filter(array_map('trim', explode(',', $roomsRaw))));

function app_schedule_time_add(string $time, int $minutes): string {
	$dt = new DateTime('1970-01-01 '.$time.':00');
	$dt->modify("+{$minutes} minutes");
	return $dt->format('H:i:s');
}

function app_schedule_overlap(array $candidate, array $existing): bool {
	return $candidate['date'] === $existing['date']
		&& $candidate['start_time'] < $existing['end_time']
		&& $candidate['end_time'] > $existing['start_time'];
}

function app_schedule_slot_conflicts(array $candidate, array $existingRows): bool {
	foreach ($existingRows as $existing) {
		if (!app_schedule_overlap($candidate, $existing)) {
			continue;
		}
		if ((int)$existing['class_id'] === (int)$candidate['class_id']) {
			return true;
		}
		if ((int)$existing['teacher_id'] > 0 && (int)$existing['teacher_id'] === (int)$candidate['teacher_id']) {
			return true;
		}
		if ($candidate['room'] !== '' && $existing['room'] !== '' && $existing['room'] === $candidate['room']) {
			return true;
		}
	}
	return false;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_subjects_table($conn);

	if (!app_table_exists($conn, 'tbl_exam_schedule')) {
		throw new RuntimeException("Exam timetable is not installed.");
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$examId, $classId, $termId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		throw new RuntimeException("Selected exam was not found for the chosen class and term.");
	}

	$subjectIds = app_exam_subject_ids($conn, $examId);
	if (empty($subjectIds)) {
		throw new RuntimeException("This exam has no selected subjects yet. Edit the exam and choose subjects first.");
	}

	$placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
	$assignmentRows = [];
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$params = array_merge([(int)$classId], $subjectIds, [(int)$termId]);
		$stmt = $conn->prepare("SELECT ta.subject_id, ta.teacher_id, s.name AS subject_name,
			concat_ws(' ', st.fname, st.lname) AS teacher_name
			FROM tbl_teacher_assignments ta
			JOIN tbl_subjects s ON s.id = ta.subject_id
			LEFT JOIN tbl_staff st ON st.id = ta.teacher_id
			WHERE ta.class_id = ? AND ta.subject_id IN ($placeholders) AND ta.term_id = ? AND ta.status = 1
			ORDER BY s.name, ta.year DESC, ta.id DESC");
		$stmt->execute($params);
		$assignmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (empty($assignmentRows) && app_table_exists($conn, 'tbl_subject_combinations')) {
		$stmt = $conn->prepare("SELECT sc.subject AS subject_id, sc.teacher AS teacher_id, sc.class AS class_list, s.name AS subject_name,
			concat_ws(' ', st.fname, st.lname) AS teacher_name
			FROM tbl_subject_combinations sc
			JOIN tbl_subjects s ON s.id = sc.subject
			LEFT JOIN tbl_staff st ON st.id = sc.teacher
			WHERE sc.subject IN ($placeholders)
			ORDER BY s.name, sc.id DESC");
		$stmt->execute($subjectIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$classList = app_unserialize((string)($row['class_list'] ?? ''));
			if (!in_array((string)$classId, array_map('strval', $classList), true)) {
				continue;
			}
			$assignmentRows[] = [
				'subject_id' => (int)$row['subject_id'],
				'teacher_id' => (int)$row['teacher_id'],
				'subject_name' => (string)$row['subject_name'],
				'teacher_name' => (string)($row['teacher_name'] ?? '')
			];
		}
	}

	$assignments = [];
	$seenSubjectIds = [];
	foreach ($assignmentRows as $row) {
		$subjectId = (int)$row['subject_id'];
		if (isset($seenSubjectIds[$subjectId])) {
			continue;
		}
		if ((int)$row['teacher_id'] < 1) {
			continue;
		}
		$comboId = app_get_teacher_subject_combination_id($conn, (int)$row['teacher_id'], $subjectId, (int)$classId, true);
		if ($comboId < 1) {
			continue;
		}
		$seenSubjectIds[$subjectId] = true;
		$assignments[] = [
			'subject_id' => $subjectId,
			'subject_name' => (string)$row['subject_name'],
			'teacher_id' => (int)$row['teacher_id'],
			'teacher_name' => (string)$row['teacher_name'],
			'subject_combination_id' => $comboId
		];
	}

	if (count($assignments) < 1) {
		throw new RuntimeException("No teacher allocations were found for the exam subjects in this class and term.");
	}

	usort($assignments, function ($a, $b) {
		return strcmp($a['subject_name'], $b['subject_name']);
	});

	$stmt = $conn->prepare("SELECT es.class_id, es.exam_date, es.start_time, es.end_time, es.room,
		COALESCE(sc.teacher, 0) AS teacher_id
		FROM tbl_exam_schedule es
		LEFT JOIN tbl_subject_combinations sc ON sc.id = es.subject_combination_id
		WHERE es.term_id = ?");
	$stmt->execute([$termId]);
	$existingRows = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$existingRows[] = [
			'class_id' => (int)$row['class_id'],
			'date' => (string)$row['exam_date'],
			'start_time' => substr((string)$row['start_time'], 0, 8),
			'end_time' => substr((string)$row['end_time'], 0, 8),
			'room' => (string)($row['room'] ?? ''),
			'teacher_id' => (int)($row['teacher_id'] ?? 0)
		];
	}

	$created = 0;
	$roomIndex = 0;
	$slotIndex = 0;
	$baseDate = new DateTime($startDate);
	$insert = $conn->prepare("INSERT INTO tbl_exam_schedule (term_id, class_id, subject_combination_id, exam_date, start_time, end_time, room, invigilator, created_by)
		VALUES (?,?,?,?,?,?,?,?,?)");

	foreach ($assignments as $assignment) {
		$scheduled = false;
		for ($attempt = 0; $attempt < 120; $attempt++) {
			$currentDate = clone $baseDate;
			$currentDate->modify('+' . floor($slotIndex / $sessionsPerDay) . ' day');
			$sessionInDay = $slotIndex % $sessionsPerDay;
			$minutesFromStart = $sessionInDay * ($durationMinutes + $breakMinutes);
			$slotStart = app_schedule_time_add($firstStartTime, $minutesFromStart);
			$slotEnd = app_schedule_time_add($slotStart, $durationMinutes);
			$room = !empty($rooms) ? $rooms[$roomIndex % count($rooms)] : '';

			$candidate = [
				'class_id' => $classId,
				'date' => $currentDate->format('Y-m-d'),
				'start_time' => $slotStart,
				'end_time' => $slotEnd,
				'room' => $room,
				'teacher_id' => (int)$assignment['teacher_id']
			];

			if (!app_schedule_slot_conflicts($candidate, $existingRows)) {
				$insert->execute([
					$termId,
					$classId,
					$assignment['subject_combination_id'],
					$candidate['date'],
					$candidate['start_time'],
					$candidate['end_time'],
					$room,
					$assignment['teacher_id'],
					(int)$account_id
				]);
				$existingRows[] = $candidate;
				$created++;
				$slotIndex++;
				$roomIndex++;
				$scheduled = true;
				break;
			}

			$slotIndex++;
			$roomIndex++;
		}

		if (!$scheduled) {
			throw new RuntimeException("Could not find a conflict-free slot for ".$assignment['subject_name'].".");
		}
	}

	$_SESSION['reply'] = array(array("success", "Smart timetable generated for ".$created." subject(s)."));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", "Failed to generate timetable: ".$e->getMessage()));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
}
