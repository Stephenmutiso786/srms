<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../exam_timetable");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$combId = (int)($_POST['subject_combination_id'] ?? 0);
$date = trim((string)($_POST['exam_date'] ?? ''));
$start = trim((string)($_POST['start_time'] ?? ''));
$end = trim((string)($_POST['end_time'] ?? ''));
$room = trim((string)($_POST['room'] ?? ''));
$invigilatorRaw = trim((string)($_POST['invigilator'] ?? ''));
$invigilator = $invigilatorRaw === '' ? null : (int)$invigilatorRaw;

if ($classId < 1 || $termId < 1 || $combId < 1 || $date === '' || $start === '' || $end === '') {
	$_SESSION['reply'] = array(array("error", "Please fill all required fields."));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
}

if ($end <= $start) {
	$_SESSION['reply'] = array(array("error", "End time must be after start time."));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_exam_schedule')) {
		$_SESSION['reply'] = array(array("error", "Exam timetable is not installed (run migration 005_exam_timetable.sql)."));
		header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
		exit;
	}

	// Validate combination belongs to class
	$stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations WHERE id = ? LIMIT 1");
	$stmt->execute([$combId]);
	$classList = $stmt->fetchColumn();
	if (!$classList) {
		$_SESSION['reply'] = array(array("error", "Invalid subject combination."));
		header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
		exit;
	}
	$list = app_unserialize((string)$classList);
	if (!in_array((string)$classId, array_map('strval', $list), true)) {
		$_SESSION['reply'] = array(array("error", "This subject combination is not assigned to the selected class."));
		header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
		exit;
	}

	$stmt = $conn->prepare("SELECT teacher FROM tbl_subject_combinations WHERE id = ? LIMIT 1");
	$stmt->execute([$combId]);
	$subjectTeacher = (int)$stmt->fetchColumn();

	// Conflict checks: overlapping time window same date
	$conf = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_schedule
		LEFT JOIN tbl_subject_combinations sc ON sc.id = tbl_exam_schedule.subject_combination_id
		WHERE exam_date = ? AND (
			(class_id = ?)
			OR (? <> '' AND room = ?)
			OR (? IS NOT NULL AND invigilator = ?)
			OR (? > 0 AND sc.teacher = ?)
		)
		AND (start_time < ? AND end_time > ?)");
	$roomKey = $room;
	$conf->execute([$date, $classId, $roomKey, $roomKey, $invigilator, $invigilator, $subjectTeacher, $subjectTeacher, $end, $start]);
	$cnt = (int)$conf->fetchColumn();
	if ($cnt > 0) {
		$_SESSION['reply'] = array(array("error", "Timetable conflict detected (class/room/invigilator/teacher overlap)."));
		header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_exam_schedule (term_id, class_id, subject_combination_id, exam_date, start_time, end_time, room, invigilator, created_by)
		VALUES (?,?,?,?,?,?,?,?,?)");
	$stmt->execute([$termId, $classId, $combId, $date, $start, $end, $room, $invigilator, (int)$account_id]);

	app_audit_log($conn, 'staff', (string)$account_id, 'exam_schedule.create', 'exam_schedule', (string)$conn->lastInsertId());

	$_SESSION['reply'] = array(array("success", "Timetable entry added."));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
}
