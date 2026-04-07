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

$name = trim($_POST['name'] ?? '');
$category = trim($_POST['category'] ?? '');
$quantity = (int)($_POST['quantity'] ?? 0);
$location = trim($_POST['location'] ?? '');

if ($name === '') {
	$_SESSION['reply'] = array (array("danger", "Asset name is required."));
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

	$stmt = $conn->prepare("INSERT INTO tbl_assets (name, category, quantity, location) VALUES (?,?,?,?)");
	$stmt->execute([$name, $category, $quantity, $location]);

	$assetId = (int)$conn->lastInsertId();
	if (app_table_exists($conn, 'tbl_asset_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_asset_logs (asset_id, action, quantity, note) VALUES (?,?,?,?)");
		$stmt->execute([$assetId, 'add', $quantity, 'Initial stock']);
	}

	$_SESSION['reply'] = array (array("success", "Asset saved."));
	header("location:../inventory");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save asset: " . $e->getMessage()));
	header("location:../inventory");
}
