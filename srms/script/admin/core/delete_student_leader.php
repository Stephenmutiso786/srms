<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('student.leadership.manage', '../student_leaders');

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid leadership assignment.'));
	header('location:../student_leaders');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_roles_table($conn);
	$stmt = $conn->prepare('DELETE FROM tbl_student_roles WHERE id = ?');
	$stmt->execute([$id]);
	$_SESSION['reply'] = array(array('success', 'Student leadership assignment removed.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to remove assignment.'));
}

header('location:../student_leaders');
exit;
