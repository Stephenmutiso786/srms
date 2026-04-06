<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$name = ucfirst($_POST['name']);
$status = $_POST['status'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE name = ?");
$stmt->execute([$name]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("INSERT INTO tbl_terms (name, status) VALUES (?,?)");
$stmt->execute([$name, $status]);

$_SESSION['reply'] = array (array("success",'Academic term registered successfully'));
header("location:../terms");

}else{

$_SESSION['reply'] = array (array("danger",'Academic term is already registered'));
header("location:../terms");

}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
