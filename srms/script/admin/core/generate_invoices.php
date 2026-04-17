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
$issueDate = date('Y-m-d');

if ($classId < 1 || $termId < 1) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../invoices");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);

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

	$conn->beginTransaction();
	$selectInvoice = $conn->prepare("SELECT id, status FROM tbl_invoices WHERE student_id = ? AND term_id = ? LIMIT 1");
	$updateInvoice = $conn->prepare("UPDATE tbl_invoices SET class_id = ?, due_date = ?, status = ? WHERE id = ?");
	$insertInvoice = $conn->prepare("INSERT INTO tbl_invoices (student_id, class_id, term_id, issue_date, due_date, status, created_by) VALUES (?,?,?,?,?, 'open', ?)");
	$updateLine = $conn->prepare("UPDATE tbl_invoice_lines SET amount = ? WHERE invoice_id = ? AND item_id = ?");
	$insertLine = $conn->prepare("INSERT INTO tbl_invoice_lines (invoice_id, item_id, amount) VALUES (?,?,?)");

	foreach ($students as $sid) {
		$invoiceId = 0;
		$selectInvoice->execute([(string)$sid, $termId]);
		$existingInvoice = $selectInvoice->fetch(PDO::FETCH_ASSOC) ?: [];
		if ($existingInvoice) {
			$invoiceId = (int)($existingInvoice['id'] ?? 0);
			$status = ((string)($existingInvoice['status'] ?? '') === 'void') ? 'open' : (string)($existingInvoice['status'] ?? 'open');
			if ($status === '') {
				$status = 'open';
			}
			$updateInvoice->execute([$classId, $dueDate, $status, $invoiceId]);
		} else {
			$insertInvoice->execute([(string)$sid, $classId, $termId, $issueDate, $dueDate, (int)$account_id]);
			$invoiceId = (int)$conn->lastInsertId();
			if ($invoiceId < 1) {
				$selectInvoice->execute([(string)$sid, $termId]);
				$invoiceId = (int)$selectInvoice->fetchColumn();
			}
		}

		if ($invoiceId < 1) continue;

		foreach ($structure as $line) {
			$updateLine->execute([(float)$line['amount'], $invoiceId, (int)$line['item_id']]);
			if ($updateLine->rowCount() < 1) {
				$insertLine->execute([$invoiceId, (int)$line['item_id'], (float)$line['amount']]);
			}
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
