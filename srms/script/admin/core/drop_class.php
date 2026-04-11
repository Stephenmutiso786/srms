<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = (int)($_GET['id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();
	[$ok, $message] = app_delete_class($conn, $id);
	if (!$ok) {
		$conn->rollBack();
		app_reply_redirect('danger', $message, '../classes');
	}
	$conn->commit();
	app_reply_redirect('success', $message, '../classes');
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('[admin.drop_class] ' . $e->getMessage());
	app_reply_redirect('danger', 'Unable to delete class right now. Remove linked records first or try again.', '../classes');
}
