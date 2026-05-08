<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$title = trim((string)($_POST['title'] ?? ''));
$audience = trim((string)($_POST['audience'] ?? ''));
$announcement = trim((string)($_POST['announcement'] ?? ''));
$levelValue = $audience;
$id = (int)($_POST['id'] ?? 0);

if ($res !== "1" || !in_array((string)($GLOBALS['level'] ?? ''), ['1', '0'], true)) {
	header("location:../");
	exit;
}

if ($title === '' || $announcement === '' || $id < 1) {
	$_SESSION['reply'] = array (array("danger",'Complete all announcement fields.'));
	header("location:../announcement.php");
	exit;
}

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("UPDATE tbl_announcements SET title=?, announcement=?, level=? WHERE id = ?");
$stmt->execute([$title, $announcement, $levelValue, $id]);

$_SESSION['reply'] = array (array("success",'Announcement updated successfully'));
header("location:../announcement.php");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
$_SESSION['reply'] = array (array("danger",'Unable to update announcement right now.'));
header("location:../announcement.php");
exit;
}


}else{
header("location:../");
}
?>
