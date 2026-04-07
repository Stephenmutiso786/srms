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

$studentId = trim($_POST['student_id'] ?? '');
$routeId = (int)($_POST['route_id'] ?? 0);
$stopId = $_POST['stop_id'] ?? null;
$stopId = $stopId === '' ? null : (int)$stopId;

if ($studentId === '' || $routeId < 1) {
	$_SESSION['reply'] = array (array("danger", "Student and route are required."));
	header("location:../transport");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_transport_assignments')) {
		$_SESSION['reply'] = array (array("danger", "Transport tables missing. Run migration 011."));
		header("location:../transport");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_transport_assignments (student_id, route_id, stop_id) VALUES (?,?,?)");
	$stmt->execute([$studentId, $routeId, $stopId]);

	$_SESSION['reply'] = array (array("success", "Student assigned to route."));
	header("location:../transport");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to assign: " . $e->getMessage()));
	header("location:../transport");
}
