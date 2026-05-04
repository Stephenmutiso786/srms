<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
if ($res !== "1" || ((int)$level !== 1 && !app_current_user_has_any_permission(['staff.manage', 'academic.manage']))) {
	$_SESSION['reply'] = array (array("danger",'Access denied.'));
	header("location:../teachers");
	exit;
}

$fname = ucfirst(trim((string)($_POST['fname'] ?? '')));
$lname = ucfirst(trim((string)($_POST['lname'] ?? '')));
$email = trim((string)($_POST['email'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$role = (string)($_POST['role'] ?? '2');
$allowedRoles = ['0', '1', '2', '5', '6', '7', '8', '9'];
if (!in_array($role, $allowedRoles, true)) {
	$role = '2';
}
$rawPassword = (string)($_POST['password'] ?? '');
$status = (string)($_POST['status'] ?? '1');

if ($fname === '' || $lname === '' || $email === '' || $rawPassword === '') {
	$_SESSION['reply'] = array (array("danger",'All required staff fields must be filled in.'));
	header("location:../teachers");
	exit;
}

$pass = password_hash($rawPassword, PASSWORD_DEFAULT);

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
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}


}else{
header("location:../");
}
?>
