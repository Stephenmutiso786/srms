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

$ids = $_POST['type_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one exam type to delete"));
	header("location:../exams");
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("DELETE FROM tbl_exam_types WHERE id IN ($placeholders)");
	$stmt->execute($ids);
	$_SESSION['reply'] = array (array("success","Selected exam types deleted successfully"));
	header("location:../exams");
} catch(PDOException $e) {
	echo "Connection failed: " . $e->getMessage();
}
?>
