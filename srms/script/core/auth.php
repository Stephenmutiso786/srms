<?php
session_start();
chdir('../');
require_once('db/config.php');
require_once('const/rand.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$_username = $_POST['username'];
$_password = $_POST['password'];
$cookie_length = "4320";

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');

$hasParents = app_table_exists($conn, 'tbl_parents');
$hasParentSessions = $hasParents && app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'parent');

if ($isPgsql) {
	$sql = "SELECT id::text AS id, email, password, level, status FROM tbl_staff WHERE id::text = ? OR email = ?
UNION SELECT id::text AS id, email, password, level, status FROM tbl_students WHERE id::text = ? OR email = ?";
	$params = [$_username, $_username, $_username, $_username];
	if ($hasParents) {
		$sql .= "\nUNION SELECT id::text AS id, email, password, 4 AS level, status FROM tbl_parents WHERE id::text = ? OR email = ?";
		$params[] = $_username;
		$params[] = $_username;
	}
	$stmt = $conn->prepare($sql);
} else {
	$sql = "SELECT id, email, password, level, status FROM tbl_staff WHERE id = ? OR email = ?
UNION SELECT id, email, password, level, status FROM tbl_students WHERE id = ? OR email = ?";
	$params = [$_username, $_username, $_username, $_username];
	if ($hasParents) {
		$sql .= "\nUNION SELECT id, email, password, 4 AS level, status FROM tbl_parents WHERE id = ? OR email = ?";
		$params[] = $_username;
		$params[] = $_username;
	}
	$stmt = $conn->prepare($sql);
}

$stmt->execute($params);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$_SESSION['reply'] = array (array("danger", "Invalid login credentials"));
header("location:../");
}else{

foreach($result as $row)
{

if ($row[4] > 0) {

if (password_verify($_password, $row[2])) {
$account_id = $row[0];
$session_id = mb_strtoupper(GRS(20));
$ip =  $_SERVER['REMOTE_ADDR'];

$loginLevel = (int)$row[3];

if ($loginLevel === 4) {
	if (!$hasParentSessions) {
		$_SESSION['reply'] = array (array("danger", "Parent portal is not enabled on this server yet. Ask the admin to run DB migrations (001 + 002)."));
		header("location:../");
		exit;
	}

	$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE parent = ?");
	$stmt->execute([(int)$account_id]);

	$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, parent, ip_address) VALUES (?,?,?)");
	$stmt->execute([$session_id, (int)$account_id, $ip]);
	app_audit_log($conn, 'parent', (string)$account_id, 'auth.login', 'session', (string)$session_id);
} elseif ($loginLevel === 3) {
$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE student = ?");
$stmt->execute([$account_id]);

$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, student, ip_address) VALUES (?,?,?)");
$stmt->execute([$session_id, $account_id, $ip]);
	app_audit_log($conn, 'student', (string)$account_id, 'auth.login', 'session', (string)$session_id);
}else{
$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff = ?");
$stmt->execute([$account_id]);

$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, ip_address) VALUES (?,?,?)");
$stmt->execute([$session_id, $account_id, $ip]);
	app_audit_log($conn, 'staff', (string)$account_id, 'auth.login', 'session', (string)$session_id);
}


app_issue_auth_cookies((string)$row[3], (string)$session_id, false, (int)$cookie_length);

switch ($row[3]) {
case '0':
header("location:../admin");
break;
case '9':
header("location:../admin");
break;

case '1':
header("location:../academic");
break;

case '2':
header("location:../teacher");
break;

case '3':
header("location:../student");
break;

case '4':
header("location:../parent");
break;

case '5':
header("location:../accountant");
break;
}


}else{
$_SESSION['reply'] = array (array("danger", "Invalid login credentials"));
header("location:../");
}

}else{
$_SESSION['reply'] = array (array("danger", "Your account is blocked"));
header("location:../");
}

}


}

}catch(PDOException $e)
{
error_log('[core.auth] ' . $e->getMessage());
$_SESSION['reply'] = array (array("danger", "Something went wrong. Please try again."));
header("location:../");
exit;
}

}else{
header("location:../");
}
?>
