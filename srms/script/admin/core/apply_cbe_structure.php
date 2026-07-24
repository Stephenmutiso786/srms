<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true)) { header("location:../"); exit; }
app_require_permission('students.manage', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../classes");
	exit;
}

try {
	$conn = app_db();
	$summary = app_apply_cbe_curriculum_defaults($conn, (int)$account_id);
	$message = sprintf(
		'CBE class and subject structure merged into the current setup. Added %d subject(s), %d class(es), synced %d default class-subject link(s), preserved %d extra subject(s), preserved %d extra class(es), skipped %d subject(s), and skipped %d class(es).',
		(int)$summary['subjects'],
		(int)$summary['classes'],
		(int)$summary['assignments'],
		(int)($summary['preserved_subjects'] ?? 0),
		(int)($summary['preserved_classes'] ?? 0),
		(int)($summary['skipped_subjects'] ?? 0),
		(int)($summary['skipped_classes'] ?? 0)
	);
	if (!empty($summary['errors'])) {
		$message .= ' ' . implode(' ', array_slice($summary['errors'], 0, 3));
	}
	app_reply_redirect('success', $message, '../classes');
} catch (Throwable $e) {
	error_log('[admin.apply_cbe_structure] ' . $e->getMessage());
	app_reply_redirect('danger', 'Failed to apply CBE defaults. Please try again or contact support.', '../classes');
}
