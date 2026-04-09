<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../exam_timetable");
	exit;
}

$id = (int)($_GET['id'] ?? 0);
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);

if ($id < 1) {
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("DELETE FROM tbl_exam_schedule WHERE id = ?");
	$stmt->execute([$id]);

	app_audit_log($conn, 'staff', (string)$account_id, 'exam_schedule.delete', 'exam_schedule', (string)$id);

	$_SESSION['reply'] = array(array("success", "Timetable entry deleted."));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", "Unable to delete timetable entry right now."));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
	exit;
}
