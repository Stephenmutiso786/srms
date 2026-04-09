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
	$conn->beginTransaction();
	app_delete_subject($conn, $id);
	$conn->commit();
	app_reply_redirect('success', 'Subject deleted successfully.', '../subjects');
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	app_reply_redirect('danger', 'Unable to delete subject. Remove linked historical records first or unassign it from active workflows.', '../subjects');
}
