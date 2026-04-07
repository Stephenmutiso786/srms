<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/mpesa.php');

if (!isset($res) || $res !== "1" || !isset($level) || ($level !== "0" && $level !== "5")) {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../invoices");
	exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$phone = trim((string)($_POST['phone'] ?? ''));
$amount = (float)($_POST['amount'] ?? 0);

if ($invoiceId < 1 || $phone === '' || $amount <= 0) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../mpesa_pay?invoice_id=".$invoiceId);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_mpesa_stk_requests')) {
		$_SESSION['reply'] = array(array("error", "M-Pesa module not installed. Run migration 006_mpesa_stk.sql."));
		header("location:../mpesa_pay?invoice_id=".$invoiceId);
		exit;
	}

	$stmt = $conn->prepare("SELECT i.id, i.student_id,
		COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) AS total,
		COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id), 0) AS paid
		FROM tbl_invoices i WHERE i.id = ? LIMIT 1");
	$stmt->execute([$invoiceId]);
	$inv = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$inv) {
		throw new RuntimeException("Invoice not found.");
	}

	$balance = max(0, (float)$inv['total'] - (float)$inv['paid']);
	if ($balance <= 0) {
		throw new RuntimeException("Invoice already fully paid.");
	}

	if ($amount > $balance) {
		$amount = $balance;
	}

	$cfg = mpesa_config($conn);
	$accountRef = 'INV-' . $invoiceId;
	$desc = 'School fees payment';

	// Create request row (pending)
	$stmt = $conn->prepare("INSERT INTO tbl_mpesa_stk_requests (invoice_id, phone, amount, account_reference, status, created_by)
		VALUES (?,?,?,?, 'pending', ?)");
	$stmt->execute([$invoiceId, $phone, $amount, $accountRef, (int)$account_id]);
	$reqId = (int)$conn->lastInsertId();

	// Send STK
	$data = mpesa_stk_push($conn, $cfg, $phone, $amount, $accountRef, $desc);

	$merchantRequestId = (string)($data['MerchantRequestID'] ?? '');
	$checkoutRequestId = (string)($data['CheckoutRequestID'] ?? '');
	$responseCode = (string)($data['ResponseCode'] ?? '');
	$responseDesc = (string)($data['ResponseDescription'] ?? '');
	$customerMessage = (string)($data['CustomerMessage'] ?? '');

	$stmt = $conn->prepare("UPDATE tbl_mpesa_stk_requests
		SET status = 'sent', merchant_request_id = ?, checkout_request_id = ?, response_code = ?, response_desc = ?, customer_message = ?, updated_at = CURRENT_TIMESTAMP
		WHERE id = ?");
	$stmt->execute([$merchantRequestId, $checkoutRequestId, $responseCode, $responseDesc, $customerMessage, $reqId]);

	app_audit_log($conn, 'staff', (string)$account_id, 'mpesa.stk_push', 'invoice', (string)$invoiceId);

	$_SESSION['reply'] = array(array("success", $customerMessage !== '' ? $customerMessage : "STK push sent."));
	header("location:../mpesa_pay?invoice_id=".$invoiceId);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../mpesa_pay?invoice_id=".$invoiceId);
	exit;
}

