<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "0") { header("location:../"); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../report_settings");
	exit;
}

$bestOf = (int)($_POST['best_of'] ?? 0);
$useWeights = (int)($_POST['use_weights'] ?? 1);
$requireFees = (int)($_POST['require_fees_clear'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_result_settings')) {
		$_SESSION['reply'] = array (array("danger", "Result settings table missing. Run migration 007."));
		header("location:../report_settings");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_result_settings (best_of, use_weights, require_fees_clear) VALUES (?,?,?)");
	$stmt->execute([$bestOf, $useWeights, $requireFees]);

	$_SESSION['reply'] = array (array("success", "Settings saved."));
	header("location:../report_settings");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save settings: " . $e->getMessage()));
	header("location:../report_settings");
}
