<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$title = $_POST['title'];
$audience = $_POST['audience'];
$announcement = $_POST['announcement'];
$post_date = date('Y-m-d G:i:s');
$level = $_POST['audience'];
$id = $_POST['id'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("UPDATE tbl_announcements SET title=?, announcement=?, level=? WHERE id = ?");
$stmt->execute([$title, $announcement, $level, $id]);

$_SESSION['reply'] = array (array("success",'Announcement updated successfully'));
header("location:../announcement");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
