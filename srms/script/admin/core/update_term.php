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

$name = ucfirst(trim((string)($_POST['name'] ?? '')));
$academicYear = trim((string)($_POST['academic_year'] ?? ''));
$status = (string)($_POST['status'] ?? '0');
$id = (int)($_POST['id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_terms_academic_year_schema($conn);
	$termName = app_term_base_name($name);
	if ($termName === '') {
		$termName = trim($name);
	}
	if ($termName === '' || $academicYear === '') {
		app_reply_redirect('danger', 'Term name and academic year are required.', '../terms');
	}
	$storedName = app_term_compose_name($termName, $academicYear);
	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_terms WHERE name = ? AND id != ?");
	$stmt->execute([$storedName, $id]);
	if ((int)$stmt->fetchColumn() > 0) {
		app_reply_redirect('danger', 'Academic term is already registered.', '../terms');
	}

	$stmt = $conn->prepare("UPDATE tbl_terms SET name = ?, academic_year = ?, status = ? WHERE id = ?");
	$stmt->execute([$storedName, $academicYear, $status, $id]);
	app_reply_redirect('success', 'Academic term updated successfully.', '../terms');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Unable to update academic term right now.', '../terms');
}
