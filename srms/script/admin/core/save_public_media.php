<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1' || $level != '0') { header('location:../../'); exit; }
app_require_permission('academic.manage', '../system');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../system');
	exit;
}

function app_uploaded_image_payload(array $file, int $maxBytes, int $minWidth, int $minHeight, string $label): ?array
{
	if (!isset($file['name']) || (string)$file['name'] === '') {
		return null;
	}
	$check = app_validate_upload($file, ['jpg', 'jpeg', 'png', 'webp'], $maxBytes);
	if (!$check['ok']) {
		throw new RuntimeException($label . ': ' . (string)$check['message']);
	}

	$path = (string)($file['tmp_name'] ?? '');
	$size = @getimagesize($path);
	if (!is_array($size) || empty($size[0]) || empty($size[1])) {
		throw new RuntimeException($label . ': invalid image file.');
	}
	$width = (int)$size[0];
	$height = (int)$size[1];
	if ($minWidth > 1 && $minHeight > 1 && ($width < $minWidth || $height < $minHeight)) {
		throw new RuntimeException($label . ': image is too small. Minimum ' . $minWidth . 'x' . $minHeight . ' required.');
	}

	$bytes = @file_get_contents($path);
	if (!is_string($bytes) || $bytes === '') {
		throw new RuntimeException('Failed to read uploaded image.');
	}

	$ext = strtolower((string)pathinfo((string)$file['name'], PATHINFO_EXTENSION));
	if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
		throw new RuntimeException('Only JPG, JPEG, PNG and WEBP files are allowed.');
	}

	return [
		'b64' => base64_encode($bytes),
		'ext' => $ext,
		'name' => basename((string)$file['name']),
		'width' => $width,
		'height' => $height
	];
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (!app_table_exists($conn, 'tbl_app_settings')) {
		throw new RuntimeException('Application settings support is not installed. Run migration 030.');
	}

	$loginPayload = app_uploaded_image_payload($_FILES['login_background'] ?? [], 12 * 1024 * 1024, 1, 1, 'Login background');
	if (is_array($loginPayload)) {
		app_setting_set($conn, 'public_login_bg_b64', (string)$loginPayload['b64'], (int)$account_id, false);
		app_setting_set($conn, 'public_login_bg_ext', (string)$loginPayload['ext'], (int)$account_id, false);
		app_setting_set($conn, 'public_login_bg_name', (string)$loginPayload['name'], (int)$account_id, false);
		app_setting_set($conn, 'public_login_bg_width', (string)$loginPayload['width'], (int)$account_id, false);
		app_setting_set($conn, 'public_login_bg_height', (string)$loginPayload['height'], (int)$account_id, false);
	}

	$replace = isset($_POST['replace_gallery']) && (string)$_POST['replace_gallery'] === '1';
	$captionsRaw = trim((string)($_POST['showcase_captions'] ?? ''));
	$captions = [];
	if ($captionsRaw !== '') {
		$captions = preg_split('/\r\n|\r|\n/', $captionsRaw);
		$captions = is_array($captions) ? $captions : [];
	}

	$gallery = [];
	if (!$replace) {
		$existing = app_setting_get($conn, 'public_showcase_gallery_json', '[]');
		$decoded = json_decode($existing, true);
		if (is_array($decoded)) {
			$gallery = $decoded;
		}
	}

	$files = $_FILES['showcase_images'] ?? null;
	if (is_array($files) && isset($files['name']) && is_array($files['name'])) {
		for ($i = 0; $i < count($files['name']); $i++) {
			$name = (string)($files['name'][$i] ?? '');
			if ($name === '') {
				continue;
			}
			$single = [
				'name' => $name,
				'type' => (string)($files['type'][$i] ?? ''),
				'tmp_name' => (string)($files['tmp_name'][$i] ?? ''),
				'error' => (int)($files['error'][$i] ?? UPLOAD_ERR_NO_FILE),
				'size' => (int)($files['size'][$i] ?? 0),
			];
			$payload = app_uploaded_image_payload($single, 8 * 1024 * 1024, 1, 1, 'Showcase image');
			if (!is_array($payload)) {
				continue;
			}
			$gallery[] = [
				'b64' => (string)$payload['b64'],
				'ext' => (string)$payload['ext'],
				'name' => (string)$payload['name'],
				'width' => (int)$payload['width'],
				'height' => (int)$payload['height'],
				'caption' => isset($captions[$i]) ? trim((string)$captions[$i]) : ''
			];
		}
	}

	if (isset($_POST['clear_gallery']) && (string)$_POST['clear_gallery'] === '1') {
		$gallery = [];
	}

	if (!empty($gallery) || $replace || isset($_POST['clear_gallery'])) {
		app_setting_set($conn, 'public_showcase_gallery_json', json_encode(array_values($gallery)), (int)$account_id, false);
	}

	if (isset($_POST['use_first_showcase_as_login']) && (string)$_POST['use_first_showcase_as_login'] === '1' && !empty($gallery[0])) {
		$first = $gallery[0];
		if (!empty($first['b64']) && !empty($first['ext'])) {
			app_setting_set($conn, 'public_login_bg_b64', (string)$first['b64'], (int)$account_id, false);
			app_setting_set($conn, 'public_login_bg_ext', (string)$first['ext'], (int)$account_id, false);
			app_setting_set($conn, 'public_login_bg_name', (string)($first['name'] ?? 'showcase_1'), (int)$account_id, false);
			if (!empty($first['width']) && !empty($first['height'])) {
				app_setting_set($conn, 'public_login_bg_width', (string)$first['width'], (int)$account_id, false);
				app_setting_set($conn, 'public_login_bg_height', (string)$first['height'], (int)$account_id, false);
			}
		}
	}

	app_reply_redirect('success', 'Public media saved permanently in the database.', '../system');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to save public media: ' . $e->getMessage(), '../system');
}
