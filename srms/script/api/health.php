<?php
declare(strict_types=1);

require_once(__DIR__ . '/../db/config.php');

header('Content-Type: application/json; charset=utf-8');

$deep = isset($_GET['deep']) && (string)$_GET['deep'] !== '0';
$started = microtime(true);

$payload = [
	'ok' => true,
	'service' => 'backend',
	'app' => defined('APP_NAME') ? APP_NAME : 'Elimu Hub',
	'driver' => defined('DBDriver') ? DBDriver : 'unknown',
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

http_response_code($payload['ok'] ? 200 : 503);
echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
exit;
