<?php
// Session checker used across all role dashboards.
// Populates: $res, $level, $account_id (+ user fields).

$res = "0";
require_once(__DIR__ . '/rbac.php');

if (!function_exists('app_set_auth_error')) {
	function app_set_auth_error(int $status, string $title, string $message, array $details = []): void
	{
		$GLOBALS['app_auth_error'] = [
			'status' => $status,
			'title' => trim($title) !== '' ? trim($title) : 'Access error',
			'message' => trim($message) !== '' ? trim($message) : 'An access error occurred.',
			'details' => $details,
		];
	}
}

if (!function_exists('app_get_auth_error')) {
	function app_get_auth_error(): array
	{
		$error = $GLOBALS['app_auth_error'] ?? null;
		if (is_array($error) && isset($error['status'], $error['title'], $error['message'])) {
			return $error;
		}

		return [
			'status' => 401,
			'title' => 'Login required',
			'message' => 'No active session was found for this request.',
			'details' => [],
		];
	}
}

app_set_auth_error(401, 'Login required', 'No active session was found for this request.', [
	'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
]);

/**
 * Handle impersonation session setup for the current request.
 * This function consolidates the duplicate impersonation logic used for staff, students, and parents.
 *
 * @param PDO $conn Database connection
 * @param array|null $impersonationRow The impersonation session row from database
 * @param string $targetType The type of target (staff, student, parent)
 * @param string $targetId The ID of the target user
 * @param string $targetLevel The level/role of the target user
 * @param string $targetName The display name of the target user
 * @param string $targetRole The role title of the target user
 * @param string $exitPath The path to stop impersonation
 * @return void
 */
function app_handle_impersonation_setup(PDO $conn, ?array $impersonationRow, string $targetType, string $targetId, string $targetLevel, string $targetName, string $targetRole, string $exitPath): void
{
	if (!$impersonationRow) {
		if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['impersonation'])) {
			unset($_SESSION['impersonation']);
		}
		app_clear_impersonation_banner_cookie();
		return;
	}

	$adminId = (string)($impersonationRow['admin_staff_id'] ?? '');
	$adminName = trim((string)($impersonationRow['admin_fname'] ?? '') . ' ' . (string)($impersonationRow['admin_lname'] ?? ''));
	$_SESSION['impersonation'] = [
		'active' => true,
		'admin_id' => $adminId,
		'admin_name' => $adminName,
		'target_type' => $targetType,
		'target_id' => $targetId,
		'target_level' => $targetLevel,
		'target_name' => $targetName,
		'session_id' => (string)($impersonationRow['id'] ?? ''),
		'started_at' => (string)($impersonationRow['started_at'] ?? ''),
	];
	app_set_impersonation_banner_cookie([
		'active' => true,
		'target_name' => $targetName,
		'target_role' => $targetRole,
		'exit_path' => $exitPath,
	]);

	if (app_impersonation_blocks_current_request()) {
		app_audit_log($conn, 'staff', $adminId, 'impersonation.blocked_action', 'request', '', [
			'path' => (string)($_SERVER['REQUEST_URI'] ?? ''),
			'method' => (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'),
		]);
		http_response_code(403);
		echo 'Action not allowed during impersonation.';
		exit;
	}
}

function app_requested_staff_module(): string
{
	$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	$path = is_string($path) ? trim($path, '/') : '';
	if ($path === '') {
		return '';
	}
	$parts = explode('/', $path);
	foreach ($parts as $segment) {
		$segment = strtolower(trim((string)$segment));
		if (in_array($segment, ['admin', 'academic', 'teacher', 'accountant', 'bom'], true)) {
			return $segment;
		}
	}
	return '';
}

function app_teacher_password_change_gate_path(): string
{
	$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	return is_string($path) ? strtolower(trim($path, '/')) : '';
}

function app_teacher_password_change_gate_allows_current_request(): bool
{
	$path = app_teacher_password_change_gate_path();
	if ($path === '') {
		return false;
	}
	return str_ends_with($path, 'teacher/force_password_change')
		|| str_ends_with($path, 'teacher/force_password_change.php')
		|| str_ends_with($path, 'teacher/core/update_password')
		|| str_ends_with($path, 'teacher/core/update_password.php')
		|| str_ends_with($path, 'logout')
		|| str_ends_with($path, 'logout.php');
}

