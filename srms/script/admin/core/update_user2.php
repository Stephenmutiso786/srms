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
$id = (int)($_POST['id'] ?? 0);
$status = (string)($_POST['status'] ?? '1');

if ($id < 1 || $fname === '' || $lname === '' || $email === '') {
	$_SESSION['reply'] = array (array("danger",'A valid staff record is required.'));
	header("location:../teachers");
	exit;
}

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$isSuperAdminController = app_is_super_admin_controller($conn, (string)($account_id ?? ''), (string)($level ?? ''));

$staffStmt = $conn->prepare("SELECT id, level FROM tbl_staff WHERE id = ? LIMIT 1");
$staffStmt->execute([$id]);
$currentStaff = $staffStmt->fetch(PDO::FETCH_ASSOC);
if (!$currentStaff) {
	$_SESSION['reply'] = array (array("danger",'Staff record not found.'));
	header("location:../teachers");
	exit;
}

$currentLevel = (string)($currentStaff['level'] ?? '');
$currentIsAdminManaged = app_staff_is_admin_managed($conn, $id, $currentLevel);
$requestedIsAdminManaged = in_array($designation, ['headteacher', 'deputy_headteacher', 'senior_teacher', 'accountant'], true);
$isHeadteacherController = app_staff_designation_key($conn, (int)($account_id ?? 0), (string)($level ?? '')) === 'headteacher';
if (($currentIsAdminManaged || $requestedIsAdminManaged) && !$isSuperAdminController && !$isHeadteacherController) {
	$_SESSION['reply'] = array (array("danger",'Only the super admin or headteacher can edit leadership or admin accounts.'));
	header("location:../teachers");
	exit;
}

if (!$isSuperAdminController && !$isHeadteacherController && $designation !== 'teacher') {
	$designation = 'teacher';
	$role = '2';
}

$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
$stmt = $isPgsql
	? $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? AND id::text != ? UNION SELECT email FROM tbl_students WHERE email = ? AND id != ?")
	: $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? AND id != ? UNION SELECT email FROM tbl_students WHERE email = ? AND id != ?");
$stmt->execute([$email, (string)$id, $email, (string)$id]);
$result = $stmt->fetchAll();

if (count($result) > 0) {
$_SESSION['reply'] = array (array("error",'Email is already added'));
header("location:../teachers");
}else{

$stmt = $conn->prepare("UPDATE tbl_staff SET fname=?, lname=?, gender=?, email=?, level=?, status=? WHERE id = ?");
$stmt->execute([$fname, $lname, $gender, $email, (int)$role, $status, $id]);

if ($designation === 'senior_teacher') {
	app_assign_staff_role_name($conn, $id, 'Senior Teacher');
} else {
	app_remove_staff_role_name($conn, $id, 'Senior Teacher');
}

$_SESSION['reply'] = array (array("success",$designationMap[$designation]['label'] . ' account updated successfully'));
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
