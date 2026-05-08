<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

app_require_authentication([], ['students.manage']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../parents');
	exit;
}

$parentId = (int)($_POST['parent_id'] ?? 0);
$fname = ucfirst(trim((string)($_POST['fname'] ?? '')));
$lname = ucfirst(trim((string)($_POST['lname'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$status = (int)($_POST['status'] ?? 1);

if ($parentId < 1 || $fname === '' || $lname === '' || $email === '') {
	$_SESSION['reply'] = array(array('danger', 'Parent id, name, and email are required.'));
	header('location:../parents');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parents')) {
		throw new RuntimeException('Parent tables are not installed.');
	}

	$isPgsql = defined('DBDriver') && DBDriver === 'pgsql';
	$stmt = $isPgsql
		? $conn->prepare('SELECT id FROM tbl_parents WHERE lower(email) = lower(?) AND id <> ? LIMIT 1')
		: $conn->prepare('SELECT id FROM tbl_parents WHERE lower(email) = lower(?) AND id != ? LIMIT 1');
	$stmt->execute([$email, $parentId]);
	if ($stmt->fetchColumn()) {
		$_SESSION['reply'] = array(array('danger', 'Another parent already uses that email address.'));
		header('location:../parents');
		exit;
	}

	$stmt = $conn->prepare('UPDATE tbl_parents SET fname = ?, lname = ?, phone = ?, email = ?, status = ? WHERE id = ?');
	$stmt->execute([$fname, $lname, $phone, $email, $status === 1 ? 1 : 0, $parentId]);

	app_audit_log($conn, 'staff', (string)$account_id, 'parent.update', 'parent', (string)$parentId);
	$_SESSION['reply'] = array(array('success', 'Parent details updated successfully.'));
} catch (Throwable $e) {
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage() !== '' ? $e->getMessage() : 'Failed to update parent.'));
}

header('location:../parents');
exit;
