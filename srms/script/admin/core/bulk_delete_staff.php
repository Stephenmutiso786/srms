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

$ids = $_POST['staff_ids'] ?? [];
$action = $_POST['bulk_action'] ?? 'delete';
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one staff member to delete"));
	header("location:../teachers");
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if ($action === 'set_active' || $action === 'set_blocked') {
		$status = $action === 'set_active' ? 1 : 0;
		$stmt = $conn->prepare("UPDATE tbl_staff SET status = ? WHERE id IN ($placeholders)");
		$stmt->execute(array_merge([$status], $ids));
		$conn->commit();
		$_SESSION['reply'] = array (array("success","Selected staff updated successfully"));
		header("location:../teachers");
		exit;
	}

	if (app_table_exists($conn, 'tbl_user_roles')) {
		$stmt = $conn->prepare("DELETE FROM tbl_user_roles WHERE staff_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'staff')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff IN ($placeholders)");
		$stmt->execute($ids);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_staff WHERE id IN ($placeholders)");
	$stmt->execute($ids);

	$conn->commit();
	$_SESSION['reply'] = array (array("success","Selected staff deleted successfully"));
	header("location:../teachers");
} catch(PDOException $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	echo "Connection failed.";
}
?>
