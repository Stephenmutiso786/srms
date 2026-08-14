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
if (function_exists('app_ensure_school_subscription_schema')) {
	app_ensure_school_subscription_schema($conn);
}
$payload = [
	'exported_at' => date('c'),
	'schools' => [],
];
if (app_table_exists($conn, 'tbl_school')) {
	$stmt = $conn->query('SELECT id, name, logo, result_system, allow_results FROM tbl_school ORDER BY id ASC');
	$payload['schools'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="schools-export.json"');
echo json_encode($payload, JSON_PRETTY_PRINT);
