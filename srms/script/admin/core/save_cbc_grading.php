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

	$conn->beginTransaction();
	$conn->exec("DELETE FROM tbl_cbc_grading");

	$stmt = $conn->prepare("INSERT INTO tbl_cbc_grading (level, min_mark, max_mark, points, sort_order, active) VALUES (?,?,?,?,?,?)");
	for ($i = 0; $i < count($levels); $i++) {
		$level = strtoupper(trim((string)$levels[$i]));
		if ($level === '') { continue; }
		$min = (float)($minMarks[$i] ?? 0);
		$max = (float)($maxMarks[$i] ?? 0);
		$pts = (int)($points[$i] ?? 0);
		$order = (int)($orders[$i] ?? ($i + 1));
		$act = (int)($active[$i] ?? 1);
		$stmt->execute([$level, $min, $max, $pts, $order, $act]);
	}

	$conn->commit();
	$_SESSION['reply'] = array (array("success", "CBC grading bands saved."));
	header("location:".$returnTo);
} catch (Throwable $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array (array("danger", "Failed to save CBC grading: " . $e->getMessage()));
	header("location:".$returnTo);
}
