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
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices");
	} else {
		header("location:../invoices");
	}
	exit;
}

$ids = $_POST['invoice_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one invoice to delete"));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if (app_table_exists($conn, 'tbl_receipts') && app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_receipts WHERE payment_id IN (SELECT id FROM tbl_payments WHERE invoice_id IN ($placeholders))");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_payments WHERE invoice_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_fee_installments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_fee_installments WHERE invoice_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_mpesa_stk_requests')) {
		$stmt = $conn->prepare("DELETE FROM tbl_mpesa_stk_requests WHERE invoice_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_invoice_lines')) {
		$stmt = $conn->prepare("DELETE FROM tbl_invoice_lines WHERE invoice_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_invoices WHERE id IN ($placeholders)");
	$stmt->execute($ids);

	$conn->commit();
	$_SESSION['reply'] = array (array("success","Selected invoices deleted successfully"));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
} catch(PDOException $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	$_SESSION['reply'] = array(array("error", "Failed to delete invoices. " . $e->getMessage()));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
}
?>
