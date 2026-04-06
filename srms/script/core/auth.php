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

if ($isPgsql) {
$stmt = $conn->prepare("SELECT id::text AS id, email, password, level, status FROM tbl_staff WHERE id::text = ? OR email = ?
UNION SELECT id::text AS id, email, password, level, status FROM tbl_students WHERE id::text = ? OR email = ?");
} else {
$stmt = $conn->prepare("SELECT id, email, password, level, status FROM tbl_staff WHERE id = ? OR email = ?
UNION SELECT id, email, password, level, status FROM tbl_students WHERE id = ? OR email = ?");
}
$stmt->execute([$_username, $_username, $_username, $_username]);
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

if ($row[3] > 2) {
$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE student = ?");
$stmt->execute([$account_id]);

$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, student, ip_address) VALUES (?,?,?)");
$stmt->execute([$session_id, $account_id, $ip]);
}else{
$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff = ?");
$stmt->execute([$account_id]);

$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, ip_address) VALUES (?,?,?)");
$stmt->execute([$session_id, $account_id, $ip]);
}


setcookie("__SRMS__logged", $row[3], time() + (60 * $cookie_length), "/");
setcookie("__SRMS__key", $session_id, time() + (60 * $cookie_length), "/");

switch ($row[3]) {
case '0':
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
echo "Connection failed: " . $e->getMessage();
}

}else{
header("location:../");
}
?>
