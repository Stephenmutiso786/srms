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


$reg_no = $r[0];
$fname = ucfirst($r[1]);
$mname = ucfirst($r[2]);
$lname = ucfirst($r[3]);
$email = $r[5];
$gender = $r[4];
$class = $_POST['class'];
$role = '3';
$pass = password_hash($r[6], PASSWORD_DEFAULT);
$status = '1';
$img = 'DEFAULT';

$stmt = $conn->prepare("SELECT id, email FROM tbl_staff WHERE email = ? OR id = ? UNION SELECT id, email FROM tbl_students WHERE email = ? OR id = ?");
$stmt->execute([$email, $reg_no, $email, $reg_no]);
$result = $stmt->fetchAll();

if (count($result) > 0) {

}else{


if (preg_match('~[0-9]+~', $fname) OR preg_match('~[0-9]+~', $mname) OR preg_match('~[0-9]+~', $lname)) {

}else{

$stmt = $conn->prepare("INSERT INTO tbl_students (id, fname, mname, lname, gender, email, class, password, display_image) VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->execute([$reg_no, $fname, $mname, $lname, $gender, $email, $class, $pass, $img]);

}



}

}
$st_rec++;
}


$_SESSION['reply'] = array (array("success",'Data import completed'));
header("location:../import_students");

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
