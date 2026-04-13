<?php
// Prefer environment variables for cloud hosting (Render, etc.)
$driverEnv = strtolower(getenv('DB_DRIVER') ?: '');
$dsnEnv = getenv('DB_DSN') ?: '';
$driverFromDsn = '';
if ($dsnEnv !== '') {
	if (stripos($dsnEnv, 'pgsql:') === 0) {
		$driverFromDsn = 'pgsql';
	} elseif (stripos($dsnEnv, 'mysql:') === 0) {
		$driverFromDsn = 'mysql';
	}
}
DEFINE('DBDriver', $driverEnv !== '' ? $driverEnv : ($driverFromDsn !== '' ? $driverFromDsn : 'mysql')); // mysql | pgsql
DEFINE('DBHost', getenv('DB_HOST') ?: 'localhost');
DEFINE('DBPort', getenv('DB_PORT') ?: '');
DEFINE('DBUser', getenv('DB_USER') ?: 'root');
DEFINE('DBPass', getenv('DB_PASS') ?: '');
DEFINE('DBName', getenv('DB_NAME') ?: 'srms');
DEFINE('DBCharset', getenv('DB_CHARSET') ?: 'utf8mb4');
DEFINE('DBCollation', getenv('DB_COLLATION') ?: 'utf8_general_ci');
DEFINE('DBPrefix', getenv('DB_PREFIX') ?: '');

// Canonical DSN (the rest of the app should use this).
if (getenv('DB_DSN')) {
	DEFINE('DB_DSN', getenv('DB_DSN'));
} else {
	$portPart = DBPort !== '' ? ';port='.DBPort : '';
	$sslMode = strtoupper(trim(getenv('DB_SSL_MODE') ?: ''));

	if (DBDriver === 'pgsql') {
		$sslPart = '';
		// For Postgres, SSL is configured via the DSN string.
		// Most managed Postgres providers only need sslmode=require.
		if ($sslMode === 'REQUIRED') {
			$sslPart = ';sslmode=require';
		}
		DEFINE('DB_DSN', 'pgsql:host='.DBHost.$portPart.';dbname='.DBName.$sslPart);
	} else {
		DEFINE('DB_DSN', 'mysql:host='.DBHost.$portPart.';dbname='.DBName.';charset='.DBCharset);
	}
}

// App branding
DEFINE('APP_NAME', getenv('APP_NAME') ?: 'Elimu Hub');
DEFINE('APP_TAGLINE', getenv('APP_TAGLINE') ?: 'Student Results Management System');
DEFINE('APP_URL', rtrim(getenv('APP_URL') ?: '', '/'));
DEFINE('APP_SECRET', getenv('APP_SECRET') ?: '');
DEFINE('REPORT_PRINCIPAL_SIGN', getenv('REPORT_PRINCIPAL_SIGN') ?: '');
DEFINE('REPORT_TEACHER_SIGN', getenv('REPORT_TEACHER_SIGN') ?: '');
DEFINE('REPORT_SCHOOL_STAMP', getenv('REPORT_SCHOOL_STAMP') ?: '');

function app_db(): PDO
{
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	];

	if (DBDriver === 'mysql') {
		// TLS (Aiven MySQL and many managed DBs require SSL).
		// Prefer pasting the PEM into an env var on Render (DB_SSL_CA_PEM).
		$sslMode = strtoupper(trim(getenv('DB_SSL_MODE') ?: ''));
		$caPem = getenv('DB_SSL_CA_PEM') ?: '';
		$caPath = getenv('DB_SSL_CA') ?: '';
		if ($caPem !== '') {
			$tmpPath = '/tmp/db-ca.pem';
			// Render env vars can include newlines; write once per container.
			if (!is_file($tmpPath) || file_get_contents($tmpPath) !== $caPem) {
				file_put_contents($tmpPath, $caPem);
			}
			$caPath = $tmpPath;
		}

		// If you set DB_SSL_MODE=REQUIRED, we enable TLS without requiring a CA file.
		// If a CA is provided we verify the server certificate by default.
		if ($sslMode === 'REQUIRED' && $caPath === '') {
			$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
		}

		if ($caPath !== '') {
			$options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
			// Default to verifying the server cert unless explicitly disabled.
			$verify = getenv('DB_SSL_VERIFY');
			if ($verify === '0' || strtolower($verify) === 'false') {
				$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
			} else {
				$options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
			}
		}
	}

	$pdo = new PDO(DB_DSN, DBUser, DBPass, $options);
	return $pdo;
}

function app_cookie_secure(): bool
{
	$proto = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? $_SERVER['REQUEST_SCHEME'] ?? ''));
	$https = strtolower((string)($_SERVER['HTTPS'] ?? ''));
	if ($proto === 'https' || $https === 'on' || $https === '1') {
		return true;
	}
	return stripos(APP_URL, 'https://') === 0;
}

function app_request_is_api(): bool
{
	$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
	return strpos($uri, '/api/') !== false;
}

function app_session_enforce_ip(): bool
{
	$raw = strtolower(trim((string)(getenv('SESSION_STRICT_IP') ?: '')));
	if ($raw !== '') {
		return !in_array($raw, ['0', 'false', 'no', 'off'], true);
	}
	return !app_request_is_api();
}

function app_issue_auth_cookies(string $level, string $sessionId, bool $crossSite = false, int $minutes = 4320): void
{
	$expires = time() + (60 * max(1, $minutes));
	$options = [
		'expires' => $expires,
		'path' => '/',
		'secure' => app_cookie_secure(),
		'httponly' => true,
		'samesite' => $crossSite ? 'None' : 'Lax',
	];
	if ($crossSite && !$options['secure']) {
		$options['secure'] = true;
	}
	setcookie('__SRMS__logged', (string)$level, $options);
	setcookie('__SRMS__key', (string)$sessionId, $options);
}

function app_clear_auth_cookies(bool $crossSite = false): void
{
	$options = [
		'expires' => time() - 3600,
		'path' => '/',
		'secure' => app_cookie_secure() || $crossSite,
		'httponly' => true,
		'samesite' => $crossSite ? 'None' : 'Lax',
	];
	setcookie('__SRMS__logged', '', $options);
	setcookie('__SRMS__key', '', $options);
}

function app_generate_school_id(PDO $conn, string $prefix, int $year, string $table): string
{
	$prefix = strtoupper(trim($prefix));
	$year = $year > 0 ? $year : (int)date('Y');
	$like = $prefix.'-'.$year.'-%';

	do {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM {$table} WHERE school_id LIKE ?");
		$stmt->execute([$like]);
		$count = (int)$stmt->fetchColumn();
		$number = $count + 1;
		$schoolId = sprintf('%s-%d-%04d', $prefix, $year, $number);

		$stmt = $conn->prepare("SELECT 1 FROM {$table} WHERE school_id = ? LIMIT 1");
		$stmt->execute([$schoolId]);
		$exists = $stmt->fetchColumn();
	} while ($exists);

	return $schoolId;
}

function app_staff_prefix(string $level): string
{
	if ($level === '0' || $level === '1') { return 'ADM'; }
	if ($level === '2') { return 'TCH'; }
	if ($level === '5') { return 'ACC'; }
	return 'STF';
}

function app_level_title_label(int $level): string
{
	switch ($level) {
		case 0:
			return 'Headteacher';
		case 1:
			return 'Deputy Headteacher';
		case 2:
			return 'Teacher';
		case 5:
			return 'Accountant';
		case 6:
			return 'HR Manager';
		case 7:
			return 'Transport Manager';
		case 8:
			return 'Librarian';
		case 9:
			return 'Super Admin';
		default:
			return 'Staff';
	}
}

function app_ensure_school_roles(PDO $conn): void
{
	if (!app_table_exists($conn, 'tbl_roles') || !app_table_exists($conn, 'tbl_permissions') || !app_table_exists($conn, 'tbl_role_permissions')) {
		return;
	}
	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');

	$permissions = [
		['system.manage', 'Manage system settings'],
		['audit.view', 'View audit logs'],
		['students.manage', 'Manage students'],
		['staff.manage', 'Manage staff and role assignment'],
		['teacher.allocate', 'Allocate teachers to subjects/classes'],
		['student.leadership.manage', 'Manage student leadership assignments and reports'],
		['bom.manage', 'Manage Board of Management records and meetings'],
		['bom.view', 'View Board of Management records'],
		['attendance.manage', 'Manage attendance'],
		['exams.manage', 'Manage exams and timetable'],
		['marks.enter', 'Enter marks and assessments'],
		['results.approve', 'Approve results'],
		['results.lock', 'Lock results'],
		['results.unlock', 'Unlock results'],
		['report.generate', 'Generate report cards'],
		['report.view', 'View report cards'],
		['finance.manage', 'Manage fees and payments'],
		['finance.view', 'View finance reports'],
		['communication.manage', 'Manage communication'],
		['transport.manage', 'Manage transport'],
		['library.manage', 'Manage library'],
		['inventory.manage', 'Manage inventory'],
	];

	foreach ($permissions as $perm) {
		if ($isPgsql) {
			$stmt = $conn->prepare("INSERT INTO tbl_permissions (code, description) VALUES (?, ?) ON CONFLICT (code) DO UPDATE SET description = EXCLUDED.description");
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_permissions (code, description) VALUES (?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)");
		}
		$stmt->execute([$perm[0], $perm[1]]);
	}

	$roles = [
		['Headteacher', 100, 'Overall in charge of school operations, policy, staff and performance.', [
			'system.manage','audit.view','students.manage','staff.manage','attendance.manage','exams.manage','marks.enter',
			'results.approve','results.lock','results.unlock','report.generate','report.view','teacher.allocate','student.leadership.manage','bom.manage','bom.view','finance.manage','finance.view',
			'communication.manage','transport.manage','library.manage','inventory.manage'
		]],
		['Deputy Headteacher', 95, 'Assists the headteacher and runs day-to-day academics, discipline, timetable and attendance.', [
			'audit.view','students.manage','staff.manage','attendance.manage','exams.manage','marks.enter','results.approve',
			'results.lock','results.unlock','report.generate','report.view','teacher.allocate','student.leadership.manage','bom.view','finance.view','communication.manage','transport.manage',
			'library.manage','inventory.manage'
		]],
		['HOD Academics', 90, 'Oversees teaching and learning and syllabus coverage.', ['attendance.manage','exams.manage','marks.enter','results.approve','report.generate','report.view','teacher.allocate','communication.manage']],
		['HOD Exams', 89, 'Leads exam planning, marking standards, CBC-aligned assessment and grading workflows.', ['exams.manage','marks.enter','results.approve','results.lock','results.unlock','report.generate','report.view','teacher.allocate','communication.manage']],
		['HOD Languages', 84, 'Leads language department instruction and quality assurance.', ['marks.enter','report.view','communication.manage']],
		['HOD Sciences', 84, 'Leads science department instruction and quality assurance.', ['marks.enter','report.view','communication.manage']],
		['HOD Mathematics', 84, 'Leads mathematics department instruction and quality assurance.', ['marks.enter','report.view','communication.manage']],
		['HOD Creative Arts / Co-curricular', 84, 'Leads arts and co-curricular teaching programs.', ['marks.enter','report.view','communication.manage']],
		['Class Teacher', 75, 'In charge of class performance, discipline and parent communication.', ['attendance.manage','marks.enter','report.view','communication.manage']],
		['Subject Teacher', 70, 'Teaches subjects across classes and records continuous assessment.', ['marks.enter','report.view']],
		['Examination Officer', 88, 'Supports HOD Exams in recording marks, report generation and analysis.', ['exams.manage','marks.enter','report.generate','report.view','communication.manage']],
		['Bursar / Accounts Clerk', 80, 'Handles fees, payments and school financial records.', ['finance.manage','finance.view']],
		['BOM Chairperson', 82, 'Heads Board of Management and oversees governance meetings and decisions.', ['bom.manage','bom.view','finance.view','report.view','audit.view']],
		['BOM Treasurer', 79, 'Oversees BOM financial approvals and controls.', ['bom.manage','bom.view','finance.view','audit.view']],
		['BOM Member', 74, 'Participates in BOM meetings and governance decisions.', ['bom.view']],
		['Secretary / Office Admin', 65, 'Manages communication, records and office administration.', ['students.manage','communication.manage','report.view']],
		['ICT Teacher / System Admin', 92, 'Maintains school digital systems, e-learning and technical operations.', ['system.manage','audit.view','exams.manage','report.generate','report.view','communication.manage']],
		['Guidance and Counselling Teacher', 72, 'Supports learner welfare, behaviour and counselling records.', ['attendance.manage','report.view','communication.manage']],
		['Games / Sports Teacher', 68, 'Manages sports and co-curricular participation records.', ['attendance.manage','report.view','communication.manage']],
		['HOD', 85, 'Legacy generic HOD role kept for backward compatibility.', ['attendance.manage','marks.enter','report.view','communication.manage']],
		['Exam Officer', 86, 'Legacy exam officer role kept for backward compatibility.', ['exams.manage','marks.enter','results.approve','results.lock','results.unlock','report.generate','report.view','communication.manage']],
	];

	$roleIds = [];
	foreach ($roles as $role) {
		if ($isPgsql) {
			$stmt = $conn->prepare("INSERT INTO tbl_roles (name, level, description, is_system) VALUES (?,?,?,true) ON CONFLICT (name) DO UPDATE SET level = EXCLUDED.level, description = EXCLUDED.description");
			$stmt->execute([$role[0], $role[1], $role[2]]);
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_roles (name, level, description, is_system) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE level = VALUES(level), description = VALUES(description)");
			$stmt->execute([$role[0], $role[1], $role[2]]);
		}
		$stmt = $conn->prepare('SELECT id FROM tbl_roles WHERE name = ? LIMIT 1');
		$stmt->execute([$role[0]]);
		$roleIds[$role[0]] = (int)$stmt->fetchColumn();
	}

	$permIds = [];
	$stmt = $conn->prepare('SELECT id, code FROM tbl_permissions');
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$permIds[(string)$row['code']] = (int)$row['id'];
	}

	foreach ($roles as $role) {
		$roleId = (int)($roleIds[$role[0]] ?? 0);
		if ($roleId < 1) {
			continue;
		}
		foreach ($role[3] as $permCode) {
			if (!isset($permIds[$permCode])) {
				continue;
			}
			if ($isPgsql) {
				$stmt = $conn->prepare('INSERT INTO tbl_role_permissions (role_id, permission_id) VALUES (?,?) ON CONFLICT DO NOTHING');
			} else {
				$stmt = $conn->prepare('INSERT IGNORE INTO tbl_role_permissions (role_id, permission_id) VALUES (?,?)');
			}
			$stmt->execute([$roleId, $permIds[$permCode]]);
		}
	}
}

