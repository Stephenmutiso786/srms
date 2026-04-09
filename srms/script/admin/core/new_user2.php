<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$fname = ucfirst($_POST['fname']);
$lname = ucfirst($_POST['lname']);
$email = $_POST['email'];
$gender = $_POST['gender'];
$role = (string)($_POST['role'] ?? '2');
$allowedRoles = ['2', '5'];
if (!in_array($role, $allowedRoles, true)) {
	$role = '2';
}
$pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
$status = $_POST['status'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
$stmt = $isPgsql
	? $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? UNION SELECT email FROM tbl_students WHERE email = ?")
	: $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? UNION SELECT email FROM tbl_students WHERE email = ?");
$stmt->execute([$email, $email]);
$result = $stmt->fetchAll();

if (count($result) > 0) {
$_SESSION['reply'] = array (array("error",'Email is already added'));
header("location:../teachers");
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

$_SESSION['reply'] = array (array("success",'Staff registered successfully'));
header("location:../teachers");
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
