<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.approve', '../report_settings');
app_require_unlocked('reports', '../report_settings');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../report_settings");
	exit;
}

$subjectId = (int)($_POST['subject_id'] ?? 0);
$weight = (float)($_POST['weight'] ?? 1);

if ($subjectId < 1) {
	$_SESSION['reply'] = array (array("danger", "Invalid subject"));
	header("location:../report_settings");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_subject_weights')) {
		$_SESSION['reply'] = array (array("danger", "Subject weights table missing. Run migration 007."));
		header("location:../report_settings");
		exit;
	}

	$stmt = $conn->prepare("SELECT subject_id FROM tbl_subject_weights WHERE subject_id = ?");
	$stmt->execute([$subjectId]);
	if ($stmt->fetchColumn()) {
		$stmt = $conn->prepare("UPDATE tbl_subject_weights SET weight = ? WHERE subject_id = ?");
		$stmt->execute([$weight, $subjectId]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_subject_weights (subject_id, weight) VALUES (?,?)");
		$stmt->execute([$subjectId, $weight]);
	}

	$_SESSION['reply'] = array (array("success", "Weight saved."));
	header("location:../report_settings");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save weight: " . $e->getMessage()));
	header("location:../report_settings");
}
