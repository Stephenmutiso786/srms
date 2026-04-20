<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "2") {
	header("location:../../");
	exit;
}
app_require_permission('staff.manage', '../roles');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../roles");
	exit;
}

$staffId = (int)($_POST['staff_id'] ?? 0);
$roleId = (int)($_POST['role_id'] ?? 0);

if ($staffId < 1 || $roleId < 1) {
	$_SESSION['reply'] = array(array("danger", "Select staff and role."));
	header("location:../roles");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_user_roles')) {
		$_SESSION['reply'] = array(array("danger", "RBAC tables missing. Run migration 012."));
		header("location:../roles");
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	if ($isPgsql) {
		$stmt = $conn->prepare("INSERT INTO tbl_user_roles (staff_id, role_id) VALUES (?,?) ON CONFLICT DO NOTHING");
		$stmt->execute([$staffId, $roleId]);
	} else {
		$stmt = $conn->prepare("INSERT IGNORE INTO tbl_user_roles (staff_id, role_id) VALUES (?,?)");
		$stmt->execute([$staffId, $roleId]);
	}

	$_SESSION['reply'] = array(array("success", "Role assigned."));
	header("location:../roles");
} catch (PDOException $e) {
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
	header("location:../roles");
}
