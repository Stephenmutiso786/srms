<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/phpexcel/SimpleXLSX.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$uploadCheck = app_validate_upload($_FILES['file'], ['xlsx', 'xls']);
if (!$uploadCheck['ok']) {
$_SESSION['reply'] = array (array("danger", $uploadCheck['message']));
header("location:../students");
exit;
}
$file = $_FILES['file']['tmp_name'];
$st_rec = 0;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if ( $xlsx = SimpleXLSX::parse($file) ) {
foreach( $xlsx->rows() as $r ) {

if ($st_rec == 0) {

}else{


$cells = array_pad($r, 7, '');
$importRow = [
	'reg_no' => trim((string)$cells[0]),
	'fname' => ucfirst(trim((string)$cells[1])),
	'mname' => ucfirst(trim((string)$cells[2])),
	'lname' => ucfirst(trim((string)$cells[3])),
	'gender' => trim((string)$cells[4]),
	'email' => trim((string)$cells[5]),
	'password' => trim((string)$cells[6]),
];

$reg_no = $importRow['reg_no'];
$fname = $importRow['fname'];
$mname = $importRow['mname'];
$lname = $importRow['lname'];
$email = $importRow['email'];
$gender = $importRow['gender'];
$class = $_POST['class'];
$role = '3';
$plainPassword = $importRow['password'];
if ($plainPassword === '') {
	$plainPassword = '12345678';
}
$pass = password_hash($plainPassword, PASSWORD_DEFAULT);
$status = '1';
$img = 'DEFAULT';

$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
$stmt = $isPgsql
? $conn->prepare("SELECT id::text AS id, email FROM tbl_staff WHERE email = ? OR id::text = ? UNION SELECT id::text AS id, email FROM tbl_students WHERE email = ? OR id::text = ?")
: $conn->prepare("SELECT id, email FROM tbl_staff WHERE email = ? OR id = ? UNION SELECT id, email FROM tbl_students WHERE email = ? OR id = ?");
$stmt->execute([$email, $reg_no, $email, $reg_no]);
$result = $stmt->fetchColumn();

if ($result) {

}else{


if (preg_match('~[0-9]+~', $fname) OR preg_match('~[0-9]+~', $mname) OR preg_match('~[0-9]+~', $lname)) {

}else{

if (app_column_exists($conn, 'tbl_students', 'school_id')) {
	$schoolId = app_generate_school_id($conn, 'STD', (int)date('Y'), 'tbl_students');
	$stmt = $conn->prepare("INSERT INTO tbl_students (id, school_id, fname, mname, lname, gender, email, class, password, display_image) VALUES (?,?,?,?,?,?,?,?,?,?)");
	$stmt->execute([$reg_no, $schoolId, $fname, $mname, $lname, $gender, $email, $class, $pass, $img]);
} else {
	$stmt = $conn->prepare("INSERT INTO tbl_students (id, fname, mname, lname, gender, email, class, password, display_image) VALUES (?,?,?,?,?,?,?,?,?)");
	$stmt->execute([$reg_no, $fname, $mname, $lname, $gender, $email, $class, $pass, $img]);
}

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
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}




}else{
header("location:../");
}
?>
