<?php

function app_default_permissions_for_level(int $level): array
{
	if (in_array($level, [0, 1, 9], true)) {
		return ['*'];
	}

	switch ($level) {
		case 2:
			return ['attendance.manage','marks.enter','report.view'];
		case 5:
			return ['finance.manage','finance.view'];
		case 6:
			return ['staff.manage'];
		case 7:
			return ['transport.manage'];
		case 8:
			return ['library.manage'];
		default:
			return [];
	}
}

function app_get_permissions(PDO $conn, string $staffId, string $level): array
{
	if (isset($GLOBALS['super_admin']) && $GLOBALS['super_admin'] === true) {
		return ['*'];
	}
	$levelInt = (int)$level;
	$defaults = app_default_permissions_for_level($levelInt);
	if (in_array('*', $defaults, true)) {
		return ['*'];
	}

	if (!app_table_exists($conn, 'tbl_user_roles') || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_permissions')) {
		return $defaults;
	}

	try {
		$stmt = $conn->prepare("SELECT p.code
			FROM tbl_user_roles ur
			JOIN tbl_role_permissions rp ON rp.role_id = ur.role_id
			JOIN tbl_permissions p ON p.id = rp.permission_id
			WHERE ur.staff_id = ?");
		$stmt->execute([(int)$staffId]);
		$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
		if (!$rows || count($rows) === 0) {
			return $defaults;
		}
		return array_values(array_unique(array_map('strval', $rows)));
	} catch (Throwable $e) {
		return $defaults;
	}
}

function app_has_permission(PDO $conn, string $staffId, string $level, string $permission): bool
{
	$perms = app_get_permissions($conn, $staffId, $level);
	if (in_array('*', $perms, true)) {
		return true;
	}
	return in_array($permission, $perms, true);
}

function app_require_permission(string $permission, string $redirect = '../'): void
{
	if (!isset($_SESSION)) {
		session_start();
	}

	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$allowed = app_has_permission($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level'], $permission);
		if (!$allowed) {
			$_SESSION['reply'] = array (array("danger", "Access denied: missing permission ($permission)."));
			$redirect = app_normalize_redirect_target($redirect);
			header("location:$redirect");
			exit;
		}
	} catch (Throwable $e) {
		$_SESSION['reply'] = array (array("danger", "Permission check failed."));
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}
}

function app_module_locked(PDO $conn, string $module): bool
{
	if (!app_table_exists($conn, 'tbl_module_locks')) {
		return false;
	}
	try {
		$stmt = $conn->prepare("SELECT locked FROM tbl_module_locks WHERE module = ? LIMIT 1");
		$stmt->execute([$module]);
		return (int)$stmt->fetchColumn() === 1;
	} catch (Throwable $e) {
		return false;
	}
}

function app_require_unlocked(string $module, string $redirect = '../'): void
{
	if (!isset($_SESSION)) {
		session_start();
	}

	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if (app_has_permission($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level'], 'system.manage')) {
			return;
		}
		if (app_module_locked($conn, $module)) {
			$_SESSION['reply'] = array (array("danger", "Module locked by Super Admin."));
			$redirect = app_normalize_redirect_target($redirect);
			header("location:$redirect");
			exit;
		}
	} catch (Throwable $e) {
		$_SESSION['reply'] = array (array("danger", "Module lock check failed."));
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}
}

function app_normalize_redirect_target(string $redirect): string
{
	$redirect = trim($redirect);
	if ($redirect === '' || $redirect === '../' || str_starts_with($redirect, '../') || str_starts_with($redirect, './') || str_starts_with($redirect, '/') || preg_match('/^https?:/i', $redirect)) {
		return $redirect === '' ? '../' : $redirect;
	}

	$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
	$dir = trim((string)dirname($scriptName), '/');
	if ($dir === '') {
		return $redirect;
	}

	return '../' . ltrim($redirect, '/');
}
