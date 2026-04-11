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

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("INSERT INTO tbl_announcements (title, announcement, create_date, level) VALUES (?,?,?,?)");
$stmt->execute([$title, $announcement, $post_date, $level]);

$_SESSION['reply'] = array (array("success",'Announcement created successfully'));
header("location:../announcement");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}


}else{
header("location:../");
}
?>