function app_module_level_bridge_rules(): array
{
	return [
		'teacher' => [
			'expected_level' => '2',
			'permissions' => ['attendance.manage', 'marks.enter', 'report.view', 'report.generate', 'exams.manage', 'student.leadership.manage', 'communication.manage', 'communication.send', 'results.approve'],
		],
		'academic' => [
			'expected_level' => '1',
			'permissions' => ['academic.manage', 'teacher.allocate', 'classes.assign', 'timetable.manage', 'exams.manage', 'marks.review', 'results.approve', 'report.generate'],
		],
		'accountant' => [
			'expected_level' => '5',
			'permissions' => ['finance.manage', 'finance.view', 'sms.wallet.manage'],
		],
		'bom' => [
			'expected_level' => '10',
			'permissions' => ['bom.view', 'bom.manage'],
		],
	];
}

function app_apply_module_level_bridge(PDO $conn, string $staffId, string $currentLevel): string
{
	static $cache = [];
	$module = app_requested_staff_module();
	if ($module === '' || $staffId === '' || $currentLevel === '') {
		return $currentLevel;
	}

	$cacheKey = $module . '|' . $staffId . '|' . $currentLevel;
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$rules = app_module_level_bridge_rules();
	if (!isset($rules[$module])) {
		$cache[$cacheKey] = $currentLevel;
		return $cache[$cacheKey];
	}

	$expected = (string)$rules[$module]['expected_level'];
	if ($module === 'teacher' && app_staff_has_active_teaching_assignment($conn, (int)$staffId)) {
		$cache[$cacheKey] = $expected;
		return $cache[$cacheKey];
	}
	if ($currentLevel === $expected || $currentLevel === '0' || $currentLevel === '9') {
		$cache[$cacheKey] = $currentLevel;
		return $cache[$cacheKey];
	}

	if (!function_exists('app_get_permissions')) {
		require_once(__DIR__ . '/rbac.php');
	}

	$permissionSet = array_fill_keys(app_get_permissions($conn, $staffId, $currentLevel), true);
	foreach ((array)$rules[$module]['permissions'] as $permissionCode) {
		if (!empty($permissionSet[(string)$permissionCode])) {
			$cache[$cacheKey] = $expected;
			return $cache[$cacheKey];
		}
	}

	$cache[$cacheKey] = $currentLevel;
	return $cache[$cacheKey];
}

if (!isset($_COOKIE["__SRMS__logged"]) || !isset($_COOKIE["__SRMS__key"])) {
	app_set_auth_error(401, 'Login required', 'The session cookies are missing, so this request is not authenticated.', [
		'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
		'cookie_logged_present' => isset($_COOKIE["__SRMS__logged"]) ? 'yes' : 'no',
		'cookie_key_present' => isset($_COOKIE["__SRMS__key"]) ? 'yes' : 'no',
	]);
	return;
}

