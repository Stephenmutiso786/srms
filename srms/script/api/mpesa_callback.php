<?php
// M-Pesa STK Push callback endpoint.
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

	if (!app_table_exists($conn, 'tbl_mpesa_stk_requests')) {
		throw new RuntimeException("Missing tbl_mpesa_stk_requests");
	}

	$stk = $payload['Body']['stkCallback'] ?? null;
	if (!is_array($stk)) {
		throw new RuntimeException("Missing stkCallback");
	}

	$merchantRequestId = (string)($stk['MerchantRequestID'] ?? '');
	$checkoutRequestId = (string)($stk['CheckoutRequestID'] ?? '');
	$resultCode = isset($stk['ResultCode']) ? (int)$stk['ResultCode'] : null;
	$resultDesc = (string)($stk['ResultDesc'] ?? '');

	if ($checkoutRequestId === '') {
		throw new RuntimeException("Missing CheckoutRequestID");
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

	// Update request
	$stmt = $conn->prepare("UPDATE tbl_mpesa_stk_requests
		SET status = ?, merchant_request_id = ?, result_code = ?, result_desc = ?, mpesa_receipt = ?, raw_callback = ?, updated_at = CURRENT_TIMESTAMP
		WHERE checkout_request_id = ?");
	$stmt->execute([$status, $merchantRequestId, $resultCode, $resultDesc, $mpesaReceipt, $raw, $checkoutRequestId]);

	// If success: auto-create payment (idempotent by checking receipt/checkout)
	if ($status === 'success') {
		$stmt = $conn->prepare("SELECT id, invoice_id, amount FROM tbl_mpesa_stk_requests WHERE checkout_request_id = ? LIMIT 1");
		$stmt->execute([$checkoutRequestId]);
		$req = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($req) {
			$invoiceId = (int)$req['invoice_id'];
			$payAmount = $amount !== null ? $amount : (float)$req['amount'];
			$ref = $mpesaReceipt !== '' ? $mpesaReceipt : $checkoutRequestId;

			// Prevent duplicates
			$stmt = $conn->prepare("SELECT id FROM tbl_payments WHERE invoice_id = ? AND method = 'mpesa' AND reference = ? LIMIT 1");
			$stmt->execute([$invoiceId, $ref]);
			$exists = $stmt->fetchColumn();
			if (!$exists) {
				$stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?)");
				$stmt->execute([$invoiceId, $payAmount, 'mpesa', $ref, null]);

				app_audit_log($conn, 'staff', 'system', 'payment.mpesa_callback', 'invoice', (string)$invoiceId);
			}

			// Update invoice status if fully paid
			$stmt = $conn->prepare("SELECT
				COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) AS total,
				COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id), 0) AS paid
				FROM tbl_invoices i WHERE i.id = ? LIMIT 1");
			$stmt->execute([$invoiceId]);
			$tot = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($tot) {
				$total = (float)$tot['total'];
				$paid = (float)$tot['paid'];
				$newStatus = ($paid + 0.00001 >= $total && $total > 0) ? 'paid' : 'open';
				$stmt = $conn->prepare("UPDATE tbl_invoices SET status = ? WHERE id = ?");
				$stmt->execute([$newStatus, $invoiceId]);
			}
		}
	}

	echo json_encode(['ok' => true]);
} catch (Throwable $e) {
	http_response_code(500);
	error_log('[api.mpesa_callback] ' . $e->getMessage());
	echo json_encode(['error' => 'Internal server error.']);
}

