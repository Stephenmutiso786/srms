<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.approve', '../system');
app_require_unlocked('reports', '../system');

$returnTo = ($_POST['return'] ?? '') === 'system' ? '../system' : '../report_settings';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:".$returnTo);
	exit;
}

$levels = $_POST['level'] ?? [];
$minMarks = $_POST['min_mark'] ?? [];
$maxMarks = $_POST['max_mark'] ?? [];
$points = $_POST['points'] ?? [];
$orders = $_POST['sort_order'] ?? [];
$active = $_POST['active'] ?? [];

if (!is_array($levels) || count($levels) < 1) {
	$_SESSION['reply'] = array (array("danger", "No grading bands provided."));
	header("location:".$returnTo);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_cbc_grading')) {
		$_SESSION['reply'] = array (array("danger", "CBC grading table missing. Run migration 015."));
		header("location:".$returnTo);
		exit;
	}

	app_ensure_overall_grading_defaults($conn);
	$_SESSION['reply'] = array (array("success", "CBC grading bands reset to the default Overall Grading System."));
	header("location:".$returnTo);
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array (array("danger", "Failed to save CBC grading: " . $e->getMessage()));
	header("location:".$returnTo);
}
