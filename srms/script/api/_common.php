<?php

require_once(__DIR__ . '/../db/config.php');
require_once(__DIR__ . '/../const/school.php');
require_once(__DIR__ . '/../const/check_session.php');
require_once(__DIR__ . '/../const/report_engine.php');
require_once(__DIR__ . '/../const/id_card_engine.php');

function api_allowed_origins(): array
{
	$list = [];
	$currentHost = api_backend_base_url();
	if ($currentHost !== '') {
		$list[] = rtrim($currentHost, '/');
	}
	$defaults = [
		'http://localhost:3000',
		'http://127.0.0.1:3000',
	];
	foreach ($defaults as $origin) {
		if (!in_array($origin, $list, true)) {
			$list[] = $origin;
		}
	}
	return $list;
}

function api_apply_cors(): void
{
	$origin = rtrim((string)($_SERVER['HTTP_ORIGIN'] ?? ''), '/');
	if ($origin !== '' && in_array($origin, api_allowed_origins(), true)) {
		header('Access-Control-Allow-Origin: ' . $origin);
		header('Vary: Origin');
		header('Access-Control-Allow-Credentials: true');
		header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
		header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
	}
	if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
		http_response_code(204);
		exit;
	}
}

function api_json($payload, int $status = 200): void
{
	http_response_code($status);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}

function api_backend_base_url(): string
{
	if (APP_URL !== '') {
		return APP_URL;
	}
	$scheme = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? 'http'));
	$host = (string)($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
	if ($host === '') {
		return '';
	}
	return rtrim($scheme . '://' . $host, '/');
}

function api_backend_url(string $path = ''): string
{
	$base = api_backend_base_url();
	$cleanPath = '/' . ltrim($path, '/');
	return $base !== '' ? $base . $cleanPath : $cleanPath;
}

function api_fail(string $message, int $status = 400, array $extra = []): void
{
	api_json(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

function api_portal_name(string $level): string
{
	return match ($level) {
		'0', '9' => 'admin',
		'1' => 'academic',
		'2' => 'teacher',
		'3' => 'student',
		'4' => 'parent',
		'5' => 'accountant',
		default => 'guest',
	};
}

function api_session_user(): array
{
	global $res, $level, $account_id, $fname, $lname, $email, $act_class, $class;
	if (($res ?? '0') !== '1') {
		api_fail('Authentication required.', 401);
	}
	return [
		'id' => (string)$account_id,
		'level' => (string)$level,
		'portal' => api_portal_name((string)$level),
		'name' => trim((string)($fname ?? '') . ' ' . (string)($lname ?? '')),
		'email' => (string)($email ?? ''),
		'class_id' => isset($class) ? (int)$class : 0,
		'class_name' => (string)($act_class ?? ''),
	];
}

function api_require_portal(string $portal): array
{
	$user = api_session_user();
	if ($user['portal'] !== $portal) {
		api_fail('Forbidden for this portal.', 403, ['portal' => $user['portal']]);
	}
	return $user;
}

function api_pick_term_name(array $terms, int $termId): string
{
	foreach ($terms as $term) {
		if ((int)($term['id'] ?? 0) === $termId) {
			return (string)($term['name'] ?? '');
		}
	}
	return '';
}
