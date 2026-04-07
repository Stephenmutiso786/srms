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

$recipient = trim($_POST['recipient'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($recipient === '' || $subject === '' || $message === '') {
	$_SESSION['reply'] = array (array("danger", "Recipient, subject, and message are required."));
	header("location:../communication");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_email_logs')) {
		$_SESSION['reply'] = array (array("danger", "Email logs table missing. Run migration 009."));
		header("location:../communication");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_email_logs (recipient, subject, message, status, provider) VALUES (?,?,?,?,?)");
	$stmt->execute([$recipient, $subject, $message, 'queued', '']);

	$_SESSION['reply'] = array (array("success", "Email queued. Hook SMTP worker to process queued logs."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to queue email: " . $e->getMessage()));
	header("location:../communication");
}
