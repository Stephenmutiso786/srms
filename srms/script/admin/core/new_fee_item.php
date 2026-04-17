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
	header("location:../fee_structure");
	exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));

if ($name === '') {
	$_SESSION['reply'] = array(array("error", "Item name is required."));
	header("location:../fee_structure");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);

	$stmt = $conn->prepare("INSERT INTO tbl_fee_items (name, description, status) VALUES (?,?,1)");
	$stmt->execute([$name, $description]);

	app_audit_log($conn, 'staff', (string)$account_id, 'fee_item.create', 'fee_item', (string)$conn->lastInsertId());

	$_SESSION['reply'] = array(array("success", "Fee item added."));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure");
	} else {
		header("location:../fee_structure");
	}
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure");
	} else {
		header("location:../fee_structure");
	}
	exit;
}
