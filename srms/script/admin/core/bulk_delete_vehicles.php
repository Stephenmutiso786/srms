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

$ids = $_POST['vehicle_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one vehicle to delete"));
	header("location:../transport");
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	$routeIds = [];
	if (app_table_exists($conn, 'tbl_routes')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_routes WHERE vehicle_id IN ($placeholders)");
		$stmt->execute($ids);
		$routeIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
	}

	if (!empty($routeIds)) {
		$routePlaceholders = implode(',', array_fill(0, count($routeIds), '?'));
		if (app_table_exists($conn, 'tbl_route_stops')) {
			$stmt = $conn->prepare("DELETE FROM tbl_route_stops WHERE route_id IN ($routePlaceholders)");
			$stmt->execute($routeIds);
		}
		if (app_table_exists($conn, 'tbl_transport_assignments')) {
			$stmt = $conn->prepare("DELETE FROM tbl_transport_assignments WHERE route_id IN ($routePlaceholders)");
			$stmt->execute($routeIds);
		}
		$stmt = $conn->prepare("DELETE FROM tbl_routes WHERE id IN ($routePlaceholders)");
		$stmt->execute($routeIds);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_vehicles WHERE id IN ($placeholders)");
	$stmt->execute($ids);

	$conn->commit();
	$_SESSION['reply'] = array (array("success","Selected vehicles deleted successfully"));
	header("location:../transport");
} catch(PDOException $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	echo "Connection failed.";
}
?>
