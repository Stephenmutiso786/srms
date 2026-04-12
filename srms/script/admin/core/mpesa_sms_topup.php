<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/mpesa.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}
app_require_permission('sms.wallet.manage', '../sms_topup');
app_require_unlocked('communication', '../sms_topup');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../sms_topup");
	exit;
}

$phone = trim((string)($_POST['phone'] ?? ''));
$tokens = (int)($_POST['tokens'] ?? 0);
if ($tokens < 1) {
	$tokens = 0;
}

if ($phone === '' || $tokens < 1) {
	$_SESSION['reply'] = array(array("error", "Enter a valid phone number and token amount."));
	header("location:../sms_topup");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_sms_wallet_tables($conn);

	if (!app_table_exists($conn, 'tbl_sms_topup_requests')) {
		throw new RuntimeException('SMS top-up table missing. Run migration 034.');
	}

	$cfg = mpesa_config($conn);
	$callbackUrl = getenv('SMS_TOPUP_CALLBACK_URL') ?: (APP_URL !== '' ? APP_URL . '/api/mpesa_sms_callback' : '');
	if ($callbackUrl === '') {
		throw new RuntimeException('Set APP_URL or SMS_TOPUP_CALLBACK_URL for SMS top-up callbacks.');
	}
	$callbackToken = getenv('MPESA_CALLBACK_TOKEN') ?: '';
	if ($callbackToken !== '') {
		$callbackUrl .= (str_contains($callbackUrl, '?') ? '&' : '?') . 'token=' . urlencode($callbackToken);
	}
	$cfg['callback_url'] = $callbackUrl;

	$amount = (float)$tokens;
	$accountRef = 'SMS-' . date('YmdHis');
	$description = 'SMS token purchase';

	$stmt = $conn->prepare('INSERT INTO tbl_sms_topup_requests (wallet_id, phone, tokens, amount, account_reference, status, created_by) VALUES (1,?,?,?,?,?,?)');
	$stmt->execute([$phone, $tokens, $amount, $accountRef, (int)$account_id]);
	$requestId = (int)$conn->lastInsertId();

	$data = mpesa_stk_push($conn, $cfg, $phone, $amount, $accountRef, $description);
	$merchantRequestId = (string)($data['MerchantRequestID'] ?? '');
	$checkoutRequestId = (string)($data['CheckoutRequestID'] ?? '');
	$responseCode = (string)($data['ResponseCode'] ?? '');
	$responseDesc = (string)($data['ResponseDescription'] ?? '');
	$customerMessage = (string)($data['CustomerMessage'] ?? '');

	$stmt = $conn->prepare('UPDATE tbl_sms_topup_requests SET status = ?, merchant_request_id = ?, checkout_request_id = ?, response_code = ?, response_desc = ?, customer_message = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
	$stmt->execute(['sent', $merchantRequestId, $checkoutRequestId, $responseCode, $responseDesc, $customerMessage, $requestId]);

	app_audit_log($conn, 'staff', (string)$account_id, 'sms.wallet.topup', 'wallet', '1', [
		'tokens' => $tokens,
		'phone' => $phone,
		'amount' => $amount,
	]);

	$_SESSION['reply'] = array(array("success", $customerMessage !== '' ? $customerMessage : 'SMS token top-up STK push sent.'));
	header("location:../sms_topup");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../sms_topup");
	exit;
}
