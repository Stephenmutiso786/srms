<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rand.php');

if ($res !== "1") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../../");
	exit;
}

$currentSessionKey = (string)($_COOKIE['__SRMS__key'] ?? '');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_impersonation_schema($conn);

	$imp = app_impersonation_session_by_impersonated_key($conn, $currentSessionKey);
	if (!$imp) {
		$_SESSION['reply'] = array(array('warning', 'No active impersonation session found.'));
		header('location:../../');
		exit;
	}

	$adminId = (int)($imp['admin_staff_id'] ?? 0);
	$adminLevel = (string)($imp['admin_level'] ?? '0');
	$adminSessionKey = (string)($imp['admin_session_key'] ?? '');
	if ($adminId < 1 || $adminSessionKey === '') {
		throw new RuntimeException('Original admin session is missing.');
	}

	$stmt = $conn->prepare("UPDATE tbl_impersonation_sessions
		SET is_active = 0,
			ended_at = CURRENT_TIMESTAMP,
			stopped_by = ?,
			reason = CASE WHEN reason = '' THEN 'manual_stop' ELSE reason END
		WHERE id = ?");
	$stmt->execute([$adminId, (int)$imp['id']]);

	$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE session_key = ?");
	$stmt->execute([$currentSessionKey]);

	$stmt = $conn->prepare("SELECT id, level, status FROM tbl_staff WHERE id = ? LIMIT 1");
	$stmt->execute([$adminId]);
	$admin = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$admin || (string)($admin['status'] ?? '0') !== '1') {
		throw new RuntimeException('Original admin account is no longer active.');
	}

	$stmt = $conn->prepare("SELECT session_key FROM tbl_login_sessions WHERE session_key = ? AND staff = ? LIMIT 1");
	$stmt->execute([$adminSessionKey, $adminId]);
	$restoredKey = (string)$stmt->fetchColumn();
	if ($restoredKey === '') {
		$restoredKey = mb_strtoupper(GRS(20));
		$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
		$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, ip_address) VALUES (?,?,?)");
		$stmt->execute([$restoredKey, $adminId, $ip]);
	}

	$adminLevel = (string)($admin['level'] ?? $adminLevel);
	app_issue_auth_cookies($adminLevel, $restoredKey, false, 4320);
	if (isset($_SESSION['impersonation'])) {
		unset($_SESSION['impersonation']);
	}
	app_clear_impersonation_banner_cookie();

	app_audit_log($conn, 'staff', (string)$adminId, 'auth.impersonation.stop', (string)($imp['target_type'] ?? 'user'), (string)($imp['target_id'] ?? ''), [
		'session_id' => (string)($imp['id'] ?? ''),
	]);

	$portal = app_staff_login_portal($conn, $adminId, $adminLevel);
	$_SESSION['reply'] = array(array('success', 'Impersonation stopped. Returned to your account.'));
	header('location:../../' . $portal);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Unable to stop impersonation: ' . $e->getMessage()));
	header('location:../../');
	exit;
}
