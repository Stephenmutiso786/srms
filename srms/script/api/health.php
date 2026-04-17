<?php
session_start();
require_once(__DIR__ . '/_common.php');

api_apply_cors();

$deep = isset($_GET['deep']) && (string)$_GET['deep'] !== '0';
$started = microtime(true);

$payload = [
	'ok' => true,
	'service' => 'backend',
	'app' => APP_NAME,
	'driver' => DBDriver,
	'time' => date('c'),
	'php' => PHP_VERSION,
	'mode' => $deep ? 'deep' : 'basic',
];

if ($deep) {
	$db = [
		'ok' => false,
		'latency_ms' => null,
		'error' => null,
	];
	try {
		$dbStarted = microtime(true);
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$conn->query('SELECT 1');
		$db['ok'] = true;
		$db['latency_ms'] = round((microtime(true) - $dbStarted) * 1000, 2);
	} catch (Throwable $e) {
		$db['error'] = 'database_unreachable';
		error_log('[api/health] deep check failed: ' . $e->getMessage());
	}

	$payload['checks'] = [
		'database' => $db,
	];

	if (!$db['ok']) {
		$payload['ok'] = false;
	}
}

$payload['latency_ms'] = round((microtime(true) - $started) * 1000, 2);

api_json($payload, $payload['ok'] ? 200 : 503);
