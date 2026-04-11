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

$ids = $_POST['route_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one route to delete"));
	header("location:../transport");
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if (app_table_exists($conn, 'tbl_route_stops')) {
		$stmt = $conn->prepare("DELETE FROM tbl_route_stops WHERE route_id IN ($placeholders)");
		$stmt->execute($ids);
	}
	if (app_table_exists($conn, 'tbl_transport_assignments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_transport_assignments WHERE route_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_routes WHERE id IN ($placeholders)");
	$stmt->execute($ids);

	$conn->commit();
	$_SESSION['reply'] = array (array("success","Selected routes deleted successfully"));
	header("location:../transport");
} catch(PDOException $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	echo "Connection failed.";
}
?>
