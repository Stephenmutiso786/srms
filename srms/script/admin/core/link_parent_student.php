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

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		$_SESSION['reply'] = array(array("error", "Parent module is not installed."));
		header("location:../parents");
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	if ($isPgsql) {
		$stmt = $conn->prepare("INSERT INTO tbl_parent_students (parent_id, student_id) VALUES (?,?) ON CONFLICT DO NOTHING");
	} else {
		$stmt = $conn->prepare("INSERT IGNORE INTO tbl_parent_students (parent_id, student_id) VALUES (?,?)");
	}
	$stmt->execute([$parentId, $studentId]);

	app_audit_log($conn, 'staff', (string)$account_id, 'parent.link_student', 'parent', (string)$parentId);

	$_SESSION['reply'] = array(array("success", "Student linked to parent."));
	header("location:../parents");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../parents");
	exit;
}

