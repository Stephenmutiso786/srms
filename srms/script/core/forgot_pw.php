<?php
session_start();
chdir('../');
require_once('db/config.php');
require_once('const/rand.php');
require_once('const/notify.php');
require_once('const/school.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$_username = $_POST['username'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');

if ($isPgsql) {
$stmt = $conn->prepare("SELECT id::text AS id, fname, email, level FROM tbl_staff WHERE id::text = ? OR email = ?
UNION SELECT id::text AS id, fname, email, level FROM tbl_students WHERE id::text = ? OR email = ?");
} else {
$stmt = $conn->prepare("SELECT id, fname, email, level FROM tbl_staff WHERE id = ? OR email = ?
UNION SELECT id, fname, email, level FROM tbl_students WHERE id = ? OR email = ?");
}
$stmt->execute([$_username, $_username, $_username, $_username]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$_SESSION['reply'] = array (array("danger", "Account was not found"));
header("location:../");
}else{

foreach($result as $row)
{
$account = $row[0];
$name = $row[1];
$np = GP(8);
$email = $row[2];
$level = $row[3];
$npassword = password_hash($np, PASSWORD_DEFAULT);

$msg = "<h3 style='font-size:22px;'>Reset your password</h3> <p  style='font-size:20px;'>Hello $name! <br>
We received a request to change your password, Your new password is <b style='font-family:Courier New;'>$np</b><br><br>
</p>";

$result = app_send_email($conn, $email, 'Reset Password', $msg);

if (!empty($result['ok'])) {

if ($level < 3) {

$stmt = $isPgsql
? $conn->prepare("UPDATE tbl_staff SET password = ? WHERE id = CAST(? AS integer)")
: $conn->prepare("UPDATE tbl_staff SET password = ? WHERE id = ?");
$stmt->execute([$npassword, $account]);

}else{

$stmt = $conn->prepare("UPDATE tbl_students SET password = ? WHERE id = ?");
$stmt->execute([$npassword, $account]);

}


$_SESSION['reply'] = array (array("success", "Check $email for new password"));
header("location:../");

} else {

	error_log('[core.forgot_pw.smtp] ' . (string)($result['error'] ?? 'Email send failed'));

	$_SESSION['reply'] = array (array("danger", "Unable to send reset email right now. Please try again later."));
	header("location:../");

}

}


}

}catch(PDOException $e)
{
error_log('[core.forgot_pw] ' . $e->getMessage());
$_SESSION['reply'] = array (array("danger", "Something went wrong. Please try again."));
header("location:../");
exit;
}

}else{
header("location:../");
}
?>
