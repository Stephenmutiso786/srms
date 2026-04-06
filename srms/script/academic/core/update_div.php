<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$div = $_POST['div'];
$min = $_POST['min'];
$max = $_POST['max'];
$min2 = $_POST['min2'];
$max2 = $_POST['max2'];
$points = $_POST['points'];
$id = $_POST['id'];

if ($min > 100 OR $max > 100) {
$_SESSION['reply'] = array (array("danger","Minimum and Maximum percentage must be less or equal to 100%"));
header("location:../division-system");
}else{

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("UPDATE tbl_division_system SET division=?, min=?, max=?, min_point=?, max_point=?, points=? WHERE division = ?");
$stmt->execute([$div, $min, $max, $min2, $max2, $points, $id]);

$_SESSION['reply'] = array (array("success","Division updated successfully"));
header("location:../division-system");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

}



}else{
header("location:../");
}
?>
