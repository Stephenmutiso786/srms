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

$recipientType = trim($_POST['recipient_type'] ?? '');
$recipient = trim($_POST['recipient_id'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

if ($recipientType === '' || $recipient === '' || $body === '') {
	$_SESSION['reply'] = array (array("danger", "Recipient and message are required."));
	header("location:../communication");
	exit;
}

// recipient_id value is "type:id"
$parts = explode(':', $recipient);
$recType = $parts[0] ?? $recipientType;
$recId = $parts[1] ?? '';

if ($recId === '') {
	$_SESSION['reply'] = array (array("danger", "Invalid recipient."));
	header("location:../communication");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_messages')) {
		$_SESSION['reply'] = array (array("danger", "Messages table missing. Run migration 009."));
		header("location:../communication");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_messages (sender_type, sender_id, recipient_type, recipient_id, subject, body) VALUES (?,?,?,?,?,?)");
	$stmt->execute(['admin', $myid, $recType, $recId, $subject, $body]);

	$_SESSION['reply'] = array (array("success", "Message sent."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to send: " . $e->getMessage()));
	header("location:../communication");
}
