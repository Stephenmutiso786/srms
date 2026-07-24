<?php

function app_default_permissions_for_level(int $level): array
{
	if (in_array($level, [9], true)) {
		return ['*'];
	}

	switch ($level) {
		case 0:
			return [
				'system.manage', 'audit.view', 'staff.manage', 'students.manage', 'academic.manage',
				'teacher.allocate', 'classes.assign', 'timetable.manage', 'attendance.manage',
				'exams.manage', 'marks.review', 'results.approve', 'report.generate', 'report.view',
				'student.leadership.manage',
				'finance.manage', 'finance.view', 'communication.manage', 'communication.send',
				'bom.manage', 'bom.view', 'sms.wallet.manage'
			];
		case 1:
			return [
				'academic.manage', 'teacher.allocate', 'classes.assign', 'timetable.manage',
				'attendance.manage', 'exams.manage', 'marks.review', 'results.approve',
				'results.lock', 'results.unlock', 'report.generate', 'report.view', 'students.manage',
				'student.leadership.manage', 'communication.manage', 'communication.send',
				'finance.manage', 'finance.view'
			];
		case 2:
			return ['attendance.manage', 'marks.enter', 'report.view', 'communication.send'];
		case 3:
			return ['report.view', 'finance.view', 'student.leadership.view'];
		case 4:
			return ['report.view', 'finance.view'];
		case 5:
			return ['finance.manage', 'finance.view', 'sms.wallet.manage', 'communication.send'];
		case 6:
			return ['staff.manage'];
		case 7:
			return ['transport.manage'];
		case 8:
			return ['library.manage'];
		case 10:
			return ['bom.view', 'bom.manage', 'finance.view'];
		default:
			return [];
	}
}

