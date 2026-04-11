<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || !in_array((string)$level, ['0', '9'], true)) { header("location:../"); exit; }
app_require_permission('exams.manage', '../');

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
	$_SESSION['reply'] = array(array("danger", "Invalid subject."));
	header("location:../subjects");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();
	app_delete_subject($conn, $id);
	$conn->commit();

	$_SESSION['reply'] = array(array("success", "Subject deleted."));
	header("location:../subjects");
	exit;
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('[admin.subject_delete] ' . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Unable to delete subject. Remove linked historical records first or unassign it from active workflows."));
	header("location:../subjects");
	exit;
}
