<?php
$schoolNameFromDb = '';
$schoolLogoFromDb = 'school_logo.png';
$schoolResSysFromDb = 1;
$schoolResAviFromDb = 1;
$schoolHeadteacherNameFromDb = '';
$schoolHeadteacherTitleFromDb = 'Headteacher';
$schoolDeputyHeadteacherNameFromDb = '';
$schoolDeputyHeadteacherTitleFromDb = 'Deputy Headteacher';
$schoolHeadteacherSignatureFromDb = '';
$schoolStampFromDb = '';
$conn = null;

$isPublicBootstrap = true;
try {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		@session_start();
	}
	$isPublicBootstrap = empty($_SESSION['school_id']) && empty($_SESSION['user_id']) && empty($_SESSION['user']);
} catch (Throwable $e) {
	$isPublicBootstrap = true;
}

if (!$isPublicBootstrap) {
	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		$schoolRow = app_school_row($conn);
		$schoolNameFromDb = trim((string)($schoolRow['name'] ?? ''));
		$schoolLogoFromDb = trim((string)($schoolRow['logo'] ?? $schoolLogoFromDb));
		$schoolResSysFromDb = (int)($schoolRow['result_system'] ?? $schoolResSysFromDb);
		$schoolResAviFromDb = (int)($schoolRow['allow_results'] ?? $schoolResAviFromDb);

		if (function_exists('app_setting_get')) {
			$schoolHeadteacherNameFromDb = trim((string)app_setting_get($conn, 'headteacher_name', ''));
			$schoolHeadteacherTitleFromDb = trim((string)app_setting_get($conn, 'headteacher_title', 'Headteacher'));
			$schoolDeputyHeadteacherNameFromDb = trim((string)app_setting_get($conn, 'deputy_headteacher_name', ''));
			$schoolDeputyHeadteacherTitleFromDb = trim((string)app_setting_get($conn, 'deputy_headteacher_title', 'Deputy Headteacher'));
			$schoolHeadteacherSignatureFromDb = trim((string)app_setting_get($conn, 'headteacher_signature_path', ''));
			$schoolStampFromDb = trim((string)app_setting_get($conn, 'school_stamp_path', ''));
		}
	} catch (Throwable $e) {
		// Allow public pages to render even if the school database is unavailable.
	}
}

if (!defined('WBName')) {
	$resolvedSchoolName = trim((string)$schoolNameFromDb);
	if ($resolvedSchoolName === '' && defined('APP_NAME')) {
		$resolvedSchoolName = trim((string)APP_NAME);
	}
	DEFINE('WBName', $resolvedSchoolName);
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
			if (trim($email) === '') {
				$email = (string)app_setting_get($conn, 'public_school_email', '');
			}
		}
	} catch (Throwable $e) {
		$email = '';
	}
	DEFINE('WBEmail', $email);
}

if (!defined('WBHeadteacherName')) { DEFINE('WBHeadteacherName', $schoolHeadteacherNameFromDb); }
if (!defined('WBHeadteacherTitle')) { DEFINE('WBHeadteacherTitle', $schoolHeadteacherTitleFromDb !== '' ? $schoolHeadteacherTitleFromDb : 'Headteacher'); }
if (!defined('WBDeputyHeadteacherName')) { DEFINE('WBDeputyHeadteacherName', $schoolDeputyHeadteacherNameFromDb); }
if (!defined('WBDeputyHeadteacherTitle')) { DEFINE('WBDeputyHeadteacherTitle', $schoolDeputyHeadteacherTitleFromDb !== '' ? $schoolDeputyHeadteacherTitleFromDb : 'Deputy Headteacher'); }
if (!defined('WBHeadteacherSignature')) { DEFINE('WBHeadteacherSignature', $schoolHeadteacherSignatureFromDb); }
if (!defined('WBSchoolStamp')) { DEFINE('WBSchoolStamp', $schoolStampFromDb); }

try {
	if (!defined('WBLogo')) {
		DEFINE('WBLogo', 'school_logo.png');
	}
	$logoFile = trim((string)WBLogo);
	if ($logoFile !== '' && $conn instanceof PDO) {
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
