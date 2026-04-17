<?php
// M-Pesa STK Push callback endpoint for SMS token top-ups.
// Set MPESA_CALLBACK_TOKEN and include it in the callback URL for basic verification.

require_once(__DIR__ . '/../db/config.php');
require_once(__DIR__ . '/../const/mpesa.php');

header('Content-Type: application/json; charset=utf-8');

$expectedToken = getenv('MPESA_CALLBACK_TOKEN') ?: '';
$providedToken = $_GET['token'] ?? '';
if ($expectedToken !== '' && !hash_equals($expectedToken, (string)$providedToken)) {
	http_response_code(403);
	echo json_encode(['error' => 'forbidden']);
	exit;
}

$raw = file_get_contents('php://input') ?: '';
$payload = json_decode($raw, true);
if (!is_array($payload)) {
	http_response_code(400);
	echo json_encode(['error' => 'invalid_json']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_sms_wallet_tables($conn);

	if (!app_table_exists($conn, 'tbl_sms_topup_requests')) {
		throw new RuntimeException('Missing tbl_sms_topup_requests');
	}

	$stk = $payload['Body']['stkCallback'] ?? null;
	if (!is_array($stk)) {
		throw new RuntimeException('Missing stkCallback');
	}

	$merchantRequestId = (string)($stk['MerchantRequestID'] ?? '');
	$checkoutRequestId = (string)($stk['CheckoutRequestID'] ?? '');
	$resultCode = isset($stk['ResultCode']) ? (int)$stk['ResultCode'] : null;
	$resultDesc = (string)($stk['ResultDesc'] ?? '');

	if ($checkoutRequestId === '') {
		throw new RuntimeException('Missing CheckoutRequestID');
	}

	$mpesaReceipt = '';
	$amount = null;
	$phone = '';

	$items = $stk['CallbackMetadata']['Item'] ?? [];
	if (is_array($items)) {
		foreach ($items as $it) {
			if (!is_array($it)) continue;
			$name = (string)($it['Name'] ?? '');
			$val = $it['Value'] ?? null;
			if ($name === 'MpesaReceiptNumber' && is_string($val)) $mpesaReceipt = $val;
			if ($name === 'Amount') $amount = is_numeric($val) ? (float)$val : $amount;
			if ($name === 'PhoneNumber') $phone = is_numeric($val) ? (string)$val : (string)$phone;
		}
	}

	$status = ($resultCode === 0) ? 'success' : 'failed';

	$stmt = $conn->prepare('UPDATE tbl_sms_topup_requests SET status = ?, merchant_request_id = ?, result_code = ?, result_desc = ?, mpesa_receipt = ?, raw_callback = ?, updated_at = CURRENT_TIMESTAMP WHERE checkout_request_id = ?');
	$stmt->execute([$status, $merchantRequestId, $resultCode, $resultDesc, $mpesaReceipt, $raw, $checkoutRequestId]);

	if ($status === 'success') {
		$stmt = $conn->prepare('SELECT id, wallet_id, tokens, amount, created_by FROM tbl_sms_topup_requests WHERE checkout_request_id = ? LIMIT 1');
		$stmt->execute([$checkoutRequestId]);
		$req = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($req) {
			$walletId = (int)$req['wallet_id'];
			$tokens = (int)$req['tokens'];
			$reference = $mpesaReceipt !== '' ? $mpesaReceipt : $checkoutRequestId;
			$duplicateStmt = $conn->prepare('SELECT id FROM tbl_sms_token_transactions WHERE wallet_id = ? AND txn_type = ? AND reference_no = ? LIMIT 1');
			$duplicateStmt->execute([$walletId, 'topup', $reference]);
			if (!(int)$duplicateStmt->fetchColumn()) {
				app_sms_wallet_adjust($conn, $walletId, $tokens, $reference, 'SMS token top-up via M-Pesa', 'topup', (int)($req['created_by'] ?? 0) ?: null);
			}
		}
	}

	echo json_encode(['ok' => true]);
} catch (Throwable $e) {
	http_response_code(500);
	error_log('[api.mpesa_sms_callback] ' . $e->getMessage());
	echo json_encode(['error' => 'Internal server error.']);
}
