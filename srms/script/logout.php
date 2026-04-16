<?php
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

$session_key = $_COOKIE['__SRMS__key'] ?? '';

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (isset($res) && $res === "1" && isset($level) && isset($account_id)) {
	$actorType = 'staff';
	if ($level === "3") { $actorType = 'student'; }
	if ($level === "4") { $actorType = 'parent'; }
	app_audit_log($conn, $actorType, (string)$account_id, 'auth.logout', 'session', (string)$session_key);
}

if (app_table_exists($conn, 'tbl_impersonation_sessions')) {
	$stmt = $conn->prepare("UPDATE tbl_impersonation_sessions
		SET is_active = 0,
			ended_at = COALESCE(ended_at, CURRENT_TIMESTAMP),
			stopped_by = CASE WHEN stopped_by IS NULL THEN ? ELSE stopped_by END,
			reason = CASE WHEN reason = '' THEN 'logout' ELSE reason END
		WHERE impersonated_session_key = ?
		AND COALESCE(is_active, 1) = 1
		AND ended_at IS NULL");
	$stmt->execute([isset($account_id) ? (int)$account_id : null, $session_key]);
}

$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE session_key = ?");
$stmt->execute([$session_key]);


app_clear_auth_cookies();
app_clear_impersonation_banner_cookie();

}catch(PDOException $e)
{
error_log('[logout] ' . $e->getMessage());
}
header("location:./");
?>
