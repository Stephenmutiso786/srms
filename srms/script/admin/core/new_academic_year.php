<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || !in_array((string)$level, ['0', '1'], true)) {
	header("location:../../");
	exit;
}
app_require_permission('system.manage', '../terms');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../terms");
	exit;
}

$academicYear = trim((string)($_POST['academic_year'] ?? ''));
$status = (string)($_POST['status'] ?? '0');
$termNames = app_term_names_from_input($_POST['term_names'] ?? []);
$setCurrentYear = isset($_POST['set_current_year']) && (string)$_POST['set_current_year'] === '1';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$result = app_create_academic_year_terms($conn, $academicYear, $termNames, $status, (int)$account_id, $setCurrentYear);

	$createdCount = count((array)($result['created'] ?? []));
	$existingCount = count((array)($result['existing'] ?? []));
	if ($createdCount < 1 && $existingCount > 0) {
		app_reply_redirect('danger', 'All selected terms already exist for that academic year.', '../terms');
	}

	$message = 'Academic year created with ' . $createdCount . ' linked term' . ($createdCount === 1 ? '' : 's') . '.';
	if ($existingCount > 0) {
		$message .= ' ' . $existingCount . ' existing term' . ($existingCount === 1 ? ' was' : 's were') . ' skipped.';
	}
	app_reply_redirect('success', $message, '../terms');
} catch (Throwable $e) {
	app_reply_redirect('danger', $e->getMessage() !== '' ? $e->getMessage() : 'Unable to create academic year right now.', '../terms');
}
