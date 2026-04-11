<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || $level !== "0") { header("location:../"); exit; }
app_require_permission('students.manage', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../classes");
	exit;
}

try {
	$conn = app_db();
	$summary = app_apply_cbc_curriculum_defaults($conn, (int)$account_id);
	$message = sprintf(
		'CBC class and subject structure applied. Added %d subject(s), %d class(es), synced %d class-subject link(s), removed %d unused extra subject(s), and removed %d unused extra class(es).',
		(int)$summary['subjects'],
		(int)$summary['classes'],
		(int)$summary['assignments'],
		(int)$summary['removed_subjects'],
		(int)$summary['removed_classes']
	);
	app_reply_redirect('success', $message, '../classes');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to apply CBC defaults.', '../classes');
}
