<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('bom.manage', '../bom');

$entity = trim((string)($_GET['entity'] ?? ''));
$id = (int)($_GET['id'] ?? 0);
if ($id < 1 || $entity === '') {
	$_SESSION['reply'] = array(array('danger', 'Invalid delete request.'));
	header('location:../bom');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_bom_tables($conn);

	$map = [
		'member' => 'tbl_bom_members',
		'meeting' => 'tbl_bom_meetings',
		'approval' => 'tbl_bom_financial_approvals',
		'document' => 'tbl_bom_documents',
	];
	if (!isset($map[$entity])) {
		throw new RuntimeException('Unknown delete entity.');
	}

	if ($entity === 'document') {
		$stmt = $conn->prepare('SELECT file_path FROM tbl_bom_documents WHERE id = ? LIMIT 1');
		$stmt->execute([$id]);
		$file = (string)$stmt->fetchColumn();
		if ($file !== '' && is_file($file)) {
			@unlink($file);
		}
	}

	$stmt = $conn->prepare('DELETE FROM '.$map[$entity].' WHERE id = ?');
	$stmt->execute([$id]);
	$_SESSION['reply'] = array(array('success', 'Record deleted.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to delete record.'));
}

header('location:../bom');
exit;
