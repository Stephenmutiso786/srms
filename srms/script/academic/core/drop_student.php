<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = trim((string)($_GET['id'] ?? ''));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();
	app_delete_students($conn, [$id]);
	$conn->commit();
	app_reply_redirect('success', 'Student deleted successfully.', '../students');
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	if (isset($conn) && app_table_exists($conn, 'tbl_students') && app_column_exists($conn, 'tbl_students', 'status')) {
		try {
			$stmt = $conn->prepare("UPDATE tbl_students SET status = 0 WHERE id = ?");
			$stmt->execute([$id]);
			app_reply_redirect('warning', 'Student could not be fully deleted because linked history exists. The account has been blocked instead.', '../students');
		} catch (Throwable $ignored) {
		}
	}
	app_reply_redirect('danger', 'Unable to delete student right now.', '../students');
}
