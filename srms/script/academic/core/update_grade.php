<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$grade_name = ucwords($_POST['grade_name']);
$min = $_POST['min'];
$max = $_POST['max'];
$remark = ucwords($_POST['remark']);
$id = $_POST['id'];

if ($min > 100 OR $max > 100) {
$_SESSION['reply'] = array (array("danger","Minimum and Maximum percentage must be less or equal to 100%"));
header("location:../grading-system");
}else{

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system WHERE name = ? AND id != ? OR min = ? AND max = ? AND id != ?");
$stmt->execute([$grade_name, $id, $min, $max, $id]);
$result = $stmt->fetchAll();

if (count($result) > 0) {
$_SESSION['reply'] = array (array("warning","Grade is already registered"));
header("location:../grading-system");
}else{

$stmt = $conn->prepare("UPDATE tbl_grade_system  SET name=?, min=?, max=?, remark=? WHERE id = ?");
$stmt->execute([$grade_name, $min, $max,  $remark, $id]);

$_SESSION['reply'] = array (array("success","Grade updated successfully"));
header("location:../grading-system");

}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}



}else{
header("location:../");
}
?>
