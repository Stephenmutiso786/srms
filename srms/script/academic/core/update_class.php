<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$name = ucfirst($_POST['name']);
$id = $_POST['id'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE name = ? AND id != ?");
$stmt->execute([$name, $id]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("UPDATE tbl_classes SET name=? WHERE id=?");
$stmt->execute([$name, $id]);

$_SESSION['reply'] = array (array("success",'Class updated successfully'));
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
