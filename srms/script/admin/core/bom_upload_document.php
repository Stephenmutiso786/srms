<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('bom.manage', '../bom');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../bom');
	exit;
}

$title = trim((string)($_POST['title'] ?? ''));
$documentType = trim((string)($_POST['document_type'] ?? 'policy'));
if ($title === '' || empty($_FILES['document']['name'])) {
	$_SESSION['reply'] = array(array('danger', 'Title and document file are required.'));
	header('location:../bom');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_bom_tables($conn);

	$uploadCheck = app_validate_upload($_FILES['document'], ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png']);
	if (!$uploadCheck['ok']) {
		$_SESSION['reply'] = array(array('danger', $uploadCheck['message']));
		header('location:../bom');
		exit;
	}

	$dir = 'uploads/bom_docs/';
	if (!is_dir($dir)) {
		@mkdir($dir, 0775, true);
	}
	$ext = strtolower(pathinfo((string)$_FILES['document']['name'], PATHINFO_EXTENSION));
	$fileName = 'bom_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
	$target = $dir . $fileName;

	if (!move_uploaded_file($_FILES['document']['tmp_name'], $target)) {
		throw new RuntimeException('Failed to upload document.');
	}

	$stmt = $conn->prepare('INSERT INTO tbl_bom_documents (title, document_type, file_path, uploaded_by) VALUES (?, ?, ?, ?)');
	$stmt->execute([$title, $documentType, $target, (int)$account_id]);

	$_SESSION['reply'] = array(array('success', 'BOM document uploaded.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to upload document.'));
}

header('location:../bom');
exit;
