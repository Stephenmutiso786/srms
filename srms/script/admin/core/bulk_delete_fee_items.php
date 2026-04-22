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
		header("location:../../accountant/fee_structure");
	} else {
		header("location:../fee_structure");
	}
	exit;
}

$ids = $_POST['item_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one fee item to delete"));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure");
	} else {
		header("location:../fee_structure");
	}
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if (app_table_exists($conn, 'tbl_fee_structures')) {
		$stmt = $conn->prepare("DELETE FROM tbl_fee_structures WHERE item_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	$referencedIds = [];
	if (app_table_exists($conn, 'tbl_invoice_lines')) {
		$stmt = $conn->prepare("SELECT DISTINCT item_id FROM tbl_invoice_lines WHERE item_id IN ($placeholders)");
		$stmt->execute($ids);
		$referencedIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
	}

	$deleteIds = array_values(array_diff(array_map('intval', $ids), $referencedIds));
	if (count($deleteIds) > 0) {
		$deletePlaceholders = implode(',', array_fill(0, count($deleteIds), '?'));
		$stmt = $conn->prepare("DELETE FROM tbl_fee_items WHERE id IN ($deletePlaceholders)");
		$stmt->execute($deleteIds);
	}

	if (count($referencedIds) > 0) {
		$archivePlaceholders = implode(',', array_fill(0, count($referencedIds), '?'));
		$stmt = $conn->prepare("UPDATE tbl_fee_items SET status = 0 WHERE id IN ($archivePlaceholders)");
		$stmt->execute($referencedIds);
	}

	$conn->commit();
	$deletedCount = count($deleteIds);
	$archivedCount = count($referencedIds);
	if ($archivedCount > 0) {
		$_SESSION['reply'] = array (array("success","Fee items updated: {$deletedCount} deleted, {$archivedCount} archived because they are used by invoices."));
	} else {
		$_SESSION['reply'] = array (array("success","Selected fee items deleted successfully"));
	}
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure");
	} else {
		header("location:../fee_structure");
	}
} catch(PDOException $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	$_SESSION['reply'] = array(array("error", "Failed to update fee items. " . $e->getMessage()));
	if (isset($level) && $level === "5") {
		header("location:../../accountant/fee_structure");
	} else {
		header("location:../fee_structure");
	}
}
?>
