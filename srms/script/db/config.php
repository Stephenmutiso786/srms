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

function app_audit_log(PDO $conn, string $actorType, string $actorId, string $action, string $entity, string $entityId = ''): void
{
	try {
		if (!app_table_exists($conn, 'tbl_audit_logs')) {
			return;
		}

		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		$stmt = $conn->prepare("INSERT INTO tbl_audit_logs (actor_type, actor_id, action, entity, entity_id, ip, user_agent) VALUES (?,?,?,?,?,?,?)");
		$stmt->execute([$actorType, $actorId, $action, $entity, $entityId, $ip, $ua]);
	} catch (Throwable $e) {
		// Best-effort only.
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

function app_reply_redirect(string $type, string $message, string $location): void
{
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$_SESSION['reply'] = array(array($type, $message));
	header("location:" . $location);
	exit;
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
