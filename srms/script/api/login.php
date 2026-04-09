<?php
session_start();
require_once(__DIR__ . '/_common.php');
require_once(__DIR__ . '/../const/rand.php');

api_apply_cors();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	api_fail('Method not allowed.', 405);
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$username = trim((string)($input['username'] ?? $_POST['username'] ?? ''));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');

if ($username === '' || $password === '') {
	api_fail('Enter username and password.', 422);
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$hasParents = app_table_exists($conn, 'tbl_parents');
	$hasParentSessions = $hasParents && app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'parent');

	if ($isPgsql) {
		$sql = "SELECT id::text AS id, email, password, level, status FROM tbl_staff WHERE id::text = ? OR email = ?
UNION SELECT id::text AS id, email, password, level, status FROM tbl_students WHERE id::text = ? OR email = ?";
		$params = [$username, $username, $username, $username];
		if ($hasParents) {
			$sql .= "\nUNION SELECT id::text AS id, email, password, 4 AS level, status FROM tbl_parents WHERE id::text = ? OR email = ?";
			$params[] = $username;
			$params[] = $username;
		}
	} else {
		$sql = "SELECT id, email, password, level, status FROM tbl_staff WHERE id = ? OR email = ?
UNION SELECT id, email, password, level, status FROM tbl_students WHERE id = ? OR email = ?";
		$params = [$username, $username, $username, $username];
		if ($hasParents) {
			$sql .= "\nUNION SELECT id, email, password, 4 AS level, status FROM tbl_parents WHERE id = ? OR email = ?";
			$params[] = $username;
			$params[] = $username;
		}
	}

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	if (!$rows) {
		api_fail('Invalid login credentials.', 401);
	}

	foreach ($rows as $row) {
		$status = (string)($row[4] ?? '0');
		if ($status !== '1') {
			continue;
		}
		if (!password_verify($password, (string)$row[2])) {
			continue;
		}

		$accountId = (string)$row[0];
		$loginLevel = (int)$row[3];
		$sessionId = mb_strtoupper(GRS(20));
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';

		if ($loginLevel === 4) {
			if (!$hasParentSessions) {
				api_fail('Parent portal is not enabled on this server yet.', 409);
			}
			$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE parent = ?");
			$stmt->execute([(int)$accountId]);
			$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, parent, ip_address) VALUES (?,?,?)");
			$stmt->execute([$sessionId, (int)$accountId, $ip]);
			app_audit_log($conn, 'parent', $accountId, 'auth.login', 'session', $sessionId);
		} elseif ($loginLevel === 3) {
			$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE student = ?");
			$stmt->execute([$accountId]);
			$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, student, ip_address) VALUES (?,?,?)");
			$stmt->execute([$sessionId, $accountId, $ip]);
			app_audit_log($conn, 'student', $accountId, 'auth.login', 'session', $sessionId);
		} else {
			$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff = ?");
			$stmt->execute([$accountId]);
			$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, ip_address) VALUES (?,?,?)");
			$stmt->execute([$sessionId, $accountId, $ip]);
			app_audit_log($conn, 'staff', $accountId, 'auth.login', 'session', $sessionId);
		}

		app_issue_auth_cookies((string)$row[3], $sessionId, true, 4320);
		api_json([
			'ok' => true,
			'portal' => api_portal_name((string)$row[3]),
			'redirect' => '/' . api_portal_name((string)$row[3]),
		]);
	}

	api_fail('Invalid login credentials.', 401);
} catch (Throwable $e) {
	api_fail($e->getMessage(), 500);
}

