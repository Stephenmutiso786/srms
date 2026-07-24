<?php
declare(strict_types=1);

session_start();
require_once(__DIR__ . '/../db/config.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$deep = isset($_GET['deep']) && (string)$_GET['deep'] !== '0';
set_time_limit(5);
$logoFile = defined('WBLogo') && trim((string)WBLogo) !== '' ? trim((string)WBLogo) : 'school_logo.png';
$payload = [
	'ok' => true,
	'service_status' => 'operational',
	'school_name' => defined('WBName') && trim((string)WBName) !== '' ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'SRMS'),
	'logo_url' => 'images/logo/' . $logoFile,
	'favicon_url' => 'images/icon.ico',
	'academic_year' => date('Y'),
];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$payload['academic_year'] = trim(app_setting_get($conn, 'current_academic_year', date('Y')));
} catch (Throwable $e) {
	$payload['academic_year'] = date('Y');
}

if ($deep) {
	require_once(__DIR__ . '/../const/check_session.php');
	require_once(__DIR__ . '/../const/rbac.php');
	if (($res ?? '0') !== '1' || !app_current_user_has_permission('system.manage')) {
		http_response_code(403);
		echo json_encode([
			'ok' => false,
			'error' => 'forbidden',
			'message' => 'Admin diagnostics require an authenticated system administrator session.',
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		exit;
	}

	$started = microtime(true);
	$db = [
		'ok' => false,
		'latency_ms' => null,
		'error' => null,
	];

	try {
		$dbStarted = microtime(true);
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$conn->setAttribute(PDO::ATTR_TIMEOUT, 2);
		
		// Simple ping query with timeout
		$result = @$conn->query('SELECT 1', PDO::FETCH_NUM);
		if ($result) {
			$db['ok'] = true;
			$db['latency_ms'] = round((microtime(true) - $dbStarted) * 1000, 2);
		}
	} catch (Throwable $e) {
		$db['error'] = 'database_unreachable';
		$db['latency_ms'] = round((microtime(true) - $dbStarted) * 1000, 2);
		error_log('[api/health] deep check failed: ' . $e->getMessage());
	}

	$payload['checks'] = [
		'database' => $db,
	];

	if (!$db['ok']) {
		$payload['ok'] = false;
		$payload['service_status'] = 'degraded';
	}

	$payload['diagnostics'] = [
		'checked_at' => date('c'),
		'driver' => defined('DBDriver') ? DBDriver : 'unknown',
		'php' => PHP_VERSION,
		'latency_ms' => round((microtime(true) - $started) * 1000, 2),
	];
}

http_response_code($payload['ok'] ? 200 : 503);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
