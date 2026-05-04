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
$allowedRoles = ['2', '5'];
if (!in_array($role, $allowedRoles, true)) {
	$role = '2';
}
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

$stmt = $conn->prepare("UPDATE tbl_staff SET fname=?, lname=?, gender=?, email=?, level=?, status=? WHERE id = ? AND level IN (2,5)");
$stmt->execute([$fname, $lname, $gender, $email, (int)$role, $status, $id]);

$_SESSION['reply'] = array (array("success",'Staff updated successfully'));
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
