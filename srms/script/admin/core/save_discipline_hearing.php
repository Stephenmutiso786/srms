<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

app_require_discipline_access();

$returnTo = trim((string)($_POST['return_to'] ?? '../discipline.php'));
if ($returnTo === '') { $returnTo = '../discipline.php'; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	app_render_access_error_page('Invalid request method', 'This endpoint only accepts POST requests for saving discipline hearings.', 405, [
		'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
	]);
}

$caseId = (int)($_POST['case_id'] ?? 0);
$hearingDate = trim((string)($_POST['hearing_date'] ?? ''));
$participants = trim((string)($_POST['participants'] ?? ''));
$notes = trim((string)($_POST['notes'] ?? ''));
$outcome = trim((string)($_POST['outcome'] ?? ''));

if ($caseId < 1 || $hearingDate === '') {
	$_SESSION['reply'] = array(array('danger', 'Case and hearing date are required.'));
	header('location:' . $returnTo);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);

	$stmt = $conn->prepare('INSERT INTO tbl_discipline_hearings (case_id, hearing_date, participants, notes, outcome, status, created_by) VALUES (?,?,?,?,?,?,?)');
	$stmt->execute([$caseId, date('Y-m-d H:i:s', strtotime($hearingDate)), $participants, $notes, $outcome, 'Scheduled', (int)$account_id]);

	$update = $conn->prepare("UPDATE tbl_discipline_cases SET case_status = 'Hearing Scheduled', reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
	$update->execute([(int)$account_id, $caseId]);

	$_SESSION['reply'] = array(array('success', 'Hearing scheduled successfully.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to schedule hearing.'));
}

header('location:' . $returnTo);
exit;
?>
