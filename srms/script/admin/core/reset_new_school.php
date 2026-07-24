<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true)) { header("location:../"); exit; }
app_require_permission('system.manage', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../reset_new_school");
	exit;
}
if ((string)($_POST['confirm_reset'] ?? '') !== '1') {
	header("location:../reset_new_school");
	exit;
}

try {
	$conn = app_db();
	$backup = app_reset_school_backup_export($conn, (int)$account_id);
	$summary = app_reset_school_people_data($conn);
	$message = sprintf(
		'New-school reset completed. Deleted %d student(s) and blocked %d student(s); deleted %d teacher/staff account(s) and blocked %d teacher/staff account(s); deleted %d parent account(s); kept %d admin account(s). Classes, subjects, terms, and school settings were preserved.',
		(int)$summary['students_removed'],
		(int)($summary['students_blocked'] ?? 0),
		(int)$summary['staff_removed'],
		(int)($summary['staff_blocked'] ?? 0),
		(int)$summary['parents_removed'],
		(int)$summary['admins_kept']
	);
	$backupLink = 'admin/core/download_reset_backup?file=' . rawurlencode((string)($backup['file'] ?? ''));
	$backupHtml = '<p><strong>Backup created:</strong> ' . htmlspecialchars((string)($backup['file'] ?? '')) . '</p>'
		. '<p><a class="btn btn-primary" href="' . htmlspecialchars($backupLink) . '">Download Backup Export</a></p>';
	$warnings = is_array($summary['warnings'] ?? null) ? $summary['warnings'] : [];
	if (count($warnings) > 0) {
		app_reply_redirect_html('warning', $message . ' Completed with ' . count($warnings) . ' warning(s). Some historical records may have been blocked instead of deleted.', '../system', $backupHtml);
	}
	app_reply_redirect_html('success', $message, '../system', $backupHtml);
} catch (Throwable $e) {
	error_log('[admin.reset_new_school] ' . $e->getMessage());
	app_reply_redirect('danger', 'Failed to reset school data. Please check server logs and try again.', '../system');
}
