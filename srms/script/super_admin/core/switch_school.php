<?php
chdir('../../../script');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../../');
	exit;
}

$schoolId = (int)($_POST['school_id'] ?? 0);
$redirectTo = trim((string)($_POST['redirect_to'] ?? ''));
if ($schoolId < 1) {
	$_SESSION['reply'] = [['danger', 'Select a school first.']];
	header('location:../index.php');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_school_subscription_schema($conn);
if (app_table_exists($conn, 'tbl_school')) {
	$stmt = $conn->prepare('SELECT id FROM tbl_school WHERE id = ? LIMIT 1');
	$stmt->execute([$schoolId]);
	if (!$stmt->fetchColumn()) {
		$_SESSION['reply'] = [['danger', 'School not found.']];
		header('location:../index.php');
		exit;
	}
}

app_set_current_school_id($schoolId);
$_SESSION['reply'] = [['success', 'School switched successfully.']];
$redirectTo = preg_replace('/[^a-zA-Z0-9_\/.-]/', '', $redirectTo);
if ($redirectTo !== '') {
	header('location:../../' . ltrim($redirectTo, '/'));
	exit;
}
header('location:../index.php');
