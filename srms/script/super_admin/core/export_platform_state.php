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

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_school_subscription_schema($conn);
$payload = [
	'exported_at' => date('c'),
	'schools' => [],
	'owners' => [],
];
if (app_table_exists($conn, 'tbl_school')) {
	$stmt = $conn->query('SELECT id, name, logo, result_system, allow_results FROM tbl_school ORDER BY id ASC');
	$payload['schools'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
if (app_table_exists($conn, 'tbl_staff')) {
	$stmt = $conn->query("SELECT id, fname, lname, gender, email, password, level, status FROM tbl_staff WHERE level = 9 ORDER BY id ASC");
	$payload['owners'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="platform-state-backup.json"');
echo json_encode($payload, JSON_PRETTY_PRINT);
