<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.approve', '../report_settings');
app_require_unlocked('reports', '../report_settings');

$returnTo = ($_POST['return'] ?? '') === 'system' ? '../system' : '../report_settings';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:".$returnTo);
	exit;
}

$bestOf = (int)($_POST['best_of'] ?? 0);
$useWeights = (int)($_POST['use_weights'] ?? 1);
$requireFees = (int)($_POST['require_fees_clear'] ?? 0);
$reportCardTemplate = (string)($_POST['report_card_template'] ?? '2');
if (!in_array($reportCardTemplate, ['1', '2'], true)) {
	$reportCardTemplate = '2';
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_current_mode_mysql_schema($conn);

	if (app_table_exists($conn, 'tbl_result_settings') && !app_column_exists($conn, 'tbl_result_settings', 'report_card_template')) {
		$conn->exec("ALTER TABLE tbl_result_settings ADD COLUMN report_card_template varchar(10) NOT NULL DEFAULT '2' AFTER require_fees_clear");
	}

	$hasTemplateColumn = app_table_exists($conn, 'tbl_result_settings') && app_column_exists($conn, 'tbl_result_settings', 'report_card_template');
	$columns = "best_of, use_weights, require_fees_clear" . ($hasTemplateColumn ? ", report_card_template" : "");
	$placeholders = "?,?,?" . ($hasTemplateColumn ? ",?" : "");
	$stmt = $conn->prepare("INSERT INTO tbl_result_settings ({$columns}) VALUES ({$placeholders})");
	$params = [$bestOf, $useWeights, $requireFees];
	if ($hasTemplateColumn) {
		$params[] = $reportCardTemplate;
	}
	$stmt->execute($params);

	$_SESSION['reply'] = array (array("success", "Settings saved."));
	header("location:".$returnTo);
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save settings: " . $e->getMessage()));
	header("location:".$returnTo);
}
