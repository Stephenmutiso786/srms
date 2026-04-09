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

$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE session_key = ?");
$stmt->execute([$session_key]);


app_clear_auth_cookies();

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
header("location:./");
?>
