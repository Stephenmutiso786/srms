<?php
chdir('../../../script');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rand.php');

$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../../');
	exit;
}

$schoolId = (int)($_POST['school_id'] ?? 0);
if ($schoolId < 1) {
	$_SESSION['reply'] = [['danger', 'Select a school first.']];
	header('location:../index.php');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_subscription_schema($conn);
	app_ensure_impersonation_schema($conn);

	$stmt = $conn->prepare('SELECT id, name FROM tbl_school WHERE id = ? LIMIT 1');
	$stmt->execute([$schoolId]);
	$school = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$school) {
		throw new RuntimeException('School not found.');
	}

	if (app_table_exists($conn, 'tbl_staff') && app_column_exists($conn, 'tbl_staff', 'school_id')) {
		$stmt = $conn->prepare("SELECT id, fname, lname, level, status FROM tbl_staff WHERE school_id = ? AND level = 0 ORDER BY id ASC LIMIT 1");
		$stmt->execute([$schoolId]);
		$admin = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$admin) {
			$seed = app_seed_school_workspace($conn, $schoolId, (string)$school['name']);
			$stmt = $conn->prepare("SELECT id, fname, lname, level, status FROM tbl_staff WHERE school_id = ? AND level = 0 ORDER BY id ASC LIMIT 1");
			$stmt->execute([$schoolId]);
			$admin = $stmt->fetch(PDO::FETCH_ASSOC);
		}
	} else {
		throw new RuntimeException('School admin accounts are unavailable.');
	}

	if (!$admin) {
		throw new RuntimeException('School administrator account not found.');
	}
	if ((string)($admin['status'] ?? '0') !== '1') {
		throw new RuntimeException('School administrator account is blocked.');
	}

	$adminId = (int)$admin['id'];
	$adminLevel = (string)($admin['level'] ?? '0');
	$sessionKey = function_exists('mb_strtoupper') ? mb_strtoupper(GRS(20)) : strtoupper(GRS(20));
	$ip = app_request_client_ip();

	$stmt = $conn->prepare('INSERT INTO tbl_login_sessions (session_key, staff, ip_address) VALUES (?,?,?)');
	$stmt->execute([$sessionKey, $adminId, $ip]);

	app_issue_auth_cookies($adminLevel, $sessionKey, false, 4320);
	$_SESSION['school_id'] = $schoolId;
	$_SESSION['reply'] = [['success', 'Opened school administrator portal successfully.']];
	header('location:../../admin');
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = [['danger', 'Unable to open school admin portal: ' . $e->getMessage()]];
	header('location:../index.php');
	exit;
}
