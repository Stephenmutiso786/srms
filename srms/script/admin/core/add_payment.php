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

function app_payment_trace(string $step, string $sql, array $params = [], ?Throwable $error = null): void
{
	$parts = ['[admin.add_payment][' . $step . ']'];
	$parts[] = $sql;
	if (!empty($params)) {
		$parts[] = 'params=' . json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}
	if ($error !== null) {
		$parts[] = 'error=' . $error->getMessage();
		if ($error instanceof PDOException && isset($error->errorInfo)) {
			$parts[] = 'sqlstate=' . (string)($error->errorInfo[0] ?? '');
			$parts[] = 'driver=' . (string)($error->errorInfo[2] ?? '');
		}
	}
	error_log(implode(' | ', $parts));
}

function app_payment_execute(PDOStatement $stmt, string $step, string $sql, array $params = []): void
{
	app_payment_trace($step, $sql, $params);
	try {
		$stmt->execute($params);
	} catch (Throwable $e) {
		app_payment_trace($step, $sql, $params, $e);
		throw $e;
	}
}

function app_payment_fetch_column(PDOStatement $stmt, string $step, string $sql, array $params = []): mixed
{
	app_payment_execute($stmt, $step, $sql, $params);
	return $stmt->fetchColumn();
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
	app_payment_execute($stmt, 'load_invoice', 'SELECT i.id, i.student_id, i.status, COALESCE(SUM(l.amount),0) AS total FROM tbl_invoices i LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id WHERE i.id = ? GROUP BY i.id, i.student_id, i.status', [$invoiceId]);
	$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$invoice || (string)$invoice['status'] === 'void') {
		throw new RuntimeException('Invoice not found.');
	}

	$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE invoice_id = ?");
	app_payment_execute($stmt, 'load_paid_total', 'SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE invoice_id = ?', [$invoiceId]);
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

	$receivedBy = null;
	if (app_table_exists($conn, 'tbl_staff')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_staff WHERE id = ? LIMIT 1");
		app_payment_execute($stmt, 'resolve_staff', 'SELECT id FROM tbl_staff WHERE id = ? LIMIT 1', [(int)$account_id]);
		$staffId = (int)$stmt->fetchColumn();
		if ($staffId > 0) {
			$receivedBy = $staffId;
		}
	}

	if ($conn->inTransaction()) {
		$conn->rollBack();
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?) RETURNING id");
		app_payment_execute($stmt, 'insert_payment', 'INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?) RETURNING id', [$invoiceId, $amount, $method, $reference, $receivedBy]);
		$paymentId = (int)$stmt->fetchColumn();
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?)");
		app_payment_execute($stmt, 'insert_payment', 'INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?)', [$invoiceId, $amount, $method, $reference, $receivedBy]);
		$paymentId = (int)$conn->lastInsertId();
	}

	if ($paymentId < 1) {
		$stmt = $conn->prepare("SELECT id FROM tbl_payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 1");
		app_payment_execute($stmt, 'fallback_payment_id', 'SELECT id FROM tbl_payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 1', [$invoiceId]);
		$paymentId = (int)$stmt->fetchColumn();
	}
	if ($paymentId < 1) {
		throw new RuntimeException('Payment failed at step insert_payment: failed to resolve payment id.');
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
	app_payment_execute($stmt, 'update_invoice_status', "UPDATE tbl_invoices i SET status = CASE WHEN (COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id), 0) + 0.00001) >= COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) AND COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) > 0 THEN 'paid' ELSE 'open' END WHERE i.id = ?", [$invoiceId]);

	$receiptNo = '';
	$receiptError = '';
	try {
		$receiptNo = app_generate_receipt_number($conn);
		$generatedBy = $receivedBy;
		$stmt = $conn->prepare("INSERT INTO tbl_receipts (payment_id, receipt_number, generated_by) VALUES (?,?,?)");
		app_payment_execute($stmt, 'insert_receipt', 'INSERT INTO tbl_receipts (payment_id, receipt_number, generated_by) VALUES (?,?,?)', [$paymentId, $receiptNo, $generatedBy]);
	} catch (Throwable $receiptEx) {
		$receiptNo = '';
		$receiptError = $receiptEx->getMessage();
		error_log('[admin.add_payment.receipt] ' . $receiptError);
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'payment.add', 'invoice', (string)$invoiceId, [
		'amount' => $amount,
		'method' => 'cash',
		'receipt' => $receiptNo,
		'receipt_error' => $receiptError,
	]);

	if ($receiptNo !== '') {
		$_SESSION['reply'] = array(array("success", "Cash payment recorded. Receipt: " . $receiptNo));
	} else {
		$_SESSION['reply'] = array(array("success", "Cash payment recorded. Receipt generation skipped; invoice balance updated."));
	}
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
