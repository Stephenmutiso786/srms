<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


$grade_name = ucwords($_POST['grade_name']);
$min = $_POST['min'];
$max = $_POST['max'];
$remark = ucwords($_POST['remark']);

if ($min > 100 OR $max > 100) {
$_SESSION['reply'] = array (array("danger","Minimum and Maximum percentage must be less or equal to 100%"));
header("location:../grading-system");
}else{

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system WHERE name = ? OR min = ? AND max = ?");
$stmt->execute([$grade_name, $min, $max]);
$result = $stmt->fetchAll();

if (count($result) > 0) {
$_SESSION['reply'] = array (array("warning","Grade is already registered"));
header("location:../grading-system");
}else{

$stmt = $conn->prepare("INSERT INTO tbl_grade_system (name, min, max, remark) VALUES (?,?,?,?)");
$stmt->execute([$grade_name, $min, $max, $remark]);

$_SESSION['reply'] = array (array("success","Grade registered successfully"));
header("location:../grading-system");

}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

}



}else{
header("location:../");
}
?>
