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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../terms");
	exit;
}

$id = (int)($_GET['id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("DELETE FROM tbl_terms WHERE id = ?");
	$stmt->execute([$id]);
	app_reply_redirect('success', 'Academic term deleted successfully.', '../terms');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Unable to delete term. It is likely referenced by exams, results, or attendance.', '../terms');
}
