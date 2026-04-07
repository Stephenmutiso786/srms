<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../mpesa");
	exit;
}

$enabled = (int)($_POST['enabled'] ?? 0);
$environment = trim((string)($_POST['environment'] ?? 'sandbox'));
$shortcode = trim((string)($_POST['shortcode'] ?? ''));
$passkey = trim((string)($_POST['passkey'] ?? ''));
$consumerKey = trim((string)($_POST['consumer_key'] ?? ''));
$consumerSecret = trim((string)($_POST['consumer_secret'] ?? ''));
$callbackUrl = trim((string)($_POST['callback_url'] ?? ''));

if ($environment !== 'sandbox' && $environment !== 'live') {
	$environment = 'sandbox';
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_payment_settings')) {
		$_SESSION['reply'] = array(array("error", "M-Pesa settings table missing. Run migration 006_mpesa_stk.sql."));
		header("location:../mpesa");
		exit;
	}

	$stmt = $conn->prepare("UPDATE tbl_payment_settings
		SET environment = ?, shortcode = ?, passkey = ?, consumer_key = ?, consumer_secret = ?, callback_url = ?, enabled = ?, updated_at = CURRENT_TIMESTAMP
		WHERE id = 1");
	$stmt->execute([$environment, $shortcode, $passkey, $consumerKey, $consumerSecret, $callbackUrl, $enabled]);

	app_audit_log($conn, 'staff', (string)$account_id, 'mpesa.settings.update', 'payment_settings', '1');

	$_SESSION['reply'] = array(array("success", "M-Pesa settings saved."));
	header("location:../mpesa");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../mpesa");
	exit;
}

