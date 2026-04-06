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

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$dueDate = trim((string)($_POST['due_date'] ?? ''));
$dueDate = $dueDate === '' ? null : $dueDate;

if ($classId < 1 || $termId < 1) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../invoices");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_fee_structures') || !app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines')) {
		$_SESSION['reply'] = array(array("error", "Fees module is not installed. Run migration 003_fees_finance.sql."));
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
		exit;
	}

	$stmt = $conn->prepare("SELECT item_id, amount FROM tbl_fee_structures WHERE class_id = ? AND term_id = ? AND amount > 0");
	$stmt->execute([$classId, $termId]);
	$structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($structure) < 1) {
		$_SESSION['reply'] = array(array("error", "No fee structure found for the selected class/term. Set it first."));
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
		exit;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ? AND status = 1 ORDER BY id");
	$stmt->execute([$classId]);
	$students = $stmt->fetchAll(PDO::FETCH_COLUMN);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$conn->beginTransaction();

	$insertInvoice = $isPgsql
		? $conn->prepare("INSERT INTO tbl_invoices (student_id, class_id, term_id, due_date, status, created_by) VALUES (?,?,?,?, 'open', ?)
			ON CONFLICT (student_id, term_id) DO UPDATE SET class_id = EXCLUDED.class_id, due_date = EXCLUDED.due_date, status = CASE WHEN tbl_invoices.status='void' THEN 'open' ELSE tbl_invoices.status END
			RETURNING id")
		: $conn->prepare("INSERT INTO tbl_invoices (student_id, class_id, term_id, due_date, status, created_by) VALUES (?,?,?,?, 'open', ?)
			ON DUPLICATE KEY UPDATE class_id = VALUES(class_id), due_date = VALUES(due_date), status = IF(status='void','open',status)");

	$selectInvoiceId = $conn->prepare("SELECT id FROM tbl_invoices WHERE student_id = ? AND term_id = ? LIMIT 1");

	$upsertLine = $isPgsql
		? $conn->prepare("INSERT INTO tbl_invoice_lines (invoice_id, item_id, amount) VALUES (?,?,?)
			ON CONFLICT (invoice_id, item_id) DO UPDATE SET amount = EXCLUDED.amount")
		: $conn->prepare("INSERT INTO tbl_invoice_lines (invoice_id, item_id, amount) VALUES (?,?,?)
			ON DUPLICATE KEY UPDATE amount = VALUES(amount)");

	foreach ($students as $sid) {
		$invoiceId = 0;
		if ($isPgsql) {
			$insertInvoice->execute([(string)$sid, $classId, $termId, $dueDate, (int)$account_id]);
			$invoiceId = (int)$insertInvoice->fetchColumn();
		} else {
			$insertInvoice->execute([(string)$sid, $classId, $termId, $dueDate, (int)$account_id]);
			$selectInvoiceId->execute([(string)$sid, $termId]);
			$invoiceId = (int)$selectInvoiceId->fetchColumn();
		}

		if ($invoiceId < 1) continue;

		foreach ($structure as $line) {
			$upsertLine->execute([$invoiceId, (int)$line['item_id'], (float)$line['amount']]);
		}
	}

	$conn->commit();
	app_audit_log($conn, 'staff', (string)$account_id, 'invoice.generate', 'invoice_batch', $classId . ':' . $termId);

	$_SESSION['reply'] = array(array("success", "Invoices generated/updated."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
} catch (PDOException $e) {
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
