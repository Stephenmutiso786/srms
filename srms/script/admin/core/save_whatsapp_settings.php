<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('communication.manage', '../communication');
app_require_unlocked('communication', '../communication');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../communication");
	exit;
}

$provider = trim($_POST['provider'] ?? 'wireweb');
$apiUrl = trim($_POST['api_url'] ?? 'https://app.wireweb.co.in/api/v1/messages');
$apiKey = trim($_POST['api_key'] ?? '');
$sessionId = trim($_POST['session_id'] ?? '');
$status = (int)($_POST['status'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_whatsapp_tables($conn);

	$stmt = $conn->prepare("SELECT id FROM tbl_whatsapp_settings ORDER BY id DESC LIMIT 1");
	$stmt->execute();
	$id = $stmt->fetchColumn();

	if ($id) {
		$stmt = $conn->prepare("UPDATE tbl_whatsapp_settings SET provider = ?, api_url = ?, api_key = ?, session_id = ?, status = ? WHERE id = ?");
		$stmt->execute([$provider, $apiUrl, $apiKey, $sessionId, $status, $id]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_whatsapp_settings (provider, api_url, api_key, session_id, status) VALUES (?,?,?,?,?)");
		$stmt->execute([$provider, $apiUrl, $apiKey, $sessionId, $status]);
	}

	$_SESSION['reply'] = array (array("success", "WhatsApp settings saved."));
	header("location:../communication");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save WhatsApp settings: " . $e->getMessage()));
	header("location:../communication");
}
