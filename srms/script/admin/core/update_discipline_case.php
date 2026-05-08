<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

app_require_discipline_access();

$returnTo = trim((string)($_POST['return_to'] ?? '../discipline.php'));
if ($returnTo === '') {
	$returnTo = '../discipline.php';
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	app_render_access_error_page('Invalid request method', 'This endpoint only accepts POST requests for updating discipline cases.', 405, [
		'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
	]);
}

$id = (int)($_POST['id'] ?? 0);
$status = array_key_exists('status', $_POST) ? strtolower(trim((string)$_POST['status'])) : null;
$caseStatus = array_key_exists('case_status', $_POST) ? trim((string)$_POST['case_status']) : null;
$category = array_key_exists('category', $_POST) ? trim((string)$_POST['category']) : null;
$actionTaken = array_key_exists('action_taken', $_POST) ? trim((string)$_POST['action_taken']) : null;
$parentVisitStatus = array_key_exists('parent_visit_status', $_POST) ? trim((string)$_POST['parent_visit_status']) : null;
$reviewNotes = trim((string)($_POST['review_notes'] ?? ''));
$allowedStatus = ['pending', 'reviewed', 'resolved'];
$allowedCaseStatus = app_discipline_case_status_options();
$allowedParentVisit = ['Pending', 'Visited', 'Follow Up Required'];
if ($id < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid discipline case id.'));
	header('location:' . $returnTo);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);

	$currentStmt = $conn->prepare('SELECT status, case_status, category, action_taken, parent_visit_status FROM tbl_discipline_cases WHERE id = ? LIMIT 1');
	$currentStmt->execute([$id]);
	$currentCase = $currentStmt->fetch(PDO::FETCH_ASSOC);
	if (!$currentCase) {
		throw new RuntimeException('Discipline case not found.');
	}

	if ($status === null || !in_array($status, $allowedStatus, true)) {
		$status = strtolower(trim((string)($currentCase['status'] ?? 'pending')));
	}
	if ($caseStatus === null || !in_array($caseStatus, $allowedCaseStatus, true)) {
		$caseStatus = trim((string)($currentCase['case_status'] ?? 'Reported'));
	}
	if ($category === null || !in_array($category, ['Minor', 'Moderate', 'Major', 'Severe'], true)) {
		$category = trim((string)($currentCase['category'] ?? 'Moderate'));
	}
	if ($actionTaken === null) {
		$actionTaken = trim((string)($currentCase['action_taken'] ?? ''));
	}
	if ($parentVisitStatus === null || !in_array($parentVisitStatus, $allowedParentVisit, true)) {
		$parentVisitStatus = trim((string)($currentCase['parent_visit_status'] ?? 'Pending'));
	}

	if ($caseStatus === 'Resolved') {
		$status = 'resolved';
	} elseif ($caseStatus === 'Reported' && !array_key_exists('status', $_POST)) {
		$status = 'pending';
	}

	if ($reviewNotes !== '') {
		$actionTaken = trim($actionTaken . ' | Note: ' . $reviewNotes);
	}
	$stmt = $conn->prepare('UPDATE tbl_discipline_cases
		SET status = ?, case_status = ?, category = ?, action_taken = ?, action_recommended = ?, parent_visit_status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
		WHERE id = ?');
	$suggestedAction = app_discipline_suggest_action($category);
	$stmt->execute([$status, $caseStatus, $category, $actionTaken, $suggestedAction, $parentVisitStatus, (int)$account_id, $id]);

	$_SESSION['reply'] = array(array('success', 'Discipline case updated successfully.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to update discipline case.'));
}

header('location:' . $returnTo);
exit;
?>
