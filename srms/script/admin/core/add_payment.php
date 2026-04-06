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
$method = trim((string)($_POST['method'] ?? 'cash'));
$reference = trim((string)($_POST['reference'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);

if ($invoiceId < 1 || $amount <= 0) {
	$_SESSION['reply'] = array(array("error", "Invalid payment."));
	header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("INSERT INTO tbl_payments (invoice_id, amount, method, reference, received_by) VALUES (?,?,?,?,?)");
	$stmt->execute([$invoiceId, $amount, $method, $reference, (int)$account_id]);

	app_audit_log($conn, 'staff', (string)$account_id, 'payment.add', 'invoice', (string)$invoiceId);

	$_SESSION['reply'] = array(array("success", "Payment recorded."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/invoices?class_id=".$classId."&term_id=".$termId);
	} else {
		header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	}
	exit;
}
