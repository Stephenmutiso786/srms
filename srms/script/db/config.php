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
