<?php
try
{
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_school LIMIT 1");
$stmt->execute();
$result = $stmt->fetchAll();
foreach($result as $row)
{
DEFINE('WBName', $row[1]);
DEFINE('WBLogo', $row[2]);
DEFINE('WBResSys', $row[3]);
DEFINE('WBResAvi', $row[4]);
}

}catch(PDOException $e)
{
// Allow pages to render even if DB is not configured yet.
if (!defined('WBName')) { DEFINE('WBName', ''); }
if (!defined('WBLogo')) { DEFINE('WBLogo', 'school_logo1711003619.png'); }
if (!defined('WBResSys')) { DEFINE('WBResSys', 1); }
if (!defined('WBResAvi')) { DEFINE('WBResAvi', 1); }
}

if (defined('APP_NAME') && (!defined('WBName') || WBName === '')) {
	DEFINE('WBName', APP_NAME);
}

if (!defined('WBAddress')) {
	$address = '';
	try {
		if (function_exists('app_setting_get')) {
			$address = (string)app_setting_get($conn, 'school_address', '');
		}
	} catch (Throwable $e) {
		$address = '';
	}
	DEFINE('WBAddress', $address);
}

if (!defined('WBMotto')) {
	$motto = '';
	try {
		if (function_exists('app_setting_get')) {
			$motto = (string)app_setting_get($conn, 'public_school_motto', '');
		}
	} catch (Throwable $e) {
		$motto = '';
	}
	DEFINE('WBMotto', $motto);
}

if (!defined('WBPhone')) {
	$phone = '';
	try {
		if (function_exists('app_setting_get')) {
			$phone = (string)app_setting_get($conn, 'public_school_phone', '');
		}
	} catch (Throwable $e) {
		$phone = '';
	}
	DEFINE('WBPhone', $phone);
}

if (!defined('WBEmail')) {
	$email = '';
	try {
		if (function_exists('app_setting_get')) {
			$email = (string)app_setting_get($conn, 'school_email', '');
		}
	} catch (Throwable $e) {
		$email = '';
	}
	DEFINE('WBEmail', $email);
}

try {
	if (!defined('WBLogo')) {
		DEFINE('WBLogo', 'school_logo1711003619.png');
	}
	$logoFile = trim((string)WBLogo);
	if ($logoFile !== '') {
		$logoPath = 'images/logo/' . $logoFile;
		if (!is_file($logoPath)) {
			$blobB64 = app_setting_get($conn, 'school_logo_blob_b64', '');
			if ($blobB64 !== '') {
				$blob = base64_decode($blobB64, true);
				if (is_string($blob) && $blob !== '') {
					$logoDir = dirname($logoPath);
					if (!is_dir($logoDir)) {
						@mkdir($logoDir, 0755, true);
					}
					@file_put_contents($logoPath, $blob);
				}
			}
		}
	}
} catch (Throwable $e) {
	// Best-effort restore only.
}
?>
