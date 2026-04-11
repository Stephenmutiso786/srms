<?php
session_start();
chdir('../../');
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
	app_reply_redirect('danger', 'Invalid academic account selected.', '../academic');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();
	app_delete_staff($conn, [(string)$id]);
	$conn->commit();
	app_reply_redirect('success', 'Academic account deleted successfully.', '../academic');
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	if (isset($conn) && app_table_exists($conn, 'tbl_staff') && app_column_exists($conn, 'tbl_staff', 'status')) {
		try {
			$stmt = $conn->prepare("UPDATE tbl_staff SET status = 0 WHERE id = ?");
			$stmt->execute([$id]);
			app_reply_redirect('warning', 'Academic account could not be fully deleted because linked history exists. The account has been blocked instead.', '../academic');
		} catch (Throwable $ignored) {
		}
	}
	app_reply_redirect('danger', 'Unable to delete academic account right now.', '../academic');
}
