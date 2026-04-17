<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || ($level !== "0" && $level !== "5")) {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../invoices");
	exit;
}

$invoiceId = (int)($_POST['invoice_id'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$reference = trim((string)($_POST['reference'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

if ($invoiceId < 1 || $amount <= 0) {
	$_SESSION['reply'] = array(array("error", "Invalid cash payment."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);

	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines') || !app_table_exists($conn, 'tbl_payments')) {
		throw new RuntimeException('Fees module is not installed. Run migration 003_fees_finance.sql.');
	}

	app_ensure_receipts_table($conn);

	$stmt = $conn->prepare("SELECT i.id, i.student_id, i.status, COALESCE(SUM(l.amount),0) AS total
		FROM tbl_invoices i
		LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
		WHERE i.id = ?
		GROUP BY i.id, i.student_id, i.status");
	$stmt->execute([$invoiceId]);
	$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$invoice || (string)$invoice['status'] === 'void') {
		throw new RuntimeException('Invoice not found.');
	}

	$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE invoice_id = ?");
	$stmt->execute([$invoiceId]);
	$currentPaid = (float)$stmt->fetchColumn();
	$total = (float)($invoice['total'] ?? 0);
	$balance = max(0, round($total - $currentPaid, 2));
	if ($amount > $balance && $balance > 0) {
		throw new RuntimeException('Payment exceeds invoice balance.');
	}

	$method = 'cash';
	if ($reference === '') {
		$reference = 'CASH-' . date('YmdHis');
	}

	$conn->beginTransaction();

	$stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?)");
	$stmt->execute([$invoiceId, $amount, $method, $reference, (int)$account_id]);
	$paymentId = (int)$conn->lastInsertId();
	if ($paymentId < 1) {
		$stmt = $conn->prepare("SELECT id FROM tbl_payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 1");
		$stmt->execute([$invoiceId]);
		$paymentId = (int)$stmt->fetchColumn();
	}

	$receiptNo = app_generate_receipt_number($conn);
	$stmt = $conn->prepare("INSERT INTO tbl_receipts (payment_id, receipt_number, generated_by) VALUES (?,?,?)");
	$stmt->execute([$paymentId, $receiptNo, (int)$account_id]);

	$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE invoice_id = ?");
	$stmt->execute([$invoiceId]);
	$newPaid = (float)$stmt->fetchColumn();
	$newStatus = ($newPaid + 0.00001 >= $total && $total > 0) ? 'paid' : 'open';
	$stmt = $conn->prepare("UPDATE tbl_invoices SET status = ? WHERE id = ?");
	$stmt->execute([$newStatus, $invoiceId]);

	app_audit_log($conn, 'staff', (string)$account_id, 'payment.add', 'invoice', (string)$invoiceId, [
		'amount' => $amount,
		'method' => 'cash',
		'receipt' => $receiptNo,
	]);

	$conn->commit();

	$_SESSION['reply'] = array(array("success", "Cash payment recorded. Receipt: " . $receiptNo));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
}
