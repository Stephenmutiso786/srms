<?php

function app_default_permissions_for_level(int $level): array
{
	if (in_array($level, [0, 1, 9], true)) {
		return ['*'];
	}

	switch ($level) {
		case 2:
			return ['attendance.manage','marks.enter','report.view'];
		case 5:
			return ['finance.manage','finance.view'];
		case 6:
			return ['staff.manage'];
		case 7:
			return ['transport.manage'];
		case 8:
			return ['library.manage'];
		default:
			return [];
	}
}

function app_get_permissions(PDO $conn, string $staffId, string $level): array
{
	if (isset($GLOBALS['super_admin']) && $GLOBALS['super_admin'] === true) {
		return ['*'];
	}
	$levelInt = (int)$level;
	$defaults = app_default_permissions_for_level($levelInt);
	if (in_array('*', $defaults, true)) {
		return ['*'];
	}

	if (function_exists('app_ensure_school_roles')) {
		try {
			app_ensure_school_roles($conn);
		} catch (Throwable $e) {
			// Continue with whatever permissions are available.
		}
	}

	if (!app_table_exists($conn, 'tbl_user_roles') || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_permissions')) {
		return $defaults;
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
			return $defaults;
		}
		return array_values(array_unique(array_map('strval', $rows)));
	} catch (Throwable $e) {
		return $defaults;
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

function app_teacher_portal_module_catalog(): array
{
	return [
		['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'teacher', 'icon' => 'feather icon-monitor', 'description' => 'Overview and quick actions', 'permissions' => [], 'core' => true],
		['key' => 'terms', 'label' => 'Academic Terms', 'href' => 'teacher/terms', 'icon' => 'feather icon-folder', 'description' => 'View term structure', 'permissions' => [], 'core' => true],
		['key' => 'attendance', 'label' => 'Attendance', 'href' => 'teacher/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Class attendance and monitoring', 'permissions' => ['attendance.manage'], 'core' => true],
		['key' => 'marks_entry', 'label' => 'Marks Entry', 'href' => 'teacher/exam_marks_entry', 'icon' => 'feather icon-edit-3', 'description' => 'Enter exam and CBC marks', 'permissions' => ['marks.enter'], 'core' => true],
		['key' => 'results', 'label' => 'Results', 'href' => 'teacher/manage_results', 'icon' => 'feather icon-graph', 'description' => 'Review and publish results', 'permissions' => ['report.view', 'report.generate', 'marks.review', 'results.approve'], 'core' => true],
		['key' => 'discipline', 'label' => 'Discipline', 'href' => 'teacher/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Learner welfare and discipline', 'permissions' => ['student.leadership.manage'], 'core' => true],
		['key' => 'students', 'label' => 'Students', 'href' => 'teacher/students', 'icon' => 'feather icon-users', 'description' => 'Student directory and class lists', 'permissions' => ['students.manage', 'report.view'], 'core' => true],
		['key' => 'staff_attendance', 'label' => 'Staff Attendance', 'href' => 'teacher/staff_attendance', 'icon' => 'feather icon-clock', 'description' => 'Monitor staff attendance', 'permissions' => ['attendance.manage'], 'core' => true],
		['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'teacher/exam_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable planning', 'permissions' => ['timetable.manage', 'exams.manage'], 'core' => false],
		['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'teacher/grading-system', 'icon' => 'feather icon-award', 'description' => 'Grading and assessment setup', 'permissions' => ['exams.manage', 'academic.manage'], 'core' => false],
		['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'teacher/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Digital lessons and content', 'permissions' => ['academic.manage'], 'core' => false],
		['key' => 'subject_combinations', 'label' => 'Subject Combinations', 'href' => 'teacher/combinations', 'icon' => 'feather icon-book-open', 'description' => 'Subject allocation and combinations', 'permissions' => ['teacher.allocate', 'academic.manage'], 'core' => false],
		['key' => 'roles', 'label' => 'Roles', 'href' => 'teacher/roles', 'icon' => 'feather icon-shield', 'description' => 'Assign staff roles', 'permissions' => ['staff.manage'], 'core' => false],
		['key' => 'how_system_works', 'label' => 'How The System Works', 'href' => 'teacher/how_system_works', 'icon' => 'feather icon-help-circle', 'description' => 'Help and guidance', 'permissions' => [], 'core' => true],
		['key' => 'profile', 'label' => 'Profile', 'href' => 'teacher/profile', 'icon' => 'feather icon-user', 'description' => 'My staff profile', 'permissions' => [], 'core' => true],
	];
}

function app_portal_module_catalog(string $portal): array
{
	$portal = strtolower(trim($portal));
	if ($portal === 'student') {
		return [
			['key' => 'attendance', 'label' => 'Attendance', 'href' => 'student/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Attendance view', 'permissions' => [], 'core' => true, 'active' => ['attendance']],
			['key' => 'certificates', 'label' => 'Certificates', 'href' => 'student/certificates', 'icon' => 'feather icon-award', 'description' => 'Download certificates', 'permissions' => [], 'core' => true, 'active' => ['certificates']],
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'student', 'icon' => 'feather icon-monitor', 'description' => 'Student overview', 'permissions' => [], 'core' => true, 'active' => ['index', 'dashboard']],
			['key' => 'discipline', 'label' => 'Discipline', 'href' => 'student/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Discipline information', 'permissions' => [], 'core' => true, 'active' => ['discipline']],
			['key' => 'division_system', 'label' => 'Division System', 'href' => 'student/division-system', 'icon' => 'feather icon-layers', 'description' => 'Division guidance', 'permissions' => [], 'core' => true, 'active' => ['division-system']],
			['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'student/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Lessons and content', 'permissions' => [], 'core' => true, 'active' => ['elearning']],
			['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'student/exam_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable', 'permissions' => [], 'core' => true, 'active' => ['exam_timetable']],
			['key' => 'fees', 'label' => 'Fees', 'href' => 'student/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Fee statements', 'permissions' => ['finance.view'], 'core' => true, 'active' => ['fees']],
			['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'student/grading-system', 'icon' => 'feather icon-award', 'description' => 'Grading rules', 'permissions' => [], 'core' => true, 'active' => ['grading-system']],
			['key' => 'leadership', 'label' => 'Leadership', 'href' => 'student/leadership', 'icon' => 'feather icon-users', 'description' => 'Student leadership', 'permissions' => ['student.leadership.view'], 'core' => false, 'active' => ['leadership']],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'student/view', 'icon' => 'feather icon-user', 'description' => 'My profile', 'permissions' => [], 'core' => true, 'active' => ['view', 'profile', 'id_card']],
			['key' => 'ranking', 'label' => 'Ranking', 'href' => 'student/ranking', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Class ranking', 'permissions' => ['report.view'], 'core' => false, 'active' => ['ranking']],
			['key' => 'report_card', 'label' => 'Report Card', 'href' => 'student/report_card', 'icon' => 'feather icon-file-text', 'description' => 'Report card and results', 'permissions' => ['report.view'], 'core' => true, 'active' => ['report_card']],
			['key' => 'results', 'label' => 'Results', 'href' => 'student/results', 'icon' => 'feather icon-file-text', 'description' => 'My result summary', 'permissions' => ['report.view'], 'core' => true, 'active' => ['results']],
			['key' => 'settings', 'label' => 'Settings', 'href' => 'student/settings', 'icon' => 'feather icon-settings', 'description' => 'Account settings', 'permissions' => [], 'core' => true, 'active' => ['settings']],
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
			['key' => 'report_card', 'label' => 'Report Card', 'href' => 'parent/report_card', 'icon' => 'feather icon-file-text', 'description' => 'Report cards and results', 'permissions' => ['report.view'], 'core' => true, 'active' => ['report_card']],
		];
	}

	if ($portal === 'academic') {
		return [
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'academic', 'icon' => 'feather icon-monitor', 'description' => 'Academic overview', 'permissions' => [], 'core' => true],
			['key' => 'terms', 'label' => 'Academic Terms', 'href' => 'academic/terms', 'icon' => 'feather icon-folder', 'description' => 'Manage academic terms', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'classes', 'label' => 'Classes', 'href' => 'academic/classes', 'icon' => 'feather icon-home', 'description' => 'Class setup and structure', 'permissions' => ['classes.assign', 'academic.manage'], 'core' => true],
			['key' => 'subjects', 'label' => 'Subjects', 'href' => 'academic/subjects', 'icon' => 'feather icon-book', 'description' => 'Subject setup', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'combinations', 'label' => 'Subject Combinations', 'href' => 'academic/combinations', 'icon' => 'feather icon-book-open', 'description' => 'Teacher-subject allocation', 'permissions' => ['teacher.allocate', 'academic.manage'], 'core' => true],
			['key' => 'students', 'label' => 'Student Promotion', 'href' => 'academic/promote_students', 'icon' => 'feather icon-users', 'description' => 'Promote and manage learners', 'permissions' => ['students.manage', 'academic.manage'], 'core' => true],
			['key' => 'results_manage', 'label' => 'Manage Results', 'href' => 'academic/manage_results', 'icon' => 'feather icon-file-text', 'description' => 'Results entry and approval', 'permissions' => ['marks.enter', 'marks.review', 'results.approve'], 'core' => true],
			['key' => 'individual_results', 'label' => 'Individual Results', 'href' => 'academic/individual_results', 'icon' => 'feather icon-user-check', 'description' => 'Single-student result review', 'permissions' => ['report.view', 'report.generate'], 'core' => true],
			['key' => 'report_tool', 'label' => 'Report Tool', 'href' => 'academic/report', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Class report analysis', 'permissions' => ['report.generate', 'report.view'], 'core' => true],
			['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'academic/grading-system', 'icon' => 'feather icon-award', 'description' => 'Grade scale and grading rules', 'permissions' => ['exams.manage', 'academic.manage'], 'core' => true],
			['key' => 'division_system', 'label' => 'Division System', 'href' => 'academic/division-system', 'icon' => 'feather icon-layers', 'description' => 'Division and performance bands', 'permissions' => ['academic.manage', 'report.generate'], 'core' => true],
			['key' => 'announcements', 'label' => 'Announcements', 'href' => 'academic/announcement', 'icon' => 'feather icon-bell', 'description' => 'Publish academic notices', 'permissions' => ['communication.manage', 'communication.send'], 'core' => false],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'academic/profile', 'icon' => 'feather icon-user', 'description' => 'My academic staff profile', 'permissions' => [], 'core' => true],
		];
	}

	if ($portal === 'accountant') {
		return [
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'accountant', 'icon' => 'feather icon-monitor', 'description' => 'Finance overview', 'permissions' => [], 'core' => true],
			['key' => 'fees', 'label' => 'Fees & Finance', 'href' => 'accountant/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Payments and finance activity', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'fee_structure', 'label' => 'Fee Structure', 'href' => 'accountant/fee_structure', 'icon' => 'feather icon-sliders', 'description' => 'Fee setup and policies', 'permissions' => ['finance.manage'], 'core' => true],
			['key' => 'invoices', 'label' => 'Invoices', 'href' => 'accountant/invoices', 'icon' => 'feather icon-file-text', 'description' => 'Invoices and collections', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true],
			['key' => 'profile', 'label' => 'Profile', 'href' => 'accountant/profile', 'icon' => 'feather icon-user', 'description' => 'My accountant profile', 'permissions' => [], 'core' => true],
		];
	}

	return app_teacher_portal_module_catalog();
}

function app_portal_visible_modules(PDO $conn, string $portal, string $staffId, string $level): array
{
	$modules = app_portal_module_catalog($portal);
	$visible = [];

	foreach ($modules as $module) {
		$permissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
		if (empty($permissions)) {
			$visible[] = $module;
			continue;
		}

		foreach ($permissions as $permission) {
			if (app_has_permission($conn, $staffId, $level, $permission)) {
				$visible[] = $module;
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
	if (isset($cache[$portal])) {
		return $cache[$portal];
	}

	$modules = app_portal_module_catalog($portal);
	$permissions = app_current_user_permission_codes();
	if (in_array('*', $permissions, true)) {
		$cache[$portal] = $modules;
		return $cache[$portal];
	}

	$visible = [];
	foreach ($modules as $module) {
		$modulePermissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
		if (empty($modulePermissions)) {
			$visible[] = $module;
			continue;
		}

		foreach ($modulePermissions as $permission) {
			if (in_array($permission, $permissions, true)) {
				$visible[] = $module;
				break;
			}
		}
	}

	$cache[$portal] = $visible;
	return $cache[$portal];
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

function app_require_permission(string $permission, string $redirect = '../'): void
{
	if (!isset($_SESSION)) {
		session_start();
	}

	if (!isset($GLOBALS['account_id']) || !isset($GLOBALS['level'])) {
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}

	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$allowed = app_has_permission($conn, (string)$GLOBALS['account_id'], (string)$GLOBALS['level'], $permission);
		if (!$allowed) {
			$_SESSION['reply'] = array (array("danger", "Access denied: missing permission ($permission)."));
			$redirect = app_normalize_redirect_target($redirect);
			header("location:$redirect");
			exit;
		}
	} catch (Throwable $e) {
		$_SESSION['reply'] = array (array("danger", "Permission check failed."));
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
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
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
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