function app_staff_primary_title(PDO $conn, int $staffId, string $level = ''): string
{
	if ($staffId > 0 && app_table_exists($conn, 'tbl_user_roles') && app_table_exists($conn, 'tbl_roles')) {
		try {
			$stmt = $conn->prepare("SELECT r.name
				FROM tbl_user_roles ur
				JOIN tbl_roles r ON r.id = ur.role_id
				WHERE ur.staff_id = ?
				ORDER BY r.level DESC, r.name ASC
				LIMIT 1");
			$stmt->execute([$staffId]);
			$roleName = trim((string)$stmt->fetchColumn());
			if ($roleName !== '') {
				return $roleName;
			}
		} catch (Throwable $e) {
			// fall through to level title
		}
	}

	return app_level_title_label((int)$level);
}

function app_staff_has_permission_code(PDO $conn, int $staffId, string $permissionCode): bool
{
	if ($staffId < 1 || $permissionCode === '') {
		return false;
	}
	if (!app_table_exists($conn, 'tbl_user_roles') || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_permissions')) {
		return false;
	}
	try {
		$stmt = $conn->prepare("SELECT 1
			FROM tbl_user_roles ur
			JOIN tbl_role_permissions rp ON rp.role_id = ur.role_id
			JOIN tbl_permissions p ON p.id = rp.permission_id
			WHERE ur.staff_id = ? AND p.code = ?
			LIMIT 1");
		$stmt->execute([$staffId, $permissionCode]);
		return (bool)$stmt->fetchColumn();
	} catch (Throwable $e) {
		return false;
	}
}

function app_staff_login_portal(PDO $conn, int $staffId, string $level): string
{
	// Check primary staff level first (not BOM permissions)
	if (in_array($level, ['0', '9'], true)) {
		return 'admin';
	}
	if ($level === '1') {
		return 'academic';
	}
	if ($level === '2') {
		return 'teacher';
	}
	if ($level === '5') {
		return 'accountant';
	}
	if ($level === '10') {
		return 'bom';
	}

	// Fallback: Check BOM permissions for backward compatibility
	if (app_staff_has_permission_code($conn, $staffId, 'bom.view') || app_staff_has_permission_code($conn, $staffId, 'bom.manage')) {
		return 'bom';
	}

	// All other staff roles managed through admin
	return 'admin';
}

function app_student_role_catalog(): array
{
	return [
		'head_boy' => 'Head Boy',
		'head_girl' => 'Head Girl',
		'deputy_head_boy' => 'Deputy Head Boy',
		'deputy_head_girl' => 'Deputy Head Girl',
		'class_prefect' => 'Class Prefect',
		'dining_prefect' => 'Dining Prefect',
		'sanitation_prefect' => 'Sanitation / Environment Prefect',
		'time_keeper' => 'Time Keeper',
		'library_prefect' => 'Library Prefect',
		'games_captain' => 'Games / Sports Captain',
		'dormitory_prefect' => 'Dormitory Prefect',
		'class_monitor' => 'Class Monitor',
		'class_rep' => 'Class Representative',
	];
}

function app_ensure_student_roles_table(PDO $conn): void
{
	static $done = false;
	if ($done) {
		return;
	}

	if (app_table_exists($conn, 'tbl_student_roles')) {
		if (!app_column_exists($conn, 'tbl_student_roles', 'responsibilities')) {
			if (defined('DBDriver') && DBDriver === 'pgsql') {
				$conn->exec("ALTER TABLE tbl_student_roles ADD COLUMN responsibilities text NOT NULL DEFAULT ''");
			} else {
				$conn->exec("ALTER TABLE tbl_student_roles ADD COLUMN responsibilities text NOT NULL");
			}
		}
		$done = true;
		return;
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_student_roles (
			id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
			student_id varchar(20) NOT NULL,
			class_id integer NOT NULL,
			role_code varchar(40) NOT NULL,
			responsibilities text NOT NULL DEFAULT '',
			term_id integer NULL,
			year integer NOT NULL,
			status integer NOT NULL DEFAULT 1,
			created_by integer NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			CONSTRAINT tbl_student_roles_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_roles_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_roles_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_roles_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_roles_unique UNIQUE (student_id, class_id, role_code, term_id, year)
		)");
		$conn->exec("CREATE INDEX IF NOT EXISTS tbl_student_roles_class_term_idx ON tbl_student_roles (class_id, term_id, year, status)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_student_roles (
			id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
			student_id varchar(20) NOT NULL,
			class_id int NOT NULL,
			role_code varchar(40) NOT NULL,
			responsibilities text NOT NULL,
			term_id int NULL,
			year int NOT NULL,
			status int NOT NULL DEFAULT 1,
			created_by int NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY tbl_student_roles_unique (student_id, class_id, role_code, term_id, year),
			KEY tbl_student_roles_class_term_idx (class_id, term_id, year, status),
			CONSTRAINT tbl_student_roles_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_roles_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_roles_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_roles_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	}

	$done = true;
}

function app_ensure_student_leadership_reports_table(PDO $conn): void
{
	static $done = false;
	if ($done || app_table_exists($conn, 'tbl_student_leadership_reports')) {
		$done = true;
		return;
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_student_leadership_reports (
			id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
			student_id varchar(20) NOT NULL,
			class_id integer NULL,
			role_code varchar(40) NOT NULL,
			term_id integer NULL,
			year integer NOT NULL,
			report_type varchar(50) NOT NULL DEFAULT 'discipline',
			title varchar(200) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			handled_by integer NULL,
			handled_at timestamp NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			CONSTRAINT tbl_student_leadership_reports_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_leadership_reports_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_leadership_reports_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_leadership_reports_staff_fk FOREIGN KEY (handled_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_student_leadership_reports (
			id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
			student_id varchar(20) NOT NULL,
			class_id int NULL,
			role_code varchar(40) NOT NULL,
			term_id int NULL,
			year int NOT NULL,
			report_type varchar(50) NOT NULL DEFAULT 'discipline',
			title varchar(200) NOT NULL,
			details text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			handled_by int NULL,
			handled_at timestamp NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			KEY tbl_student_leadership_reports_lookup (class_id, term_id, year, status),
			CONSTRAINT tbl_student_leadership_reports_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_leadership_reports_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_leadership_reports_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE SET NULL,
			CONSTRAINT tbl_student_leadership_reports_staff_fk FOREIGN KEY (handled_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	}

	$done = true;
}

function app_bom_role_catalog(): array
{
	return [
		'chairperson' => 'Chairperson',
		'secretary_headteacher' => 'Secretary (Headteacher)',
		'treasurer' => 'Treasurer',
		'parent_representative' => 'Parent Representative',
		'teacher_representative' => 'Teacher Representative',
		'sponsor_representative' => 'Sponsor Representative',
		'community_representative' => 'Local Community Representative',
		'special_interest' => 'Special Interest Member',
	];
}

function app_ensure_bom_tables(PDO $conn): void
{
	if (app_table_exists($conn, 'tbl_bom_members') && !app_column_exists($conn, 'tbl_bom_members', 'staff_id')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("ALTER TABLE tbl_bom_members ADD COLUMN staff_id integer NULL");
			$conn->exec("ALTER TABLE tbl_bom_members ADD CONSTRAINT tbl_bom_members_linked_staff_fk FOREIGN KEY (staff_id) REFERENCES tbl_staff (id) ON DELETE SET NULL");
		} else {
			$conn->exec("ALTER TABLE tbl_bom_members ADD COLUMN staff_id int NULL");
			$conn->exec("ALTER TABLE tbl_bom_members ADD CONSTRAINT tbl_bom_members_linked_staff_fk FOREIGN KEY (staff_id) REFERENCES tbl_staff (id) ON DELETE SET NULL");
		}
	}

	if (!app_table_exists($conn, 'tbl_bom_members')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_members (
				id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
				staff_id integer NULL,
				full_name varchar(160) NOT NULL,
				role_code varchar(60) NOT NULL,
				representing varchar(120) NOT NULL DEFAULT '',
				phone varchar(30) NOT NULL DEFAULT '',
				email varchar(120) NOT NULL DEFAULT '',
				term_start date NULL,
				term_end date NULL,
				status integer NOT NULL DEFAULT 1,
				created_by integer NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_members_linked_staff_fk FOREIGN KEY (staff_id) REFERENCES tbl_staff (id) ON DELETE SET NULL,
				CONSTRAINT tbl_bom_members_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		} else {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_members (
				id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				staff_id int NULL,
				full_name varchar(160) NOT NULL,
				role_code varchar(60) NOT NULL,
				representing varchar(120) NOT NULL DEFAULT '',
				phone varchar(30) NOT NULL DEFAULT '',
				email varchar(120) NOT NULL DEFAULT '',
				term_start date NULL,
				term_end date NULL,
				status int NOT NULL DEFAULT 1,
				created_by int NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_members_linked_staff_fk FOREIGN KEY (staff_id) REFERENCES tbl_staff (id) ON DELETE SET NULL,
				CONSTRAINT tbl_bom_members_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		}
	}

	if (!app_table_exists($conn, 'tbl_bom_meetings')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_meetings (
				id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
				meeting_date date NOT NULL,
				title varchar(180) NOT NULL,
				agenda text NOT NULL,
				minutes text NOT NULL DEFAULT '',
				decisions text NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'planned',
				created_by integer NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_meetings_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		} else {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_meetings (
				id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				meeting_date date NOT NULL,
				title varchar(180) NOT NULL,
				agenda text NOT NULL,
				minutes text NOT NULL,
				decisions text NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'planned',
				created_by int NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_meetings_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		}
	}

	if (!app_table_exists($conn, 'tbl_bom_financial_approvals')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_financial_approvals (
				id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
				approval_date date NOT NULL,
				item_title varchar(180) NOT NULL,
				amount numeric(12,2) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'pending',
				notes text NOT NULL DEFAULT '',
				approved_by_member_id integer NULL,
				created_by integer NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_financial_approvals_member_fk FOREIGN KEY (approved_by_member_id) REFERENCES tbl_bom_members (id) ON DELETE SET NULL,
				CONSTRAINT tbl_bom_financial_approvals_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		} else {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_financial_approvals (
				id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				approval_date date NOT NULL,
				item_title varchar(180) NOT NULL,
				amount decimal(12,2) NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'pending',
				notes text NOT NULL,
				approved_by_member_id int NULL,
				created_by int NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_financial_approvals_member_fk FOREIGN KEY (approved_by_member_id) REFERENCES tbl_bom_members (id) ON DELETE SET NULL,
				CONSTRAINT tbl_bom_financial_approvals_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		}
	}

	if (!app_table_exists($conn, 'tbl_bom_documents')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_documents (
				id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
				title varchar(180) NOT NULL,
				document_type varchar(80) NOT NULL DEFAULT 'policy',
				file_path varchar(255) NOT NULL,
				uploaded_by integer NULL,
				uploaded_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_documents_staff_fk FOREIGN KEY (uploaded_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		} else {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_bom_documents (
				id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
				title varchar(180) NOT NULL,
				document_type varchar(80) NOT NULL DEFAULT 'policy',
				file_path varchar(255) NOT NULL,
				uploaded_by int NULL,
				uploaded_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				CONSTRAINT tbl_bom_documents_staff_fk FOREIGN KEY (uploaded_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		}
	}
}

function app_sms_token_segments(string $message): int
{
	$message = trim($message);
	if ($message === '') {
		return 0;
	}
	$length = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
	return max(1, (int)ceil($length / 160));
}

function app_ensure_sms_wallet_tables(PDO $conn): void
{
	if (!app_table_exists($conn, 'tbl_sms_wallets')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_sms_wallets (
				id integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,
				wallet_name varchar(120) NOT NULL DEFAULT 'School SMS Wallet',
				phone_number varchar(30) NOT NULL DEFAULT '',
				balance_tokens integer NOT NULL DEFAULT 0,
				status integer NOT NULL DEFAULT 1,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			)");
		} else {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_sms_wallets (
				id int NOT NULL AUTO_INCREMENT,
				wallet_name varchar(120) NOT NULL DEFAULT 'School SMS Wallet',
				phone_number varchar(30) NOT NULL DEFAULT '',
				balance_tokens int NOT NULL DEFAULT 0,
				status int NOT NULL DEFAULT 1,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			)");
		}
	}

	if (!app_table_exists($conn, 'tbl_sms_token_transactions')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_sms_token_transactions (
				id integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,
				wallet_id integer NOT NULL,
				txn_type varchar(20) NOT NULL,
				tokens integer NOT NULL,
				reference_no varchar(120) NOT NULL DEFAULT '',
				description text NOT NULL DEFAULT '',
				created_by integer NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				CONSTRAINT tbl_sms_token_transactions_wallet_fk FOREIGN KEY (wallet_id) REFERENCES tbl_sms_wallets (id) ON DELETE CASCADE,
				CONSTRAINT tbl_sms_token_transactions_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		} else {
			$conn->exec("CREATE TABLE IF NOT EXISTS tbl_sms_token_transactions (
				id int NOT NULL AUTO_INCREMENT,
				wallet_id int NOT NULL,
				txn_type varchar(20) NOT NULL,
				tokens int NOT NULL,
				reference_no varchar(120) NOT NULL DEFAULT '',
				description text NOT NULL,
				created_by int NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				CONSTRAINT tbl_sms_token_transactions_wallet_fk FOREIGN KEY (wallet_id) REFERENCES tbl_sms_wallets (id) ON DELETE CASCADE,
				CONSTRAINT tbl_sms_token_transactions_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
			)");
		}
	}

	$stmt = $conn->prepare('SELECT id FROM tbl_sms_wallets WHERE id = 1 LIMIT 1');
	$stmt->execute();
	if (!(int)$stmt->fetchColumn()) {
		$stmt = $conn->prepare("INSERT INTO tbl_sms_wallets (id, wallet_name, phone_number, balance_tokens, status) VALUES (1, 'School SMS Wallet', '', 0, 1)");
		$stmt->execute();
	}
}

function app_ensure_mpesa_request_fields(PDO $conn): void
{
	if (!app_table_exists($conn, 'tbl_mpesa_stk_requests')) {
		return;
	}

	$columns = [
		'purpose' => "varchar(30) NOT NULL DEFAULT 'invoice'",
		'target_ref' => "varchar(120) NOT NULL DEFAULT ''",
		'notes' => "text NOT NULL DEFAULT ''",
	];

	foreach ($columns as $column => $definition) {
		if (app_column_exists($conn, 'tbl_mpesa_stk_requests', $column)) {
			continue;
		}
		try {
			$conn->exec("ALTER TABLE tbl_mpesa_stk_requests ADD COLUMN {$column} {$definition}");
		} catch (Throwable $e) {
			// best effort for existing deployments
		}
	}
}

function app_sms_wallet_balance(PDO $conn, int $walletId = 1): int
{
	if (!app_table_exists($conn, 'tbl_sms_wallets')) {
		return 0;
	}
	$stmt = $conn->prepare('SELECT balance_tokens FROM tbl_sms_wallets WHERE id = ? LIMIT 1');
	$stmt->execute([$walletId]);
	return (int)($stmt->fetchColumn() ?: 0);
}

function app_sms_wallet_adjust(PDO $conn, int $walletId, int $tokensDelta, string $referenceNo, string $description, string $txnType, ?int $createdBy = null): void
{
	app_ensure_sms_wallet_tables($conn);
	$conn->beginTransaction();
	try {
		$stmt = $conn->prepare('SELECT balance_tokens FROM tbl_sms_wallets WHERE id = ? LIMIT 1');
		$stmt->execute([$walletId]);
		$balance = (int)($stmt->fetchColumn() ?: 0);
		$newBalance = $balance + $tokensDelta;
		if ($newBalance < 0) {
			throw new RuntimeException('Insufficient SMS tokens.');
		}
		$stmt = $conn->prepare('UPDATE tbl_sms_wallets SET balance_tokens = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
		$stmt->execute([$newBalance, $walletId]);
		$stmt = $conn->prepare('INSERT INTO tbl_sms_token_transactions (wallet_id, txn_type, tokens, reference_no, description, created_by) VALUES (?,?,?,?,?,?)');
		$stmt->execute([$walletId, $txnType, $tokensDelta, $referenceNo, $description, $createdBy]);
		$conn->commit();
	} catch (Throwable $e) {
		if ($conn->inTransaction()) {
			$conn->rollBack();
		}
		throw $e;
	}
}

function app_table_exists(PDO $conn, string $table): bool
{
	static $cache = [];
	$key = DBDriver . ':' . DBName . ':' . $table;
	if (array_key_exists($key, $cache)) {
		return (bool)$cache[$key];
	}

	try {
		if (DBDriver === 'pgsql') {
			$stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = ? LIMIT 1");
			$stmt->execute([$table]);
		} else {
			$stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = ? AND table_name = ? LIMIT 1");
			$stmt->execute([DBName, $table]);
		}
		$exists = (bool)$stmt->fetchColumn();
		$cache[$key] = $exists;
		return $exists;
	} catch (Throwable $e) {
		$cache[$key] = false;
		return false;
	}
}

function app_column_exists(PDO $conn, string $table, string $column): bool
{
	static $cache = [];
	$key = DBDriver . ':' . DBName . ':' . $table . ':' . $column;
	if (array_key_exists($key, $cache)) {
		return (bool)$cache[$key];
	}

	try {
		if (DBDriver === 'pgsql') {
			$stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ? LIMIT 1");
			$stmt->execute([$table, $column]);
		} else {
			$stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ? LIMIT 1");
			$stmt->execute([DBName, $table, $column]);
		}
		$exists = (bool)$stmt->fetchColumn();
		$cache[$key] = $exists;
		return $exists;
	} catch (Throwable $e) {
		$cache[$key] = false;
		return false;
	}
}

function app_tx_savepoint_begin(PDO $conn, string $prefix = 'sp'): ?string
{
	if (!$conn->inTransaction()) {
		return null;
	}
	$name = preg_replace('/[^a-zA-Z0-9_]/', '_', $prefix.'_'.uniqid('', true));
	if ($name === null || $name === '') {
		return null;
	}
	try {
		$conn->exec("SAVEPOINT {$name}");
		return $name;
	} catch (Throwable $e) {
		return null;
	}
}

function app_tx_savepoint_release(PDO $conn, ?string $name): void
{
	if (!$name || !$conn->inTransaction()) {
		return;
	}
	try {
		$conn->exec("RELEASE SAVEPOINT {$name}");
	} catch (Throwable $e) {
		// best effort only
	}
}

function app_tx_savepoint_rollback(PDO $conn, ?string $name): void
{
	if (!$name || !$conn->inTransaction()) {
		return;
	}
	try {
		$conn->exec("ROLLBACK TO SAVEPOINT {$name}");
	} catch (Throwable $e) {
		// best effort only
	}
	try {
		$conn->exec("RELEASE SAVEPOINT {$name}");
	} catch (Throwable $e) {
		// best effort only
	}
}

function app_audit_log(PDO $conn, string $actorType, string $actorId, string $action, string $entity, string $entityId = '', array $meta = []): void
{
	$savepoint = app_tx_savepoint_begin($conn, 'audit_log');
	try {
		if (!app_table_exists($conn, 'tbl_audit_logs')) {
			app_tx_savepoint_release($conn, $savepoint);
			return;
		}

		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		if (!empty($meta)) {
			$metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			if ($metaJson !== false && $metaJson !== '[]' && $metaJson !== '{}') {
				$ua = trim($ua . ' | meta=' . $metaJson);
			}
		}
		$stmt = $conn->prepare("INSERT INTO tbl_audit_logs (actor_type, actor_id, action, entity, entity_id, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
		$stmt->execute([$actorType, $actorId, $action, $entity, $entityId, $ip, $ua]);
		app_tx_savepoint_release($conn, $savepoint);
	} catch (Throwable $e) {
		app_tx_savepoint_rollback($conn, $savepoint);
	}
}

function app_results_locked(PDO $conn, int $classId, int $termId): bool
{
	if ($classId < 1 || $termId < 1) {
		return false;
	}
	if (!app_table_exists($conn, 'tbl_results_locks')) {
		return false;
	}

	try {
		$stmt = $conn->prepare("SELECT locked FROM tbl_results_locks WHERE class_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([$classId, $termId]);
		$locked = $stmt->fetchColumn();
		return (int)$locked === 1;
	} catch (Throwable $e) {
		return false;
	}
}

function app_exam_submission_status(PDO $conn, int $examId, int $subjectCombinationId): string
{
	if ($examId < 1 || $subjectCombinationId < 1) {
		return 'draft';
	}
	if (!app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		return 'draft';
	}
	try {
		$stmt = $conn->prepare("SELECT status FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
		$stmt->execute([$examId, $subjectCombinationId]);
		$status = (string)$stmt->fetchColumn();
		return $status !== '' ? $status : 'draft';
	} catch (Throwable $e) {
		return 'draft';
	}
}

function app_exam_can_enter_marks(string $status): bool
{
	return in_array($status, ['active'], true);
}

function app_exam_status_badge(string $status): string
{
	$normalized = strtolower(trim($status));
	$map = [
		'draft' => 'secondary',
		'active' => 'primary',
		'reviewed' => 'info',
		'finalized' => 'success',
		'published' => 'dark',
		'closed' => 'warning',
	];
	return $map[$normalized] ?? 'secondary';
}

function app_ensure_exam_subjects_table(PDO $conn): void
{
	if (app_table_exists($conn, 'tbl_exam_subjects')) {
		return;
	}

	if (DBDriver === 'pgsql') {
		$conn->exec("
			CREATE TABLE IF NOT EXISTS tbl_exam_subjects (
				exam_id integer NOT NULL,
				subject_id integer NOT NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (exam_id, subject_id),
				CONSTRAINT tbl_exam_subjects_exam_fk FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE,
				CONSTRAINT tbl_exam_subjects_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
			)
		");
	} else {
		$conn->exec("
			CREATE TABLE IF NOT EXISTS tbl_exam_subjects (
				exam_id int NOT NULL,
				subject_id int NOT NULL,
				created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (exam_id, subject_id),
				CONSTRAINT tbl_exam_subjects_exam_fk FOREIGN KEY (exam_id) REFERENCES tbl_exams (id) ON DELETE CASCADE,
				CONSTRAINT tbl_exam_subjects_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}
}

function app_ensure_exam_assessment_mode_column(PDO $conn): void
{
	if (!app_table_exists($conn, 'tbl_exams') || app_column_exists($conn, 'tbl_exams', 'assessment_mode')) {
		return;
	}

	if (DBDriver === 'pgsql') {
		$conn->exec("ALTER TABLE tbl_exams ADD COLUMN assessment_mode varchar(20) NOT NULL DEFAULT 'normal'");
	} else {
		$conn->exec("ALTER TABLE tbl_exams ADD COLUMN assessment_mode varchar(20) NOT NULL DEFAULT 'normal'");
	}
}

function app_exam_subject_ids(PDO $conn, int $examId): array
{
	if ($examId < 1) {
		return [];
	}
	app_ensure_exam_subjects_table($conn);
	$stmt = $conn->prepare("SELECT subject_id FROM tbl_exam_subjects WHERE exam_id = ? ORDER BY subject_id");
	$stmt->execute([$examId]);
	return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function app_exam_has_subject(PDO $conn, int $examId, int $subjectId): bool
{
	if ($examId < 1 || $subjectId < 1) {
		return false;
	}
	$subjectIds = app_exam_subject_ids($conn, $examId);
	if (empty($subjectIds)) {
		return true;
	}
	return in_array($subjectId, $subjectIds, true);
}

function app_exam_assessment_mode(PDO $conn, int $examId): string
{
	if ($examId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		return 'normal';
	}
	app_ensure_exam_assessment_mode_column($conn);
	$stmt = $conn->prepare("SELECT assessment_mode FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$mode = strtolower(trim((string)$stmt->fetchColumn()));
	return $mode === 'cbc' ? 'cbc' : 'normal';
}

function app_sync_subject_combination(PDO $conn, int $teacherId, int $subjectId, int $classId, bool $remove): int
{
	if (!app_table_exists($conn, 'tbl_subject_combinations') || $teacherId < 1 || $subjectId < 1 || $classId < 1) {
		return 0;
	}

	$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations WHERE teacher = ? AND subject = ? LIMIT 1");
	$stmt->execute([$teacherId, $subjectId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	$classIdStr = (string)$classId;
	$classList = $row ? app_unserialize($row['class']) : [];
	$classList = array_values(array_unique(array_map('strval', $classList)));

	if ($remove) {
		$classList = array_values(array_filter($classList, function ($val) use ($classIdStr) {
			return (string)$val !== $classIdStr;
		}));
	} elseif (!in_array($classIdStr, $classList, true)) {
		$classList[] = $classIdStr;
	}

	if ($row) {
		if (count($classList) < 1) {
			$stmt = $conn->prepare("DELETE FROM tbl_subject_combinations WHERE id = ?");
			$stmt->execute([(int)$row['id']]);
			return 0;
		}

		$stmt = $conn->prepare("UPDATE tbl_subject_combinations SET class = ? WHERE id = ?");
		$stmt->execute([serialize($classList), (int)$row['id']]);
		return (int)$row['id'];
	}

	if ($remove) {
		return 0;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_subject_combinations (class, subject, teacher, reg_date) VALUES (?,?,?,CURRENT_TIMESTAMP)");
	$stmt->execute([serialize([$classIdStr]), $subjectId, $teacherId]);
	return (int)$conn->lastInsertId();
}

function app_get_teacher_subject_combination_id(PDO $conn, int $teacherId, int $subjectId, int $classId, bool $createIfMissing = false): int
{
	if ($teacherId < 1 || $subjectId < 1 || $classId < 1 || !app_table_exists($conn, 'tbl_subject_combinations')) {
		return 0;
	}

	$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations WHERE teacher = ? AND subject = ? LIMIT 1");
	$stmt->execute([$teacherId, $subjectId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		$classList = app_unserialize($row['class']);
		if (!in_array((string)$classId, array_map('strval', $classList), true) && $createIfMissing) {
			return app_sync_subject_combination($conn, $teacherId, $subjectId, $classId, false);
		}
		return in_array((string)$classId, array_map('strval', $classList), true) ? (int)$row['id'] : 0;
	}

	return $createIfMissing ? app_sync_subject_combination($conn, $teacherId, $subjectId, $classId, false) : 0;
}

function app_refresh_exam_status(PDO $conn, int $examId): string
{
	if ($examId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		return 'draft';
	}

	try {
		$stmt = $conn->prepare("SELECT status, class_id, term_id, COALESCE(assessment_mode, 'normal') AS assessment_mode FROM tbl_exams WHERE id = ? LIMIT 1");
		$stmt->execute([$examId]);
		$examRow = $stmt->fetch(PDO::FETCH_ASSOC);
		$current = (string)($examRow['status'] ?? '');
		if ($current === '') {
			return 'draft';
		}
		if (in_array($current, ['finalized', 'published'], true)) {
			return $current;
		}

		$assessmentMode = (string)($examRow['assessment_mode'] ?? 'normal');
		$counts = [];

		if ($assessmentMode === 'cbc' && app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
			$classId = (int)($examRow['class_id'] ?? 0);
			$termId = (int)($examRow['term_id'] ?? 0);
			$stmt = $conn->prepare("SELECT s.status, COUNT(*) AS total
				FROM tbl_cbc_mark_submissions s
				WHERE s.class_id = ? AND s.term_id = ?
				GROUP BY s.status");
			$stmt->execute([$classId, $termId]);
		} elseif (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			$stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM tbl_exam_mark_submissions WHERE exam_id = ? GROUP BY status");
			$stmt->execute([$examId]);
		} else {
			return $current;
		}

		$counts = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$counts[(string)$row['status']] = (int)$row['total'];
		}
		if (empty($counts)) {
			$nextStatus = in_array($current, ['active', 'reviewed'], true) ? 'active' : 'draft';
		} elseif (!empty($counts['submitted'])) {
			$nextStatus = 'active';
		} elseif (!empty($counts['reviewed']) || !empty($counts['approved']) || !empty($counts['finalized'])) {
			$nextStatus = 'reviewed';
		} else {
			$nextStatus = 'active';
		}

		if ($nextStatus !== $current) {
			$stmt = $conn->prepare("UPDATE tbl_exams SET status = ? WHERE id = ?");
			$stmt->execute([$nextStatus, $examId]);
		}
		return $nextStatus;
	} catch (Throwable $e) {
		return 'draft';
	}
}

function app_reply_redirect(string $type, string $message, string $location): void
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$_SESSION['reply'] = array(array($type, $message));
	header("location:" . $location);
	exit;
}

function app_setting_get(PDO $conn, string $key, string $default = ''): string
{
	if ($key === '' || !app_table_exists($conn, 'tbl_app_settings')) {
		return $default;
	}
	try {
		$stmt = $conn->prepare("SELECT setting_value FROM tbl_app_settings WHERE setting_key = ? LIMIT 1");
		$stmt->execute([$key]);
		$value = $stmt->fetchColumn();
		return $value === false || $value === null ? $default : (string)$value;
	} catch (Throwable $e) {
		return $default;
	}
}

function app_class_subject_ids(PDO $conn, int $classId): array
{
	if ($classId < 1 || !app_table_exists($conn, 'tbl_subject_class_assignments')) {
		return [];
	}
	$stmt = $conn->prepare("SELECT subject_id FROM tbl_subject_class_assignments WHERE class_id = ? ORDER BY subject_id");
	$stmt->execute([$classId]);
	return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function app_save_class_subject_assignments(PDO $conn, int $classId, array $subjectIds, ?int $userId = null): void
{
	if ($classId < 1 || !app_table_exists($conn, 'tbl_subject_class_assignments')) {
		return;
	}
	if (!app_column_exists($conn, 'tbl_subject_class_assignments', 'class_id') || !app_column_exists($conn, 'tbl_subject_class_assignments', 'subject_id')) {
		return;
	}

	$subjectIds = array_values(array_unique(array_filter(array_map('intval', $subjectIds))));
	$stmt = $conn->prepare("DELETE FROM tbl_subject_class_assignments WHERE class_id = ?");
	$stmt->execute([$classId]);

	if (empty($subjectIds)) {
		return;
	}

	$hasCreatedBy = app_column_exists($conn, 'tbl_subject_class_assignments', 'created_by');
	if ($hasCreatedBy) {
		$insert = $conn->prepare("INSERT INTO tbl_subject_class_assignments (subject_id, class_id, created_by) VALUES (?,?,?)");
		foreach ($subjectIds as $subjectId) {
			$insert->execute([$subjectId, $classId, $userId ? (int)$userId : null]);
		}
		return;
	}

	$insert = $conn->prepare("INSERT INTO tbl_subject_class_assignments (subject_id, class_id) VALUES (?,?)");
	foreach ($subjectIds as $subjectId) {
		$insert->execute([$subjectId, $classId]);
	}
}

function app_class_subject_teacher_rows(PDO $conn, int $classId): array
{
	if ($classId < 1) {
		return [];
	}

	$subjectNames = [];
	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("SELECT sc.subject_id, s.name AS subject_name
			FROM tbl_subject_class_assignments sc
			JOIN tbl_subjects s ON s.id = sc.subject_id
			WHERE sc.class_id = ?
			ORDER BY s.name");
		$stmt->execute([$classId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$subjectNames[(int)$row['subject_id']] = (string)$row['subject_name'];
		}
	}

	$teacherMap = [];
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT ta.subject_id, st.fname, st.lname
			FROM tbl_teacher_assignments ta
			JOIN tbl_staff st ON st.id = ta.teacher_id
			WHERE ta.class_id = ? AND ta.status = 1
			ORDER BY ta.year DESC, ta.id DESC");
		$stmt->execute([$classId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$teacherMap[(int)$row['subject_id']][] = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
		}
	}

	$rows = [];
	foreach ($subjectNames as $subjectId => $subjectName) {
		$teachers = array_values(array_unique(array_filter($teacherMap[$subjectId] ?? [])));
		$rows[] = [
			'subject_id' => $subjectId,
			'subject_name' => $subjectName,
			'teachers' => $teachers,
		];
	}

	return $rows;
}

function app_setting_set(PDO $conn, string $key, string $value, ?int $userId = null, bool $strict = false): void
{
	if ($key === '' || !app_table_exists($conn, 'tbl_app_settings')) {
		return;
	}
	$userId = ($userId && $userId > 0) ? $userId : null;
	$savepoint = (!$strict) ? app_tx_savepoint_begin($conn, 'app_setting') : null;
	try {
		if (DBDriver === 'pgsql') {
			$stmt = $conn->prepare("INSERT INTO tbl_app_settings (setting_key, setting_value, updated_by, updated_at)
				VALUES (?,?,?,CURRENT_TIMESTAMP)
				ON CONFLICT (setting_key)
				DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_by = EXCLUDED.updated_by, updated_at = CURRENT_TIMESTAMP");
			$stmt->execute([$key, $value, $userId]);
			app_tx_savepoint_release($conn, $savepoint);
			return;
		}

		$stmt = $conn->prepare("SELECT id FROM tbl_app_settings WHERE setting_key = ? LIMIT 1");
		$stmt->execute([$key]);
		$id = (int)$stmt->fetchColumn();
		if ($id > 0) {
			$stmt = $conn->prepare("UPDATE tbl_app_settings SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
			$stmt->execute([$value, $userId, $id]);
			app_tx_savepoint_release($conn, $savepoint);
			return;
		}
		$stmt = $conn->prepare("INSERT INTO tbl_app_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?,?,?,CURRENT_TIMESTAMP)");
		$stmt->execute([$key, $value, $userId]);
		app_tx_savepoint_release($conn, $savepoint);
	} catch (Throwable $e) {
		app_tx_savepoint_rollback($conn, $savepoint);
		if ($strict) {
			throw $e;
		}
	}
}

function app_school_days(PDO $conn): array
{
	$raw = app_setting_get($conn, 'default_school_days', 'Monday,Tuesday,Wednesday,Thursday,Friday');
	$days = array_values(array_filter(array_map('trim', explode(',', $raw))));
	return !empty($days) ? $days : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
}

function app_next_student_registration_number(PDO $conn): string
{
	$start = (int)app_setting_get($conn, 'admission_start_number', '1');
	if ($start < 1) {
		$start = 1;
	}
	if (!app_table_exists($conn, 'tbl_students')) {
		return (string)$start;
	}

	try {
		if (DBDriver === 'pgsql') {
			$stmt = $conn->prepare("SELECT id::text AS id FROM tbl_students");
		} else {
			$stmt = $conn->prepare("SELECT id FROM tbl_students");
		}
		$stmt->execute();
		$maxNumeric = $start - 1;
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
			$text = trim((string)$value);
			if ($text !== '' && ctype_digit($text)) {
				$number = (int)$text;
				if ($number > $maxNumeric) {
					$maxNumeric = $number;
				}
			}
		}
		$next = max($start, $maxNumeric + 1);
		return (string)$next;
	} catch (Throwable $e) {
		return (string)$start;
	}
}

function app_default_overall_grading_rows(): array
{
	return [
		['grade' => 'EE', 'min' => 90.0, 'max' => 100.0, 'points' => 4, 'remark' => 'Excellent', 'order' => 1, 'active' => 1],
		['grade' => 'ME', 'min' => 75.0, 'max' => 89.0, 'points' => 3, 'remark' => 'Good', 'order' => 2, 'active' => 1],
		['grade' => 'AE', 'min' => 50.0, 'max' => 74.0, 'points' => 2, 'remark' => 'Average', 'order' => 3, 'active' => 1],
		['grade' => 'BE', 'min' => 0.0, 'max' => 49.0, 'points' => 1, 'remark' => 'Needs Improvement', 'order' => 4, 'active' => 1],
	];
}

function app_cbc_rows_match_overall_default(array $rows): bool
{
	$rows = array_values($rows);
	$default = app_default_overall_grading_rows();
	if (count($rows) !== count($default)) {
		return false;
	}
	foreach ($default as $index => $expected) {
		$row = $rows[$index] ?? [];
		if (
			strtoupper(trim((string)($row['level'] ?? $row['grade'] ?? ''))) !== $expected['grade'] ||
			(float)($row['min_mark'] ?? $row['min'] ?? -1) !== (float)$expected['min'] ||
			(float)($row['max_mark'] ?? $row['max'] ?? -1) !== (float)$expected['max'] ||
			(int)($row['points'] ?? -1) !== (int)$expected['points']
		) {
			return false;
		}
	}
	return true;
}

function app_ensure_overall_grading_defaults(PDO $conn): void
{
	$defaultRows = app_default_overall_grading_rows();

	if (app_table_exists($conn, 'tbl_grading_systems') && app_table_exists($conn, 'tbl_grading_scales')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE name = ? LIMIT 1");
		$stmt->execute(['Overall Grading System']);
		$overallId = (int)$stmt->fetchColumn();

		if ($overallId < 1) {
			if (DBDriver === 'pgsql') {
				$stmt = $conn->prepare("INSERT INTO tbl_grading_systems (name, type, description, is_default, is_active) VALUES (?,?,?,?,?) RETURNING id");
				$stmt->execute(['Overall Grading System', 'cbc', 'System-wide default competency grading', 1, 1]);
				$overallId = (int)$stmt->fetchColumn();
			} else {
				$stmt = $conn->prepare("INSERT INTO tbl_grading_systems (name, type, description, is_default, is_active) VALUES (?,?,?,?,?)");
				$stmt->execute(['Overall Grading System', 'cbc', 'System-wide default competency grading', 1, 1]);
				$overallId = (int)$conn->lastInsertId();
			}
		} else {
			$stmt = $conn->prepare("UPDATE tbl_grading_systems SET type = 'cbc', description = ?, is_default = 1, is_active = 1 WHERE id = ?");
			$stmt->execute(['System-wide default competency grading', $overallId]);
		}

		$conn->prepare("UPDATE tbl_grading_systems SET is_default = CASE WHEN id = ? THEN 1 ELSE 0 END")->execute([$overallId]);

		$stmt = $conn->prepare("SELECT grade, min_score, max_score, points FROM tbl_grading_scales WHERE grading_system_id = ? ORDER BY sort_order ASC, min_score DESC");
		$stmt->execute([$overallId]);
		$currentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$matches = app_cbc_rows_match_overall_default(array_map(function ($row) {
			return [
				'level' => $row['grade'] ?? '',
				'min_mark' => $row['min_score'] ?? 0,
				'max_mark' => $row['max_score'] ?? 0,
				'points' => $row['points'] ?? 0,
			];
		}, $currentRows));

		if (!$matches) {
			$conn->prepare("DELETE FROM tbl_grading_scales WHERE grading_system_id = ?")->execute([$overallId]);
			$stmt = $conn->prepare("INSERT INTO tbl_grading_scales (grading_system_id, min_score, max_score, grade, points, remark, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)");
			foreach ($defaultRows as $row) {
				$stmt->execute([$overallId, $row['min'], $row['max'], $row['grade'], $row['points'], $row['remark'], $row['order'], $row['active']]);
			}
		}
	}

	if (app_table_exists($conn, 'tbl_cbc_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points FROM tbl_cbc_grading ORDER BY sort_order ASC, min_mark DESC");
		$stmt->execute();
		$currentBands = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!app_cbc_rows_match_overall_default($currentBands)) {
			$conn->exec("DELETE FROM tbl_cbc_grading");
			$stmt = $conn->prepare("INSERT INTO tbl_cbc_grading (level, min_mark, max_mark, points, sort_order, active) VALUES (?,?,?,?,?,?)");
			foreach ($defaultRows as $row) {
				$stmt->execute([$row['grade'], $row['min'], $row['max'], $row['points'], $row['order'], $row['active']]);
			}
		}
	}
}

function app_delete_students(PDO $conn, array $ids): void
{
	if (empty($ids)) {
		return;
	}

	$placeholders = implode(',', array_fill(0, count($ids), '?'));

	if (app_table_exists($conn, 'tbl_students')) {
		$stmt = $conn->prepare("SELECT id, display_image FROM tbl_students WHERE id IN ($placeholders)");
		$stmt->execute($ids);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$img = $row['display_image'] ?? '';
			if ($img !== '' && $img !== 'DEFAULT' && $img !== 'Blank') {
				@unlink('images/students/' . $img);
			}
		}
	}

	$deletes = [
		['tbl_parent_students', 'DELETE FROM tbl_parent_students WHERE student_id IN (%s)', 'student_id'],
		['tbl_cbc_assessments', 'DELETE FROM tbl_cbc_assessments WHERE student_id IN (%s)', 'student_id'],
		['tbl_attendance_records', 'DELETE FROM tbl_attendance_records WHERE student_id IN (%s)', 'student_id'],
		['tbl_exam_results', 'DELETE FROM tbl_exam_results WHERE student IN (%s)', 'student'],
		['tbl_assignment_submissions', 'DELETE FROM tbl_assignment_submissions WHERE student_id IN (%s)', 'student_id'],
		['tbl_quiz_results', 'DELETE FROM tbl_quiz_results WHERE student_id IN (%s)', 'student_id'],
		['tbl_attendance_elearning', 'DELETE FROM tbl_attendance_elearning WHERE student_id IN (%s)', 'student_id'],
		['tbl_ai_recommendations', 'DELETE FROM tbl_ai_recommendations WHERE student_id IN (%s)', 'student_id'],
	];
	foreach ($deletes as $rule) {
		if (app_table_exists($conn, $rule[0]) && app_column_exists($conn, $rule[0], $rule[2])) {
			$stmt = $conn->prepare(sprintf($rule[1], $placeholders));
			$stmt->execute($ids);
		}
	}

	if (app_table_exists($conn, 'tbl_report_cards')) {
		if (app_table_exists($conn, 'tbl_report_card_subjects') && app_column_exists($conn, 'tbl_report_card_subjects', 'report_id') && app_column_exists($conn, 'tbl_report_cards', 'id') && app_column_exists($conn, 'tbl_report_cards', 'student_id')) {
			$stmt = $conn->prepare("DELETE FROM tbl_report_card_subjects WHERE report_id IN (SELECT id FROM tbl_report_cards WHERE student_id IN ($placeholders))");
			$stmt->execute($ids);
		}
		if (app_column_exists($conn, 'tbl_report_cards', 'student_id')) {
			$stmt = $conn->prepare("DELETE FROM tbl_report_cards WHERE student_id IN ($placeholders)");
			$stmt->execute($ids);
		}
	}

	if (app_table_exists($conn, 'tbl_invoices') && app_column_exists($conn, 'tbl_invoices', 'student_id') && app_column_exists($conn, 'tbl_invoices', 'id')) {
		$invoiceIdsStmt = $conn->prepare("SELECT id FROM tbl_invoices WHERE student_id IN ($placeholders)");
		$invoiceIdsStmt->execute($ids);
		$invoiceIds = $invoiceIdsStmt->fetchAll(PDO::FETCH_COLUMN);
		if (!empty($invoiceIds)) {
			$invoicePlaceholders = implode(',', array_fill(0, count($invoiceIds), '?'));
			if (app_table_exists($conn, 'tbl_payments') && app_column_exists($conn, 'tbl_payments', 'invoice_id')) {
				$stmt = $conn->prepare("DELETE FROM tbl_payments WHERE invoice_id IN ($invoicePlaceholders)");
				$stmt->execute($invoiceIds);
			}
			if (app_table_exists($conn, 'tbl_invoice_lines') && app_column_exists($conn, 'tbl_invoice_lines', 'invoice_id')) {
				$stmt = $conn->prepare("DELETE FROM tbl_invoice_lines WHERE invoice_id IN ($invoicePlaceholders)");
				$stmt->execute($invoiceIds);
			}
			$stmt = $conn->prepare("DELETE FROM tbl_invoices WHERE id IN ($invoicePlaceholders)");
			$stmt->execute($invoiceIds);
		}
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'student')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE student IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_column_exists($conn, 'tbl_students', 'id')) {
		$stmt = $conn->prepare("DELETE FROM tbl_students WHERE id IN ($placeholders)");
		$stmt->execute($ids);
	}
}

function app_delete_staff(PDO $conn, array $ids): void
{
	if (empty($ids)) {
		return;
	}

	$placeholders = implode(',', array_fill(0, count($ids), '?'));

	$deletes = [
		['tbl_user_roles', 'DELETE FROM tbl_user_roles WHERE staff_id IN (%s)', 'staff_id'],
		['tbl_teacher_assignments', 'DELETE FROM tbl_teacher_assignments WHERE teacher_id IN (%s)', 'teacher_id'],
		['tbl_staff_attendance', 'DELETE FROM tbl_staff_attendance WHERE staff_id IN (%s)', 'staff_id'],
	];
	foreach ($deletes as $rule) {
		if (app_table_exists($conn, $rule[0]) && app_column_exists($conn, $rule[0], $rule[2])) {
			$stmt = $conn->prepare(sprintf($rule[1], $placeholders));
			$stmt->execute($ids);
		}
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'staff')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_column_exists($conn, 'tbl_staff', 'id')) {
		$stmt = $conn->prepare("DELETE FROM tbl_staff WHERE id IN ($placeholders)");
		$stmt->execute($ids);
	}
}

function app_reset_school_people_data(PDO $conn): array
{
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$summary = [
		'students_removed' => 0,
		'students_blocked' => 0,
		'teachers_removed' => 0,
		'parents_removed' => 0,
		'staff_removed' => 0,
		'staff_blocked' => 0,
		'admins_kept' => 0,
		'warnings' => [],
	];

	$studentIds = [];
	if (app_table_exists($conn, 'tbl_students')) {
		if (app_column_exists($conn, 'tbl_students', 'id')) {
			try {
				$stmt = $conn->query("SELECT id FROM tbl_students");
				$studentIds = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
			} catch (Throwable $e) {
				$summary['warnings'][] = 'student precheck failed: ' . $e->getMessage();
				error_log('[app_reset_school_people_data] student precheck failed: ' . $e->getMessage());
			}
		} else {
			$summary['warnings'][] = 'student precheck skipped: id column missing';
			error_log('[app_reset_school_people_data] student precheck skipped: id column missing');
		}
		$summary['students_removed'] = 0;
	}

	$staffIds = [];
	$adminCount = 0;
	if (app_table_exists($conn, 'tbl_staff')) {
		$hasStaffId = app_column_exists($conn, 'tbl_staff', 'id');
		$hasLevel = app_column_exists($conn, 'tbl_staff', 'level');
		if ($hasStaffId && $hasLevel) {
			try {
				$stmt = $conn->query("SELECT id, level FROM tbl_staff");
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					$level = (string)($row['level'] ?? '');
					if (in_array($level, ['0', '1', '9'], true)) {
						$adminCount++;
						continue;
					}
					$staffIds[] = (string)$row['id'];
				}
			} catch (Throwable $e) {
				$summary['warnings'][] = 'staff precheck failed: ' . $e->getMessage();
				error_log('[app_reset_school_people_data] staff precheck failed: ' . $e->getMessage());
			}
		} elseif ($hasStaffId) {
			try {
				$adminCount = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn();
				$summary['warnings'][] = 'staff delete skipped: level column missing; all staff preserved';
				error_log('[app_reset_school_people_data] staff delete skipped: level column missing; all staff preserved');
			} catch (Throwable $e) {
				$summary['warnings'][] = 'staff precheck failed: ' . $e->getMessage();
				error_log('[app_reset_school_people_data] staff precheck failed: ' . $e->getMessage());
			}
		} else {
			$summary['warnings'][] = 'staff precheck skipped: id column missing';
			error_log('[app_reset_school_people_data] staff precheck skipped: id column missing');
		}
	}
	$summary['admins_kept'] = $adminCount;
	$summary['staff_removed'] = 0;
	$summary['teachers_removed'] = count(array_filter($staffIds, function ($id) use ($conn) {
		try {
			$stmt = $conn->prepare("SELECT level FROM tbl_staff WHERE id = ? LIMIT 1");
			$stmt->execute([$id]);
			return (string)$stmt->fetchColumn() === '2';
		} catch (Throwable $e) {
			return false;
		}
	}));

	$parentCount = 0;
	if (app_table_exists($conn, 'tbl_parents')) {
		$parentCount = (int)$conn->query("SELECT COUNT(*) FROM tbl_parents")->fetchColumn();
	}
	$summary['parents_removed'] = $parentCount;

	$countExistingIds = function (string $table, string $column, array $ids) use ($conn): int {
		if (empty($ids) || !app_table_exists($conn, $table) || !app_column_exists($conn, $table, $column)) {
			return 0;
		}
		$placeholders = implode(',', array_fill(0, count($ids), '?'));
		$stmt = $conn->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} IN ({$placeholders})");
		$stmt->execute($ids);
		return (int)$stmt->fetchColumn();
	};

	$conn->beginTransaction();
	try {
		$fullWipeTables = [
			'tbl_exam_mark_submissions',
			'tbl_cbc_mark_submissions',
			'tbl_exam_results',
			'tbl_report_card_subjects',
			'tbl_report_cards',
			'tbl_exam_schedule',
			'tbl_exam_subjects',
			'tbl_exams',
			'tbl_results_locks',
			'tbl_validation_issues',
			'tbl_insights_alerts',
			'tbl_attendance_records',
			'tbl_attendance_sessions',
			'tbl_staff_attendance',
			'tbl_school_timetable',
			'tbl_class_teachers',
			'tbl_teacher_assignments',
			'tbl_subject_combinations',
			'tbl_assignment_submissions',
			'tbl_assignments',
			'tbl_attendance_elearning',
			'tbl_live_classes',
			'tbl_quiz_results',
			'tbl_quizzes',
			'tbl_lesson_content',
			'tbl_lessons',
			'tbl_courses',
			'tbl_transport_assignments',
			'tbl_parent_students',
			'tbl_notifications',
			'tbl_login_sessions',
			'tbl_ai_recommendations',
			'tbl_cbc_assessments',
			'tbl_invoices',
			'tbl_payments',
			'tbl_invoice_lines',
		];

		foreach ($fullWipeTables as $table) {
			if (app_table_exists($conn, $table)) {
				$sp = app_tx_savepoint_begin($conn, 'reset_' . $table);
				try {
					$conn->exec("DELETE FROM {$table}");
					app_tx_savepoint_release($conn, $sp);
				} catch (Throwable $e) {
					app_tx_savepoint_rollback($conn, $sp);
					error_log('[app_reset_school_people_data] table wipe failed for ' . $table . ': ' . $e->getMessage());
				}
			}
		}

		if ($parentCount > 0 && app_table_exists($conn, 'tbl_parents')) {
			$sp = app_tx_savepoint_begin($conn, 'reset_parents');
			try {
				$conn->exec("DELETE FROM tbl_parents");
				app_tx_savepoint_release($conn, $sp);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $sp);
				$msg = 'parent delete failed: ' . $e->getMessage();
				error_log('[app_reset_school_people_data] ' . $msg);
				$summary['warnings'][] = $msg;
			}
		}

		if (!empty($studentIds)) {
			$sp = app_tx_savepoint_begin($conn, 'reset_students');
			try {
				app_delete_students($conn, $studentIds);
				app_tx_savepoint_release($conn, $sp);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $sp);
				$msg = 'student delete failed, falling back to block: ' . $e->getMessage();
				error_log('[app_reset_school_people_data] ' . $msg);
				$summary['warnings'][] = $msg;
				if (app_column_exists($conn, 'tbl_students', 'status')) {
					try {
						$placeholders = implode(',', array_fill(0, count($studentIds), '?'));
						$stmt = $conn->prepare("UPDATE tbl_students SET status = 0 WHERE id IN ($placeholders)");
						$stmt->execute($studentIds);
					} catch (Throwable $fallbackError) {
						$msg2 = 'student fallback block failed: ' . $fallbackError->getMessage();
						error_log('[app_reset_school_people_data] ' . $msg2);
						$summary['warnings'][] = $msg2;
					}
				} else {
					$summary['warnings'][] = 'student fallback unavailable: status column missing';
				}
			}
		}

		if (!empty($staffIds)) {
			$sp = app_tx_savepoint_begin($conn, 'reset_staff');
			try {
				app_delete_staff($conn, $staffIds);
				app_tx_savepoint_release($conn, $sp);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $sp);
				$msg = 'staff delete failed, falling back to block: ' . $e->getMessage();
				error_log('[app_reset_school_people_data] ' . $msg);
				$summary['warnings'][] = $msg;
				if (app_column_exists($conn, 'tbl_staff', 'status')) {
					try {
						$placeholders = implode(',', array_fill(0, count($staffIds), '?'));
						$stmt = $conn->prepare("UPDATE tbl_staff SET status = 0 WHERE id IN ($placeholders)");
						$stmt->execute($staffIds);
					} catch (Throwable $fallbackError) {
						$msg2 = 'staff fallback block failed: ' . $fallbackError->getMessage();
						error_log('[app_reset_school_people_data] ' . $msg2);
						$summary['warnings'][] = $msg2;
					}
				} else {
					$summary['warnings'][] = 'staff fallback unavailable: status column missing';
				}
			}
		}

		$remainingStudents = $countExistingIds('tbl_students', 'id', $studentIds);
		$summary['students_removed'] = max(0, count($studentIds) - $remainingStudents);
		if (!empty($studentIds) && app_table_exists($conn, 'tbl_students') && app_column_exists($conn, 'tbl_students', 'status')) {
			$placeholders = implode(',', array_fill(0, count($studentIds), '?'));
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE id IN ({$placeholders}) AND status = 0");
			$stmt->execute($studentIds);
			$summary['students_blocked'] = (int)$stmt->fetchColumn();
		}

		$remainingStaff = $countExistingIds('tbl_staff', 'id', $staffIds);
		$summary['staff_removed'] = max(0, count($staffIds) - $remainingStaff);
		if (!empty($staffIds) && app_table_exists($conn, 'tbl_staff') && app_column_exists($conn, 'tbl_staff', 'status')) {
			$placeholders = implode(',', array_fill(0, count($staffIds), '?'));
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_staff WHERE id IN ({$placeholders}) AND status = 0");
			$stmt->execute($staffIds);
			$summary['staff_blocked'] = (int)$stmt->fetchColumn();
		}

		if ($parentCount > 0 && app_table_exists($conn, 'tbl_parents')) {
			$remainingParents = (int)$conn->query("SELECT COUNT(*) FROM tbl_parents")->fetchColumn();
			$summary['parents_removed'] = max(0, $parentCount - $remainingParents);
		}

		$conn->commit();
		return $summary;
	} catch (Throwable $e) {
		if ($conn->inTransaction()) {
			$conn->rollBack();
		}
		throw $e;
	}
}

function app_delete_subject(PDO $conn, int $id): void
{
	if ($id < 1) {
		return;
	}

	$singleArg = [$id];
	$cleanup = [
		['tbl_subject_class_assignments', 'subject_id', 'DELETE FROM tbl_subject_class_assignments WHERE subject_id = ?'],
		['tbl_teacher_assignments', 'subject_id', 'DELETE FROM tbl_teacher_assignments WHERE subject_id = ?'],
		['tbl_subject_weights', 'subject_id', 'DELETE FROM tbl_subject_weights WHERE subject_id = ?'],
		['tbl_cbc_strands', 'subject_id', 'DELETE FROM tbl_cbc_strands WHERE subject_id = ?'],
		['tbl_cbc_mark_submissions', 'subject_id', 'DELETE FROM tbl_cbc_mark_submissions WHERE subject_id = ?'],
		['tbl_validation_issues', 'subject_id', 'DELETE FROM tbl_validation_issues WHERE subject_id = ?'],
		['tbl_insights_alerts', 'subject_id', 'DELETE FROM tbl_insights_alerts WHERE subject_id = ?'],
		['tbl_exam_subjects', 'subject_id', 'DELETE FROM tbl_exam_subjects WHERE subject_id = ?'],
		['tbl_exam_schedule', 'subject_id', 'DELETE FROM tbl_exam_schedule WHERE subject_id = ?'],
		['tbl_school_timetable', 'subject_id', 'DELETE FROM tbl_school_timetable WHERE subject_id = ?'],
		['tbl_courses', 'subject_id', 'DELETE FROM tbl_courses WHERE subject_id = ?'],
		['tbl_report_card_subjects', 'subject_id', 'DELETE FROM tbl_report_card_subjects WHERE subject_id = ?'],
		['tbl_attendance_sessions', 'subject_id', 'DELETE FROM tbl_attendance_sessions WHERE subject_id = ?'],
		['tbl_subject_combinations', 'subject', 'DELETE FROM tbl_subject_combinations WHERE subject = ?'],
		['tbl_subject_combinations', 'subject_id', 'DELETE FROM tbl_subject_combinations WHERE subject_id = ?'],
	];
	foreach ($cleanup as $rule) {
		if (app_table_exists($conn, $rule[0]) && app_column_exists($conn, $rule[0], $rule[1])) {
			$stmt = $conn->prepare($rule[2]);
			$stmt->execute($singleArg);
		}
	}

	$stmt = $conn->prepare("DELETE FROM tbl_subjects WHERE id = ?");
	$stmt->execute([$id]);
}

function app_delete_class(PDO $conn, int $id): array
{
	if ($id < 1) {
		return [false, 'Invalid class selected.'];
	}

	if (app_table_exists($conn, 'tbl_students')) {
		$studentClassColumn = null;
		if (app_column_exists($conn, 'tbl_students', 'class')) {
			$studentClassColumn = 'class';
		} elseif (app_column_exists($conn, 'tbl_students', 'class_id')) {
			$studentClassColumn = 'class_id';
		}
		if ($studentClassColumn !== null) {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE {$studentClassColumn} = ?");
			$stmt->execute([$id]);
			if ((int)$stmt->fetchColumn() > 0) {
				return [false, 'This class still has students. Move or delete the students first.'];
			}
		}
	}

	$cleanup = [
		['tbl_subject_class_assignments', 'class_id', 'DELETE FROM tbl_subject_class_assignments WHERE class_id = ?'],
		['tbl_teacher_assignments', 'class_id', 'DELETE FROM tbl_teacher_assignments WHERE class_id = ?'],
		['tbl_results_locks', 'class_id', 'DELETE FROM tbl_results_locks WHERE class_id = ?'],
		['tbl_exam_schedule', 'class_id', 'DELETE FROM tbl_exam_schedule WHERE class_id = ?'],
		['tbl_exams', 'class_id', 'DELETE FROM tbl_exams WHERE class_id = ?'],
		['tbl_attendance_sessions', 'class_id', 'DELETE FROM tbl_attendance_sessions WHERE class_id = ?'],
		['tbl_courses', 'class_id', 'DELETE FROM tbl_courses WHERE class_id = ?'],
		['tbl_fee_structures', 'class_id', 'DELETE FROM tbl_fee_structures WHERE class_id = ?'],
		['tbl_validation_issues', 'class_id', 'DELETE FROM tbl_validation_issues WHERE class_id = ?'],
		['tbl_insights_alerts', 'class_id', 'DELETE FROM tbl_insights_alerts WHERE class_id = ?'],
		['tbl_notifications', 'class_id', 'DELETE FROM tbl_notifications WHERE class_id = ?'],
		['tbl_school_timetable', 'class_id', 'DELETE FROM tbl_school_timetable WHERE class_id = ?'],
		['tbl_class_teachers', 'class_id', 'DELETE FROM tbl_class_teachers WHERE class_id = ?'],
		['tbl_exam_mark_submissions', 'class_id', 'DELETE FROM tbl_exam_mark_submissions WHERE class_id = ?'],
		['tbl_cbc_mark_submissions', 'class_id', 'DELETE FROM tbl_cbc_mark_submissions WHERE class_id = ?'],
	];
	foreach ($cleanup as $rule) {
		if (app_table_exists($conn, $rule[0]) && app_column_exists($conn, $rule[0], $rule[1])) {
			$sp = app_tx_savepoint_begin($conn, 'drop_class_' . $rule[0]);
			try {
				$stmt = $conn->prepare($rule[2]);
				$stmt->execute([$id]);
				app_tx_savepoint_release($conn, $sp);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $sp);
				error_log('[app_delete_class] cleanup failed for ' . $rule[0] . ': ' . $e->getMessage());
				return [false, 'Unable to delete class right now. Remove linked records first or try again.'];
			}
		}
	}

	if (app_table_exists($conn, 'tbl_subject_combinations') && app_column_exists($conn, 'tbl_subject_combinations', 'id') && app_column_exists($conn, 'tbl_subject_combinations', 'class')) {
		$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$classList = app_unserialize((string)($row['class'] ?? ''));
			if (empty($classList)) {
				continue;
			}
			$filtered = array_values(array_filter($classList, function ($classId) use ($id) {
				return (string)$classId !== (string)$id;
			}));
			$sp = app_tx_savepoint_begin($conn, 'drop_class_subject_combinations');
			try {
				if (empty($filtered)) {
					$delete = $conn->prepare("DELETE FROM tbl_subject_combinations WHERE id = ?");
					$delete->execute([(int)$row['id']]);
				} else {
					$update = $conn->prepare("UPDATE tbl_subject_combinations SET class = ? WHERE id = ?");
					$update->execute([serialize($filtered), (int)$row['id']]);
				}
				app_tx_savepoint_release($conn, $sp);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $sp);
				error_log('[app_delete_class] subject combination cleanup failed: ' . $e->getMessage());
				return [false, 'Unable to delete class right now. Remove linked records first or try again.'];
			}
		}
	}

	$sp = app_tx_savepoint_begin($conn, 'drop_class_final_delete');
	try {
		$stmt = $conn->prepare("DELETE FROM tbl_classes WHERE id = ?");
		$stmt->execute([$id]);
		app_tx_savepoint_release($conn, $sp);
	} catch (Throwable $e) {
		app_tx_savepoint_rollback($conn, $sp);
		error_log('[app_delete_class] class delete failed for id ' . $id . ': ' . $e->getMessage());
		return [false, 'Unable to delete class right now. Remove linked records first or try again.'];
	}
	return [true, 'Class deleted successfully.'];
}

function app_force_delete_class(PDO $conn, int $id): bool
{
	if ($id < 1 || !app_table_exists($conn, 'tbl_classes')) {
		return false;
	}

	$sp = app_tx_savepoint_begin($conn, 'force_drop_class');
	try {
		if (app_table_exists($conn, 'tbl_students')) {
			$studentClassColumn = null;
			if (app_column_exists($conn, 'tbl_students', 'class')) {
				$studentClassColumn = 'class';
			} elseif (app_column_exists($conn, 'tbl_students', 'class_id')) {
				$studentClassColumn = 'class_id';
			}
			if ($studentClassColumn !== null) {
				$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE {$studentClassColumn} = ?");
				$stmt->execute([$id]);
				$studentIds = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
				if (!empty($studentIds)) {
					app_delete_students($conn, $studentIds);
				}
			}
		}

		$cleanup = [
			['tbl_subject_class_assignments', 'class_id', 'DELETE FROM tbl_subject_class_assignments WHERE class_id = ?'],
			['tbl_teacher_assignments', 'class_id', 'DELETE FROM tbl_teacher_assignments WHERE class_id = ?'],
			['tbl_results_locks', 'class_id', 'DELETE FROM tbl_results_locks WHERE class_id = ?'],
			['tbl_exam_schedule', 'class_id', 'DELETE FROM tbl_exam_schedule WHERE class_id = ?'],
			['tbl_exams', 'class_id', 'DELETE FROM tbl_exams WHERE class_id = ?'],
			['tbl_attendance_sessions', 'class_id', 'DELETE FROM tbl_attendance_sessions WHERE class_id = ?'],
			['tbl_courses', 'class_id', 'DELETE FROM tbl_courses WHERE class_id = ?'],
			['tbl_fee_structures', 'class_id', 'DELETE FROM tbl_fee_structures WHERE class_id = ?'],
			['tbl_validation_issues', 'class_id', 'DELETE FROM tbl_validation_issues WHERE class_id = ?'],
			['tbl_insights_alerts', 'class_id', 'DELETE FROM tbl_insights_alerts WHERE class_id = ?'],
			['tbl_notifications', 'class_id', 'DELETE FROM tbl_notifications WHERE class_id = ?'],
			['tbl_school_timetable', 'class_id', 'DELETE FROM tbl_school_timetable WHERE class_id = ?'],
			['tbl_class_teachers', 'class_id', 'DELETE FROM tbl_class_teachers WHERE class_id = ?'],
			['tbl_exam_mark_submissions', 'class_id', 'DELETE FROM tbl_exam_mark_submissions WHERE class_id = ?'],
			['tbl_cbc_mark_submissions', 'class_id', 'DELETE FROM tbl_cbc_mark_submissions WHERE class_id = ?'],
			['tbl_report_cards', 'class_id', 'DELETE FROM tbl_report_cards WHERE class_id = ?'],
			['tbl_attendance_records', 'class_id', 'DELETE FROM tbl_attendance_records WHERE class_id = ?'],
		];
		foreach ($cleanup as $rule) {
			if (app_table_exists($conn, $rule[0]) && app_column_exists($conn, $rule[0], $rule[1])) {
				try {
					$stmt = $conn->prepare($rule[2]);
					$stmt->execute([$id]);
				} catch (Throwable $e) {
					error_log('[app_force_delete_class] cleanup failed for ' . $rule[0] . ': ' . $e->getMessage());
				}
			}
		}

		if (app_table_exists($conn, 'tbl_subject_combinations') && app_column_exists($conn, 'tbl_subject_combinations', 'id') && app_column_exists($conn, 'tbl_subject_combinations', 'class')) {
			try {
				$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations");
				$stmt->execute();
				foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
					$classList = app_unserialize((string)($row['class'] ?? ''));
					if (empty($classList)) {
						continue;
					}
					$filtered = array_values(array_filter($classList, function ($classId) use ($id) {
						return (string)$classId !== (string)$id;
					}));
					if (empty($filtered)) {
						$delete = $conn->prepare("DELETE FROM tbl_subject_combinations WHERE id = ?");
						$delete->execute([(int)$row['id']]);
					} else {
						$update = $conn->prepare("UPDATE tbl_subject_combinations SET class = ? WHERE id = ?");
						$update->execute([serialize($filtered), (int)$row['id']]);
					}
				}
			} catch (Throwable $e) {
				error_log('[app_force_delete_class] subject combination cleanup failed: ' . $e->getMessage());
			}
		}

		$stmt = $conn->prepare("DELETE FROM tbl_classes WHERE id = ?");
		$stmt->execute([$id]);
		app_tx_savepoint_release($conn, $sp);
		return true;
	} catch (Throwable $e) {
		app_tx_savepoint_rollback($conn, $sp);
		error_log('[app_force_delete_class] class delete failed for id ' . $id . ': ' . $e->getMessage());
		return false;
	}
}

function app_class_name_parts(string $name): array
{
	$name = trim($name);
	if ($name === '') {
		return ['grade' => '', 'stream' => ''];
	}
	$parts = preg_split('/\s+/', $name);
	if (!$parts || count($parts) < 2) {
		return ['grade' => $name, 'stream' => ''];
	}
	$stream = array_pop($parts);
	return [
		'grade' => trim(implode(' ', $parts)),
		'stream' => trim((string)$stream),
	];
}

function app_cbc_canonical_class_name(string $className): string
{
	$raw = trim($className);
	if ($raw === '') {
		return '';
	}

	$upper = strtoupper($raw);
	$compact = preg_replace('/[^A-Z0-9]/', '', $upper);
	if (!is_string($compact)) {
		$compact = '';
	}

	$hasPP1 = (strpos($compact, 'PP1') !== false) || (strpos($compact, 'PREPRIMARY1') !== false);
	$hasPP2 = (strpos($compact, 'PP2') !== false) || (strpos($compact, 'PREPRIMARY2') !== false);
	if ($hasPP1 && !$hasPP2) {
		return 'PP1';
	}
	if ($hasPP2 && !$hasPP1) {
		return 'PP2';
	}

	if (preg_match('/GRADE\s*([1-9])\b/i', $raw, $m)) {
		return 'Grade ' . (int)$m[1];
	}
	if (preg_match('/\b([1-9])\b/', $raw, $m)) {
		$n = (int)$m[1];
		if ($n >= 1 && $n <= 9) {
			return 'Grade ' . $n;
		}
	}

	return '';
}

function app_cbc_class_band(string $className): string
{
	$canonical = app_cbc_canonical_class_name($className);
	if (in_array($canonical, ['PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3'], true)) {
		return 'lower_primary';
	}
	if (in_array($canonical, ['Grade 4', 'Grade 5', 'Grade 6'], true)) {
		return 'upper_primary';
	}
	if (in_array($canonical, ['Grade 7', 'Grade 8', 'Grade 9'], true)) {
		return 'junior_secondary';
	}
	return '';
}

function app_class_band_by_id(PDO $conn, int $classId): string
{
	if ($classId < 1 || !app_table_exists($conn, 'tbl_classes') || !app_column_exists($conn, 'tbl_classes', 'id') || !app_column_exists($conn, 'tbl_classes', 'name')) {
		return '';
	}
	try {
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		return app_cbc_class_band((string)$stmt->fetchColumn());
	} catch (Throwable $e) {
		return '';
	}
}

function app_cbc_jss_choice_catalog(): array
{
	return [
		'language' => ['Kiswahili', 'Kenyan Sign Language'],
		'religion' => ['CRE', 'IRE', 'HRE'],
		'optional' => ['Computer Science / ICT', 'Home Science', 'French', 'German', 'Pre-Technical and Pre-Career Education'],
	];
}

function app_subject_id_map_by_names(PDO $conn, array $names): array
{
	$names = array_values(array_unique(array_filter(array_map('trim', $names))));
	if (empty($names) || !app_table_exists($conn, 'tbl_subjects') || !app_column_exists($conn, 'tbl_subjects', 'id') || !app_column_exists($conn, 'tbl_subjects', 'name')) {
		return [];
	}
	$placeholders = implode(',', array_fill(0, count($names), '?'));
	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects WHERE name IN ($placeholders)");
	$stmt->execute($names);
	$map = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$map[(string)$row['name']] = (int)$row['id'];
	}
	return $map;
}

function app_cbc_jss_choice_id_map(PDO $conn): array
{
	$catalog = app_cbc_jss_choice_catalog();

	// Auto-discover extra optional subjects from current Grade 7-9 class assignments.
	if (
		app_table_exists($conn, 'tbl_classes') && app_column_exists($conn, 'tbl_classes', 'id') && app_column_exists($conn, 'tbl_classes', 'name') &&
		app_table_exists($conn, 'tbl_subject_class_assignments') && app_column_exists($conn, 'tbl_subject_class_assignments', 'class_id') && app_column_exists($conn, 'tbl_subject_class_assignments', 'subject_id') &&
		app_table_exists($conn, 'tbl_subjects') && app_column_exists($conn, 'tbl_subjects', 'id') && app_column_exists($conn, 'tbl_subjects', 'name')
	) {
		try {
			$jssClassIds = [];
			$stmt = $conn->query("SELECT id, name FROM tbl_classes");
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $classRow) {
				$name = (string)($classRow['name'] ?? '');
				$parts = app_class_name_parts($name);
				$base = in_array($name, ['Grade 7', 'Grade 8', 'Grade 9'], true) ? $name : (string)($parts['grade'] ?? '');
				if (in_array($base, ['Grade 7', 'Grade 8', 'Grade 9'], true)) {
					$jssClassIds[] = (int)$classRow['id'];
				}
			}

			if (!empty($jssClassIds)) {
				$placeholders = implode(',', array_fill(0, count($jssClassIds), '?'));
				$stmt = $conn->prepare("SELECT DISTINCT s.name
					FROM tbl_subject_class_assignments sc
					JOIN tbl_subjects s ON s.id = sc.subject_id
					WHERE sc.class_id IN ($placeholders)
					ORDER BY s.name");
				$stmt->execute($jssClassIds);

				$nonOptional = [
					'English',
					'Kiswahili',
					'Kenyan Sign Language',
					'Mathematics',
					'Integrated Science',
					'Social Studies',
					'Religious Education',
					'CRE',
					'IRE',
					'HRE',
					'Business Studies',
					'Agriculture',
					'Life Skills Education',
					'Sports & Physical Education',
					'Visual Arts',
					'Performing Arts',
				];
				$optional = array_values(array_unique($catalog['optional'] ?? []));
				foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $subjectName) {
					$name = trim((string)$subjectName);
					if ($name === '' || in_array($name, $nonOptional, true)) {
						continue;
					}
					if (!in_array($name, $optional, true)) {
						$optional[] = $name;
					}
				}
				$catalog['optional'] = $optional;
			}
		} catch (Throwable $e) {
			// Keep static catalog if auto-discovery fails.
		}
	}

	$map = [];
	foreach ($catalog as $choiceType => $names) {
		$ids = app_subject_id_map_by_names($conn, $names);
		$map[$choiceType] = [];
		foreach ($names as $name) {
			if (isset($ids[$name])) {
				$map[$choiceType][(int)$ids[$name]] = $name;
			}
		}
	}
	return $map;
}

function app_ensure_student_subject_choices_table(PDO $conn): void
{
	if (app_table_exists($conn, 'tbl_student_subject_choices')) {
		return;
	}
	if (!app_table_exists($conn, 'tbl_students') || !app_table_exists($conn, 'tbl_subjects')) {
		return;
	}

	if (DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_student_subject_choices (
			id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
			student_id varchar(20) NOT NULL,
			choice_type varchar(30) NOT NULL,
			subject_id integer NOT NULL,
			created_by integer NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE (student_id, choice_type, subject_id),
			CONSTRAINT tbl_student_subject_choices_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_subject_choices_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
		)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_student_subject_choices (
			id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
			student_id varchar(20) COLLATE utf8mb4_general_ci NOT NULL,
			choice_type varchar(30) NOT NULL,
			subject_id int NOT NULL,
			created_by int NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY tbl_student_subject_choices_unique (student_id, choice_type, subject_id),
			CONSTRAINT tbl_student_subject_choices_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_student_subject_choices_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE
		)");
	}
}

function app_student_subject_choice_summary(PDO $conn, string $studentId): array
{
	$summary = [
		'language' => 0,
		'religion' => 0,
		'optional' => [],
	];
	$studentId = trim($studentId);
	if ($studentId === '' || !app_table_exists($conn, 'tbl_student_subject_choices') || !app_column_exists($conn, 'tbl_student_subject_choices', 'student_id')) {
		return $summary;
	}
	if (!app_column_exists($conn, 'tbl_student_subject_choices', 'choice_type') || !app_column_exists($conn, 'tbl_student_subject_choices', 'subject_id')) {
		return $summary;
	}
	try {
		$stmt = $conn->prepare("SELECT choice_type, subject_id FROM tbl_student_subject_choices WHERE student_id = ? ORDER BY id");
		$stmt->execute([$studentId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$choiceType = (string)($row['choice_type'] ?? '');
			$subjectId = (int)($row['subject_id'] ?? 0);
			if ($choiceType === 'language') {
				$summary['language'] = $subjectId;
			} elseif ($choiceType === 'religion') {
				$summary['religion'] = $subjectId;
			} elseif ($choiceType === 'optional' && $subjectId > 0) {
				$summary['optional'][] = $subjectId;
			}
		}
	} catch (Throwable $e) {
		return $summary;
	}
	$summary['optional'] = array_values(array_unique(array_filter(array_map('intval', $summary['optional']))));
	return $summary;
}

function app_save_student_subject_choices(PDO $conn, string $studentId, ?int $languageSubjectId = null, ?int $religionSubjectId = null, array $optionalSubjectIds = [], ?int $userId = null): void
{
	$studentId = trim($studentId);
	if ($studentId === '') {
		return;
	}
	app_ensure_student_subject_choices_table($conn);
	if (!app_table_exists($conn, 'tbl_student_subject_choices')) {
		return;
	}
	$catalog = app_cbc_jss_choice_id_map($conn);
	$allowedLanguageIds = array_keys($catalog['language'] ?? []);
	$allowedReligionIds = array_keys($catalog['religion'] ?? []);
	$allowedOptionalIds = array_keys($catalog['optional'] ?? []);
	$languageSubjectId = in_array((int)$languageSubjectId, $allowedLanguageIds, true) ? (int)$languageSubjectId : (int)($allowedLanguageIds[0] ?? 0);
	$religionSubjectId = in_array((int)$religionSubjectId, $allowedReligionIds, true) ? (int)$religionSubjectId : (int)($allowedReligionIds[0] ?? 0);
	$optionalSubjectIds = array_values(array_unique(array_filter(array_map('intval', $optionalSubjectIds), function ($subjectId) use ($allowedOptionalIds) {
		return in_array((int)$subjectId, $allowedOptionalIds, true);
	})));

	$delete = $conn->prepare("DELETE FROM tbl_student_subject_choices WHERE student_id = ?");
	$delete->execute([$studentId]);

	$hasCreatedBy = app_column_exists($conn, 'tbl_student_subject_choices', 'created_by');
	if ($languageSubjectId > 0) {
		if ($hasCreatedBy) {
			$stmt = $conn->prepare("INSERT INTO tbl_student_subject_choices (student_id, choice_type, subject_id, created_by) VALUES (?,?,?,?)");
			$stmt->execute([$studentId, 'language', $languageSubjectId, $userId ? (int)$userId : null]);
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_student_subject_choices (student_id, choice_type, subject_id) VALUES (?,?,?)");
			$stmt->execute([$studentId, 'language', $languageSubjectId]);
		}
	}
	if ($religionSubjectId > 0) {
		if ($hasCreatedBy) {
			$stmt = $conn->prepare("INSERT INTO tbl_student_subject_choices (student_id, choice_type, subject_id, created_by) VALUES (?,?,?,?)");
			$stmt->execute([$studentId, 'religion', $religionSubjectId, $userId ? (int)$userId : null]);
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_student_subject_choices (student_id, choice_type, subject_id) VALUES (?,?,?)");
			$stmt->execute([$studentId, 'religion', $religionSubjectId]);
		}
	}
	if (!empty($optionalSubjectIds)) {
		if ($hasCreatedBy) {
			$stmt = $conn->prepare("INSERT INTO tbl_student_subject_choices (student_id, choice_type, subject_id, created_by) VALUES (?,?,?,?)");
			foreach ($optionalSubjectIds as $subjectId) {
				$stmt->execute([$studentId, 'optional', (int)$subjectId, $userId ? (int)$userId : null]);
			}
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_student_subject_choices (student_id, choice_type, subject_id) VALUES (?,?,?)");
			foreach ($optionalSubjectIds as $subjectId) {
				$stmt->execute([$studentId, 'optional', (int)$subjectId]);
			}
		}
	}
}

function app_clear_student_subject_choices(PDO $conn, string $studentId): void
{
	$studentId = trim($studentId);
	if ($studentId === '' || !app_table_exists($conn, 'tbl_student_subject_choices')) {
		return;
	}
	try {
		$stmt = $conn->prepare("DELETE FROM tbl_student_subject_choices WHERE student_id = ?");
		$stmt->execute([$studentId]);
	} catch (Throwable $e) {
		// best effort only
	}
}

function app_build_class_name(string $grade, string $stream = '', string $fallback = ''): string
{
	$grade = trim($grade);
	$stream = trim($stream);
	$fallback = trim($fallback);
	if ($grade === '' && $fallback !== '') {
		return ucfirst($fallback);
	}
	if ($stream === '') {
		return ucfirst($grade);
	}
	return trim(ucfirst($grade) . ' ' . strtoupper($stream));
}

function app_cbc_default_classes(): array
{
	return [
		'PP1',
		'PP2',
		'Grade 1',
		'Grade 2',
		'Grade 3',
		'Grade 4',
		'Grade 5',
		'Grade 6',
		'Grade 7',
		'Grade 8',
		'Grade 9',
	];
}

function app_cbc_default_subject_catalog(): array
{
	return [
		['name' => 'Literacy Activities', 'level' => 'Lower Primary', 'category' => 'Core'],
		['name' => 'Kiswahili Language Activities', 'level' => 'Lower Primary', 'category' => 'Core'],
		['name' => 'English Language Activities', 'level' => 'Lower Primary', 'category' => 'Core'],
		['name' => 'Mathematical Activities', 'level' => 'Lower Primary', 'category' => 'Core'],
		['name' => 'Environmental Activities', 'level' => 'Lower Primary', 'category' => 'Core'],
		['name' => 'Psychomotor & Creative Activities', 'level' => 'Lower Primary', 'category' => 'Core'],
		['name' => 'Religious Education Activities', 'level' => 'Lower Primary', 'category' => 'Optional'],
		['name' => 'English', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'Kiswahili', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'Kenyan Sign Language', 'level' => 'Upper Primary', 'category' => 'Optional'],
		['name' => 'Mathematics', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'Science and Technology', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'Social Studies', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'Creative Arts', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'Physical and Health Education', 'level' => 'Upper Primary', 'category' => 'Core'],
		['name' => 'CRE', 'level' => 'Upper Primary', 'category' => 'Religious'],
		['name' => 'IRE', 'level' => 'Upper Primary', 'category' => 'Religious'],
		['name' => 'HRE', 'level' => 'Upper Primary', 'category' => 'Religious'],
		['name' => 'Integrated Science', 'level' => 'Junior Secondary', 'category' => 'Core'],
		['name' => 'Religious Education', 'level' => 'Junior Secondary', 'category' => 'Core'],
		['name' => 'CRE', 'level' => 'Junior Secondary', 'category' => 'Religious'],
		['name' => 'IRE', 'level' => 'Junior Secondary', 'category' => 'Religious'],
		['name' => 'HRE', 'level' => 'Junior Secondary', 'category' => 'Religious'],
		['name' => 'Business Studies', 'level' => 'Junior Secondary', 'category' => 'Core'],
		['name' => 'Agriculture', 'level' => 'Junior Secondary', 'category' => 'Core'],
		['name' => 'Life Skills Education', 'level' => 'Junior Secondary', 'category' => 'Core'],
		['name' => 'Sports & Physical Education', 'level' => 'Junior Secondary', 'category' => 'Core'],
		['name' => 'Visual Arts', 'level' => 'Junior Secondary', 'category' => 'Creative'],
		['name' => 'Performing Arts', 'level' => 'Junior Secondary', 'category' => 'Creative'],
		['name' => 'Pre-Technical and Pre-Career Education', 'level' => 'Junior Secondary', 'category' => 'Optional'],
		['name' => 'Computer Science / ICT', 'level' => 'Junior Secondary', 'category' => 'Optional'],
		['name' => 'Home Science', 'level' => 'Junior Secondary', 'category' => 'Optional'],
		['name' => 'Kenyan Sign Language', 'level' => 'Junior Secondary', 'category' => 'Optional'],
		['name' => 'French', 'level' => 'Junior Secondary', 'category' => 'Optional'],
		['name' => 'German', 'level' => 'Junior Secondary', 'category' => 'Optional'],
	];
}

function app_cbc_default_subjects_for_class(string $className): array
{
	$className = app_cbc_canonical_class_name($className);
	$lower = [
		'PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3',
	];
	$upper = [
		'Grade 4', 'Grade 5', 'Grade 6',
	];
	$junior = [
		'Grade 7', 'Grade 8', 'Grade 9',
	];
	$juniorCore = [
		'English',
		'Kiswahili',
		'Mathematics',
		'Integrated Science',
		'Social Studies',
		'Religious Education',
		'Business Studies',
		'Agriculture',
		'Life Skills Education',
		'Sports & Physical Education',
		'Visual Arts',
		'Performing Arts',
	];
	$juniorOptional = [
		'Kenyan Sign Language',
		'Pre-Technical and Pre-Career Education',
		'Computer Science / ICT',
		'Home Science',
		'French',
		'German',
	];

	if (in_array($className, $lower, true)) {
		return [
			'Literacy Activities',
			'Kiswahili Language Activities',
			'English Language Activities',
			'Mathematical Activities',
			'Environmental Activities',
			'Psychomotor & Creative Activities',
			'Religious Education Activities',
		];
	}

	if (in_array($className, $upper, true)) {
		return [
			'English',
			'Kiswahili',
			'Mathematics',
			'Science and Technology',
			'Social Studies',
			'Creative Arts',
			'Physical and Health Education',
			'CRE',
			'IRE',
			'HRE',
			'Kenyan Sign Language',
		];
	}

	if (in_array($className, $junior, true)) {
		return array_merge($juniorCore, $juniorOptional);
	}

	return [];
}

function app_apply_cbc_curriculum_defaults(PDO $conn, ?int $userId = null): array
{
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	try {
		app_ensure_class_teachers_table($conn);
		app_ensure_student_subject_choices_table($conn);
	} catch (Throwable $e) {
		error_log('[app_apply_cbc_curriculum_defaults] class teachers table ensure failed: ' . $e->getMessage());
	}

	$subjectNames = array_map(function ($row) {
		return (string)$row['name'];
	}, app_cbc_default_subject_catalog());
	$classNames = app_cbc_default_classes();
	$subjectIdMap = [];
	$summary = [
		'subjects' => 0,
		'classes' => 0,
		'assignments' => 0,
		'removed_subjects' => 0,
		'removed_classes' => 0,
		'skipped_subjects' => 0,
		'skipped_classes' => 0,
		'errors' => [],
	];

	if (!app_table_exists($conn, 'tbl_subjects') || !app_column_exists($conn, 'tbl_subjects', 'id') || !app_column_exists($conn, 'tbl_subjects', 'name')) {
		$summary['errors'][] = 'Unable to sync CBC subjects because tbl_subjects schema is incomplete.';
		return $summary;
	}
	if (!app_table_exists($conn, 'tbl_classes') || !app_column_exists($conn, 'tbl_classes', 'id') || !app_column_exists($conn, 'tbl_classes', 'name')) {
		$summary['errors'][] = 'Unable to sync CBC classes because tbl_classes schema is incomplete.';
		return $summary;
	}
	$classHasRegistrationDate = app_column_exists($conn, 'tbl_classes', 'registration_date');

	$conn->beginTransaction();
	try {
		foreach ($subjectNames as $subjectName) {
			$savepoint = app_tx_savepoint_begin($conn, 'cbc_subject_seed');
			try {
				$stmt = $conn->prepare("SELECT id FROM tbl_subjects WHERE LOWER(name) = LOWER(?) LIMIT 1");
				$stmt->execute([$subjectName]);
				$subjectId = (int)$stmt->fetchColumn();
				if ($subjectId < 1) {
					if (defined('DBDriver') && DBDriver === 'pgsql') {
						$stmt = $conn->prepare("INSERT INTO tbl_subjects (name) VALUES (?) RETURNING id");
						$stmt->execute([$subjectName]);
						$subjectId = (int)$stmt->fetchColumn();
					} else {
						$stmt = $conn->prepare("INSERT INTO tbl_subjects (name) VALUES (?)");
						$stmt->execute([$subjectName]);
						$subjectId = (int)$conn->lastInsertId();
					}
					$summary['subjects']++;
				}
				$subjectIdMap[$subjectName] = $subjectId;
				app_tx_savepoint_release($conn, $savepoint);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $savepoint);
				error_log('[app_apply_cbc_curriculum_defaults] subject seed failed for ' . $subjectName . ': ' . $e->getMessage());
				$summary['errors'][] = 'Skipped subject "' . $subjectName . '" due to schema mismatch.';
			}
		}

		$classIdMap = [];
		$existingClassByCanonical = [];
		$existingClassNameById = [];
		$existingClassRows = $conn->query("SELECT id, name FROM tbl_classes")->fetchAll(PDO::FETCH_ASSOC);
		foreach ($existingClassRows as $existingClassRow) {
			$existingId = (int)($existingClassRow['id'] ?? 0);
			$existingName = trim((string)($existingClassRow['name'] ?? ''));
			if ($existingId < 1 || $existingName === '') {
				continue;
			}
			$existingClassNameById[$existingId] = $existingName;
			$canonical = app_cbc_canonical_class_name($existingName);
			if ($canonical !== '' && !isset($existingClassByCanonical[$canonical])) {
				$existingClassByCanonical[$canonical] = $existingId;
			}
		}

		foreach ($classNames as $className) {
			$savepoint = app_tx_savepoint_begin($conn, 'cbc_class_seed');
			try {
				$classId = (int)($existingClassByCanonical[$className] ?? 0);
				if ($classId < 1) {
					$stmt = $conn->prepare("SELECT id FROM tbl_classes WHERE LOWER(name) = LOWER(?) LIMIT 1");
					$stmt->execute([$className]);
					$classId = (int)$stmt->fetchColumn();
				}
				if ($classId < 1) {
					if (defined('DBDriver') && DBDriver === 'pgsql') {
						if ($classHasRegistrationDate) {
							$stmt = $conn->prepare("INSERT INTO tbl_classes (name, registration_date) VALUES (?, ?) RETURNING id");
							$stmt->execute([$className, date('Y-m-d G:i:s')]);
						} else {
							$stmt = $conn->prepare("INSERT INTO tbl_classes (name) VALUES (?) RETURNING id");
							$stmt->execute([$className]);
						}
						$classId = (int)$stmt->fetchColumn();
					} else {
						if ($classHasRegistrationDate) {
							$stmt = $conn->prepare("INSERT INTO tbl_classes (name, registration_date) VALUES (?, ?)");
							$stmt->execute([$className, date('Y-m-d G:i:s')]);
						} else {
							$stmt = $conn->prepare("INSERT INTO tbl_classes (name) VALUES (?)");
							$stmt->execute([$className]);
						}
						$classId = (int)$conn->lastInsertId();
					}
					$summary['classes']++;
				}

				$currentName = trim((string)($existingClassNameById[$classId] ?? ''));
				if ($currentName !== '' && strcasecmp($currentName, $className) !== 0) {
					$renameStmt = $conn->prepare("UPDATE tbl_classes SET name = ? WHERE id = ?");
					$renameStmt->execute([$className, $classId]);
					$existingClassNameById[$classId] = $className;
				}

				$classIdMap[$className] = $classId;
				app_tx_savepoint_release($conn, $savepoint);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $savepoint);
				error_log('[app_apply_cbc_curriculum_defaults] class seed failed for ' . $className . ': ' . $e->getMessage());
				$summary['errors'][] = 'Skipped class "' . $className . '" due to schema mismatch.';
			}
		}

		foreach ($classIdMap as $className => $classId) {
			$subjectIds = [];
			foreach (app_cbc_default_subjects_for_class($className) as $subjectName) {
				if (isset($subjectIdMap[$subjectName])) {
					$subjectIds[] = (int)$subjectIdMap[$subjectName];
				}
			}
			$savepoint = app_tx_savepoint_begin($conn, 'cbc_assignments');
			try {
				app_save_class_subject_assignments($conn, $classId, $subjectIds, $userId);
				$summary['assignments'] += count($subjectIds);
				app_tx_savepoint_release($conn, $savepoint);
			} catch (Throwable $e) {
				app_tx_savepoint_rollback($conn, $savepoint);
				$summary['errors'][] = 'Skipped subject assignment sync for class "' . $className . '" due to linked data mismatch.';
			}
		}

		$subjectRows = [];
		try {
			$subjectRows = $conn->query("SELECT id, name FROM tbl_subjects ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
		} catch (Throwable $e) {
			error_log('[app_apply_cbc_curriculum_defaults] subject cleanup load failed: ' . $e->getMessage());
			$summary['errors'][] = 'Skipped extra-subject cleanup because subjects could not be read.';
		}
		foreach ($subjectRows as $row) {
			$subjectName = (string)$row['name'];
			$subjectId = (int)$row['id'];
			if (in_array($subjectName, $subjectNames, true)) {
				continue;
			}
			$refChecks = [
				['tbl_subject_class_assignments', 'subject_id', 'SELECT COUNT(*) FROM tbl_subject_class_assignments WHERE subject_id = ?'],
				['tbl_teacher_assignments', 'subject_id', 'SELECT COUNT(*) FROM tbl_teacher_assignments WHERE subject_id = ?'],
				['tbl_exam_subjects', 'subject_id', 'SELECT COUNT(*) FROM tbl_exam_subjects WHERE subject_id = ?'],
				['tbl_subject_combinations', 'subject', 'SELECT COUNT(*) FROM tbl_subject_combinations WHERE subject = ?'],
				['tbl_subject_combinations', 'subject_id', 'SELECT COUNT(*) FROM tbl_subject_combinations WHERE subject_id = ?'],
				['tbl_exam_results', 'subject_id', 'SELECT COUNT(*) FROM tbl_exam_results WHERE subject_id = ?'],
				['tbl_courses', 'subject_id', 'SELECT COUNT(*) FROM tbl_courses WHERE subject_id = ?'],
				['tbl_exam_schedule', 'subject_id', 'SELECT COUNT(*) FROM tbl_exam_schedule WHERE subject_id = ?'],
				['tbl_school_timetable', 'subject_id', 'SELECT COUNT(*) FROM tbl_school_timetable WHERE subject_id = ?'],
				['tbl_report_card_subjects', 'subject_id', 'SELECT COUNT(*) FROM tbl_report_card_subjects WHERE subject_id = ?'],
			];
			$inUse = false;
			foreach ($refChecks as $check) {
				if (!app_table_exists($conn, $check[0]) || !app_column_exists($conn, $check[0], $check[1])) {
					continue;
				}
				$checkSavepoint = app_tx_savepoint_begin($conn, 'cbc_subject_refcheck');
				try {
					$stmt = $conn->prepare($check[2]);
					$stmt->execute([$subjectId]);
					if ((int)$stmt->fetchColumn() > 0) {
						$inUse = true;
					}
					app_tx_savepoint_release($conn, $checkSavepoint);
					if ($inUse) {
						break;
					}
				} catch (Throwable $e) {
					app_tx_savepoint_rollback($conn, $checkSavepoint);
					$inUse = true;
					$summary['errors'][] = 'Skipped subject "' . $subjectName . '" because dependencies could not be verified safely.';
					break;
				}
			}
			if (!$inUse) {
				$savepoint = app_tx_savepoint_begin($conn, 'cbc_subject_cleanup');
				try {
					app_delete_subject($conn, $subjectId);
					$summary['removed_subjects']++;
					app_tx_savepoint_release($conn, $savepoint);
				} catch (Throwable $e) {
					app_tx_savepoint_rollback($conn, $savepoint);
					$summary['skipped_subjects']++;
					$summary['errors'][] = 'Skipped subject "' . $subjectName . '" because it is still linked elsewhere.';
				}
			}
		}

		$classRows = [];
		try {
			$classRows = $conn->query("SELECT id, name FROM tbl_classes ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
		} catch (Throwable $e) {
			error_log('[app_apply_cbc_curriculum_defaults] class cleanup load failed: ' . $e->getMessage());
			$summary['errors'][] = 'Skipped extra-class cleanup because classes could not be read.';
		}
		foreach ($classRows as $row) {
			$className = (string)$row['name'];
			$classId = (int)$row['id'];
			$parts = app_class_name_parts($className);
			if (in_array($className, $classNames, true) || in_array($parts['grade'], $classNames, true)) {
				continue;
			}
			$refChecks = [
				['tbl_students', 'class', 'SELECT COUNT(*) FROM tbl_students WHERE class = ?'],
				['tbl_teacher_assignments', 'class_id', 'SELECT COUNT(*) FROM tbl_teacher_assignments WHERE class_id = ?'],
				['tbl_exams', 'class_id', 'SELECT COUNT(*) FROM tbl_exams WHERE class_id = ?'],
				['tbl_school_timetable', 'class_id', 'SELECT COUNT(*) FROM tbl_school_timetable WHERE class_id = ?'],
				['tbl_courses', 'class_id', 'SELECT COUNT(*) FROM tbl_courses WHERE class_id = ?'],
				['tbl_class_teachers', 'class_id', 'SELECT COUNT(*) FROM tbl_class_teachers WHERE class_id = ?'],
				['tbl_exam_schedule', 'class_id', 'SELECT COUNT(*) FROM tbl_exam_schedule WHERE class_id = ?'],
				['tbl_results_locks', 'class_id', 'SELECT COUNT(*) FROM tbl_results_locks WHERE class_id = ?'],
			];
			$inUse = false;
			foreach ($refChecks as $check) {
				if (!app_table_exists($conn, $check[0]) || !app_column_exists($conn, $check[0], $check[1])) {
					continue;
				}
				$checkSavepoint = app_tx_savepoint_begin($conn, 'cbc_class_refcheck');
				try {
					$stmt = $conn->prepare($check[2]);
					$stmt->execute([$classId]);
					if ((int)$stmt->fetchColumn() > 0) {
						$inUse = true;
					}
					app_tx_savepoint_release($conn, $checkSavepoint);
					if ($inUse) {
						break;
					}
				} catch (Throwable $e) {
					app_tx_savepoint_rollback($conn, $checkSavepoint);
					$inUse = true;
					$summary['errors'][] = 'Skipped class "' . $className . '" because dependencies could not be verified safely.';
					break;
				}
			}
			if (!$inUse) {
				$savepoint = app_tx_savepoint_begin($conn, 'cbc_class_cleanup');
				try {
					if (app_force_delete_class($conn, $classId)) {
						$summary['removed_classes']++;
						app_tx_savepoint_release($conn, $savepoint);
					} else {
						app_tx_savepoint_rollback($conn, $savepoint);
						$summary['skipped_classes']++;
						$summary['errors'][] = 'Skipped class "' . $className . '" because it could not be removed safely.';
					}
				} catch (Throwable $e) {
					app_tx_savepoint_rollback($conn, $savepoint);
					$summary['skipped_classes']++;
					$summary['errors'][] = 'Skipped class "' . $className . '" because it is still linked elsewhere.';
				}
			}
		}

		$conn->commit();
		return $summary;
	} catch (Throwable $e) {
		if ($conn->inTransaction()) {
			$conn->rollBack();
		}
		throw $e;
	}
}

function app_cbc_submission_status(PDO $conn, int $termId, int $classId, int $subjectCombinationId): string
{
	if ($termId < 1 || $classId < 1 || $subjectCombinationId < 1) {
		return 'draft';
	}
	if (!app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
		return 'draft';
	}
	try {
		$stmt = $conn->prepare("SELECT status FROM tbl_cbc_mark_submissions WHERE term_id = ? AND class_id = ? AND subject_combination_id = ? LIMIT 1");
		$stmt->execute([$termId, $classId, $subjectCombinationId]);
		$status = (string)$stmt->fetchColumn();
		return $status !== '' ? $status : 'draft';
	} catch (Throwable $e) {
		return 'draft';
	}
}

function app_unserialize($value): array
{
	if (!is_string($value) || $value === '') {
		return [];
	}

	// When importing MySQL dumps into Postgres, strings that contain serialized
	// PHP data may keep backslashes (e.g. \" inside the stored value). Normalize
	// so unserialize works the same as on MySQL.
	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$value = str_replace(['\\"', '\\\\'], ['"', '\\'], $value);
	}

	$decoded = @unserialize($value);
	return is_array($decoded) ? $decoded : [];
}

function app_max_upload_bytes(): int
{
	return 1024 * 1024;
}

function app_validate_upload(array $file, array $allowedExtensions = [], ?int $maxBytes = null): array
{
	$maxBytes = $maxBytes ?? app_max_upload_bytes();
	$hasFile = !empty($file['name']) || !empty($file['tmp_name']);
	if (!$hasFile) {
		return ['ok' => false, 'message' => 'No file uploaded.', 'extension' => ''];
	}

	$error = (int)($file['error'] ?? UPLOAD_ERR_OK);
	if ($error !== UPLOAD_ERR_OK) {
		return ['ok' => false, 'message' => 'Upload failed. Please try again with a smaller file.', 'extension' => ''];
	}

	$size = (int)($file['size'] ?? 0);
	if ($size < 1) {
		return ['ok' => false, 'message' => 'Uploaded file is empty.', 'extension' => ''];
	}
	if ($size > $maxBytes) {
		return ['ok' => false, 'message' => 'Files larger than 1MB are not allowed.', 'extension' => ''];
	}

	$extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
	if ($allowedExtensions && !in_array($extension, $allowedExtensions, true)) {
		return ['ok' => false, 'message' => 'Invalid file type.', 'extension' => $extension];
	}

	return ['ok' => true, 'message' => '', 'extension' => $extension];
}

function app_pdf_image_html(string $relativePath, int $width = 60, int $height = 0, string $alt = ''): string
{
	$relativePath = ltrim(trim($relativePath), '/');
	if ($relativePath === '' || !file_exists($relativePath)) {
		return '';
	}

	$size = $height > 0 ? ' width="'.$width.'" height="'.$height.'"' : ' width="'.$width.'"';
	return '<img src="'.$relativePath.'"'.$size.' alt="'.htmlspecialchars($alt, ENT_QUOTES).'" />';
}

function app_ensure_class_teachers_table(PDO $conn): void
{
	static $done = false;
	if ($done || app_table_exists($conn, 'tbl_class_teachers')) {
		$done = true;
		return;
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_class_teachers (
			id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
			class_id integer NOT NULL,
			teacher_id integer NOT NULL,
			active integer NOT NULL DEFAULT 1,
			created_by integer NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE (class_id),
			CONSTRAINT tbl_class_teachers_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
			CONSTRAINT tbl_class_teachers_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE CASCADE,
			CONSTRAINT tbl_class_teachers_created_by_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_class_teachers (
			id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
			class_id int NOT NULL,
			teacher_id int NOT NULL,
			active int NOT NULL DEFAULT 1,
			created_by int NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY tbl_class_teachers_class_unique (class_id),
			CONSTRAINT tbl_class_teachers_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
			CONSTRAINT tbl_class_teachers_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE CASCADE,
			CONSTRAINT tbl_class_teachers_created_by_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	}

	$done = true;
}

function app_ensure_school_timetable_table(PDO $conn): void
{
	static $done = false;
	if ($done || app_table_exists($conn, 'tbl_school_timetable')) {
		$done = true;
		return;
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_school_timetable (
			id integer GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
			term_id integer NOT NULL,
			class_id integer NOT NULL,
			subject_id integer NOT NULL,
			teacher_id integer NOT NULL,
			day_name varchar(20) NOT NULL,
			session_label varchar(50) NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			room varchar(100) NULL,
			created_by integer NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			CONSTRAINT tbl_school_timetable_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
		$conn->exec("CREATE INDEX IF NOT EXISTS tbl_school_timetable_term_class_idx ON tbl_school_timetable (term_id, class_id, day_name, start_time)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_school_timetable (
			id int NOT NULL AUTO_INCREMENT PRIMARY KEY,
			term_id int NOT NULL,
			class_id int NOT NULL,
			subject_id int NOT NULL,
			teacher_id int NOT NULL,
			day_name varchar(20) NOT NULL,
			session_label varchar(50) NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			room varchar(100) NULL,
			created_by int NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			KEY tbl_school_timetable_term_class_idx (term_id, class_id, day_name, start_time),
			CONSTRAINT tbl_school_timetable_term_fk FOREIGN KEY (term_id) REFERENCES tbl_terms (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_subject_fk FOREIGN KEY (subject_id) REFERENCES tbl_subjects (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_teacher_fk FOREIGN KEY (teacher_id) REFERENCES tbl_staff (id) ON DELETE CASCADE,
			CONSTRAINT tbl_school_timetable_staff_fk FOREIGN KEY (created_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	}

	$done = true;
}

function app_ensure_certificates_table(PDO $conn): void
{
	static $done = false;
	if ($done) {
		return;
	}

	$ensureCertificateColumns = static function () use ($conn): void {
		$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
		$columnSql = [
			'certificate_category' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN certificate_category varchar(50) DEFAULT 'general'" : "ALTER TABLE tbl_certificates ADD COLUMN certificate_category varchar(50) DEFAULT 'general'",
			'mean_score' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN mean_score numeric(5,2) NULL" : "ALTER TABLE tbl_certificates ADD COLUMN mean_score decimal(5,2) NULL",
			'merit_grade' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN merit_grade varchar(1) NULL" : "ALTER TABLE tbl_certificates ADD COLUMN merit_grade varchar(1) NULL",
			'competencies_json' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN competencies_json text NULL" : "ALTER TABLE tbl_certificates ADD COLUMN competencies_json longtext NULL",
			'position_in_class' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN position_in_class integer NULL" : "ALTER TABLE tbl_certificates ADD COLUMN position_in_class int NULL",
			'approved_by' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN approved_by integer NULL" : "ALTER TABLE tbl_certificates ADD COLUMN approved_by int NULL",
			'approved_at' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN approved_at timestamp NULL" : "ALTER TABLE tbl_certificates ADD COLUMN approved_at timestamp NULL",
			'locked' => $isPgsql ? "ALTER TABLE tbl_certificates ADD COLUMN locked boolean NOT NULL DEFAULT false" : "ALTER TABLE tbl_certificates ADD COLUMN locked tinyint(1) NOT NULL DEFAULT 0",
		];

		foreach ($columnSql as $column => $sql) {
			if (!app_column_exists($conn, 'tbl_certificates', $column)) {
				try {
					$conn->exec($sql);
				} catch (Throwable $e) {
					// Ignore migration errors for optional additions.
				}
			}
		}
	};

	if (app_table_exists($conn, 'tbl_certificates')) {
		$ensureCertificateColumns();
		$done = true;
		return;
	}

	if (DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_certificates (
			id integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,
			student_id varchar(64) NOT NULL,
			class_id integer NULL,
			certificate_type varchar(50) NOT NULL,
			title varchar(180) NOT NULL,
			serial_no varchar(80) NOT NULL,
			issue_date date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'issued',
			notes text NULL,
			verification_code varchar(80) NOT NULL,
			cert_hash varchar(128) NOT NULL,
			issued_by integer NULL,
			downloads integer NOT NULL DEFAULT 0,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			CONSTRAINT tbl_certificates_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_certificates_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE SET NULL,
			CONSTRAINT tbl_certificates_staff_fk FOREIGN KEY (issued_by) REFERENCES tbl_staff (id) ON DELETE SET NULL,
			CONSTRAINT tbl_certificates_serial_uk UNIQUE (serial_no),
			CONSTRAINT tbl_certificates_code_uk UNIQUE (verification_code)
		)");
		$conn->exec("CREATE INDEX IF NOT EXISTS tbl_certificates_student_idx ON tbl_certificates (student_id, issue_date DESC)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_certificates (
			id int NOT NULL AUTO_INCREMENT,
			student_id varchar(64) NOT NULL,
			class_id int NULL,
			certificate_type varchar(50) NOT NULL,
			title varchar(180) NOT NULL,
			serial_no varchar(80) NOT NULL,
			issue_date date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'issued',
			notes text NULL,
			verification_code varchar(80) NOT NULL,
			cert_hash varchar(128) NOT NULL,
			issued_by int NULL,
			downloads int NOT NULL DEFAULT 0,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY tbl_certificates_serial_uk (serial_no),
			UNIQUE KEY tbl_certificates_code_uk (verification_code),
			KEY tbl_certificates_student_idx (student_id, issue_date),
			CONSTRAINT tbl_certificates_student_fk FOREIGN KEY (student_id) REFERENCES tbl_students (id) ON DELETE CASCADE,
			CONSTRAINT tbl_certificates_class_fk FOREIGN KEY (class_id) REFERENCES tbl_classes (id) ON DELETE SET NULL,
			CONSTRAINT tbl_certificates_staff_fk FOREIGN KEY (issued_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	}

	$ensureCertificateColumns();

	$done = true;
}

function app_ensure_receipts_table(PDO $conn): void
{
	static $done = false;
	if ($done || app_table_exists($conn, 'tbl_receipts')) {
		$done = true;
		return;
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_receipts (
			id integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,
			payment_id integer NOT NULL,
			receipt_number varchar(80) NOT NULL,
			generated_by integer NULL,
			file_url varchar(255) NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			CONSTRAINT tbl_receipts_payment_fk FOREIGN KEY (payment_id) REFERENCES tbl_payments (id) ON DELETE CASCADE,
			CONSTRAINT tbl_receipts_staff_fk FOREIGN KEY (generated_by) REFERENCES tbl_staff (id) ON DELETE SET NULL,
			CONSTRAINT tbl_receipts_number_uk UNIQUE (receipt_number),
			CONSTRAINT tbl_receipts_payment_uk UNIQUE (payment_id)
		)");
	} else {
		$conn->exec("CREATE TABLE IF NOT EXISTS tbl_receipts (
			id int NOT NULL AUTO_INCREMENT,
			payment_id int NOT NULL,
			receipt_number varchar(80) NOT NULL,
			generated_by int NULL,
			file_url varchar(255) NULL,
			created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY tbl_receipts_number_uk (receipt_number),
			UNIQUE KEY tbl_receipts_payment_uk (payment_id),
			CONSTRAINT tbl_receipts_payment_fk FOREIGN KEY (payment_id) REFERENCES tbl_payments (id) ON DELETE CASCADE,
			CONSTRAINT tbl_receipts_staff_fk FOREIGN KEY (generated_by) REFERENCES tbl_staff (id) ON DELETE SET NULL
		)");
	}

	$done = true;
}

function app_generate_receipt_number(PDO $conn): string
{
	app_ensure_receipts_table($conn);
	$prefix = 'RCPT-' . date('Ymd') . '-';
	for ($i = 0; $i < 12; $i++) {
		$rand = (string)random_int(1000, 9999);
		$receiptNo = $prefix . $rand;
		$stmt = $conn->prepare("SELECT 1 FROM tbl_receipts WHERE receipt_number = ? LIMIT 1");
		$stmt->execute([$receiptNo]);
		if (!$stmt->fetchColumn()) {
			return $receiptNo;
		}
	}
	return $prefix . (string)time();
}

date_default_timezone_set('Africa/Dar_es_Salaam');
?>
