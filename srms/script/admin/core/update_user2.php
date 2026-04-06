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
$id = $_POST['id'];
$status = $_POST['status'];

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
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
