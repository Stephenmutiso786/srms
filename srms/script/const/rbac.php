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
				'finance.manage', 'finance.view', 'communication.manage', 'communication.send',
				'bom.manage', 'bom.view', 'sms.wallet.manage'
			];
		case 1:
			return [
				'academic.manage', 'teacher.allocate', 'classes.assign', 'timetable.manage',
				'attendance.manage', 'exams.manage', 'marks.review', 'results.approve',
				'report.generate', 'report.view', 'students.manage', 'communication.manage',
				'communication.send'
			];
		case 2:
			return ['attendance.manage', 'marks.enter', 'report.view', 'communication.send'];
		case 3:
			return ['report.view', 'finance.view', 'student.leadership.view'];
		case 4:
			return ['report.view', 'finance.view'];
		case 5:
			return ['finance.manage', 'finance.view', 'sms.wallet.manage'];
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
		['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'teacher', 'icon' => 'feather icon-monitor', 'description' => 'Overview and quick actions', 'permissions' => [], 'core' => true, 'routes' => ['teacher/index']],
		['key' => 'terms', 'label' => 'Academic Terms', 'href' => 'teacher/terms', 'icon' => 'feather icon-folder', 'description' => 'View term structure', 'permissions' => [], 'core' => true],
		['key' => 'attendance', 'label' => 'Attendance', 'href' => 'teacher/attendance', 'icon' => 'feather icon-check-square', 'description' => 'Class attendance and monitoring', 'permissions' => ['attendance.manage'], 'core' => true, 'routes' => ['teacher/attendance_session']],
		['key' => 'marks_entry', 'label' => 'Marks Entry', 'href' => 'teacher/exam_marks_entry', 'icon' => 'feather icon-edit-3', 'description' => 'Enter exam and CBC marks', 'permissions' => ['marks.enter'], 'core' => true, 'routes' => ['teacher/marks_entry', 'teacher/exam_marks_table', 'teacher/cbc_entry', 'teacher/import_results']],
		['key' => 'results', 'label' => 'Results', 'href' => 'teacher/manage_results', 'icon' => 'feather icon-graph', 'description' => 'Review and publish results', 'permissions' => ['report.view', 'report.generate', 'marks.review', 'results.approve'], 'core' => true, 'routes' => ['teacher/results', 'teacher/report_card', 'teacher/class_report', 'teacher/published_analytics', 'teacher/print_mark_sheet', 'teacher/report_card_pdf']],
		['key' => 'discipline', 'label' => 'Discipline', 'href' => 'teacher/discipline', 'icon' => 'feather icon-alert-triangle', 'description' => 'Learner welfare and discipline', 'permissions' => ['student.leadership.manage'], 'core' => true],
		['key' => 'students', 'label' => 'Students', 'href' => 'teacher/students', 'icon' => 'feather icon-users', 'description' => 'Student directory and class lists', 'permissions' => ['students.manage', 'report.view'], 'core' => true, 'routes' => ['teacher/list_students', 'teacher/export_students', 'teacher/certificates']],
		['key' => 'staff_attendance', 'label' => 'Staff Attendance', 'href' => 'teacher/staff_attendance', 'icon' => 'feather icon-clock', 'description' => 'Monitor staff attendance', 'permissions' => ['attendance.manage'], 'core' => true],
		['key' => 'exam_timetable', 'label' => 'Exam Timetable', 'href' => 'teacher/exam_timetable', 'icon' => 'feather icon-calendar', 'description' => 'Exam timetable planning', 'permissions' => ['timetable.manage', 'exams.manage'], 'core' => false],
		['key' => 'grading_system', 'label' => 'Grading System', 'href' => 'teacher/grading-system', 'icon' => 'feather icon-award', 'description' => 'Grading and assessment setup', 'permissions' => ['exams.manage', 'academic.manage'], 'core' => false, 'routes' => ['teacher/division-system']],
		['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'teacher/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Digital lessons and content', 'permissions' => ['academic.manage'], 'core' => false],
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
			['key' => 'teachers', 'label' => 'Teachers', 'href' => 'admin/teachers', 'icon' => 'feather icon-user', 'description' => 'Teacher records and access', 'permissions' => ['staff.manage'], 'core' => true],
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
			['key' => 'fees', 'label' => 'Fees & Finance', 'href' => 'admin/fees', 'icon' => 'feather icon-credit-card', 'description' => 'Fee and finance tools', 'permissions' => ['finance.manage', 'finance.view'], 'core' => true, 'routes' => ['admin/financial_reports', 'admin/installment_plans', 'admin/fee_structure', 'admin/invoices']],
			['key' => 'import_export', 'label' => 'Import / Export', 'href' => 'admin/import_export', 'icon' => 'feather icon-upload-cloud', 'description' => 'Data import and export', 'permissions' => ['system.manage'], 'core' => false],
			['key' => 'communication', 'label' => 'Communication', 'href' => 'admin/communication', 'icon' => 'feather icon-message-circle', 'description' => 'Announcements and messages', 'permissions' => ['communication.manage'], 'core' => true],
			['key' => 'sms_topup', 'label' => 'SMS Tokens', 'href' => 'admin/sms_topup', 'icon' => 'feather icon-credit-card', 'description' => 'SMS wallet', 'permissions' => ['communication.manage', 'finance.manage'], 'core' => false],
			['key' => 'elearning', 'label' => 'E-Learning', 'href' => 'admin/elearning', 'icon' => 'feather icon-book-open', 'description' => 'Digital learning', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'feedback', 'label' => 'AI & Feedback', 'href' => 'admin/feedback', 'icon' => 'feather icon-message-square', 'description' => 'AI feedback tools', 'permissions' => ['academic.manage'], 'core' => false],
			['key' => 'library', 'label' => 'Library', 'href' => 'admin/library', 'icon' => 'feather icon-book', 'description' => 'Library inventory', 'permissions' => ['library.manage'], 'core' => false],
			['key' => 'inventory', 'label' => 'Inventory', 'href' => 'admin/inventory', 'icon' => 'feather icon-box', 'description' => 'Asset inventory', 'permissions' => ['inventory.manage'], 'core' => false],
			['key' => 'transport', 'label' => 'Transport', 'href' => 'admin/transport', 'icon' => 'feather icon-truck', 'description' => 'Fleet management', 'permissions' => ['transport.manage'], 'core' => false],
			['key' => 'exams', 'label' => 'Exams', 'href' => 'admin/exams', 'icon' => 'feather icon-file-text', 'description' => 'Exam setup', 'permissions' => ['exams.manage'], 'core' => true, 'routes' => ['admin/edit_exam']],
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
			['key' => 'dashboard', 'label' => 'Dashboard', 'href' => 'academic', 'icon' => 'feather icon-monitor', 'description' => 'Academic overview', 'permissions' => [], 'core' => true],
			['key' => 'terms', 'label' => 'Academic Terms', 'href' => 'academic/terms', 'icon' => 'feather icon-folder', 'description' => 'Manage academic terms', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'classes', 'label' => 'Classes', 'href' => 'academic/classes', 'icon' => 'feather icon-home', 'description' => 'Class setup and structure', 'permissions' => ['classes.assign', 'academic.manage'], 'core' => true],
			['key' => 'subjects', 'label' => 'Subjects', 'href' => 'academic/subjects', 'icon' => 'feather icon-book', 'description' => 'Subject setup', 'permissions' => ['academic.manage'], 'core' => true],
			['key' => 'combinations', 'label' => 'Subject Combinations', 'href' => 'academic/combinations', 'icon' => 'feather icon-book-open', 'description' => 'Teacher-subject allocation', 'permissions' => ['teacher.allocate', 'academic.manage'], 'core' => true],
			['key' => 'students', 'label' => 'Student Promotion', 'href' => 'academic/promote_students', 'icon' => 'feather icon-users', 'description' => 'Promote and manage learners', 'permissions' => ['students.manage', 'academic.manage'], 'core' => true],
			['key' => 'results_manage', 'label' => 'Manage Results', 'href' => 'academic/manage_results', 'icon' => 'feather icon-file-text', 'description' => 'Results entry and approval', 'permissions' => ['marks.enter', 'marks.review', 'results.approve'], 'core' => true, 'routes' => ['academic/bulk_results', 'academic/single_results']],
			['key' => 'individual_results', 'label' => 'Individual Results', 'href' => 'academic/individual_results', 'icon' => 'feather icon-user-check', 'description' => 'Single-student result review', 'permissions' => ['report.view', 'report.generate'], 'core' => true],
			['key' => 'report_tool', 'label' => 'Report Tool', 'href' => 'academic/report', 'icon' => 'feather icon-bar-chart-2', 'description' => 'Class report analysis', 'permissions' => ['report.generate', 'report.view'], 'core' => true, 'routes' => ['academic/save_pdf', 'academic/save_report']],
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
			return;
		}

		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION['reply'] = array(array('danger', 'Access denied: missing required permissions for this module.'));
		}
		$redirect = app_normalize_redirect_target($redirect);
		header("location:$redirect");
		exit;
	}

	if (!$matchedModule) {
		if (session_status() === PHP_SESSION_ACTIVE) {
			$_SESSION['reply'] = array(array('danger', 'Access denied: this route is not registered as an authorized module.'));
		}
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
