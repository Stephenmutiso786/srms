<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../'); exit; }
app_require_permission('student.leadership.manage', '../discipline');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('location:../discipline'); exit; }

$id = (int)($_POST['id'] ?? 0);
$status = strtolower(trim((string)($_POST['status'] ?? 'pending')));
$actionTaken = trim((string)($_POST['action_taken'] ?? ''));
if (!in_array($status, ['pending', 'reviewed', 'resolved'], true)) {
	$status = 'pending';
}
if ($id < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid discipline case id.'));
	header('location:../discipline');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_cases_table($conn);

	$stmt = $conn->prepare('UPDATE tbl_discipline_cases SET status = ?, action_taken = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?');
	$stmt->execute([$status, $actionTaken, (int)$account_id, $id]);
	$_SESSION['reply'] = array(array('success', 'Discipline case updated successfully.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to update discipline case.'));
}

header('location:../discipline');
exit;
