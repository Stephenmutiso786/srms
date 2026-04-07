<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('communication.manage', '../communication');
app_require_unlocked('communication', '../communication');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../communication");
	exit;
}

$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$audience = $_POST['audience'] ?? 'students';

if ($title === '' || $message === '') {
	$_SESSION['reply'] = array (array("danger", "Title and message are required."));
	header("location:../communication");
	exit;
}

$level = 1; // students
if ($audience === 'staff') {
	$level = 0;
} elseif ($audience === 'both') {
	$level = 2;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_announcements')) {
		$_SESSION['reply'] = array (array("danger", "Announcements table missing."));
		header("location:../communication");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_announcements (title, announcement, create_date, level) VALUES (?,?,CURRENT_TIMESTAMP,?)");
	$stmt->execute([$title, $message, $level]);

	$_SESSION['reply'] = array (array("success", "Announcement published."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to publish: " . $e->getMessage()));
	header("location:../communication");
}
