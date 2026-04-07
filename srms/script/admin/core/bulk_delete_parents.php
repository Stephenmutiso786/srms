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

$ids = $_POST['parent_ids'] ?? [];
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^\d+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one parent to delete"));
	header("location:../parents");
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if (app_table_exists($conn, 'tbl_parent_students')) {
		$stmt = $conn->prepare("DELETE FROM tbl_parent_students WHERE parent_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'parent')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE parent IN ($placeholders)");
		$stmt->execute($ids);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_parents WHERE id IN ($placeholders)");
	$stmt->execute($ids);

	$conn->commit();
	$_SESSION['reply'] = array (array("success","Selected parents deleted successfully"));
	header("location:../parents");
} catch(PDOException $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	echo "Connection failed: " . $e->getMessage();
}
?>
