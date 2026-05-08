<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json');

if (!isset($res) || $res !== "1" || (int)$level !== 5) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$studentId = trim((string)($input['student_id'] ?? ''));
$amount = (float)($input['amount'] ?? 0);
$method = strtolower(trim((string)($input['method'] ?? 'cash')));

if ($studentId === '' || $amount <= 0 || !in_array($method, ['cash', 'cheque', 'bank', 'mpesa'], true)) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid student, amount, or payment method']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	app_ensure_receipts_table($conn);
	app_sync_student_finance_class_links($conn, $studentId);

	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE id = ? AND status = 1 LIMIT 1");
	$stmt->execute([$studentId]);
	if (!$stmt->fetchColumn()) {
		throw new RuntimeException('Student not found.');
	}

	$stmt = $conn->prepare("
		SELECT i.id,
			COALESCE(line_totals.total_amount, 0) AS total_amount,
			COALESCE(payment_totals.total_paid, 0) AS total_paid
		FROM tbl_invoices i
		LEFT JOIN (
			SELECT invoice_id, SUM(amount) AS total_amount
			FROM tbl_invoice_lines
			GROUP BY invoice_id
		) AS line_totals ON line_totals.invoice_id = i.id
		LEFT JOIN (
			SELECT invoice_id, SUM(amount) AS total_paid
			FROM tbl_payments
			GROUP BY invoice_id
		) AS payment_totals ON payment_totals.invoice_id = i.id
		WHERE i.student_id = ? AND i.status <> 'void'
		ORDER BY
			CASE WHEN i.status = 'open' THEN 0 ELSE 1 END,
			i.term_id DESC,
			i.id DESC
		LIMIT 1
	");
	$stmt->execute([$studentId]);
	$invoice = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	if (!$invoice) {
		throw new RuntimeException('No invoice found for this student.');
	}

	$invoiceId = (int)($invoice['id'] ?? 0);
	$balance = max(0, round((float)($invoice['total_amount'] ?? 0) - (float)($invoice['total_paid'] ?? 0), 2));
	if ($invoiceId < 1 || $balance <= 0) {
		throw new RuntimeException('This student has no outstanding invoice balance.');
	}
	if ($amount > $balance) {
		throw new RuntimeException('Payment exceeds the outstanding invoice balance.');
	}

	$referencePrefix = strtoupper($method);
	$reference = $referencePrefix . '-' . date('YmdHis');
	$receiptNo = '';

	$conn->beginTransaction();

	$stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?)");
	$stmt->execute([$invoiceId, $amount, $method, $reference, (int)$account_id]);
	$paymentId = (int)$conn->lastInsertId();
	if ($paymentId < 1) {
		throw new RuntimeException('Failed to record payment.');
	}

	$stmt = $conn->prepare("UPDATE tbl_invoices i
		SET status = CASE
			WHEN (
				COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id), 0) + 0.00001
			) >= COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0)
			AND COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) > 0
			THEN 'paid'
			ELSE 'open'
		END
		WHERE i.id = ?");
	$stmt->execute([$invoiceId]);

	$receiptNo = app_generate_receipt_number($conn);
	$verificationCode = app_generate_receipt_verification_code($conn);
	$stmt = $conn->prepare("INSERT INTO tbl_receipts (payment_id, receipt_number, verification_code, generated_by) VALUES (?,?,?,?)");
	$stmt->execute([$paymentId, $receiptNo, $verificationCode, (int)$account_id]);

	app_audit_log($conn, 'staff', (string)$account_id, 'payment.add', 'invoice', (string)$invoiceId, [
		'student_id' => $studentId,
		'amount' => $amount,
		'method' => $method,
		'receipt' => $receiptNo,
	]);

	$conn->commit();

	echo json_encode([
		'success' => true,
		'message' => 'Payment recorded successfully. Receipt: ' . $receiptNo,
		'receipt_number' => $receiptNo,
	]);
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[" . __FILE__ . ":" . __LINE__ . "] " . $e->getMessage());
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
