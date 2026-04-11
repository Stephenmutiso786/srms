<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$class = $_POST['class'];
$class2 = $_POST['class2'];


try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$stmt = $conn->prepare("UPDATE tbl_students SET class = ? WHERE class = ?");
$stmt->execute([$class2, $class]);

$_SESSION['reply'] = array (array("success",'Students promoted to next class'));
header("location:../promote_students");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}


}else{
header("location:../");
}
?>
