<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

app_require_discipline_access();

$returnTo = trim((string)($_POST['return_to'] ?? '../../academic/discipline.php'));
if ($returnTo === '') {
	$returnTo = '../../academic/discipline.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	app_render_access_error_page('Invalid request method', 'This endpoint only accepts POST requests for deleting discipline cases.', 405, [
		'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
	]);
}

$id = (int)($_POST['id'] ?? 0);
if ($id < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid discipline case selected.'));
	header('location:' . $returnTo);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);
	app_ensure_data_camp_schema($conn);
	$archive = app_discipline_case_archive_payload($conn, $id);
	$caseRow = (array)($archive['case'] ?? []);
	if ($archive) {
		app_data_camp_store_event($conn, [
			'module_key' => 'discipline',
			'record_type' => 'discipline_case_deleted',
			'entity_table' => 'tbl_discipline_cases',
			'entity_id' => (string)$id,
			'title' => trim((string)($caseRow['student_name'] ?? '')) !== '' ? (string)$caseRow['student_name'] . ' Discipline Case' : ('Discipline Case #' . (string)$id),
			'description' => 'Discipline case, hearings, and letters retained before deletion',
			'class_id' => (int)($caseRow['class_id'] ?? 0) > 0 ? (int)$caseRow['class_id'] : null,
			'student_id' => trim((string)($caseRow['student_id'] ?? '')) ?: null,
			'owner_portal' => 'admin,academic',
			'mime_type' => 'application/json',
			'status' => 'retained',
			'payload_json' => $archive,
			'created_by' => (int)$account_id,
		]);
	}

	if (app_table_exists($conn, 'tbl_discipline_hearings')) {
		$stmt = $conn->prepare('DELETE FROM tbl_discipline_hearings WHERE case_id = ?');
		$stmt->execute([$id]);
	}

	if (app_table_exists($conn, 'tbl_discipline_letters')) {
		$stmt = $conn->prepare('DELETE FROM tbl_discipline_letters WHERE case_id = ?');
		$stmt->execute([$id]);
	}

	$stmt = $conn->prepare('DELETE FROM tbl_discipline_cases WHERE id = ?');
	$stmt->execute([$id]);

	$_SESSION['reply'] = array(array('success', 'Discipline case deleted successfully.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to delete discipline case.'));
}

header('location:' . $returnTo);
exit;
?>
