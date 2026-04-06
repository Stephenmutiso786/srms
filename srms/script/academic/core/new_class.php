<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$name = ucfirst($_POST['name']);
$reg_date = date('Y-m-d G:i:s');

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE name = ?");
$stmt->execute([$name]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("INSERT INTO tbl_classes (name, registration_date) VALUES (?,?)");
$stmt->execute([$name, $reg_date]);

$_SESSION['reply'] = array (array("success",'Class registered successfully'));
header("location:../classes");

}else{

$_SESSION['reply'] = array (array("danger",'Class is already registered'));
header("location:../classes");

}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