$session_key = (string)$_COOKIE["__SRMS__key"];
$current_ip = app_request_client_ip();
$level = (string)$_COOKIE["__SRMS__logged"];
$levelInt = (int)$level;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	require_once(__DIR__ . '/online_presence.php');

	$impersonationRow = null;
	try {
		app_ensure_impersonation_schema($conn);
		$impersonationRow = app_impersonation_session_by_impersonated_key($conn, $session_key);
	} catch (Throwable $e) {
		$impersonationRow = null;
	}

	// Staff roles: admin(0), academic(1), teacher(2), accountant(5), etc.
	if ($levelInt !== 3 && $levelInt !== 4) {
		app_ensure_staff_password_policy_columns($conn);
		$stmt = $conn->prepare("SELECT ls.session_key, ls.ip_address, s.*
			FROM tbl_login_sessions ls
			JOIN tbl_staff s ON s.id = ls.staff
			WHERE ls.session_key = ?
			LIMIT 1");
		$stmt->execute([$session_key]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			$res = "0";
			app_set_auth_error(401, 'Session expired', 'The staff session key was not found in tbl_login_sessions. The session may have expired or been deleted.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'session_key' => $session_key,
				'account_type' => 'staff',
			]);
			return;
		}

		if (app_session_enforce_ip() && !app_session_ip_matches((string)($row['ip_address'] ?? ''), (string)$current_ip)) {
			$res = "3";
			app_set_auth_error(401, 'Session IP mismatch', 'This session is bound to a different IP address than the current request.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'stored_ip' => (string)($row['ip_address'] ?? ''),
				'current_ip' => (string)$current_ip,
				'account_type' => 'staff',
			]);
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1" && !$isSuperAdminController) {
			$res = "2";
			app_set_auth_error(403, 'Account disabled', 'The staff account tied to this session is inactive.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'account_id' => (string)($row['id'] ?? ''),
				'account_type' => 'staff',
				'status' => $status,
			]);
			return;
		}

		$account_id = (string)$row['id'];
		$fname = (string)$row['fname'];
		$lname = (string)$row['lname'];
		$gender = (string)$row['gender'];
		$email = (string)$row['email'];
		$login = (string)$row['password'];
		$level = (string)$row['level'];
		$isSuperAdminController = (strtolower(trim($email)) === strtolower(app_super_admin_owner_email())) || (int)$level === 9;
		$level = app_apply_module_level_bridge($conn, (string)$row['id'], $level);
		$designation = app_staff_primary_title($conn, (int)$row['id'], $level);
		if ($isSuperAdminController || $level === "9") {
			$super_admin = true;
			$level = "0";
		}

		try {
			if (function_exists('app_ensure_school_subscription_schema')) {
				app_ensure_school_subscription_schema($conn);
			}
			$currentSchoolId = function_exists('app_current_school_id') ? app_current_school_id() : 0;
			if ($currentSchoolId > 0 && function_exists('app_school_is_access_disabled') && app_school_is_access_disabled($conn, $currentSchoolId) && !$isSuperAdminController) {
				$message = function_exists('app_school_is_suspended') && app_school_is_suspended($conn, $currentSchoolId)
					? 'Your school account is currently suspended.'
					: 'Your school subscription has expired or is outside the configured term window.';
				app_set_auth_error(403, 'School locked', $message, [
					'school_id' => $currentSchoolId,
					'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				]);
				http_response_code(403);
				echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
				exit;
			}
			if ($currentSchoolId > 0 && function_exists('app_school_is_pending') && app_school_is_pending($conn, $currentSchoolId) && !$isSuperAdminController) {
				$message = 'Your school application is still waiting for approval.';
				app_set_auth_error(403, 'Approval pending', $message, [
					'school_id' => $currentSchoolId,
					'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				]);
				http_response_code(403);
				echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
				exit;
			}
		} catch (Throwable $e) {
			// Keep auth flow alive even if subscription metadata cannot be read.
		}

		// Use consolidated impersonation handler
		app_handle_impersonation_setup($conn, $impersonationRow, 'staff', (string)$account_id, (string)$level, trim($fname . ' ' . $lname), $designation, 'admin/core/stop_impersonation');

		app_online_touch($conn, $session_key);
		$portal = app_staff_login_portal($conn, (int)$account_id, (string)$level);
		$GLOBALS['app_staff_portal_home'] = $portal;
		if ($portal === 'super_admin') {
			app_set_auth_error(200, 'Authenticated', 'The session was validated successfully.', []);
			$res = "1";
			return;
		}
		app_enforce_portal_route_permission($conn, $portal, (string)$account_id, (string)$level, '../');
		app_set_auth_error(200, 'Authenticated', 'The session was validated successfully.', []);
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
			app_set_auth_error(401, 'Session expired', 'The student session key was not found in tbl_login_sessions. The session may have expired or been deleted.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'session_key' => $session_key,
				'account_type' => 'student',
			]);
			return;
		}

		if (app_session_enforce_ip() && !app_session_ip_matches((string)($row['ip_address'] ?? ''), (string)$current_ip)) {
			$res = "3";
			app_set_auth_error(401, 'Session IP mismatch', 'This student session is bound to a different IP address than the current request.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'stored_ip' => (string)($row['ip_address'] ?? ''),
				'current_ip' => (string)$current_ip,
				'account_type' => 'student',
			]);
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			app_set_auth_error(403, 'Account disabled', 'The student account tied to this session is inactive.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'account_id' => (string)($row['id'] ?? ''),
				'account_type' => 'student',
				'status' => $status,
			]);
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
			try {
				if (function_exists('app_ensure_school_subscription_schema')) {
					app_ensure_school_subscription_schema($conn);
				}
				$currentSchoolId = function_exists('app_current_school_id') ? app_current_school_id() : 0;
				if ($currentSchoolId > 0 && function_exists('app_school_is_access_disabled') && app_school_is_access_disabled($conn, $currentSchoolId) && !$isSuperAdminController) {
					$message = function_exists('app_school_is_suspended') && app_school_is_suspended($conn, $currentSchoolId)
						? 'Your school account is currently suspended.'
						: 'Your school subscription has expired or is outside the configured term window.';
					app_set_auth_error(403, 'School locked', $message, [
						'school_id' => $currentSchoolId,
						'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					]);
					http_response_code(403);
					echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
					exit;
				}
				if ($currentSchoolId > 0 && function_exists('app_school_is_pending') && app_school_is_pending($conn, $currentSchoolId) && !$isSuperAdminController) {
					$message = 'Your school application is still waiting for approval.';
					app_set_auth_error(403, 'Approval pending', $message, [
						'school_id' => $currentSchoolId,
						'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					]);
					http_response_code(403);
					echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
					exit;
				}
			} catch (Throwable $e) {
			}

		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$class]);
		$act_class = (string)($stmt->fetchColumn() ?: '');

		// Use consolidated impersonation handler
		app_handle_impersonation_setup($conn, $impersonationRow, 'student', (string)$account_id, (string)$level, trim($fname . ' ' . $lname), 'Student', 'admin/core/stop_impersonation');

		app_online_touch($conn, $session_key);
		app_enforce_portal_route_permission($conn, 'student', (string)$account_id, (string)$level, '../');
		app_set_auth_error(200, 'Authenticated', 'The session was validated successfully.', []);
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
			app_set_auth_error(401, 'Session expired', 'The parent session key was not found in tbl_login_sessions. The session may have expired or been deleted.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'session_key' => $session_key,
				'account_type' => 'parent',
			]);
			return;
		}

		if (app_session_enforce_ip() && !app_session_ip_matches((string)($row['ip_address'] ?? ''), (string)$current_ip)) {
			$res = "3";
			app_set_auth_error(401, 'Session IP mismatch', 'This parent session is bound to a different IP address than the current request.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'stored_ip' => (string)($row['ip_address'] ?? ''),
				'current_ip' => (string)$current_ip,
				'account_type' => 'parent',
			]);
			return;
		}

		$status = (string)($row['status'] ?? '0');
		if ($status !== "1") {
			$res = "2";
			app_set_auth_error(403, 'Account disabled', 'The parent account tied to this session is inactive.', [
				'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
				'account_id' => (string)($row['id'] ?? ''),
				'account_type' => 'parent',
				'status' => $status,
			]);
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
			try {
				if (function_exists('app_ensure_school_subscription_schema')) {
					app_ensure_school_subscription_schema($conn);
				}
				$currentSchoolId = function_exists('app_current_school_id') ? app_current_school_id() : 0;
				if ($currentSchoolId > 0 && function_exists('app_school_is_access_disabled') && app_school_is_access_disabled($conn, $currentSchoolId) && !$isSuperAdminController) {
					$message = function_exists('app_school_is_suspended') && app_school_is_suspended($conn, $currentSchoolId)
						? 'Your school account is currently suspended.'
						: 'Your school subscription has expired or is outside the configured term window.';
					app_set_auth_error(403, 'School locked', $message, [
						'school_id' => $currentSchoolId,
						'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					]);
					http_response_code(403);
					echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
					exit;
				}
				if ($currentSchoolId > 0 && function_exists('app_school_is_pending') && app_school_is_pending($conn, $currentSchoolId) && !$isSuperAdminController) {
					$message = 'Your school application is still waiting for approval.';
					app_set_auth_error(403, 'Approval pending', $message, [
						'school_id' => $currentSchoolId,
						'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
					]);
					http_response_code(403);
					echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
					exit;
				}
			} catch (Throwable $e) {
			}

		// Use consolidated impersonation handler
		app_handle_impersonation_setup($conn, $impersonationRow, 'parent', (string)$account_id, (string)$level, trim($fname . ' ' . $lname), 'Parent', 'admin/core/stop_impersonation');

		app_online_touch($conn, $session_key);
		app_enforce_portal_route_permission($conn, 'parent', (string)$account_id, (string)$level, '../');
		app_set_auth_error(200, 'Authenticated', 'The session was validated successfully.', []);
		$res = "1";
		return;
	}
} catch (PDOException $e) {
	app_set_auth_error(500, 'Session validation failed', 'A database error occurred while validating this session: ' . $e->getMessage(), [
		'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
		'db_driver' => defined('DBDriver') ? (string)DBDriver : '',
	]);
}
