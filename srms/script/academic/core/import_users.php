<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/phpexcel/SimpleXLSX.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$file = $_FILES['file']['tmp_name'];
$st_rec = 0;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ( $xlsx = SimpleXLSX::parse($file) ) {
foreach( $xlsx->rows() as $r ) {

if ($st_rec == 0) {

}else{

$fname = ucfirst($r[0]);
$lname = ucfirst($r[1]);
$email = $r[2];
$gender = $r[3];
$role = '2';
$pass = password_hash($r[5], PASSWORD_DEFAULT);
$status = $r[4];
if ($status == "Active") {
$status = 1;
}else{
$status = 0;
}

$stmt = $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? UNION SELECT email FROM tbl_students WHERE email = ?");
$stmt->execute([$email, $email]);
$result = $stmt->fetchAll();

if (count($result) > 0) {

}else{

$stmt = $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?,?,?,?,?,?,?)");
$stmt->execute([$fname, $lname, $gender, $email, $pass, $role, $status]);

}

}
$st_rec++;
}


$_SESSION['reply'] = array (array("success",'Data import completed'));
header("location:../teachers");

} else {
echo SimpleXLSX::parseError();
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}




}else{
header("location:../");
}
?>
