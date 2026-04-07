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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../results_locks");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$locked = (int)($_POST['locked'] ?? 1);
$reason = trim((string)($_POST['reason'] ?? ''));

if ($classId < 1 || $termId < 1) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../results_locks");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($locked === 0) {
		if (!app_has_permission($conn, (string)$account_id, (string)$level, 'results.unlock')) {
			$_SESSION['reply'] = array(array("error", "Only Super Admin can unlock results."));
			header("location:../results_locks?class_id=".$classId."&term_id=".$termId);
			exit;
		}
	} else {
		if (!app_has_permission($conn, (string)$account_id, (string)$level, 'results.lock')) {
			$_SESSION['reply'] = array(array("error", "Access denied to lock results."));
			header("location:../results_locks?class_id=".$classId."&term_id=".$termId);
			exit;
		}
	}

	if (!app_table_exists($conn, 'tbl_results_locks')) {
		$_SESSION['reply'] = array(array("error", "Results lock module is not installed."));
		header("location:../results_locks?class_id=".$classId."&term_id=".$termId);
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	if ($isPgsql) {
		$stmt = $conn->prepare("INSERT INTO tbl_results_locks (class_id, term_id, locked, reason, locked_by, locked_at)
			VALUES (?,?,?,?,?, CURRENT_TIMESTAMP)
			ON CONFLICT (class_id, term_id) DO UPDATE SET locked = EXCLUDED.locked, reason = EXCLUDED.reason, locked_by = EXCLUDED.locked_by, locked_at = EXCLUDED.locked_at");
		$stmt->execute([$classId, $termId, $locked, $reason, (int)$account_id]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_results_locks (class_id, term_id, locked, reason, locked_by, locked_at)
			VALUES (?,?,?,?,?, NOW())
			ON DUPLICATE KEY UPDATE locked = VALUES(locked), reason = VALUES(reason), locked_by = VALUES(locked_by), locked_at = VALUES(locked_at)");
		$stmt->execute([$classId, $termId, $locked, $reason, (int)$account_id]);
	}

	app_audit_log($conn, 'staff', (string)$account_id, $locked === 1 ? 'results.lock' : 'results.unlock', 'results_lock', $classId.':'.$termId);

	$_SESSION['reply'] = array(array("success", $locked === 1 ? "Results locked." : "Results unlocked."));
	header("location:../results_locks?class_id=".$classId."&term_id=".$termId);
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../results_locks?class_id=".$classId."&term_id=".$termId);
	exit;
}
