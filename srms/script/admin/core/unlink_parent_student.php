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
	header("location:../parents");
	exit;
}

$parentId = (int)($_POST['parent_id'] ?? 0);
$studentId = trim((string)($_POST['student_id'] ?? ''));

if ($parentId < 1 || $studentId === '') {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../parents");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("DELETE FROM tbl_parent_students WHERE parent_id = ? AND student_id = ?");
	$stmt->execute([$parentId, $studentId]);

	app_audit_log($conn, 'staff', (string)$account_id, 'parent.unlink_student', 'parent', (string)$parentId);

	$_SESSION['reply'] = array(array("success", "Student unlinked."));
	header("location:../parents");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../parents");
	exit;
}

