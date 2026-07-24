<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../../"); exit; }
app_require_permission('system.manage', '../system');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../system");
	exit;
}

function app_settings_delete_media_file(string $dir, string $fileName): void
{
	$fileName = trim($fileName);
	if ($fileName === '' || strpos($fileName, '..') !== false || strpos($fileName, '/') !== false || strpos($fileName, '\\') !== false) {
		return;
	}
	$targetPath = rtrim($dir, '/') . '/' . $fileName;
	if (is_file($targetPath)) {
		@unlink($targetPath);
	}
}

function app_settings_media_dir(string $folder): string
{
	return dirname(__DIR__, 2) . '/images/' . trim($folder, '/');
}

function app_settings_store_media_upload(array $file, string $dir, string $prefix, int $accountId): array
{
	$uploadCheck = app_validate_upload($file, ['jpg', 'jpeg', 'png', 'webp'], 4 * 1024 * 1024);
	if (!$uploadCheck['ok']) {
		throw new RuntimeException($uploadCheck['message']);
	}
	if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
		throw new RuntimeException('Could not create upload directory.');
	}
	@chmod($dir, 0777);
	if (!is_writable($dir)) {
		throw new RuntimeException('Upload folder is not writable.');
	}

	$extension = $uploadCheck['extension'] !== '' ? $uploadCheck['extension'] : 'png';
	$fileName = $prefix . '_' . date('YmdHis') . '_' . $accountId . '_' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $extension;
	$targetPath = rtrim($dir, '/') . '/' . $fileName;
	if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
		if (!@copy($file['tmp_name'], $targetPath)) {
			throw new RuntimeException('Could not save uploaded file.');
		}
	}
	if (!is_file($targetPath) || filesize($targetPath) < 1) {
		throw new RuntimeException('Could not save uploaded file.');
	}

	return [
		'file_name' => $fileName,
		'target_path' => $targetPath,
	];
}

$settings = $_POST['settings'] ?? [];
if (!is_array($settings) || !$settings) {
	app_reply_redirect('danger', 'No settings were submitted.', '../system');
}

if (isset($settings['top_banner_type'])) {
	$type = strtolower(trim((string)$settings['top_banner_type']));
	$settings['top_banner_type'] = ($type === 'warning') ? 'warning' : 'info';
}
foreach (['top_banner_enabled', 'maintenance_mode_enabled', 'auto_promotion_enabled', 'ai_enabled', 'ai_fallback_enabled', 'ai_public_widget_enabled', 'notification_email_enabled'] as $toggleKey) {
	if (isset($settings[$toggleKey])) {
		$settings[$toggleKey] = ((string)$settings[$toggleKey] === '1') ? '1' : '0';
	}
}

if (array_key_exists('ai_api_key', $settings) && trim((string)$settings['ai_api_key']) === '') {
	unset($settings['ai_api_key']);
}

if (isset($settings['ai_provider'])) {
	$provider = strtolower(trim((string)$settings['ai_provider']));
	$settings['ai_provider'] = in_array($provider, ['gemini', 'openai'], true) ? $provider : 'gemini';
}

if (isset($settings['ai_temperature'])) {
	$temperature = (float)$settings['ai_temperature'];
	$settings['ai_temperature'] = (string)max(0, min(2, $temperature));
}

if (isset($settings['ai_max_output_tokens'])) {
	$maxTokens = (int)$settings['ai_max_output_tokens'];
	$settings['ai_max_output_tokens'] = (string)max(128, min(4096, $maxTokens));
}

if (isset($settings['notification_email_min_priority'])) {
	$settings['notification_email_min_priority'] = (string)max(0, min(100, (int)$settings['notification_email_min_priority']));
}

