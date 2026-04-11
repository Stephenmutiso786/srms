<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$cpassword = $_POST['cpassword'];
$npassword = password_hash($_POST['npassword'], PASSWORD_DEFAULT);

if (password_verify($cpassword, $login)) {

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("UPDATE tbl_students SET password = ? WHERE id = ?");
$stmt->execute([$npassword, $account_id]);

$_SESSION['reply'] = array (array("success", "Password updated"));
header("location:../settings");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
$_SESSION['reply'] = array (array("warning", "Current password is not correct"));
header("location:../settings");
}
}else{
header("location:../");
}
?>
