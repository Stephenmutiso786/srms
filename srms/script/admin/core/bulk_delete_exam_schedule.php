<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "0") {
	header("location:../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$ids = $_POST['schedule_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one timetable entry to delete"));
	header("location:../exam_timetable?class_id=".(int)($_POST['class_id'] ?? 0)."&term_id=".(int)($_POST['term_id'] ?? 0));
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("DELETE FROM tbl_exam_schedule WHERE id IN ($placeholders)");
	$stmt->execute($ids);
	$_SESSION['reply'] = array (array("success","Selected timetable entries deleted successfully"));
	header("location:../exam_timetable?class_id=".$classId."&term_id=".$termId);
} catch(PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}
?>