function app_get_permissions(PDO $conn, string $staffId, string $level): array
{
	static $cache = [];
	if (isset($GLOBALS['super_admin']) && $GLOBALS['super_admin'] === true) {
		return ['*'];
	}

	$cacheKey = trim($staffId) . '|' . trim($level);
	if ($cacheKey !== '|' && isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$levelInt = (int)$level;
	$defaults = app_default_permissions_for_level($levelInt);
	if ($staffId !== '' && function_exists('app_staff_has_active_teaching_assignment')) {
		try {
			if (app_staff_has_active_teaching_assignment($conn, (int)$staffId)) {
				$defaults = array_values(array_unique(array_merge($defaults, app_default_permissions_for_level(2))));
			}
		} catch (Throwable $e) {
			// Keep baseline permissions if teaching-assignment lookup fails.
		}
	}
	if (in_array('*', $defaults, true)) {
		$cache[$cacheKey] = ['*'];
		return $cache[$cacheKey];
	}

	if (function_exists('app_ensure_school_roles')) {
		try {
			app_ensure_school_roles($conn);
		} catch (Throwable $e) {
			// Continue with whatever permissions are available.
		}
	}

	if (!app_table_exists($conn, 'tbl_user_roles') || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_permissions')) {
		$cache[$cacheKey] = $defaults;
		return $cache[$cacheKey];
	}

	try {
		$stmt = $conn->prepare("SELECT p.code
			FROM tbl_user_roles ur
			JOIN tbl_role_permissions rp ON rp.role_id = ur.role_id
			JOIN tbl_permissions p ON p.id = rp.permission_id
			WHERE ur.staff_id = ?");
			$stmt->execute([(int)$staffId]);
			$rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
			if (!$rows || count($rows) === 0) {
				$cache[$cacheKey] = $defaults;
				return $cache[$cacheKey];
			}
			$rolePermissions = array_values(array_unique(array_filter(array_map(static function ($code): string {
				return strtolower(trim((string)$code));
		}, $rows), static function (string $code): bool {
			return $code !== '';
		})));

		$defaultPermissions = array_values(array_unique(array_filter(array_map(static function ($code): string {
			return strtolower(trim((string)$code));
		}, $defaults), static function (string $code): bool {
				return $code !== '';
			})));

			$cache[$cacheKey] = array_values(array_unique(array_merge($defaultPermissions, $rolePermissions)));
			return $cache[$cacheKey];
		} catch (Throwable $e) {
			$cache[$cacheKey] = $defaults;
			return $cache[$cacheKey];
		}
}

function app_has_permission(PDO $conn, string $staffId, string $level, string $permission): bool
{
	$perms = app_get_permissions($conn, $staffId, $level);
	if (in_array('*', $perms, true)) {
		return true;
	}
	return in_array($permission, $perms, true);
}

function app_current_user_permission_codes(): array
{
	static $cached = null;

	if ($cached !== null) {
		return $cached;
	}

	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$cached = [];
		return $cached;
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$cached = app_get_permissions($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level']);
	} catch (Throwable $e) {
		$cached = [];
	}

	return $cached;
}

function app_current_user_has_permission(string $permission): bool
{
	$permissions = app_current_user_permission_codes();
	if (in_array('*', $permissions, true)) {
		return true;
	}
	return in_array($permission, $permissions, true);
}

function app_current_user_has_any_permission(array $permissions): bool
{
	foreach ($permissions as $permission) {
		if (app_current_user_has_permission((string)$permission)) {
			return true;
		}
	}
	return false;
}

function app_staff_role_names(PDO $conn, int $staffId): array
{
	if ($staffId < 1 || !app_table_exists($conn, 'tbl_user_roles') || !app_table_exists($conn, 'tbl_roles')) {
		return [];
	}

	try {
		$stmt = $conn->prepare("SELECT r.name
			FROM tbl_user_roles ur
			JOIN tbl_roles r ON r.id = ur.role_id
			WHERE ur.staff_id = ?
			ORDER BY r.level DESC, r.name ASC");
		$stmt->execute([$staffId]);
		return array_values(array_filter(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
	} catch (Throwable $e) {
		return [];
	}
}

function app_ensure_role_module_allocations_table(PDO $conn): bool
{
	if (app_table_exists($conn, 'tbl_role_module_allocations')) {
		return true;
	}

	try {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_role_module_allocations (
			role_id INT NOT NULL,
			portal VARCHAR(50) NOT NULL,
			module_key VARCHAR(120) NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (role_id, portal, module_key)
		)");
		return app_table_exists($conn, 'tbl_role_module_allocations');
	} catch (Throwable $e) {
		return false;
	}
}

function app_ensure_role_module_seed_state_table(PDO $conn): bool
{
	if (app_table_exists($conn, 'tbl_role_module_seed_state')) {
		return true;
	}

	try {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_role_module_seed_state (
			role_id INT NOT NULL,
			portal VARCHAR(50) NOT NULL,
			seeded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (role_id, portal)
		)");
		return app_table_exists($conn, 'tbl_role_module_seed_state');
	} catch (Throwable $e) {
		return false;
	}
}

function app_role_permission_codes(PDO $conn, int $roleId): array
{
	if ($roleId < 1 || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_permissions')) {
		return [];
	}

	try {
		$stmt = $conn->prepare('SELECT p.code FROM tbl_role_permissions rp JOIN tbl_permissions p ON p.id = rp.permission_id WHERE rp.role_id = ?');
		$stmt->execute([$roleId]);
		return array_values(array_unique(array_filter(array_map(static function ($code): string {
			return strtolower(trim((string)$code));
		}, $stmt->fetchAll(PDO::FETCH_COLUMN)), static function (string $code): bool {
			return $code !== '';
		})));
	} catch (Throwable $e) {
		return [];
	}
}

function app_role_effective_permission_codes(PDO $conn, int $roleId): array
{
	if ($roleId < 1 || !app_table_exists($conn, 'tbl_roles')) {
		return [];
	}

	$roleLevel = null;
	try {
		$stmt = $conn->prepare('SELECT level FROM tbl_roles WHERE id = ? LIMIT 1');
		$stmt->execute([$roleId]);
		$levelValue = $stmt->fetchColumn();
		if ($levelValue !== false && $levelValue !== null && $levelValue !== '') {
			$roleLevel = (int)$levelValue;
		}
	} catch (Throwable $e) {
		$roleLevel = null;
	}

	$defaultPermissions = [];
	if ($roleLevel !== null) {
		$defaultPermissions = app_default_permissions_for_level((int)$roleLevel);
	}
	$defaultPermissions = array_values(array_unique(array_filter(array_map(static function ($code): string {
		return strtolower(trim((string)$code));
	}, $defaultPermissions), static function (string $code): bool {
		return $code !== '';
	})));

	$explicitPermissions = app_role_permission_codes($conn, $roleId);

	return array_values(array_unique(array_merge($defaultPermissions, $explicitPermissions)));
}

function app_auto_allocate_normal_modules_for_portal(PDO $conn, string $portal): void
{
	$portal = strtolower(trim($portal));
	if ($portal === '') {
		return;
	}

	if (!app_table_exists($conn, 'tbl_roles') || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_permissions')) {
		return;
	}

	if (!app_ensure_role_module_allocations_table($conn)) {
		return;
	}
	if (!app_ensure_role_module_seed_state_table($conn)) {
		return;
	}

	$allocatableModules = [];
	foreach (app_portal_module_catalog($portal) as $module) {
		$moduleKey = strtolower(trim((string)($module['key'] ?? '')));
		$modulePermissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
		$isCore = !empty($module['core']);
		if ($moduleKey === '' || empty($modulePermissions) || $isCore) {
			continue;
		}
		$allocatableModules[] = [
			'key' => $moduleKey,
			'permissions' => array_values(array_unique(array_map(static function (string $permission): string {
				return strtolower(trim($permission));
			}, $modulePermissions))),
		];
	}

	if (empty($allocatableModules)) {
		return;
	}

	try {
		$stmt = $conn->prepare('SELECT id FROM tbl_roles');
		$stmt->execute();
		$roleIds = array_values(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), static function (int $roleId): bool {
			return $roleId > 0;
		}));
		if (empty($roleIds)) {
			return;
		}

		$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
		foreach ($roleIds as $roleId) {
			$permissionCodes = app_role_effective_permission_codes($conn, (int)$roleId);
			if (empty($permissionCodes)) {
				continue;
			}
			$permissionLookup = array_fill_keys($permissionCodes, true);

			foreach ($allocatableModules as $module) {
				$allowed = false;
				foreach ((array)$module['permissions'] as $permission) {
					if (!empty($permissionLookup[(string)$permission])) {
						$allowed = true;
						break;
					}
				}

				if (!$allowed) {
					continue;
				}

				if ($isPgsql) {
					$insertStmt = $conn->prepare('INSERT INTO tbl_role_module_allocations (role_id, portal, module_key) VALUES (?, ?, ?) ON CONFLICT DO NOTHING');
				} else {
					$insertStmt = $conn->prepare('INSERT IGNORE INTO tbl_role_module_allocations (role_id, portal, module_key) VALUES (?, ?, ?)');
				}
				$insertStmt->execute([$roleId, $portal, (string)$module['key']]);
			}

			if ($isPgsql) {
				$markSeededStmt = $conn->prepare('INSERT INTO tbl_role_module_seed_state (role_id, portal) VALUES (?, ?) ON CONFLICT DO NOTHING');
			} else {
				$markSeededStmt = $conn->prepare('INSERT IGNORE INTO tbl_role_module_seed_state (role_id, portal) VALUES (?, ?)');
			}
			$markSeededStmt->execute([$roleId, $portal]);
		}
	} catch (Throwable $e) {
		// Non-fatal: keep manual allocation flow available.
	}
}

function app_staff_role_module_allocation(PDO $conn, string $portal, string $staffId): array
{
	static $cache = [];
	$portal = strtolower(trim($portal));
	$staffIdInt = (int)$staffId;
	$cacheKey = $portal . '|' . $staffIdInt;
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$result = [
		'active' => false,
		'module_keys' => [],
	];

	if ($portal === '' || $staffIdInt < 1) {
		$cache[$cacheKey] = $result;
		return $cache[$cacheKey];
	}

	if (!app_table_exists($conn, 'tbl_user_roles')) {
		$cache[$cacheKey] = $result;
		return $cache[$cacheKey];
	}

	if (!app_ensure_role_module_allocations_table($conn)) {
		$cache[$cacheKey] = $result;
		return $cache[$cacheKey];
	}

	try {
		$stmt = $conn->prepare('SELECT role_id FROM tbl_user_roles WHERE staff_id = ?');
		$stmt->execute([$staffIdInt]);
		$roleIds = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
		$roleIds = array_values(array_filter($roleIds, static function (int $roleId): bool {
			return $roleId > 0;
		}));

		if (empty($roleIds)) {
			$cache[$cacheKey] = $result;
			return $cache[$cacheKey];
		}

		$placeholders = implode(',', array_fill(0, count($roleIds), '?'));
		$params = $roleIds;
		$params[] = $portal;

		$stmt = $conn->prepare("SELECT module_key FROM tbl_role_module_allocations WHERE role_id IN ($placeholders) AND portal = ?");
		$stmt->execute($params);
		$moduleKeys = array_values(array_unique(array_filter(array_map(static function ($moduleKey): string {
			return strtolower(trim((string)$moduleKey));
		}, $stmt->fetchAll(PDO::FETCH_COLUMN)), static function (string $moduleKey): bool {
			return $moduleKey !== '';
		})));

		$result['active'] = !empty($moduleKeys);
		$result['module_keys'] = array_fill_keys($moduleKeys, true);
	} catch (Throwable $e) {
		$result = [
			'active' => false,
			'module_keys' => [],
		];
	}

	$cache[$cacheKey] = $result;
	return $cache[$cacheKey];
}

function app_ensure_staff_module_allocations_table(PDO $conn): bool
{
	if (app_table_exists($conn, 'tbl_staff_module_allocations')) {
		return true;
	}

	try {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_staff_module_allocations (
			staff_id INT NOT NULL,
			portal VARCHAR(50) NOT NULL,
			module_key VARCHAR(120) NOT NULL,
			created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (staff_id, portal, module_key)
		)");
		return app_table_exists($conn, 'tbl_staff_module_allocations');
	} catch (Throwable $e) {
		return false;
	}
}

function app_staff_module_allocations(PDO $conn, string $portal, string $staffId): array
{
	// Returns allocation active flag and module_keys map. Prefers per-staff allocations when present.
	static $cache = [];
	$portal = strtolower(trim($portal));
	$staffIdInt = (int)$staffId;
	$cacheKey = $portal . '|' . $staffIdInt;
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$result = [
		'active' => false,
		'module_keys' => [],
	];

	if ($portal === '' || $staffIdInt < 1) {
		$cache[$cacheKey] = $result;
		return $cache[$cacheKey];
	}

	try {
		// Check staff-specific allocations first
		if (app_ensure_staff_module_allocations_table($conn)) {
			$stmt = $conn->prepare('SELECT module_key FROM tbl_staff_module_allocations WHERE staff_id = ? AND portal = ?');
			$stmt->execute([$staffIdInt, $portal]);
			$moduleKeys = array_values(array_unique(array_filter(array_map(static function ($moduleKey): string {
				return strtolower(trim((string)$moduleKey));
			}, $stmt->fetchAll(PDO::FETCH_COLUMN)), static function (string $moduleKey): bool {
				return $moduleKey !== '';
			})));

			if (!empty($moduleKeys)) {
				$result['active'] = true;
				$result['module_keys'] = array_fill_keys($moduleKeys, true);
				$cache[$cacheKey] = $result;
				return $cache[$cacheKey];
			}
		}

		// Fallback to role-based allocations
		$result = app_staff_role_module_allocation($conn, $portal, $staffId);
	} catch (Throwable $e) {
		// ignore and return defaults
	}

	$cache[$cacheKey] = $result;
	return $cache[$cacheKey];
}

function app_staff_module_allocation_allows(PDO $conn, string $portal, string $staffId, array $module): bool
{
	$allocation = app_staff_module_allocations($conn, $portal, $staffId);
	if (empty($allocation['active'])) {
		return true;
	}

	$modulePermissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
	if (empty($modulePermissions)) {
		return true;
	}

	if (!empty($module['core'])) {
		return true;
	}

	$moduleKey = strtolower(trim((string)($module['key'] ?? '')));
	if ($moduleKey === '') {
		return true;
	}

	return !empty($allocation['module_keys'][$moduleKey]);
}

function app_teacher_portal_module_catalog(): array
{
	return [
		['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'teacher', 'icon' => 'feather icon-monitor', 'description' => 'Overview and quick actions', 'permissions' => [], 'core' => true, 'routes' => ['teacher/index']],
		['key' => 'terms', 'label' => 'Academic Terms', 'href' => 'teacher/terms', 'icon' => 'feather icon-folder', 'description' => 'View term structure', 'permissions' => [], 'core' => true],
		['key' => 'attendance', 'label' => 'Attendance', 'href' => 'teacher/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Class attendance and monitoring', 'permissions' => ['attendance.manage'], 'core' => true, 'routes' => ['teacher/attendance_session']],
		['key' => 'marks_entry', 'label' => 'Marks Entry', 'href' => 'teacher/exam_marks_entry', 'icon' => 'feather icon-edit-3', 'description' => 'Enter exam and CBE marks', 'permissions' => ['marks.enter'], 'core' => true, 'routes' => ['teacher/marks_entry', 'teacher/exam_marks_table', 'teacher/cbe_entry', 'teacher/import_results']],
		['key' => 'results', 'label' => 'Results', 'href' => 'teacher/manage_results', 'icon' => 'feather icon-graph', 'description' => 'Review and publish results', 'permissions' => ['report.view', 'report.generate', 'marks.review', 'results.approve'], 'core' => true, 'routes' => ['teacher/results', 'teacher/report_card', 'teacher/class_report', 'teacher/published_analytics', 'teacher/print_mark_sheet', 'teacher/report_card_pdf']],
		['key' => 'discipline', 'label' => 'Discipline', 'href' => 'teacher/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Learner welfare and discipline', 'permissions' => ['student.leadership.manage'], 'core' => true],
		['key' => 'students', 'label' => 'Students', 'href' => 'teacher/students', 'icon' => 'feather icon-users', 'description' => 'Student directory and class lists', 'permissions' => ['students.manage', 'report.view'], 'core' => true, 'routes' => ['teacher/list_students', 'teacher/export_students', 'teacher/certificates']],
		['key' => 'staff_attendance', 'label' => 'Staff Attendance', 'href' => 'teacher/staff_attendance', 'icon' => 'feather icon-clock', 'description' => 'Monitor staff attendance', 'permissions' => ['attendance.manage'], 'core' => true],
		['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'teacher/exam_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable planning', 'permissions' => ['timetable.manage', 'exams.manage'], 'core' => false],
		['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'teacher/grading-system', 'icon' => 'feather icon-award', 'description' => 'Grading and assessment setup', 'permissions' => ['exams.manage', 'academic.manage'], 'core' => false, 'routes' => ['teacher/division-system']],
		['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'teacher/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Digital lessons and content', 'permissions' => ['academic.manage'], 'core' => false],
		['key' => 'assignment_generator', 'label' => 'AI Assignment Generator', 'href' => 'teacher/assignment_generator', 'icon' => 'feather icon-edit', 'description' => 'Generate assignments and marking guides with Edu AI', 'permissions' => ['marks.enter', 'academic.manage'], 'core' => false],
		['key' => 'subject_combinations', 'label' => 'Subject Combinations', 'href' => 'teacher/combinations', 'icon' => 'feather icon-book-open', 'description' => 'Subject allocation and combinations', 'permissions' => ['teacher.allocate', 'academic.manage'], 'core' => false],
		['key' => 'roles', 'label' => 'Roles', 'href' => 'teacher/roles', 'icon' => 'feather icon-shield', 'description' => 'Assign staff roles', 'permissions' => ['staff.manage'], 'core' => false],
		['key' => 'how_system_works', 'label' => 'How The System Works', 'href' => 'teacher/how_system_works', 'icon' => 'feather icon-help-circle', 'description' => 'Help and guidance', 'permissions' => [], 'core' => true],
		['key' => 'profile', 'label' => 'Profile', 'href' => 'teacher/profile', 'icon' => 'feather icon-user', 'description' => 'My staff profile', 'permissions' => [], 'core' => true, 'routes' => ['teacher/id_card', 'teacher/id_card_pdf']],
	];
}

function app_portal_module_catalog(string $portal): array
{
	$portal = strtolower(trim($portal));
	if ($portal === 'student') {
		return [
			['key' => 'attendance', 'label' => 'Attendance', 'href' => 'student/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Attendance view', 'permissions' => [], 'core' => true, 'active' => ['attendance']],
			['key' => 'certificates', 'label' => 'Certificates', 'href' => 'student/certificates', 'icon' => 'feather icon-award', 'description' => 'Download certificates', 'permissions' => [], 'core' => true, 'active' => ['certificates']],
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'student', 'icon' => 'feather icon-monitor', 'description' => 'Student overview', 'permissions' => [], 'core' => true, 'active' => ['index', 'dashboard', 'terms']],
			['key' => 'discipline', 'label' => 'Discipline', 'href' => 'student/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Discipline information', 'permissions' => [], 'core' => true, 'active' => ['discipline']],
			['key' => 'division_system', 'label' => 'Division System', 'href' => 'student/division-system', 'icon' => 'feather icon-layers', 'description' => 'Division guidance', 'permissions' => [], 'core' => true, 'active' => ['division-system']],
			['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'student/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Lessons and content', 'permissions' => [], 'core' => true, 'active' => ['elearning']],
			['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'student/exam_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable', 'permissions' => [], 'core' => true, 'active' => ['exam_timetable']],
			['key' => 'fees', 'label' => 'Fees', 'href' => 'student/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Fee statements', 'permissions' => ['finance.view'], 'core' => true, 'active' => ['fees']],
			['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'student/grading-system', 'icon' => 'feather icon-award', 'description' => 'Grading rules', 'permissions' => [], 'core' => true, 'active' => ['grading-system']],
			['key' => 'leadership', 'label' => 'Leadership', 'href' => 'student/leadership', 'icon' => 'feather icon-users', 'description' => 'Student leadership', 'permissions' => ['student.leadership.view'], 'core' => false, 'active' => ['leadership']],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'student/view', 'icon' => 'feather icon-user', 'description' => 'My profile', 'permissions' => [], 'core' => true, 'active' => ['view', 'profile', 'id_card', 'id_card_pdf']],
			['key' => 'portal_help', 'label' => 'Portal Guide', 'href' => 'student/how_portal_works', 'icon' => 'feather icon-help-circle', 'description' => 'How this portal works', 'permissions' => [], 'core' => true, 'active' => ['how_portal_works']],
			['key' => 'ranking', 'label' => 'Ranking', 'href' => 'student/ranking', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Class ranking', 'permissions' => ['report.view'], 'core' => false, 'active' => ['ranking']],
			['key' => 'report_card', 'label' => 'Report Card', 'href' => 'student/report_card', 'icon' => 'feather icon-file-text', 'description' => 'Report card and results', 'permissions' => ['report.view'], 'core' => true, 'active' => ['report_card', 'report_card_pdf', 'save_pdf']],
			['key' => 'results', 'label' => 'Results', 'href' => 'student/results', 'icon' => 'feather icon-file-text', 'description' => 'My result summary', 'permissions' => ['report.view'], 'core' => true, 'active' => ['results']],
			['key' => 'quiz', 'label' => 'Quiz', 'href' => 'student/quiz', 'icon' => 'feather icon-edit-2', 'description' => 'Practice quizzes', 'permissions' => ['report.view'], 'core' => false, 'active' => ['quiz']],
			['key' => 'settings', 'label' => 'Settings', 'href' => 'student/settings', 'icon' => 'feather icon-settings', 'description' => 'Account settings', 'permissions' => [], 'core' => true, 'active' => ['settings', 'privacy']],
			['key' => 'subjects', 'label' => 'Subjects', 'href' => 'student/subjects', 'icon' => 'feather icon-book', 'description' => 'Subject list', 'permissions' => [], 'core' => true, 'active' => ['subjects']],
		];
	}

	if ($portal === 'parent') {
		return [
			['key' => 'attendance', 'label' => 'Attendance', 'href' => 'parent/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Child attendance', 'permissions' => [], 'core' => true, 'active' => ['attendance']],
			['key' => 'certificates', 'label' => 'Certificates', 'href' => 'parent/certificates', 'icon' => 'feather icon-award', 'description' => 'Download certificates', 'permissions' => [], 'core' => true, 'active' => ['certificates']],
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'parent', 'icon' => 'feather icon-monitor', 'description' => 'Parent overview', 'permissions' => [], 'core' => true, 'active' => ['index', 'dashboard']],
			['key' => 'discipline', 'label' => 'Discipline', 'href' => 'parent/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Discipline information', 'permissions' => [], 'core' => true, 'active' => ['discipline']],
			['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'parent/elearning', 'icon' => 'feather icon-laptop', 'description' => 'Learning content', 'permissions' => [], 'core' => true, 'active' => ['elearning']],
			['key' => 'fees', 'label' => 'Fees', 'href' => 'parent/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Fee statements', 'permissions' => ['finance.view'], 'core' => true, 'active' => ['fees']],
			['key' => 'how_system_works', 'label' => 'How The System Works', 'href' => 'how_system_works', 'icon' => 'feather icon-help-circle', 'description' => 'Portal guide', 'permissions' => [], 'core' => true, 'active' => ['how_system_works']],
			['key' => 'report_card', 'label' => 'Report Card', 'href' => 'parent/report_card', 'icon' => 'feather icon-file-text', 'description' => 'Report cards and results', 'permissions' => ['report.view'], 'core' => true, 'active' => ['report_card', 'report_card_pdf']],
		];
	}

	if ($portal === 'admin') {
		return [
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'admin', 'icon' => 'feather icon-monitor', 'description' => 'Admin overview', 'permissions' => [], 'core' => true],
			['key' => 'academic', 'label' => 'Academic Account', 'href' => 'admin/academic', 'icon' => 'feather icon-user', 'description' => 'Academic leadership account', 'permissions' => ['academic.manage', 'staff.manage'], 'core' => true],
			['key' => 'teachers', 'label' => 'Teachers', 'href' => 'admin/teachers', 'icon' => 'feather icon-user', 'description' => 'Teacher records and access', 'permissions' => ['staff.manage', 'academic.manage'], 'core' => true],
			['key' => 'classes', 'label' => 'Class Management', 'href' => 'admin/classes', 'icon' => 'feather icon-home', 'description' => 'Class setup', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'terms', 'label' => 'Terms & Sessions', 'href' => 'admin/terms', 'icon' => 'feather icon-folder', 'description' => 'Terms and sessions', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'subjects', 'label' => 'Subject Catalog', 'href' => 'admin/subjects', 'icon' => 'feather icon-book', 'description' => 'Subject master data', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'teacher_allocation', 'label' => 'Subject Teachers', 'href' => 'admin/teacher_allocation', 'icon' => 'feather icon-users', 'description' => 'Subject allocation', 'permissions' => ['teacher.allocate', 'academic.manage'], 'core' => true],
			['key' => 'school_timetable', 'label' => 'School Timetable', 'href' => 'admin/school_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Timetable planning', 'permissions' => ['timetable.manage', 'academic.manage'], 'core' => true],
			['key' => 'discipline', 'label' => 'Discipline Cases', 'href' => 'admin/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Student discipline', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'import_students', 'label' => 'Import Students', 'href' => 'admin/import_students', 'icon' => 'feather icon-upload', 'description' => 'Bulk student import', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'manage_students', 'label' => 'Manage Students', 'href' => 'admin/manage_students', 'icon' => 'feather icon-users', 'description' => 'Student records', 'permissions' => ['students.manage'], 'core' => false, 'routes' => ['admin/students']],
			['key' => 'register_students', 'label' => 'Register Students', 'href' => 'admin/register_students', 'icon' => 'feather icon-user-plus', 'description' => 'Student registration', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'student_leaders', 'label' => 'Student Leadership', 'href' => 'admin/student_leaders', 'icon' => 'feather icon-award', 'description' => 'Student leadership', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'parents', 'label' => 'Parents', 'href' => 'admin/parents', 'icon' => 'feather icon-user-plus', 'description' => 'Parent records', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'attendance', 'label' => 'Attendance', 'href' => 'admin/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Student attendance', 'permissions' => ['attendance.manage'], 'core' => true, 'routes' => ['admin/attendance_session']],
			['key' => 'staff_attendance', 'label' => 'Staff Attendance', 'href' => 'admin/staff_attendance', 'icon' => 'feather icon-clock', 'description' => 'Staff attendance', 'permissions' => ['attendance.manage'], 'core' => true],
			['key' => 'fees', 'label' => 'Fees & Finance', 'href' => 'admin/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Fee and finance tools', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true, 'routes' => ['admin/financial_reports', 'admin/installment_plans', 'admin/fee_structure', 'admin/invoices', 'admin/expense_approvals']],
			['key' => 'import_export', 'label' => 'Import / Export', 'href' => 'admin/import_export', 'icon' => 'feather icon-upload-cloud', 'description' => 'Data import and export', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'communication', 'label' => 'Communication', 'href' => 'admin/communication', 'icon' => 'feather icon-message-circle', 'description' => 'Announcements and messages', 'permissions' => ['communication.manage'], 'core' => true],
			['key' => 'announcement_center', 'label' => 'Announcement Center', 'href' => 'admin/announcement_center', 'icon' => 'feather icon-bell', 'description' => 'Central announcements, alerts, and AI drafting', 'permissions' => ['communication.manage'], 'core' => false],
			['key' => 'sms_topup', 'label' => 'SMS Tokens', 'href' => 'admin/sms_topup', 'icon' => 'feather icon-credit-card', 'description' => 'SMS wallet', 'permissions' => ['communication.manage', 'finance.manage'], 'core' => false],
			['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'admin/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Digital learning', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'feedback', 'label' => 'Edu Bot & Feedback', 'href' => 'admin/feedback', 'icon' => 'feather icon-message-square', 'description' => 'Memory-backed school assistant and feedback tools', 'permissions' => ['academic.manage'], 'core' => false],
			['key' => 'document_generator', 'label' => 'AI Document Generator', 'href' => 'admin/document_generator', 'icon' => 'feather icon-file-plus', 'description' => 'Generate official letters, comments, reminders, and plans', 'permissions' => ['academic.manage', 'report.generate', 'communication.manage'], 'core' => false],
			['key' => 'ai_command_center', 'label' => 'AI Command Center', 'href' => 'admin/ai_command_center', 'icon' => 'feather icon-cpu', 'description' => 'School intelligence, risks, health score, and predictions', 'permissions' => ['report.view', 'academic.manage', 'finance.view'], 'core' => false],
			['key' => 'library', 'label' => 'Library', 'href' => 'admin/library', 'icon' => 'feather icon-book', 'description' => 'Library inventory', 'permissions' => ['library.manage'], 'core' => false],
			['key' => 'inventory', 'label' => 'Inventory', 'href' => 'admin/inventory', 'icon' => 'feather icon-box', 'description' => 'Asset inventory', 'permissions' => ['inventory.manage'], 'core' => false],
			['key' => 'transport', 'label' => 'Transport', 'href' => 'admin/transport', 'icon' => 'feather icon-truck', 'description' => 'Fleet management', 'permissions' => ['transport.manage'], 'core' => false],
			['key' => 'exams', 'label' => 'Exams', 'href' => 'admin/exams', 'icon' => 'feather icon-file-text', 'description' => 'Exam setup', 'permissions' => ['exams.manage'], 'core' => true, 'routes' => ['admin/edit_exam']],
			['key' => 'grading_management', 'label' => 'Grading Management', 'href' => 'admin/grading_system', 'icon' => 'feather icon-award', 'description' => 'Admin grading systems and class linkage', 'permissions' => ['exams.manage'], 'core' => true, 'routes' => ['admin/grading_system']],
			['key' => 'marks_entry', 'label' => 'Marks Entry', 'href' => 'admin/exam_marks_entry', 'icon' => 'feather icon-edit-3', 'description' => 'Open marks entry for allocated classes and subjects', 'permissions' => ['marks.enter'], 'core' => true, 'routes' => ['admin/exam_marks_entry', 'admin/exam_marks_table', 'admin/cbe_entry']],
			['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'admin/exam_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable', 'permissions' => ['exams.manage', 'timetable.manage'], 'core' => true],
			['key' => 'marks_review', 'label' => 'Marks Review', 'href' => 'admin/marks_review', 'icon' => 'feather icon-edit-3', 'description' => 'Marks moderation', 'permissions' => ['marks.review'], 'core' => false],
			['key' => 'publish_results', 'label' => 'Publish Results', 'href' => 'admin/publish_results', 'icon' => 'feather icon-share-2', 'description' => 'Publish results', 'permissions' => ['results.approve'], 'core' => false],
			['key' => 'results_analytics', 'label' => 'Results Analytics', 'href' => 'admin/results_analytics', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Result analytics', 'permissions' => ['report.view'], 'core' => false],
			['key' => 'results_locks', 'label' => 'Results Locks', 'href' => 'admin/results_locks', 'icon' => 'feather icon-lock', 'description' => 'Results locks', 'permissions' => ['results.approve'], 'core' => false],
			['key' => 'report', 'label' => 'Report Tool', 'href' => 'admin/report', 'icon' => 'feather icon-clipboard', 'description' => 'Report generation', 'permissions' => ['report.generate', 'report.view'], 'core' => false, 'routes' => ['admin/manage_results', 'admin/individual_results', 'admin/single_results', 'admin/save_report', 'admin/save_pdf', 'admin/bulk_results']],
			['key' => 'merit_list', 'label' => 'Merit List', 'href' => 'admin/merit_list', 'icon' => 'feather icon-list', 'description' => 'Merit lists', 'permissions' => ['report.view'], 'core' => false, 'routes' => ['admin/merit_list_pdf']],
			['key' => 'report_settings', 'label' => 'Report Settings', 'href' => 'admin/report_settings', 'icon' => 'feather icon-sliders', 'description' => 'Report settings', 'permissions' => ['report.generate'], 'core' => false],
			['key' => 'certificates', 'label' => 'Generate Certificates', 'href' => 'admin/certificates', 'icon' => 'feather icon-award', 'description' => 'Certificate generation', 'permissions' => ['certificates.manage'], 'core' => false],
			['key' => 'promotion_rules', 'label' => 'Promotion Rules', 'href' => 'admin/promotion_rules', 'icon' => 'feather icon-shuffle', 'description' => 'Promotion rules', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'promotions', 'label' => 'Student Promotions', 'href' => 'admin/promotions', 'icon' => 'feather icon-arrow-up', 'description' => 'Promote learners', 'permissions' => ['students.manage'], 'core' => false],
			['key' => 'alumni', 'label' => 'Alumni Register', 'href' => 'admin/alumni', 'icon' => 'feather icon-user-check', 'description' => 'Completed learners retained as alumni', 'permissions' => ['students.manage', 'report.view'], 'core' => false],
			['key' => 'data_camp', 'label' => 'Data Camp', 'href' => 'admin/data_camp', 'icon' => 'feather icon-database', 'description' => 'Permanent school archive and retained records', 'permissions' => [], 'core' => false],
			['key' => 'analytics_engine', 'label' => 'Analytics Engine', 'href' => 'admin/analytics_engine', 'icon' => 'feather icon-activity', 'description' => 'Analytics engine', 'permissions' => ['report.view'], 'core' => false],
			['key' => 'benchmarking', 'label' => 'Benchmarking', 'href' => 'admin/benchmarking', 'icon' => 'feather icon-trending-up', 'description' => 'Benchmarking', 'permissions' => ['report.view'], 'core' => false],
			['key' => 'notifications', 'label' => 'Notifications', 'href' => 'admin/notifications', 'icon' => 'feather icon-bell', 'description' => 'Notification queue', 'permissions' => ['communication.manage'], 'core' => false],
			['key' => 'online_users', 'label' => 'Online Users', 'href' => 'admin/online_users', 'icon' => 'feather icon-wifi', 'description' => 'Active sessions', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'audit_logs', 'label' => 'Audit Logs', 'href' => 'admin/audit_logs', 'icon' => 'feather icon-shield', 'description' => 'System audit trail', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'roles', 'label' => 'Roles & Permissions', 'href' => 'admin/roles', 'icon' => 'feather icon-shield', 'description' => 'Role management', 'permissions' => ['staff.manage'], 'core' => false],
			['key' => 'role_matrix', 'label' => 'Role Matrix', 'href' => 'admin/role_matrix', 'icon' => 'feather icon-grid', 'description' => 'Role-permission matrix', 'permissions' => ['staff.manage'], 'core' => false],
			['key' => 'bom', 'label' => 'BOM Management', 'href' => 'admin/bom', 'icon' => 'feather icon-briefcase', 'description' => 'Board management', 'permissions' => ['staff.manage'], 'core' => false],
			['key' => 'mpesa', 'label' => 'M-Pesa', 'href' => 'admin/mpesa', 'icon' => 'feather icon-smartphone', 'description' => 'M-Pesa integration', 'permissions' => ['finance.manage'], 'core' => false, 'routes' => ['admin/mpesa_pay']],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'admin/profile', 'icon' => 'feather icon-user', 'description' => 'Admin profile', 'permissions' => [], 'core' => true],
			['key' => 'smtp', 'label' => 'SMTP Settings', 'href' => 'admin/smtp', 'icon' => 'feather icon-mail', 'description' => 'Mail settings', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'system_diagnostics', 'label' => 'System Diagnostics', 'href' => 'admin/system_diagnostics', 'icon' => 'feather icon-activity', 'description' => 'Diagnostics', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'migrations', 'label' => 'Migrations', 'href' => 'admin/migrations', 'icon' => 'feather icon-database', 'description' => 'Database migrations', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'module_locks', 'label' => 'Module Locks', 'href' => 'admin/module_locks', 'icon' => 'feather icon-lock', 'description' => 'Module lock control', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'system', 'label' => 'System Settings', 'href' => 'admin/system', 'icon' => 'feather icon-settings', 'description' => 'Global settings', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'how_system_works', 'label' => 'How The System Works', 'href' => 'how_system_works', 'icon' => 'feather icon-help-circle', 'description' => 'Portal guide', 'permissions' => [], 'core' => true],
		];
	}

	if ($portal === 'bom') {
		return [
			['key' => 'dashboard', 'label' => 'BOM Dashboard', 'href' => 'bom', 'icon' => 'feather icon-home', 'description' => 'Governance overview', 'permissions' => ['bom.view'], 'core' => true, 'active' => ['index']],
			['key' => 'profile', 'label' => 'My Profile', 'href' => 'bom/profile', 'icon' => 'feather icon-user', 'description' => 'My BOM profile', 'permissions' => ['bom.view'], 'core' => true, 'active' => ['profile']],
			['key' => 'logout', 'label' => 'Logout', 'href' => 'logout', 'icon' => 'feather icon-log-out', 'description' => 'Sign out', 'permissions' => [], 'core' => true, 'active' => ['logout']],
		];
	}

	if ($portal === 'academic') {
		return [
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'academic', 'icon' => 'feather icon-monitor', 'description' => 'Academic overview', 'permissions' => [], 'core' => true, 'routes' => ['academic/index']],
			['key' => 'terms', 'label' => 'Academic Terms', 'href' => 'academic/terms.php', 'icon' => 'feather icon-folder', 'description' => 'Manage academic terms', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'classes', 'label' => 'Classes', 'href' => 'academic/classes.php', 'icon' => 'feather icon-home', 'description' => 'Class setup and structure', 'permissions' => ['classes.assign', 'academic.manage'], 'core' => true],
			['key' => 'subjects', 'label' => 'Subjects', 'href' => 'academic/subjects.php', 'icon' => 'feather icon-book', 'description' => 'Subject setup', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'teacher_control', 'label' => 'Teachers', 'href' => 'admin/teachers.php', 'icon' => 'feather icon-user', 'description' => 'Manage and impersonate teacher accounts', 'permissions' => ['academic.manage', 'staff.manage'], 'core' => true],
			['key' => 'combinations', 'label' => 'Subject Combinations', 'href' => 'academic/combinations.php', 'icon' => 'feather icon-book-open', 'description' => 'Teacher-subject allocation', 'permissions' => ['teacher.allocate', 'academic.manage'], 'core' => true],
			['key' => 'attendance', 'label' => 'Attendance', 'href' => 'academic/attendance.php', 'icon' => 'feather icon-check-square', 'description' => 'Class attendance and monitoring', 'permissions' => ['attendance.manage'], 'core' => true, 'routes' => ['academic/attendance_session']],
			['key' => 'students', 'label' => 'Student Promotion', 'href' => 'academic/promote_students.php', 'icon' => 'feather icon-users', 'description' => 'Promote and manage learners', 'permissions' => ['students.manage', 'academic.manage'], 'core' => true],
			['key' => 'discipline', 'label' => 'Discipline Cases', 'href' => 'academic/discipline.php', 'icon' => 'feather icon-alert-triangle', 'description' => 'Deputy discipline case manager and hearings', 'permissions' => ['student.leadership.manage', 'students.manage'], 'core' => true, 'routes' => ['academic/discipline_letter']],
			['key' => 'results_manage', 'label' => 'Manage Results', 'href' => 'academic/manage_results.php', 'icon' => 'feather icon-file-text', 'description' => 'Results entry and approval', 'permissions' => ['marks.enter', 'marks.review', 'results.approve'], 'core' => true, 'routes' => ['academic/bulk_results', 'academic/single_results']],
			['key' => 'marks_entry', 'label' => 'Marks Entry', 'href' => 'academic/exam_marks_entry.php', 'icon' => 'feather icon-edit-3', 'description' => 'Open marks entry for your allocated classes and subjects', 'permissions' => ['marks.enter'], 'core' => true, 'routes' => ['academic/exam_marks_entry', 'academic/exam_marks_table', 'academic/cbe_entry']],
			['key' => 'exams', 'label' => 'Exams', 'href' => 'admin/exams.php', 'icon' => 'feather icon-file-text', 'description' => 'Create and manage exams', 'permissions' => ['exams.manage'], 'core' => true, 'routes' => ['admin/edit_exam']],
			['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'admin/exam_timetable.php', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable planning', 'permissions' => ['exams.manage', 'timetable.manage'], 'core' => true],
			['key' => 'marks_review', 'label' => 'Marks Review', 'href' => 'admin/marks_review.php', 'icon' => 'feather icon-edit-3', 'description' => 'Review submitted exam marks', 'permissions' => ['marks.review'], 'core' => true],
			['key' => 'publish_results', 'label' => 'Publish Results', 'href' => 'admin/publish_results.php', 'icon' => 'feather icon-share-2', 'description' => 'Approve and publish exam results', 'permissions' => ['results.approve'], 'core' => true],
			['key' => 'results_analytics', 'label' => 'Results Analytics', 'href' => 'admin/results_analytics.php', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Performance analytics', 'permissions' => ['report.view'], 'core' => true],
			['key' => 'results_locks', 'label' => 'Results Locks', 'href' => 'admin/results_locks.php', 'icon' => 'feather icon-lock', 'description' => 'Lock and unlock results', 'permissions' => ['results.approve', 'results.lock', 'results.unlock'], 'core' => true],
			['key' => 'individual_results', 'label' => 'Individual Results', 'href' => 'academic/individual_results.php', 'icon' => 'feather icon-user-check', 'description' => 'Single-student result review', 'permissions' => ['report.view', 'report.generate'], 'core' => true],
			['key' => 'report_tool', 'label' => 'Report Tool', 'href' => 'academic/report.php', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Class report analysis', 'permissions' => ['report.generate', 'report.view'], 'core' => true, 'routes' => ['academic/save_pdf', 'academic/save_report']],
			['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'academic/grading-system.php', 'icon' => 'feather icon-award', 'description' => 'View grading rules set by admin', 'permissions' => ['exams.manage', 'academic.manage'], 'core' => true],
			['key' => 'division_system', 'label' => 'Division System', 'href' => 'academic/division-system.php', 'icon' => 'feather icon-layers', 'description' => 'Division and performance bands', 'permissions' => ['academic.manage', 'report.generate'], 'core' => true],
			['key' => 'fees', 'label' => 'Fees & Finance', 'href' => 'admin/fees.php', 'icon' => 'feather icon-credit-card', 'description' => 'Finance and fee operations', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true, 'routes' => ['admin/financial_reports', 'admin/fee_structure', 'admin/invoices']],
			['key' => 'announcements', 'label' => 'Announcements', 'href' => 'academic/announcement.php', 'icon' => 'feather icon-bell', 'description' => 'Publish academic notices', 'permissions' => ['communication.manage', 'communication.send'], 'core' => false],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'academic/profile.php', 'icon' => 'feather icon-user', 'description' => 'My academic staff profile', 'permissions' => [], 'core' => true],
		];
	}

	if ($portal === 'accountant') {
		return [
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'accountant', 'icon' => 'feather icon-monitor', 'description' => 'Finance overview', 'permissions' => [], 'core' => true],
			['key' => 'fees', 'label' => 'Fees & Finance', 'href' => 'accountant/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Payments and finance activity', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'receive_payment', 'label' => 'Receive Payment', 'href' => 'accountant/receive_payment', 'icon' => 'feather icon-plus-circle', 'description' => 'Quick fee collection entry', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true, 'routes' => ['accountant/add_payment']],
			['key' => 'fee_structure', 'label' => 'Fee Structure', 'href' => 'accountant/fee_structure', 'icon' => 'feather icon-sliders', 'description' => 'Fee setup and policies', 'permissions' => ['finance.manage'], 'core' => true],
			['key' => 'invoices', 'label' => 'Invoices', 'href' => 'accountant/invoices', 'icon' => 'feather icon-file-text', 'description' => 'Invoices and collections', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'expenses', 'label' => 'Expenses', 'href' => 'accountant/expenses', 'icon' => 'feather icon-shopping-cart', 'description' => 'Track school expenses', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'cashbook', 'label' => 'Cashbook & Banking', 'href' => 'accountant/cashbook', 'icon' => 'feather icon-briefcase', 'description' => 'Cash movement and balances', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'payroll', 'label' => 'Payroll', 'href' => 'accountant/payroll', 'icon' => 'feather icon-users', 'description' => 'Salary and deduction records', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'financial_reports', 'label' => 'Financial Reports', 'href' => 'accountant/financial_reports', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Collections, balances, and summaries', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'budgets', 'label' => 'Budgeting', 'href' => 'accountant/budgets', 'icon' => 'feather icon-pie-chart', 'description' => 'Budget planning and tracking', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'bursaries', 'label' => 'Bursaries', 'href' => 'accountant/bursaries', 'icon' => 'feather icon-heart', 'description' => 'Scholarship and sponsor support', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'mpesa', 'label' => 'M-Pesa', 'href' => 'accountant/mpesa', 'icon' => 'feather icon-smartphone', 'description' => 'M-Pesa reconciliation and totals', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'ledger', 'label' => 'General Ledger', 'href' => 'accountant/ledger', 'icon' => 'feather icon-book-open', 'description' => 'Ledger and journal entries', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'accountant/profile', 'icon' => 'feather icon-user', 'description' => 'My accountant profile', 'permissions' => [], 'core' => true],
		];
	}

	return app_teacher_portal_module_catalog();
}

function app_portal_visible_modules(PDO $conn, string $portal, string $staffId, string $level): array
{
	app_auto_allocate_normal_modules_for_portal($conn, $portal);

	$modules = app_portal_module_catalog($portal);
	$visible = [];

	foreach ($modules as $module) {
		$moduleKey = strtolower(trim((string)($module['key'] ?? '')));
		if ($moduleKey === 'attendance' && in_array($portal, ['teacher', 'academic'], true) && !app_can_manage_student_attendance($conn, $staffId, $level)) {
			continue;
		}

		$permissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
		if (empty($permissions)) {
			$visible[] = $module;
			continue;
		}

		foreach ($permissions as $permission) {
			if (app_has_permission($conn, $staffId, $level, $permission)) {
				if (app_staff_module_allocation_allows($conn, $portal, $staffId, $module)) {
					$visible[] = $module;
				}
				break;
			}
		}
	}

	return $visible;
}

function app_portal_allocated_modules(PDO $conn, string $portal, string $staffId, string $level): array
{
	return array_values(array_filter(app_portal_visible_modules($conn, $portal, $staffId, $level), static function (array $module): bool {
		return empty($module['core']);
	}));
}

function app_current_user_visible_portal_modules(string $portal): array
{
	static $cache = [];
	$portal = strtolower(trim($portal));
	$cacheKey = $portal . '|' . (string)($GLOBALS['account_id'] ?? '') . '|' . (string)($GLOBALS['level'] ?? '');
	if (isset($cache[$cacheKey])) {
		return $cache[$cacheKey];
	}

	$modules = app_portal_module_catalog($portal);
	if (!isset($GLOBALS['account_id'], $GLOBALS['level']) || (string)$GLOBALS['account_id'] === '' || (string)$GLOBALS['level'] === '') {
		$cache[$cacheKey] = $modules;
		return $cache[$cacheKey];
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$cache[$cacheKey] = app_portal_visible_modules($conn, $portal, (string)$GLOBALS['account_id'], (string)$GLOBALS['level']);
		return $cache[$cacheKey];
	} catch (Throwable $e) {
		$cache[$cacheKey] = $modules;
		return $cache[$cacheKey];
	}
}

function app_current_user_allocated_portal_modules(string $portal): array
{
	return array_values(array_filter(app_current_user_visible_portal_modules($portal), static function (array $module): bool {
		return empty($module['core']);
	}));
}

function app_teacher_portal_visible_modules(PDO $conn, string $staffId, string $level): array
{
	return app_portal_visible_modules($conn, 'teacher', $staffId, $level);
}

function app_teacher_portal_allocated_modules(PDO $conn, string $staffId, string $level): array
{
	return app_portal_allocated_modules($conn, 'teacher', $staffId, $level);
}

function app_is_attendance_admin_level(string $level): bool
{
	if (isset($GLOBALS['super_admin']) && $GLOBALS['super_admin'] === true) {
		return true;
	}

	return in_array((int)$level, [0, 9], true);
}

function app_staff_has_class_teacher_assignment(PDO $conn, int $staffId): bool
{
	if ($staffId < 1 || !function_exists('app_staff_class_teacher_ids')) {
		return false;
	}

	try {
		return count(app_staff_class_teacher_ids($conn, $staffId)) > 0;
	} catch (Throwable $e) {
		return false;
	}
}

function app_can_manage_student_attendance(PDO $conn, string $staffId, string $level): bool
{
	if (app_is_attendance_admin_level($level)) {
		return true;
	}

	return app_staff_has_class_teacher_assignment($conn, (int)$staffId);
}

function app_render_access_error_page(string $title, string $message, int $status = 403, array $details = []): void
{
	if (!headers_sent()) {
		http_response_code($status);
		header('Content-Type: text/html; charset=utf-8');
	}

	$appName = defined('APP_NAME') ? (string)APP_NAME : 'SRMS';
	$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '');
	$safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
	$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
	echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'
		. $safeTitle . ' | ' . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8')
		. '</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f5f7fb;color:#152033}.wrap{max-width:900px;margin:48px auto;padding:0 20px}.card{background:#fff;border:1px solid #d8e1ee;border-radius:18px;box-shadow:0 18px 50px rgba(20,32,51,.08);padding:28px}.pill{display:inline-block;padding:6px 12px;border-radius:999px;background:#fee2e2;color:#991b1b;font-weight:700;font-size:12px;letter-spacing:.04em;text-transform:uppercase}.title{margin:16px 0 10px;font-size:30px;line-height:1.2}.msg{font-size:16px;line-height:1.6}.meta{margin-top:18px;padding:14px 16px;border-radius:12px;background:#f8fafc;border:1px solid #e2e8f0}.meta code{font-family:monospace}.actions{margin-top:20px}.btn{display:inline-block;padding:10px 16px;border-radius:10px;background:#0f5fa8;color:#fff;text-decoration:none;font-weight:700}</style></head><body><div class="wrap"><div class="card"><span class="pill">HTTP '
		. (int)$status . '</span><h1 class="title">' . $safeTitle . '</h1><div class="msg">' . $safeMessage . '</div>';

	if ($requestUri !== '' || !empty($details)) {
		echo '<div class="meta">';
		if ($requestUri !== '') {
			echo '<div><strong>Request:</strong> <code>' . htmlspecialchars($requestUri, ENT_QUOTES, 'UTF-8') . '</code></div>';
		}
		foreach ($details as $key => $value) {
			if ($value === '' || $value === null) {
				continue;
			}
			echo '<div><strong>' . htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8') . ':</strong> <code>' . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') . '</code></div>';
		}
		echo '</div>';
	}

	echo '<div class="actions"><a class="btn" href="javascript:history.back()">Go Back</a></div></div></div></body></html>';
	exit;
}

function app_require_authentication(array $allowedLevels = [], array $permissions = []): void
{
	$res = (string)($GLOBALS['res'] ?? '0');
	if ($res !== '1') {
		$error = function_exists('app_get_auth_error') ? app_get_auth_error() : [
			'status' => 401,
			'title' => 'Login required',
			'message' => 'No active session was found for this request.',
			'details' => [],
		];
		app_render_access_error_page((string)$error['title'], (string)$error['message'], (int)$error['status'], (array)($error['details'] ?? []));
	}

	if (empty($allowedLevels) && empty($permissions)) {
		return;
	}

	$currentLevel = (string)($GLOBALS['level'] ?? '');
	if (!empty($allowedLevels) && in_array($currentLevel, $allowedLevels, true)) {
		return;
	}

	if (!empty($permissions)) {
		try {
			$conn = app_db();
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			foreach ($permissions as $permission) {
				if (app_has_permission($conn, (string)($GLOBALS['account_id'] ?? ''), $currentLevel, (string)$permission)) {
					return;
				}
			}
		} catch (Throwable $e) {
			app_render_access_error_page('Permission check failed', 'The system could not verify access permissions: ' . $e->getMessage(), 500);
		}
	}

	$detail = [];
	if (!empty($allowedLevels)) {
		$detail['allowed_levels'] = implode(', ', $allowedLevels);
	}
	if (!empty($permissions)) {
		$detail['accepted_permissions'] = implode(', ', $permissions);
	}
	$detail['current_level'] = $currentLevel;
	app_render_access_error_page('Access denied', 'This account is authenticated but does not have access to this page.', 403, $detail);
}

function app_require_permission(string $permission, string $redirect = '../'): void
{
	if (!isset($_SESSION)) {
		session_start();
	}

	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$error = function_exists('app_get_auth_error') ? app_get_auth_error() : [
			'status' => 401,
			'title' => 'Login required',
			'message' => 'No active session was found for this request.',
			'details' => [],
		];
		app_render_access_error_page((string)$error['title'], (string)$error['message'], (int)$error['status'], (array)($error['details'] ?? []));
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$allowed = app_has_permission($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level'], $permission);
		if (!$allowed) {
			app_render_access_error_page('Access denied', 'Missing required permission: ' . $permission . '.', 403, [
				'permission' => $permission,
				'current_level' => (string)$GLOBALS['level'],
				'account_id' => (string)$GLOBALS['account_id'],
			]);
		}
	} catch (Throwable $e) {
		app_render_access_error_page('Permission check failed', 'The system could not verify access permissions: ' . $e->getMessage(), 500, [
			'permission' => $permission,
		]);
	}
}

function app_require_any_permission(array $permissions): void
{
	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$error = function_exists('app_get_auth_error') ? app_get_auth_error() : [
			'status' => 401,
			'title' => 'Login required',
			'message' => 'No active session was found for this request.',
			'details' => [],
		];
		app_render_access_error_page((string)$error['title'], (string)$error['message'], (int)$error['status'], (array)($error['details'] ?? []));
	}

	$normalizedPermissions = array_values(array_filter(array_map(static function ($permission): string {
		return trim((string)$permission);
	}, $permissions), static function (string $permission): bool {
		return $permission !== '';
	}));
	if (empty($normalizedPermissions)) {
		return;
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		foreach ($normalizedPermissions as $permission) {
			if (app_has_permission($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level'], $permission)) {
				return;
			}
		}
		app_render_access_error_page('Access denied', 'None of the required permissions were granted for this page.', 403, [
			'accepted_permissions' => implode(', ', $normalizedPermissions),
			'current_level' => (string)$GLOBALS['level'],
			'account_id' => (string)$GLOBALS['account_id'],
		]);
	} catch (Throwable $e) {
		app_render_access_error_page('Permission check failed', 'The system could not verify the required permissions: ' . $e->getMessage(), 500, [
			'accepted_permissions' => implode(', ', $normalizedPermissions),
		]);
	}
}

function app_require_discipline_access(): void
{
	app_require_authentication(['2'], ['student.leadership.manage', 'students.manage']);
}

function app_request_route_from_portal(string $portal): string
{
	$portal = strtolower(trim($portal));
	if ($portal === '') {
		return '';
	}

	$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
	$path = is_string($path) ? trim($path, '/') : '';
	if ($path === '') {
		return '';
	}

	$segments = array_values(array_filter(array_map(static function ($segment): string {
		return strtolower(trim((string)$segment));
	}, explode('/', $path)), static function (string $segment): bool {
		return $segment !== '';
	}));

	$portalIndex = array_search($portal, $segments, true);
	if ($portalIndex === false) {
		return '';
	}

	$routeSegments = array_slice($segments, (int)$portalIndex);
	$route = implode('/', $routeSegments);
	if (str_ends_with($route, '.php')) {
		$route = substr($route, 0, -4);
	}

	return trim($route, '/');
}

function app_module_route_candidates(string $portal, array $module): array
{
	$portal = strtolower(trim($portal, '/'));
	$allRoutes = [];

	$moduleHref = (string)($module['href'] ?? '');
	if ($moduleHref !== '') {
		$allRoutes[] = $moduleHref;
	}

	foreach ((array)($module['routes'] ?? []) as $route) {
		$route = trim((string)$route);
		if ($route !== '') {
			$allRoutes[] = $route;
		}
	}

	foreach ((array)($module['active'] ?? []) as $route) {
		$route = trim((string)$route);
		if ($route === '') {
			continue;
		}
		if (strpos($route, '/') !== false) {
			$allRoutes[] = $route;
			continue;
		}
		if ($route === 'dashboard' || $route === 'index') {
			$allRoutes[] = $portal;
			$allRoutes[] = $portal . '/index';
			continue;
		}
		$allRoutes[] = $portal . '/' . $route;
	}

	$candidates = [];
	foreach ($allRoutes as $route) {
		$route = strtolower(trim((string)$route, '/'));
		if ($route === '') {
			continue;
		}
		if (str_ends_with($route, '.php')) {
			$route = substr($route, 0, -4);
		}
		$candidates[] = $route;
		if (strpos($route, '/') === false) {
			$candidates[] = $portal . '/' . $route;
		}
	}

	return array_values(array_unique(array_filter($candidates)));
}

function app_route_matches_module(string $requestRoute, string $portal, array $module): bool
{
	$requestRoute = strtolower(trim($requestRoute, '/'));
	$portal = strtolower(trim($portal, '/'));

	if ($requestRoute === '' || $portal === '') {
		return false;
	}

	foreach (app_module_route_candidates($portal, $module) as $candidate) {
		if ($candidate === $requestRoute) {
			return true;
		}
		if (str_starts_with($requestRoute, $candidate . '/')) {
			return true;
		}
		if ($candidate === $portal && $requestRoute === $portal . '/index') {
			return true;
		}
	}

	return false;
}

function app_enforce_portal_route_permission(PDO $conn, string $portal, string $staffId, string $level, string $redirect = '../'): void
{
	$portal = strtolower(trim($portal));
	if ($portal === '' || $staffId === '') {
		return;
	}

	$portalHome = $portal;
	if (!empty($GLOBALS['app_staff_portal_home']) && is_string($GLOBALS['app_staff_portal_home'])) {
		$portalHome = strtolower(trim((string)$GLOBALS['app_staff_portal_home'])) ?: $portal;
	} else {
		try {
			$portalHome = app_staff_login_portal($conn, (int)$staffId, $level) ?: $portal;
		} catch (Throwable $e) {
			$portalHome = $portal;
		}
	}

	$requestRoute = app_request_route_from_portal($portal);
	if ($requestRoute === '') {
		return;
	}

	if (strpos($requestRoute, '/core/') !== false || str_ends_with($requestRoute, '/core') || strpos($requestRoute, '/partials/') !== false || str_ends_with($requestRoute, '/partials') || strpos($requestRoute, '/api/') !== false || str_ends_with($requestRoute, '/api')) {
		return;
	}

	$modules = app_portal_module_catalog($portal);
	$matchedModule = false;
	foreach ($modules as $module) {
		if (!app_route_matches_module($requestRoute, $portal, $module)) {
			continue;
		}
		$matchedModule = true;

		$requiredPermissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
		if (empty($requiredPermissions)) {
			return;
		}

		if (app_current_user_has_any_permission($requiredPermissions)) {
			if (app_staff_module_allocation_allows($conn, $portal, $staffId, $module)) {
				return;
			}

			if (session_status() === PHP_SESSION_ACTIVE) {
				$_SESSION['reply'] = array(array('danger', 'Access denied: module not allocated to your role.'));
			}
			app_render_access_error_page('Module not allocated', 'This route matches a valid module, but that module is not allocated to your role.', 403, [
				'portal' => $portal,
				'route' => $requestRoute,
				'module' => (string)($module['key'] ?? ''),
				'portal_home' => $portalHome,
			]);
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION['reply'] = array(array('danger', 'Access denied: missing required permissions for this module.'));
		}
		app_render_access_error_page('Module permission denied', 'This route matches a valid module, but the logged-in account does not have the required permission for it.', 403, [
			'portal' => $portal,
			'route' => $requestRoute,
			'module' => (string)($module['key'] ?? ''),
			'required_permissions' => implode(', ', $requiredPermissions),
			'portal_home' => $portalHome,
		]);
	}

	if (!$matchedModule) {
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION['reply'] = array(array('danger', 'Access denied: this route is not registered as an authorized module.'));
		}
		app_render_access_error_page('Unregistered route', 'This route is not registered as an authorized module in the current portal catalog.', 403, [
			'portal' => $portal,
			'route' => $requestRoute,
			'portal_home' => $portalHome,
		]);
	}
}

function app_module_locked(PDO $conn, string $module): bool
{
	if (!app_table_exists($conn, 'tbl_module_locks')) {
		return false;
	}
	try {
		$stmt = $conn->prepare("SELECT locked FROM tbl_module_locks WHERE module = ? LIMIT 1");
		$stmt->execute([$module]);
		return (int)$stmt->fetchColumn() === 1;
	} catch (Throwable $e) {
		return false;
	}
}

function app_require_unlocked(string $module, string $redirect = '../'): void
{
	if (!isset($_SESSION)) {
		session_start();
	}

	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$error = function_exists('app_get_auth_error') ? app_get_auth_error() : [
			'status' => 401,
			'title' => 'Login required',
			'message' => 'No active session was found for this request.',
			'details' => [],
		];
		app_render_access_error_page((string)$error['title'], (string)$error['message'], (int)$error['status'], (array)($error['details'] ?? []));
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if (app_has_permission($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level'], 'system.manage')) {
			return;
		}
		if (app_module_locked($conn, $module)) {
			$_SESSION['reply'] = array (array("danger", "Module locked by Super Admin."));
			$redirect = app_normalize_redirect_target($redirect);
			header("location:$redirect");
			exit;
		}
	} catch (Throwable $e) {
		$_SESSION['reply'] = array (array("danger", "Module lock check failed."));
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}
}

function app_normalize_redirect_target(string $redirect): string
{
	$redirect = trim($redirect);
	if ($redirect === '' || $redirect === '../' || str_starts_with($redirect, '../') || str_starts_with($redirect, './') || str_starts_with($redirect, '/') || preg_match('/^https?:/i', $redirect)) {
		return $redirect === '' ? '../' : $redirect;
	}

	$scriptName = (string)($_SERVER['SCRIPT_NAME'] ?? '');
	$dir = trim((string)dirname($scriptName), '/');
	if ($dir === '') {
		return $redirect;
	}

	return '../' . ltrim($redirect, '/');
}