$continuousWeight = isset($settings['continuous_weight']) ? (int)$settings['continuous_weight'] : null;
$summativeWeight = isset($settings['summative_weight']) ? (int)$settings['summative_weight'] : null;
if ($continuousWeight !== null && $summativeWeight !== null && ($continuousWeight + $summativeWeight) !== 100) {
	app_reply_redirect('danger', 'Continuous weight and Summative weight must add up to 100%.', '../system');
}
$admissionStartNumber = isset($settings['admission_start_number']) ? (int)$settings['admission_start_number'] : null;
if ($admissionStartNumber !== null && $admissionStartNumber < 1) {
	app_reply_redirect('danger', 'Admission start number must be 1 or greater.', '../system');
}
$currentTermId = isset($settings['current_term_id']) ? trim((string)$settings['current_term_id']) : '';
$sessionStartDate = trim((string)($settings['session_start_date'] ?? ''));
$sessionEndDate = trim((string)($settings['session_end_date'] ?? ''));
$promotionReviewStartDate = trim((string)($settings['promotion_review_start_date'] ?? ''));
$promotionFinalizationDate = trim((string)($settings['promotion_finalization_date'] ?? ''));
if ($sessionStartDate !== '' && $sessionEndDate !== '' && strtotime($sessionStartDate) > strtotime($sessionEndDate)) {
	app_reply_redirect('danger', 'Session start date cannot be later than session end date.', '../system');
}
if ($sessionEndDate !== '' && $promotionReviewStartDate !== '' && strtotime($promotionReviewStartDate) < strtotime($sessionEndDate)) {
	app_reply_redirect('danger', 'Promotion review start date cannot be earlier than session end date.', '../system');
}
if ($promotionReviewStartDate !== '' && $promotionFinalizationDate !== '' && strtotime($promotionFinalizationDate) < strtotime($promotionReviewStartDate)) {
	app_reply_redirect('danger', 'Promotion finalization date cannot be earlier than promotion review start date.', '../system');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_current_mode_mysql_schema($conn);
	app_ensure_promotion_workflow_schema($conn);
	if ($currentTermId !== '') {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_terms WHERE id = ?");
		$stmt->execute([(int)$currentTermId]);
		if ((int)$stmt->fetchColumn() < 1) {
			$currentTermId = '';
			$settings['current_term_id'] = '';
		}
	}

	$existingSignature = trim((string)app_setting_get($conn, 'headteacher_signature_path', ''));
	$existingStamp = trim((string)app_setting_get($conn, 'school_stamp_path', ''));
	$signatureDir = app_settings_media_dir('signatures');
	$stampDir = app_settings_media_dir('stamps');

	if (!empty($_POST['remove_headteacher_signature']) && $existingSignature !== '') {
		app_settings_delete_media_file($signatureDir, $existingSignature);
		$settings['headteacher_signature_path'] = '';
		$existingSignature = '';
	}

	if (!empty($_POST['remove_school_stamp']) && $existingStamp !== '') {
		app_settings_delete_media_file($stampDir, $existingStamp);
		$settings['school_stamp_path'] = '';
		$existingStamp = '';
	}

	if (!empty($_FILES['headteacher_signature']['name'])) {
		$storedSignature = app_settings_store_media_upload($_FILES['headteacher_signature'], $signatureDir, 'headteacher_signature', (int)$account_id);
		if ($existingSignature !== '' && $existingSignature !== $storedSignature['file_name']) {
			app_settings_delete_media_file($signatureDir, $existingSignature);
		}
		$settings['headteacher_signature_path'] = $storedSignature['file_name'];
	}

	if (!empty($_FILES['school_stamp']['name'])) {
		$storedStamp = app_settings_store_media_upload($_FILES['school_stamp'], $stampDir, 'school_stamp', (int)$account_id);
		if ($existingStamp !== '' && $existingStamp !== $storedStamp['file_name']) {
			app_settings_delete_media_file($stampDir, $existingStamp);
		}
		$settings['school_stamp_path'] = $storedStamp['file_name'];
	}

	foreach ($settings as $key => $value) {
		app_setting_set($conn, (string)$key, trim((string)$value), (int)$account_id, false);
	}
	app_reply_redirect('success', 'Application settings saved successfully.', '../system');
} catch (Throwable $e) {
	error_log('[admin/core/save_app_settings] ' . $e->getMessage());
	app_reply_redirect('danger', 'Failed to save settings: '.$e->getMessage(), '../system');
}
