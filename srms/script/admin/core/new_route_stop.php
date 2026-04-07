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

$routeId = (int)($_POST['route_id'] ?? 0);
$stopName = trim($_POST['stop_name'] ?? '');
$stopOrder = (int)($_POST['stop_order'] ?? 1);

if ($routeId < 1 || $stopName === '') {
	$_SESSION['reply'] = array (array("danger", "Route and stop name are required."));
	header("location:../transport");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_route_stops')) {
		$_SESSION['reply'] = array (array("danger", "Transport tables missing. Run migration 011."));
		header("location:../transport");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_route_stops (route_id, stop_name, stop_order) VALUES (?,?,?)");
	$stmt->execute([$routeId, $stopName, $stopOrder]);

	$_SESSION['reply'] = array (array("success", "Stop added."));
	header("location:../transport");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to add stop: " . $e->getMessage()));
	header("location:../transport");
}
