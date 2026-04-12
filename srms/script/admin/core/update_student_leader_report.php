<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('student.leadership.manage', '../student_leaders');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../student_leaders');
	exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'open'));
$allowed = ['open', 'in_review', 'resolved', 'dismissed'];

if ($id < 1 || !in_array($status, $allowed, true)) {
	$_SESSION['reply'] = array(array('danger', 'Invalid report update request.'));
	header('location:../student_leaders');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_leadership_reports_table($conn);
	$stmt = $conn->prepare('UPDATE tbl_student_leadership_reports SET status = ?, handled_by = ?, handled_at = CURRENT_TIMESTAMP WHERE id = ?');
	$stmt->execute([$status, (int)$account_id, $id]);
	$_SESSION['reply'] = array(array('success', 'Report status updated.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to update report status.'));
}

header('location:../student_leaders');
exit;
