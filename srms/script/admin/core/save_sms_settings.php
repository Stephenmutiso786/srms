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

$provider = trim($_POST['provider'] ?? 'custom');
$apiUrl = trim($_POST['api_url'] ?? '');
$apiKey = trim($_POST['api_key'] ?? '');
$senderId = trim($_POST['sender_id'] ?? '');
$status = (int)($_POST['status'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_sms_settings')) {
		$_SESSION['reply'] = array (array("danger", "SMS settings table missing. Run migration 017."));
		header("location:../communication");
		exit;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_sms_settings ORDER BY id DESC LIMIT 1");
	$stmt->execute();
	$id = $stmt->fetchColumn();

	if ($id) {
		$stmt = $conn->prepare("UPDATE tbl_sms_settings SET provider = ?, api_url = ?, api_key = ?, sender_id = ?, status = ? WHERE id = ?");
		$stmt->execute([$provider, $apiUrl, $apiKey, $senderId, $status, $id]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_sms_settings (provider, api_url, api_key, sender_id, status) VALUES (?,?,?,?,?)");
		$stmt->execute([$provider, $apiUrl, $apiKey, $senderId, $status]);
	}

	$_SESSION['reply'] = array (array("success", "SMS settings saved."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save SMS settings: " . $e->getMessage()));
	header("location:../communication");
}
