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
$sessionsPerDay = max(1, min(8, (int)($_POST['sessions_per_day'] ?? 6)));
$durationMinutes = max(30, min(180, (int)($_POST['duration_minutes'] ?? 40)));
$breakMinutes = max(0, min(60, (int)($_POST['break_minutes'] ?? 10)));
$firstStartTime = trim((string)($_POST['first_start_time'] ?? '08:00'));
$roomPrefix = trim((string)($_POST['room_prefix'] ?? 'Class Room'));
$clearExisting = (int)($_POST['clear_existing'] ?? 1) === 1;
$days = array_values(array_filter(array_map('trim', explode(',', (string)($_POST['days'] ?? '')))));

if ($classId < 1 || $termId < 1 || $year < 2000 || empty($days)) {
	app_reply_redirect('danger', 'Missing timetable generation details.', '../school_timetable');
}

function app_timetable_time_add(string $time, int $minutes): string {
	$dt = new DateTime('1970-01-01 '.$time.':00');
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

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_timetable_table($conn);
	if (!app_table_exists($conn, 'tbl_teacher_assignments')) {
		throw new RuntimeException('Teacher allocations are required before generating the school timetable.');
	}

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

	if (!$assignments) {
		throw new RuntimeException('No teacher allocations found for this class, term, and year.');
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

	if ($clearExisting) {
		$stmt = $conn->prepare("DELETE FROM tbl_school_timetable WHERE class_id = ? AND term_id = ?");
		$stmt->execute([$classId, $termId]);
		$existingRows = array_values(array_filter($existingRows, function ($row) use ($classId) {
			return (int)$row['class_id'] !== (int)$classId;
		}));
	}

	$conn->beginTransaction();
	$insert = $conn->prepare("INSERT INTO tbl_school_timetable (term_id, class_id, subject_id, teacher_id, day_name, session_label, start_time, end_time, room, created_by)
		VALUES (?,?,?,?,?,?,?,?,?,?)");

	$created = 0;
	$slotIndex = 0;
	$roomNo = 1;
	foreach ($assignments as $assignment) {
		$placed = false;
		for ($attempt = 0; $attempt < max(1, count($days) * $sessionsPerDay * 4); $attempt++) {
			$dayIndex = $slotIndex % count($days);
			$sessionIndex = (int)floor($slotIndex / count($days)) % $sessionsPerDay;
			$minutesFromStart = $sessionIndex * ($durationMinutes + $breakMinutes);
			$startTime = app_timetable_time_add($firstStartTime, $minutesFromStart);
			$endTime = app_timetable_time_add($startTime, $durationMinutes);
			$candidate = [
				'day_name' => $days[$dayIndex],
				'start_time' => $startTime,
				'end_time' => $endTime,
				'room' => trim($roomPrefix.' '.$roomNo),
				'class_id' => $classId,
				'teacher_id' => (int)$assignment['teacher_id'],
			];

			if (!app_timetable_slot_conflict($candidate, $existingRows)) {
				$sessionLabel = 'Session '.($sessionIndex + 1);
				$insert->execute([
					$termId,
					$classId,
					(int)$assignment['subject_id'],
					(int)$assignment['teacher_id'],
					$candidate['day_name'],
					$sessionLabel,
					$candidate['start_time'],
					$candidate['end_time'],
					$candidate['room'],
					(int)$account_id
				]);
				$existingRows[] = $candidate;
				$created++;
				$slotIndex++;
				$roomNo = ($roomNo % max(1, $sessionsPerDay)) + 1;
				$placed = true;
				break;
			}
			$slotIndex++;
		}

		if (!$placed) {
			throw new RuntimeException('Could not find a conflict-free timetable slot for '.$assignment['subject_name'].'.');
		}
	}

	$conn->commit();
	app_reply_redirect('success', 'School timetable generated for '.$created.' lesson slot(s).', '../school_timetable?class_id='.$classId.'&term_id='.$termId);
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	app_reply_redirect('danger', 'Failed to generate school timetable: '.$e->getMessage(), '../school_timetable?class_id='.$classId.'&term_id='.$termId);
}
