<?php
session_start();
chdir('../');
require_once('db/config.php');
require_once('const/rand.php');
require_once('const/mail.php');
require_once('const/school.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'mail/src/Exception.php';
require 'mail/src/PHPMailer.php';
require 'mail/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$_username = $_POST['username'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, fname, email, level FROM tbl_staff WHERE id = ? OR email = ?
UNION SELECT id, fname, email, level FROM tbl_students WHERE id = ? OR email = ?");
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

$mail = new PHPMailer;
$mail->SMTPOptions = array(
'ssl' => array(
'verify_peer' => false,
'verify_peer_name' => false,
'allow_self_signed' => true
)
);

$mail->isSMTP();
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Host = $smtp_server;
$mail->SMTPAuth = true;
$mail->Username = $smtp_username;
$mail->Password = $smtp_password;
$mail->SMTPSecure = $smtp_conn_type;
$mail->Port = $smtp_conn_port;

$mail->setFrom($smtp_username, WBName);
$mail->addAddress($email, $name);
$mail->isHTML(true);

$mail->Subject = 'Reset Password';
$mail->Body    = $msg;
$mail->AltBody = $msg;

if(!$mail->send()) {

$er = '' . $mail->ErrorInfo.'';


$_SESSION['reply'] = array (array("danger", $er));
header("location:../");


} else {

if ($level < 3) {

$stmt = $conn->prepare("UPDATE tbl_staff SET password = ? WHERE id = ?");
$stmt->execute([$npassword, $account]);

}else{

$stmt = $conn->prepare("UPDATE tbl_students SET password = ? WHERE id = ?");
$stmt->execute([$npassword, $account]);

}


$_SESSION['reply'] = array (array("success", "Check $email for new password"));
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
