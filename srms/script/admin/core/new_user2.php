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
$designation = strtolower(trim((string)($_POST['designation'] ?? 'teacher')));
$designationMap = [
	'teacher' => ['role' => '2', 'label' => 'Teacher'],
	'headteacher' => ['role' => '0', 'label' => 'Headteacher'],
	'deputy_headteacher' => ['role' => '1', 'label' => 'Deputy Headteacher'],
	'senior_teacher' => ['role' => '2', 'label' => 'Senior Teacher'],
	'accountant' => ['role' => '5', 'label' => 'Accountant'],
];
if (!isset($designationMap[$designation])) {
	$designation = 'teacher';
}
$role = $designationMap[$designation]['role'];
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
$isSuperAdminController = app_is_super_admin_controller($conn, (string)($account_id ?? ''), (string)($level ?? ''));
$isAdminManagedDesignation = in_array($designation, ['headteacher', 'deputy_headteacher', 'senior_teacher', 'accountant'], true);
if ($isAdminManagedDesignation && !$isSuperAdminController) {
	$_SESSION['reply'] = array (array("danger",'Only the super admin can create leadership or admin accounts.'));
	header("location:../teachers");
	exit;
}

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

$staffId = (int)$conn->lastInsertId();
if ($staffId > 0) {
	if ($designation === 'senior_teacher') {
		app_assign_staff_role_name($conn, $staffId, 'Senior Teacher');
	} else {
		app_remove_staff_role_name($conn, $staffId, 'Senior Teacher');
	}
}

$_SESSION['reply'] = array (array("success", $designationMap[$designation]['label'] . ' account registered successfully'));
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
