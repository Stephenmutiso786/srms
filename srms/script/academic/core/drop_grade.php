<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = (int)($_GET['id'] ?? 0);
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("DELETE FROM tbl_grade_system WHERE id = ?");
	$stmt->execute([$id]);
	app_reply_redirect('success', 'Grade deleted.', '../grading-system');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Unable to delete grade. It may still be used by existing reports.', '../grading-system');
}
