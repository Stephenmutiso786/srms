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
$message = trim($_POST['message'] ?? '');

if ($recipient === '' || $message === '') {
	$_SESSION['reply'] = array (array("danger", "Recipient and message are required."));
	header("location:../communication");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_sms_logs')) {
		$_SESSION['reply'] = array (array("danger", "SMS logs table missing. Run migration 009."));
		header("location:../communication");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_sms_logs (recipient, message, status, provider) VALUES (?,?,?,?)");
	$stmt->execute([$recipient, $message, 'queued', '']);

	$_SESSION['reply'] = array (array("success", "SMS queued. Hook your gateway to process queued logs."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to queue SMS: " . $e->getMessage()));
	header("location:../communication");
}
