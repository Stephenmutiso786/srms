<?php
// Prefer environment variables for cloud hosting (Render, etc.)
DEFINE('DBHost', getenv('DB_HOST') ?: 'localhost');
DEFINE('DBPort', getenv('DB_PORT') ?: '');
DEFINE('DBUser', getenv('DB_USER') ?: 'root');
DEFINE('DBPass', getenv('DB_PASS') ?: '');
DEFINE('DBName', getenv('DB_NAME') ?: 'srms_makumbusho');
DEFINE('DBCharset', getenv('DB_CHARSET') ?: 'utf8mb4');
DEFINE('DBCollation', getenv('DB_COLLATION') ?: 'utf8_general_ci');
DEFINE('DBPrefix', getenv('DB_PREFIX') ?: '');

// Canonical DSN (the rest of the app should use this).
if (getenv('DB_DSN')) {
	DEFINE('DB_DSN', getenv('DB_DSN'));
} else {
	$portPart = DBPort !== '' ? ';port='.DBPort : '';
	DEFINE('DB_DSN', 'mysql:host='.DBHost.$portPart.';dbname='.DBName.';charset='.DBCharset);
}

// App branding
DEFINE('APP_NAME', getenv('APP_NAME') ?: 'Elimu Hub');
DEFINE('APP_TAGLINE', getenv('APP_TAGLINE') ?: 'Student Results Management System');

function app_db(): PDO
{
	static $pdo = null;
	if ($pdo instanceof PDO) {
		return $pdo;
	}

	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	];

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

	$pdo = new PDO(DB_DSN, DBUser, DBPass, $options);
	return $pdo;
}

date_default_timezone_set('Africa/Dar_es_Salaam');
?>
