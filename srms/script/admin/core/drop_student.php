<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = trim((string)($_GET['id'] ?? ''));
if ($id === '') {
	app_reply_redirect('danger', 'Invalid student selected.', '../students');
}

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
			app_reply_redirect('warning', 'Student record could not be fully deleted because of linked history. The account has been blocked instead.', '../students');
		} catch (Throwable $ignored) {
		}
	}
	app_reply_redirect('danger', 'Unable to delete student right now.', '../students');
}
