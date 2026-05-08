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
$listExamId = (int)($_POST['list_exam_id'] ?? 0);
if ($reportId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid report card selected.'));
	header('location:../report');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_data_camp_schema($conn);

	if (!app_table_exists($conn, 'tbl_report_cards')) {
		throw new RuntimeException('Report cards table not available.');
	}

	$stmt = $conn->prepare('SELECT id, student_id, class_id, term_id, verification_code FROM tbl_report_cards WHERE id = ? LIMIT 1');
	$stmt->execute([$reportId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
	if (!$row) {
		$_SESSION['reply'] = array(array('danger', 'Report card not found or already missing.'));
	} else {
		app_data_camp_store_record($conn, [
			'module_key' => 'report_cards',
			'record_type' => 'retention_notice',
			'entity_table' => 'tbl_report_cards',
			'entity_id' => (string)$reportId,
			'title' => 'Report card retained',
			'description' => 'A delete request was blocked because report cards are now permanently retained for future reference.',
			'class_id' => (int)($row['class_id'] ?? 0),
			'student_id' => (string)($row['student_id'] ?? ''),
			'source_url' => trim((string)($row['verification_code'] ?? '')) !== '' ? 'verify_report?code=' . trim((string)$row['verification_code']) : null,
			'status' => 'retained',
			'source_key' => 'report_card_retention_notice:' . $reportId,
			'created_by' => isset($account_id) ? (int)$account_id : 0,
		]);
		$_SESSION['reply'] = array(array('warning', 'Report cards are now retained permanently. This record was not deleted and remains available in Data Camp.'));
	}
} catch (Throwable $e) {
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
if ($listExamId > 0) {
	$query['list_exam_id'] = $listExamId;
}
$redirect = '../report';
if (!empty($query)) {
	$redirect .= '?' . http_build_query($query);
}
header('location:' . $redirect);
exit;
