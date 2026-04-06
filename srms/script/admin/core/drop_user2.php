<?php
session_start();
chdir('../../');
session_start();
require_once('db/config.php');


if ($_SERVER['REQUEST_METHOD'] === 'GET') {

$id = $_GET['id'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("DELETE FROM tbl_staff WHERE id = ?");
$stmt->execute([$id]);

$_SESSION['reply'] = array (array("success",'Teacher deleted successfully'));
header("location:../teachers");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
