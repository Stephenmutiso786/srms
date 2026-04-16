<?php
// Session checker used across all role dashboards.
// Populates: $res, $level, $account_id (+ user fields).

$res = "0";

if (!isset($_COOKIE["__SRMS__logged"]) || !isset($_COOKIE["__SRMS__key"])) {
	return;
}

$session_key = (string)$_COOKIE["__SRMS__key"];
$current_ip = $_SERVER['REMOTE_ADDR'] ?? '';
$level = (string)$_COOKIE["__SRMS__logged"];
$levelInt = (int)$level;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	require_once('const/online_presence.php');

	$impersonationRow = null;
	try {
		app_ensure_impersonation_schema($conn);
		$impersonationRow = app_impersonation_session_by_impersonated_key($conn, $session_key);
	} catch (Throwable $e) {
		$impersonationRow = null;
	}

	// Staff roles: admin(0), academic(1), teacher(2), accountant(5), etc.
	if ($levelInt !== 3 && $levelInt !== 4) {
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, s.*
			FROM tbl_login_sessions ls
			JOIN tbl_staff s ON s.id = ls.staff
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			return;
		}

		if (app_session_enforce_ip() && ($row['ip_address'] ?? '') !== $current_ip) {
			$res = "3";
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$lname = (string)$row['lname'];
		$gender = (string)$row['gender'];
		$email = (string)$row['email'];
		$login = (string)$row['password'];
		$level = (string)$row['level'];
		$designation = app_staff_primary_title($conn, (int)$row['id'], $level);
		if ($level === "9") {
			$super_admin = true;
			$level = "0";
		}

		if ($impersonationRow) {
			$adminId = (string)($impersonationRow['admin_staff_id'] ?? '');
			$adminName = trim((string)($impersonationRow['admin_fname'] ?? '') . ' ' . (string)($impersonationRow['admin_lname'] ?? ''));
			$_SESSION['impersonation'] = [
				'active' => true,
				'admin_id' => $adminId,
				'admin_name' => $adminName,
				'target_type' => 'staff',
				'target_id' => (string)$account_id,
				'target_level' => (string)$level,
				'target_name' => trim($fname . ' ' . $lname),
				'session_id' => (string)($impersonationRow['id'] ?? ''),
				'started_at' => (string)($impersonationRow['started_at'] ?? ''),
			];
			app_set_impersonation_banner_cookie([
				'active' => true,
				'target_name' => trim($fname . ' ' . $lname),
				'target_role' => $designation,
				'exit_path' => 'admin/core/stop_impersonation',
			]);
			if (app_impersonation_blocks_current_request()) {
				app_audit_log($conn, 'staff', $adminId, 'impersonation.blocked_action', 'request', (string)$session_key, [
					'path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
				]);
				http_response_code(403);
				echo 'Action not allowed during impersonation.';
				exit;
			}
		} else {
			if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['impersonation'])) {
				unset($_SESSION['impersonation']);
			}
			app_clear_impersonation_banner_cookie();
		}

		app_online_touch($conn, $session_key);
		$res = "1";
		return;
	}

	if ($levelInt === 3) {
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, st.*
			FROM tbl_login_sessions ls
			JOIN tbl_students st ON st.id = ls.student
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			return;
		}

		if (app_session_enforce_ip() && ($row['ip_address'] ?? '') !== $current_ip) {
			$res = "3";
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$mname = (string)$row['mname'];
		$lname = (string)$row['lname'];
		$gender = (string)$row['gender'];
		$email = (string)$row['email'];
		$class = (string)$row['class'];
		$login = (string)$row['password'];
		$level = (string)$row['level'];
		$img = (string)$row['display_image'];
		$designation = app_level_title_label((int)$level);

		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$class]);
		$act_class = (string)($stmt->fetchColumn() ?: '');

		if ($impersonationRow) {
			$adminId = (string)($impersonationRow['admin_staff_id'] ?? '');
			$adminName = trim((string)($impersonationRow['admin_fname'] ?? '') . ' ' . (string)($impersonationRow['admin_lname'] ?? ''));
			$_SESSION['impersonation'] = [
				'active' => true,
				'admin_id' => $adminId,
				'admin_name' => $adminName,
				'target_type' => 'student',
				'target_id' => (string)$account_id,
				'target_level' => (string)$level,
				'target_name' => trim($fname . ' ' . $lname),
				'session_id' => (string)($impersonationRow['id'] ?? ''),
				'started_at' => (string)($impersonationRow['started_at'] ?? ''),
			];
			app_set_impersonation_banner_cookie([
				'active' => true,
				'target_name' => trim($fname . ' ' . $lname),
				'target_role' => 'Student',
				'exit_path' => 'admin/core/stop_impersonation',
			]);
			if (app_impersonation_blocks_current_request()) {
				app_audit_log($conn, 'staff', $adminId, 'impersonation.blocked_action', 'request', (string)$session_key, [
					'path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
				]);
				http_response_code(403);
				echo 'Action not allowed during impersonation.';
				exit;
			}
		} else {
			if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['impersonation'])) {
				unset($_SESSION['impersonation']);
			}
			app_clear_impersonation_banner_cookie();
		}

		app_online_touch($conn, $session_key);
		$res = "1";
		return;
	}

	// Parent portal (level=4). Requires migration adding tbl_parents and tbl_login_sessions.parent
	if ($levelInt === 4 && app_table_exists($conn, 'tbl_parents') && app_column_exists($conn, 'tbl_login_sessions', 'parent')) {
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, p.*
			FROM tbl_login_sessions ls
			JOIN tbl_parents p ON p.id = ls.parent
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			return;
		}

		if (app_session_enforce_ip() && ($row['ip_address'] ?? '') !== $current_ip) {
			$res = "3";
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$lname = (string)$row['lname'];
		$phone = (string)($row['phone'] ?? '');
		$email = (string)$row['email'];
		$login = (string)$row['password'];
		$level = "4";
		$designation = 'Parent';

		if ($impersonationRow) {
			$adminId = (string)($impersonationRow['admin_staff_id'] ?? '');
			$adminName = trim((string)($impersonationRow['admin_fname'] ?? '') . ' ' . (string)($impersonationRow['admin_lname'] ?? ''));
			$_SESSION['impersonation'] = [
				'active' => true,
				'admin_id' => $adminId,
				'admin_name' => $adminName,
				'target_type' => 'parent',
				'target_id' => (string)$account_id,
				'target_level' => (string)$level,
				'target_name' => trim($fname . ' ' . $lname),
				'session_id' => (string)($impersonationRow['id'] ?? ''),
				'started_at' => (string)($impersonationRow['started_at'] ?? ''),
			];
			app_set_impersonation_banner_cookie([
				'active' => true,
				'target_name' => trim($fname . ' ' . $lname),
				'target_role' => 'Parent',
				'exit_path' => 'admin/core/stop_impersonation',
			]);
			if (app_impersonation_blocks_current_request()) {
				app_audit_log($conn, 'staff', $adminId, 'impersonation.blocked_action', 'request', (string)$session_key, [
					'path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
				]);
				http_response_code(403);
				echo 'Action not allowed during impersonation.';
				exit;
			}
		} else {
			if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['impersonation'])) {
				unset($_SESSION['impersonation']);
			}
			app_clear_impersonation_banner_cookie();
		}

		app_online_touch($conn, $session_key);
		$res = "1";
		return;
	}
} catch (PDOException $e) {
	// Keep $res=0 (treat as not logged in).
}
