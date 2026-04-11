<?php
session_start();
require_once(__DIR__ . '/_common.php');

api_apply_cors();

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$user = null;
	if (($res ?? '0') === '1') {
		$user = api_session_user();
	}
	$sessionKey = (string)($_COOKIE['__SRMS__key'] ?? '');
	if ($sessionKey !== '' && app_table_exists($conn, 'tbl_login_sessions')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE session_key = ?");
		$stmt->execute([$sessionKey]);
	}
	if ($user) {
		$actorType = $user['portal'] === 'student' ? 'student' : ($user['portal'] === 'parent' ? 'parent' : 'staff');
		app_audit_log($conn, $actorType, (string)$user['id'], 'auth.logout', 'session', $sessionKey);
	}
	app_clear_auth_cookies(true);
	api_json(['ok' => true]);
} catch (Throwable $e) {
	api_internal_error($e, 'api.logout');
}

