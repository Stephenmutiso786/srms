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

if ($min > 100 OR $max > 100) {
$_SESSION['reply'] = array (array("danger","Minimum and Maximum percentage must be less or equal to 100%"));
header("location:../division-system");
}else{

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_division_system WHERE division = ?
  OR min = ? AND max = ? OR min_point = ? AND max_point = ? OR points = ?");

$stmt->execute([$div, $min, $max, $min2, $max2, $points]);
$result = $stmt->fetchAll();

if (count($result) > 0) {
$_SESSION['reply'] = array (array("warning","Division is already registered"));
header("location:../division-system");
}else{

$stmt = $conn->prepare("INSERT INTO tbl_division_system (division, min, max, min_point, max_point, points) VALUES (?,?,?,?,?,?)");
$stmt->execute([$div, $min, $max, $min2, $max2, $points]);

$_SESSION['reply'] = array (array("success","Division registered successfully"));
header("location:../division-system");

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
