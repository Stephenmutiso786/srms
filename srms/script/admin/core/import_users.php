<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/phpexcel/SimpleXLSX.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$uploadCheck = app_validate_upload($_FILES['file'], ['xlsx', 'xls']);
if (!$uploadCheck['ok']) {
$_SESSION['reply'] = array (array("danger", $uploadCheck['message']));
header("location:../teachers");
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

$cells = array_pad($r, 6, '');
$importRow = [
	'fname' => ucfirst(trim((string)$cells[0])),
	'lname' => ucfirst(trim((string)$cells[1])),
	'email' => trim((string)$cells[2]),
	'gender' => trim((string)$cells[3]),
	'status' => trim((string)$cells[4]),
	'password' => (string)$cells[5],
];

$fname = $importRow['fname'];
$lname = $importRow['lname'];
$email = $importRow['email'];
$gender = $importRow['gender'];
$role = '2';
$pass = password_hash($importRow['password'], PASSWORD_DEFAULT);
$status = $importRow['status'];
if ($status == "Active") {
$status = 1;
}else{
$status = 0;
}

$stmt = $conn->prepare("SELECT 1 FROM tbl_staff WHERE email = ? UNION SELECT 1 FROM tbl_students WHERE email = ?");
$stmt->execute([$email, $email]);
$result = $stmt->fetchColumn();

if ($result) {

}else{

if (preg_match('~[0-9]+~', $fname) OR preg_match('~[0-9]+~', $lname)) {

}else{

if (app_column_exists($conn, 'tbl_staff', 'school_id')) {
	$prefix = app_staff_prefix($role);
	$schoolId = app_generate_school_id($conn, $prefix, (int)date('Y'), 'tbl_staff');
	$stmt = $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id) VALUES (?,?,?,?,?,?,?,?)");
	$stmt->execute([$fname, $lname, $gender, $email, $pass, $role, $status, $schoolId]);
} else {
	$stmt = $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?,?,?,?,?,?,?)");
	$stmt->execute([$fname, $lname, $gender, $email, $pass, $role, $status]);
}

}

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
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}




}else{
header("location:../");
}
?>
