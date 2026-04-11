<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$smtp_server = $_POST['mail_server'];
$smtp_username = $_POST['mail_username'];
$smtp_password = $_POST['mail_password'];
$smtp_conn_type = $_POST['mail_security'];
$smtp_conn_port = $_POST['mail_port'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("UPDATE tbl_smtp SET server = ?, username = ?, password = ?, port = ?, encryption = ?");
$stmt->execute([$smtp_server, $smtp_username, $smtp_password, $smtp_conn_port, $smtp_conn_type]);
$_SESSION['reply'] = array (array("success","SMTP settings updated"));
header("location:../smtp");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}


}else{
header("location:../");
}
?>
