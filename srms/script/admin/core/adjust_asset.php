<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('inventory.manage', '../inventory');
app_require_unlocked('inventory', '../inventory');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../inventory");
	exit;
}

$assetId = (int)($_POST['asset_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$qty = (int)($_POST['quantity'] ?? 0);
$note = trim($_POST['note'] ?? '');

if ($assetId < 1 || $action === '') {
	$_SESSION['reply'] = array (array("danger", "Invalid request."));
	header("location:../inventory");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_assets')) {
		$_SESSION['reply'] = array (array("danger", "Inventory tables missing. Run migration 010."));
		header("location:../inventory");
		exit;
	}

	$stmt = $conn->prepare("SELECT quantity FROM tbl_assets WHERE id = ? LIMIT 1");
	$stmt->execute([$assetId]);
	$current = (int)$stmt->fetchColumn();

	$newQty = $current;
	if (in_array($action, ['add', 'return'], true)) {
		$newQty = $current + $qty;
	} elseif (in_array($action, ['issue', 'dispose'], true)) {
		$newQty = max(0, $current - $qty);
	} elseif ($action === 'update') {
		$newQty = $qty;
	}

	$conn->beginTransaction();
	$stmt = $conn->prepare("UPDATE tbl_assets SET quantity = ? WHERE id = ?");
	$stmt->execute([$newQty, $assetId]);
	if (app_table_exists($conn, 'tbl_asset_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_asset_logs (asset_id, action, quantity, note) VALUES (?,?,?,?)");
		$stmt->execute([$assetId, $action, $qty, $note]);
	}
	$conn->commit();

	$_SESSION['reply'] = array (array("success", "Stock updated."));
	header("location:../inventory");
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array (array("danger", "Failed to update: " . $e->getMessage()));
	header("location:../inventory");
}
