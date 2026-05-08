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
