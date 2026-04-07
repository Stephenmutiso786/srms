<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('transport.manage', '../transport');
app_require_unlocked('transport', '../transport');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../transport");
	exit;
}

$name = trim($_POST['name'] ?? '');
$vehicleId = $_POST['vehicle_id'] ?? null;
$vehicleId = $vehicleId === '' ? null : (int)$vehicleId;

if ($name === '') {
	$_SESSION['reply'] = array (array("danger", "Route name is required."));
	header("location:../transport");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_routes')) {
		$_SESSION['reply'] = array (array("danger", "Transport tables missing. Run migration 011."));
		header("location:../transport");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_routes (name, vehicle_id) VALUES (?,?)");
	$stmt->execute([$name, $vehicleId]);

	$_SESSION['reply'] = array (array("success", "Route added."));
	header("location:../transport");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to add route: " . $e->getMessage()));
	header("location:../transport");
}
