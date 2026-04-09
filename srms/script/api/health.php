<?php
session_start();
require_once(__DIR__ . '/_common.php');

api_apply_cors();

api_json([
	'ok' => true,
	'service' => 'backend',
	'app' => APP_NAME,
	'driver' => DBDriver,
	'time' => date('c'),
]);
