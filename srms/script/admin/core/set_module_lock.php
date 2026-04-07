<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}

app_require_permission('system.manage', '../module_locks');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../module_locks");
	exit;
}

$module = trim($_POST['module'] ?? '');
$locked = (int)($_POST['locked'] ?? 0);
$reason = trim($_POST['reason'] ?? '');

if ($module === '') {
	$_SESSION['reply'] = array(array("error", "Invalid module."));
	header("location:../module_locks");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_module_locks')) {
		$_SESSION['reply'] = array(array("error", "Module locks table missing. Run migration 012."));
		header("location:../module_locks");
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	if ($isPgsql) {
		$stmt = $conn->prepare("INSERT INTO tbl_module_locks (module, locked, reason, locked_by, locked_at)
			VALUES (?,?,?,?, CURRENT_TIMESTAMP)
			ON CONFLICT (module) DO UPDATE SET locked = EXCLUDED.locked, reason = EXCLUDED.reason, locked_by = EXCLUDED.locked_by, locked_at = EXCLUDED.locked_at");
		$stmt->execute([$module, $locked, $reason, (int)$account_id]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_module_locks (module, locked, reason, locked_by, locked_at)
			VALUES (?,?,?,?, NOW())
			ON DUPLICATE KEY UPDATE locked = VALUES(locked), reason = VALUES(reason), locked_by = VALUES(locked_by), locked_at = VALUES(locked_at)");
		$stmt->execute([$module, $locked, $reason, (int)$account_id]);
	}

	$_SESSION['reply'] = array(array("success", $locked === 1 ? "Module locked." : "Module unlocked."));
	header("location:../module_locks");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../module_locks");
	exit;
}
