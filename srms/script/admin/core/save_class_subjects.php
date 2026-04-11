<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || !in_array((string)$level, ['0', '1'], true)) { header("location:../"); exit; }
app_require_permission('academic.manage', '../classes');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../classes");
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$subjectIds = $_POST['subject_ids'] ?? [];

if ($classId < 1) {
	app_reply_redirect('danger', 'Select a class first.', '../classes');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_save_class_subject_assignments($conn, $classId, is_array($subjectIds) ? $subjectIds : [], isset($account_id) ? (int)$account_id : null);
	app_reply_redirect('success', 'Class subjects updated successfully.', '../classes');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Unable to save class subjects right now.', '../classes');
}
