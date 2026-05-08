<?php
session_start();
chdir('../../');
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || ((int)$level !== 1 && !app_current_user_has_any_permission(['staff.manage', 'academic.manage'])) || $_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
	app_reply_redirect('danger', 'Invalid teacher selected.', '../teachers');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$isSuperAdminController = app_is_super_admin_controller($conn, (string)($account_id ?? ''), (string)($level ?? ''));
	$stmt = $conn->prepare("SELECT level FROM tbl_staff WHERE id = ? LIMIT 1");
	$stmt->execute([$id]);
	$targetLevel = (string)($stmt->fetchColumn() ?: '');
	if ($targetLevel === '') {
		app_reply_redirect('danger', 'Staff record not found.', '../teachers');
	}
	if (app_staff_is_admin_managed($conn, $id, $targetLevel) && !$isSuperAdminController) {
		app_reply_redirect('danger', 'Only the super admin can delete leadership or admin accounts.', '../teachers');
	}
	$conn->beginTransaction();
	app_delete_staff($conn, [(string)$id]);
	$conn->commit();
	app_reply_redirect('success', 'Teacher deleted successfully.', '../teachers');
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	if (isset($conn) && app_table_exists($conn, 'tbl_staff') && app_column_exists($conn, 'tbl_staff', 'status')) {
		try {
			$stmt = $conn->prepare("UPDATE tbl_staff SET status = 0 WHERE id = ?");
			$stmt->execute([$id]);
			app_reply_redirect('warning', 'Teacher could not be fully deleted because linked history exists. The account has been blocked instead.', '../teachers');
		} catch (Throwable $ignored) {
		}
	}
	app_reply_redirect('danger', 'Unable to delete teacher right now.', '../teachers');
}
