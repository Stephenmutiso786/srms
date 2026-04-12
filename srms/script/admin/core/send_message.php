<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/communication_targets.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('communication.manage', '../communication');
app_require_unlocked('communication', '../communication');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../communication");
	exit;
}

$targetType = trim($_POST['target_type'] ?? '');
$targetValue = trim($_POST['target_value'] ?? '');
$recipientType = trim($_POST['recipient_type'] ?? '');
$recipientId = trim($_POST['recipient_id'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$body = trim($_POST['body'] ?? '');

if ($body === '') {
	$_SESSION['reply'] = array(array("danger", "Message body is required."));
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

	$targets = [];
	if ($targetType !== '') {
		$targets = app_communication_targets($conn, $targetType, $targetValue);
	} elseif ($recipientType !== '' && $recipientId !== '') {
		$parts = explode(':', $recipientId, 2);
		$resolvedType = $parts[0] ?: $recipientType;
		$resolvedId = $parts[1] ?? '';
		if ($resolvedId !== '') {
			$targets[] = ['type' => $resolvedType, 'id' => $resolvedId];
		}
	}

	if (count($targets) === 0) {
		throw new RuntimeException('No recipients matched the selected target.');
	}

	$stmt = $conn->prepare('INSERT INTO tbl_messages (sender_type, sender_id, recipient_type, recipient_id, subject, body) VALUES (?,?,?,?,?,?)');
	foreach ($targets as $target) {
		$resolvedType = (string)($target['type'] ?? '');
		$resolvedId = (string)($target['id'] ?? '');
		if ($resolvedType === '' || $resolvedId === '') {
			continue;
		}
		$stmt->execute(['staff', (string)$account_id, $resolvedType, $resolvedId, $subject, $body]);
	}

	$_SESSION['reply'] = array (array("success", "Message sent successfully."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to send: " . $e->getMessage()));
	header("location:../communication");
}
