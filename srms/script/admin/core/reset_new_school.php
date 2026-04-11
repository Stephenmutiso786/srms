<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true)) { header("location:../"); exit; }
app_require_permission('system.manage', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../system");
	exit;
}

try {
	$conn = app_db();
	$summary = app_reset_school_people_data($conn);
	$message = sprintf(
		'New-school reset completed. Removed %d student(s), %d teacher/staff account(s), %d parent account(s), and kept %d admin account(s). Classes, subjects, terms, and school settings were preserved.',
		(int)$summary['students_removed'],
		(int)$summary['staff_removed'],
		(int)$summary['parents_removed'],
		(int)$summary['admins_kept']
	);
	app_reply_redirect('success', $message, '../system');
} catch (Throwable $e) {
	error_log('[admin.reset_new_school] ' . $e->getMessage());
	app_reply_redirect('danger', 'Failed to reset school data. Please check server logs and try again.', '../system');
}
