<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}
app_require_permission('staff.manage', '../role_matrix');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../role_matrix');
	exit;
}

$roleId = (int)($_POST['role_id'] ?? 0);
$permissionId = (int)($_POST['permission_id'] ?? 0);
$returnTo = trim((string)($_POST['return_to'] ?? '../role_matrix'));
if ($returnTo === '') {
	$returnTo = '../role_matrix';
}

if ($roleId < 1 || $permissionId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid role or permission selection.'));
	header('location:' . app_normalize_redirect_target($returnTo));
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_roles') || !app_table_exists($conn, 'tbl_permissions')) {
		$_SESSION['reply'] = array(array('danger', 'RBAC tables missing. Run migration 012.'));
		header('location:' . app_normalize_redirect_target($returnTo));
		exit;
	}

	$stmt = $conn->prepare('SELECT 1 FROM tbl_roles WHERE id = ? LIMIT 1');
	$stmt->execute([$roleId]);
	if (!$stmt->fetchColumn()) {
		$_SESSION['reply'] = array(array('danger', 'Selected role does not exist.'));
		header('location:' . app_normalize_redirect_target($returnTo));
		exit;
	}

	$stmt = $conn->prepare('SELECT 1 FROM tbl_permissions WHERE id = ? LIMIT 1');
	$stmt->execute([$permissionId]);
	if (!$stmt->fetchColumn()) {
		$_SESSION['reply'] = array(array('danger', 'Selected permission does not exist.'));
		header('location:' . app_normalize_redirect_target($returnTo));
		exit;
	}

	$stmt = $conn->prepare('SELECT 1 FROM tbl_role_permissions WHERE role_id = ? AND permission_id = ? LIMIT 1');
	$stmt->execute([$roleId, $permissionId]);
	$exists = (bool)$stmt->fetchColumn();

	if ($exists) {
		$stmt = $conn->prepare('DELETE FROM tbl_role_permissions WHERE role_id = ? AND permission_id = ?');
		$stmt->execute([$roleId, $permissionId]);
		$_SESSION['reply'] = array(array('success', 'Permission removed from role.'));
	} else {
		$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
		if ($isPgsql) {
			$stmt = $conn->prepare('INSERT INTO tbl_role_permissions (role_id, permission_id) VALUES (?, ?) ON CONFLICT DO NOTHING');
		} else {
			$stmt = $conn->prepare('INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id) VALUES (?, ?)');
		}
		$stmt->execute([$roleId, $permissionId]);
		$_SESSION['reply'] = array(array('success', 'Permission added to role.'));
	}

	header('location:' . app_normalize_redirect_target($returnTo));
} catch (Throwable $e) {
	error_log('[' . __FILE__ . ':' . __LINE__ . ' Throwable] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Operation failed. Please try again.'));
	header('location:' . app_normalize_redirect_target($returnTo));
}
