<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "0") {
	header("location:../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
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
	header("location:../invoices?class_id=".$classId."&term_id=".$termId);
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if (app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_payments WHERE invoice_id IN ($placeholders)");
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
	header("location:../invoices?class_id=".$classId."&term_id=".$termId);
} catch(PDOException $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	echo "Connection failed.";
}
?>
