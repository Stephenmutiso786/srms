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

$plate = trim($_POST['plate_number'] ?? '');
$model = trim($_POST['model'] ?? '');
$capacity = (int)($_POST['capacity'] ?? 0);
$driverId = $_POST['driver_id'] ?? null;
$driverId = $driverId === '' ? null : (int)$driverId;

if ($plate === '') {
	$_SESSION['reply'] = array (array("danger", "Plate number is required."));
	header("location:../transport");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_vehicles')) {
		$_SESSION['reply'] = array (array("danger", "Transport tables missing. Run migration 011."));
		header("location:../transport");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_vehicles (plate_number, model, capacity, driver_id) VALUES (?,?,?,?)");
	$stmt->execute([$plate, $model, $capacity, $driverId]);

	$_SESSION['reply'] = array (array("success", "Vehicle added."));
	header("location:../transport");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to add vehicle: " . $e->getMessage()));
	header("location:../transport");
}
