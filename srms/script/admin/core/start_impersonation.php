<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/rand.php');

if ($res !== "1" || !in_array((int)$level, [0, 1], true)) {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$targetType = strtolower(trim((string)($_POST['target_type'] ?? '')));
$targetIdRaw = trim((string)($_POST['target_id'] ?? ''));
$targetId = preg_replace('/[^a-zA-Z0-9_-]/', '', $targetIdRaw);
$currentSessionKey = (string)($_COOKIE['__SRMS__key'] ?? '');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_impersonation_schema($conn);

	if (!app_has_permission($conn, (string)$account_id, (string)$level, 'staff.manage')) {
		throw new RuntimeException('You do not have permission to impersonate users.');
	}

	if ($targetId === '' || !in_array($targetType, ['staff', 'student', 'parent'], true)) {
		throw new RuntimeException('Invalid impersonation target.');
	}
	if ($currentSessionKey === '') {
		throw new RuntimeException('Admin session is missing. Please login again.');
	}

	$stmt = $conn->prepare("SELECT session_key FROM tbl_login_sessions WHERE session_key = ? LIMIT 1");
	$stmt->execute([$currentSessionKey]);
	if (!$stmt->fetchColumn()) {
		throw new RuntimeException('Admin session is invalid. Please login again.');
	}

	$targetLevel = '';
	$targetName = '';
	$targetStatus = '0';
	if ($targetType === 'staff') {
		$stmt = $conn->prepare("SELECT id, fname, lname, level, status FROM tbl_staff WHERE id = ? LIMIT 1");
		$stmt->execute([(int)$targetId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new RuntimeException('Staff account not found.');
		}
		$targetLevel = (string)($row['level'] ?? '');
		$targetStatus = (string)($row['status'] ?? '0');
		$targetName = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
	} elseif ($targetType === 'student') {
		$stmt = $conn->prepare("SELECT id, fname, lname, level, status FROM tbl_students WHERE id = ? LIMIT 1");
		$stmt->execute([$targetId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new RuntimeException('Student account not found.');
		}
		$targetLevel = (string)($row['level'] ?? '3');
		$targetStatus = (string)($row['status'] ?? '0');
		$targetName = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
	} else {
		if (!app_table_exists($conn, 'tbl_parents')) {
			throw new RuntimeException('Parent module is not installed.');
		}
		$stmt = $conn->prepare("SELECT id, fname, lname, status FROM tbl_parents WHERE id = ? LIMIT 1");
		$stmt->execute([(int)$targetId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			throw new RuntimeException('Parent account not found.');
		}
		$targetLevel = '4';
		$targetStatus = (string)($row['status'] ?? '0');
		$targetName = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
	}

	if ($targetStatus !== '1') {
		throw new RuntimeException('Target account is blocked.');
	}

	if ($targetType === 'staff' && (int)$targetId === (int)$account_id) {
		throw new RuntimeException('You are already logged in as this user.');
	}

	$actorLevel = (int)$level;
	if ($actorLevel === 1) {
		if ($targetType === 'parent') {
			throw new RuntimeException('Headteacher can impersonate teachers and students only.');
		}
		if ($targetType === 'staff' && (int)$targetLevel !== 2) {
			throw new RuntimeException('Headteacher can only impersonate teacher accounts.');
		}
	}

	if (app_table_exists($conn, 'tbl_impersonation_sessions')) {
		$stmt = $conn->prepare("UPDATE tbl_impersonation_sessions SET is_active = 0, ended_at = CURRENT_TIMESTAMP, stopped_by = ?, reason = 'superseded' WHERE admin_session_key = ? AND COALESCE(is_active,1) = 1 AND ended_at IS NULL");
		$stmt->execute([(int)$account_id, $currentSessionKey]);
	}

	$impersonatedSessionKey = mb_strtoupper(GRS(20));
	$ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');

	if ($targetType === 'staff') {
		$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, staff, ip_address) VALUES (?,?,?)");
		$stmt->execute([$impersonatedSessionKey, (int)$targetId, $ip]);
	} elseif ($targetType === 'student') {
		$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, student, ip_address) VALUES (?,?,?)");
		$stmt->execute([$impersonatedSessionKey, $targetId, $ip]);
	} else {
		if (!app_column_exists($conn, 'tbl_login_sessions', 'parent')) {
			throw new RuntimeException('Parent sessions are not enabled. Run parent session migration first.');
		}
		$stmt = $conn->prepare("INSERT INTO tbl_login_sessions (session_key, parent, ip_address) VALUES (?,?,?)");
		$stmt->execute([$impersonatedSessionKey, (int)$targetId, $ip]);
	}

	$stmt = $conn->prepare("INSERT INTO tbl_impersonation_sessions (admin_staff_id, admin_level, admin_session_key, impersonated_session_key, target_type, target_id, target_level, is_active) VALUES (?,?,?,?,?,?,?,1)");
	$stmt->execute([(int)$account_id, (string)$level, $currentSessionKey, $impersonatedSessionKey, $targetType, (string)$targetId, (string)$targetLevel]);

	$targetRole = 'User';
	if ($targetType === 'staff') {
		$targetRole = app_level_title_label((int)$targetLevel);
	} elseif ($targetType === 'student') {
		$targetRole = 'Student';
	} elseif ($targetType === 'parent') {
		$targetRole = 'Parent';
	}

	app_set_impersonation_banner_cookie([
		'active' => true,
		'target_name' => $targetName,
		'target_role' => $targetRole,
		'exit_path' => 'admin/core/stop_impersonation',
	]);
	app_issue_auth_cookies((string)$targetLevel, $impersonatedSessionKey, false, 4320);

	app_audit_log($conn, 'staff', (string)$account_id, 'auth.impersonation.start', $targetType, (string)$targetId, [
		'target_level' => (string)$targetLevel,
		'target_name' => $targetName,
	]);

	$portal = '';
	if ((int)$targetLevel === 3) {
		$portal = 'student';
	} elseif ((int)$targetLevel === 4) {
		$portal = 'parent';
	} else {
		$portal = app_staff_login_portal($conn, (int)$targetId, (string)$targetLevel);
	}

	header("location:../../" . $portal);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Impersonation failed: ' . $e->getMessage()));
	header("location:../");
	exit;
}
