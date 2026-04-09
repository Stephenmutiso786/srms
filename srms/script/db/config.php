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
		$stmt = $conn->prepare("SELECT status FROM tbl_exams WHERE id = ? LIMIT 1");
		$stmt->execute([$examId]);
		$current = (string)$stmt->fetchColumn();
		if ($current === '') {
			return 'draft';
		}
		if (in_array($current, ['finalized', 'published'], true) || !app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			return $current;
		}

		$stmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM tbl_exam_mark_submissions WHERE exam_id = ? GROUP BY status");
		$stmt->execute([$examId]);
		$counts = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$counts[(string)$row['status']] = (int)$row['total'];
		}
		if (empty($counts)) {
			$nextStatus = in_array($current, ['active', 'reviewed'], true) ? 'active' : 'draft';
		} elseif (!empty($counts['submitted'])) {
			$nextStatus = 'active';
		} elseif (!empty($counts['reviewed']) || !empty($counts['finalized'])) {
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
		['tbl_parent_students', 'DELETE FROM tbl_parent_students WHERE student_id IN (%s)'],
		['tbl_cbc_assessments', 'DELETE FROM tbl_cbc_assessments WHERE student_id IN (%s)'],
		['tbl_attendance_records', 'DELETE FROM tbl_attendance_records WHERE student_id IN (%s)'],
		['tbl_exam_results', 'DELETE FROM tbl_exam_results WHERE student IN (%s)'],
		['tbl_assignment_submissions', 'DELETE FROM tbl_assignment_submissions WHERE student_id IN (%s)'],
		['tbl_quiz_results', 'DELETE FROM tbl_quiz_results WHERE student_id IN (%s)'],
		['tbl_attendance_elearning', 'DELETE FROM tbl_attendance_elearning WHERE student_id IN (%s)'],
		['tbl_ai_recommendations', 'DELETE FROM tbl_ai_recommendations WHERE student_id IN (%s)'],
	];
	foreach ($deletes as $rule) {
		if (app_table_exists($conn, $rule[0])) {
			$stmt = $conn->prepare(sprintf($rule[1], $placeholders));
			$stmt->execute($ids);
		}
	}

	if (app_table_exists($conn, 'tbl_report_cards')) {
		if (app_table_exists($conn, 'tbl_report_card_subjects')) {
			$stmt = $conn->prepare("DELETE FROM tbl_report_card_subjects WHERE report_id IN (SELECT id FROM tbl_report_cards WHERE student_id IN ($placeholders))");
			$stmt->execute($ids);
		}
		$stmt = $conn->prepare("DELETE FROM tbl_report_cards WHERE student_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_invoices')) {
		$invoiceIdsStmt = $conn->prepare("SELECT id FROM tbl_invoices WHERE student_id IN ($placeholders)");
		$invoiceIdsStmt->execute($ids);
		$invoiceIds = $invoiceIdsStmt->fetchAll(PDO::FETCH_COLUMN);
		if (!empty($invoiceIds)) {
			$invoicePlaceholders = implode(',', array_fill(0, count($invoiceIds), '?'));
			if (app_table_exists($conn, 'tbl_payments')) {
				$stmt = $conn->prepare("DELETE FROM tbl_payments WHERE invoice_id IN ($invoicePlaceholders)");
				$stmt->execute($invoiceIds);
			}
			if (app_table_exists($conn, 'tbl_invoice_lines')) {
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

	$stmt = $conn->prepare("DELETE FROM tbl_students WHERE id IN ($placeholders)");
	$stmt->execute($ids);
}

function app_delete_staff(PDO $conn, array $ids): void
{
	if (empty($ids)) {
		return;
	}

	$placeholders = implode(',', array_fill(0, count($ids), '?'));

	$deletes = [
		['tbl_user_roles', 'DELETE FROM tbl_user_roles WHERE staff_id IN (%s)'],
		['tbl_teacher_assignments', 'DELETE FROM tbl_teacher_assignments WHERE teacher_id IN (%s)'],
		['tbl_staff_attendance', 'DELETE FROM tbl_staff_attendance WHERE staff_id IN (%s)'],
	];
	foreach ($deletes as $rule) {
		if (app_table_exists($conn, $rule[0])) {
			$stmt = $conn->prepare(sprintf($rule[1], $placeholders));
			$stmt->execute($ids);
		}
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'staff')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE staff IN ($placeholders)");
		$stmt->execute($ids);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_staff WHERE id IN ($placeholders)");
	$stmt->execute($ids);
}

function app_delete_subject(PDO $conn, int $id): void
{
	if ($id < 1) {
		return;
	}

	$singleArg = [$id];
	$cleanup = [
		['tbl_subject_class_assignments', 'DELETE FROM tbl_subject_class_assignments WHERE subject_id = ?'],
		['tbl_teacher_assignments', 'DELETE FROM tbl_teacher_assignments WHERE subject_id = ?'],
		['tbl_subject_weights', 'DELETE FROM tbl_subject_weights WHERE subject_id = ?'],
		['tbl_cbc_strands', 'DELETE FROM tbl_cbc_strands WHERE subject_id = ?'],
		['tbl_cbc_mark_submissions', 'DELETE FROM tbl_cbc_mark_submissions WHERE subject_id = ?'],
		['tbl_validation_issues', 'DELETE FROM tbl_validation_issues WHERE subject_id = ?'],
		['tbl_insights_alerts', 'DELETE FROM tbl_insights_alerts WHERE subject_id = ?'],
	];
	foreach ($cleanup as $rule) {
		if (app_table_exists($conn, $rule[0])) {
			$stmt = $conn->prepare($rule[1]);
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
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class = ?");
		$stmt->execute([$id]);
		if ((int)$stmt->fetchColumn() > 0) {
			return [false, 'This class still has students. Move or delete the students first.'];
		}
	}

	$cleanup = [
		['tbl_subject_class_assignments', 'DELETE FROM tbl_subject_class_assignments WHERE class_id = ?'],
		['tbl_teacher_assignments', 'DELETE FROM tbl_teacher_assignments WHERE class_id = ?'],
		['tbl_results_locks', 'DELETE FROM tbl_results_locks WHERE class_id = ?'],
		['tbl_exam_schedule', 'DELETE FROM tbl_exam_schedule WHERE class_id = ?'],
		['tbl_exams', 'DELETE FROM tbl_exams WHERE class_id = ?'],
		['tbl_attendance_sessions', 'DELETE FROM tbl_attendance_sessions WHERE class_id = ?'],
		['tbl_courses', 'DELETE FROM tbl_courses WHERE class_id = ?'],
		['tbl_fee_structures', 'DELETE FROM tbl_fee_structures WHERE class_id = ?'],
		['tbl_validation_issues', 'DELETE FROM tbl_validation_issues WHERE class_id = ?'],
		['tbl_insights_alerts', 'DELETE FROM tbl_insights_alerts WHERE class_id = ?'],
		['tbl_notifications', 'DELETE FROM tbl_notifications WHERE class_id = ?'],
	];
	foreach ($cleanup as $rule) {
		if (app_table_exists($conn, $rule[0])) {
			$stmt = $conn->prepare($rule[1]);
			$stmt->execute([$id]);
		}
	}

	if (app_table_exists($conn, 'tbl_subject_combinations')) {
		$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$classList = @unserialize($row['class']);
			if (!is_array($classList)) {
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
	}

	$stmt = $conn->prepare("DELETE FROM tbl_classes WHERE id = ?");
	$stmt->execute([$id]);
	return [true, 'Class deleted successfully.'];
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

date_default_timezone_set('Africa/Dar_es_Salaam');
?>
