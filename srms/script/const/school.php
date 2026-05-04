<?php
$schoolNameFromDb = '';
$schoolLogoFromDb = 'school_logo1711003619.png';
$schoolResSysFromDb = 1;
$schoolResAviFromDb = 1;

try
{
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_school LIMIT 1");
$stmt->execute();
$result = $stmt->fetchAll();
foreach($result as $row)
{
	$schoolNameFromDb = (string)($row[1] ?? '');
	$schoolLogoFromDb = (string)($row[2] ?? $schoolLogoFromDb);
	$schoolResSysFromDb = (int)($row[3] ?? $schoolResSysFromDb);
	$schoolResAviFromDb = (int)($row[4] ?? $schoolResAviFromDb);
}

}catch(PDOException $e)
{
// Allow pages to render even if DB is not configured yet.
}

if (!defined('WBName')) {
	DEFINE('WBName', defined('APP_NAME') && trim((string)APP_NAME) !== '' ? APP_NAME : $schoolNameFromDb);
}
if (!defined('WBLogo')) { DEFINE('WBLogo', $schoolLogoFromDb); }
if (!defined('WBResSys')) { DEFINE('WBResSys', $schoolResSysFromDb); }
if (!defined('WBResAvi')) { DEFINE('WBResAvi', $schoolResAviFromDb); }

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
