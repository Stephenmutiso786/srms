<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

app_require_authentication([], ['students.manage']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../parents');
	exit;
}

$parentId = (int)($_POST['parent_id'] ?? 0);
if ($parentId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid parent selected.'));
	header('location:../parents');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if (app_table_exists($conn, 'tbl_parent_students')) {
		$stmt = $conn->prepare('DELETE FROM tbl_parent_students WHERE parent_id = ?');
		$stmt->execute([$parentId]);
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'parent')) {
		$stmt = $conn->prepare('DELETE FROM tbl_login_sessions WHERE parent = ?');
		$stmt->execute([$parentId]);
	}

	$stmt = $conn->prepare('DELETE FROM tbl_parents WHERE id = ?');
	$stmt->execute([$parentId]);
	$conn->commit();

	app_audit_log($conn, 'staff', (string)$account_id, 'parent.delete', 'parent', (string)$parentId);
	$_SESSION['reply'] = array(array('success', 'Parent deleted successfully.'));
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage() !== '' ? $e->getMessage() : 'Failed to delete parent.'));
}

header('location:../parents');
exit;
