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
$portal = strtolower(trim((string)($_POST['portal'] ?? 'admin')));
$moduleKey = strtolower(trim((string)($_POST['module_key'] ?? '')));
$returnTo = trim((string)($_POST['return_to'] ?? ('../role_matrix?portal=' . urlencode($portal))));
if ($returnTo === '') {
	$returnTo = '../role_matrix?portal=' . urlencode($portal);
}

if ($roleId < 1 || $portal === '' || $moduleKey === '') {
	$_SESSION['reply'] = array(array('danger', 'Invalid role or module selection.'));
	header('location:' . app_normalize_redirect_target($returnTo));
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_roles') || !app_table_exists($conn, 'tbl_user_roles')) {
		$_SESSION['reply'] = array(array('danger', 'RBAC tables missing. Run migration 012.'));
		header('location:' . app_normalize_redirect_target($returnTo));
		exit;
	}

	if (!app_ensure_role_module_allocations_table($conn)) {
		$_SESSION['reply'] = array(array('danger', 'Module allocation table could not be created.'));
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

	$catalog = app_portal_module_catalog($portal);
	$allocatable = false;
	foreach ($catalog as $module) {
		$catalogKey = strtolower(trim((string)($module['key'] ?? '')));
		$modulePermissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
		if ($catalogKey === $moduleKey && !empty($modulePermissions)) {
			$allocatable = true;
			break;
		}
	}

	if (!$allocatable) {
		$_SESSION['reply'] = array(array('danger', 'That module cannot be allocated in this portal.'));
		header('location:' . app_normalize_redirect_target($returnTo));
		exit;
	}

	$stmt = $conn->prepare('SELECT 1 FROM tbl_role_module_allocations WHERE role_id = ? AND portal = ? AND module_key = ? LIMIT 1');
	$stmt->execute([$roleId, $portal, $moduleKey]);
	$exists = (bool)$stmt->fetchColumn();

	if ($exists) {
		$stmt = $conn->prepare('DELETE FROM tbl_role_module_allocations WHERE role_id = ? AND portal = ? AND module_key = ?');
		$stmt->execute([$roleId, $portal, $moduleKey]);
		$_SESSION['reply'] = array(array('success', 'Module removed from role sidebar allocation.'));
	} else {
		$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
		if ($isPgsql) {
			$stmt = $conn->prepare('INSERT INTO tbl_role_module_allocations (role_id, portal, module_key) VALUES (?, ?, ?) ON CONFLICT DO NOTHING');
		} else {
			$stmt = $conn->prepare('INSERT IGNORE INTO tbl_role_module_allocations (role_id, portal, module_key) VALUES (?, ?, ?)');
		}
		$stmt->execute([$roleId, $portal, $moduleKey]);
		$_SESSION['reply'] = array(array('success', 'Module allocated to role sidebar.'));
	}

	header('location:' . app_normalize_redirect_target($returnTo));
} catch (Throwable $e) {
	error_log('[' . __FILE__ . ':' . __LINE__ . ' Throwable] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Operation failed. Please try again.'));
	header('location:' . app_normalize_redirect_target($returnTo));
}

