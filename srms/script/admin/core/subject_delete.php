<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
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

	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_subject_class_assignments WHERE subject_id = ?");
		$stmt->execute([$id]);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_subjects WHERE id = ?");
	$stmt->execute([$id]);

	$_SESSION['reply'] = array(array("success", "Subject deleted."));
	header("location:../subjects");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", $e->getMessage()));
	header("location:../subjects");
	exit;
}
