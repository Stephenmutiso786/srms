<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== '1' || !isset($level) || $level !== '0') {
	header('location:../');
	exit;
}
app_require_permission('report.generate', '../report');
app_require_unlocked('reports', '../report');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../report');
	exit;
}

$reportId = (int)($_POST['report_id'] ?? 0);
$listClassId = (int)($_POST['list_class_id'] ?? 0);
$listTermId = (int)($_POST['list_term_id'] ?? 0);
if ($reportId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid report card selected.'));
	header('location:../report');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_report_cards')) {
		throw new RuntimeException('Report cards table not available.');
	}

	$stmt = $conn->prepare('SELECT id FROM tbl_report_cards WHERE id = ? LIMIT 1');
	$stmt->execute([$reportId]);
	$exists = (int)$stmt->fetchColumn();
	if ($exists < 1) {
		$_SESSION['reply'] = array(array('danger', 'Report card not found or already deleted.'));
	} else {
		$conn->beginTransaction();
		if (app_table_exists($conn, 'tbl_report_card_subjects') && app_column_exists($conn, 'tbl_report_card_subjects', 'report_id')) {
			$stmt = $conn->prepare('DELETE FROM tbl_report_card_subjects WHERE report_id = ?');
			$stmt->execute([$reportId]);
		}
		$stmt = $conn->prepare('DELETE FROM tbl_report_cards WHERE id = ? LIMIT 1');
		$stmt->execute([$reportId]);
		$conn->commit();
		$_SESSION['reply'] = array(array('success', 'Generated report card deleted successfully.'));
	}
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to delete report card: ' . $e->getMessage()));
}

$query = array();
if ($listClassId > 0) {
	$query['list_class_id'] = $listClassId;
}
if ($listTermId > 0) {
	$query['list_term_id'] = $listTermId;
}
$redirect = '../report';
if (!empty($query)) {
	$redirect .= '?' . http_build_query($query);
}
header('location:' . $redirect);
exit;
