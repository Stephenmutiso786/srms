<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../../"); exit; }
app_require_permission('system.manage', '../system');

function app_system_reply(string $type, string $message): void
{
	$_SESSION['reply'] = [[ $type, $message ]];
	header("location:../system");
	exit;
}

function app_logo_cleanup(PDO $conn, string $keepFile): void
{
	$logoDir = 'images/logo';
	if (!is_dir($logoDir)) {
		return;
	}
	$keepFile = trim($keepFile);
	foreach ((array)glob($logoDir . '/*') as $file) {
		if (!is_file($file)) {
			continue;
		}
		if (basename($file) === $keepFile) {
			continue;
		}
		@unlink($file);
	}
}

function app_logo_write_png_from_bytes(string $bytes, string $targetPath): bool
{
	if (!function_exists('imagecreatefromstring')) {
		return false;
	}
	$image = @imagecreatefromstring($bytes);
	if (!$image) {
		return false;
	}
	imagealphablending($image, true);
	imagesavealpha($image, true);
	$result = @imagepng($image, $targetPath);
	imagedestroy($image);
	return (bool)$result;
}

function app_logo_resize_png(string $sourcePath, string $targetPath, int $size): bool
{
	if (!function_exists('imagecreatefrompng')) {
		return false;
	}
	$src = @imagecreatefrompng($sourcePath);
	if (!$src) {
		return false;
	}
	$width = imagesx($src);
	$height = imagesy($src);
	$dest = imagecreatetruecolor($size, $size);
	imagealphablending($dest, false);
	imagesavealpha($dest, true);
	$transparent = imagecolorallocatealpha($dest, 0, 0, 0, 127);
	imagefilledrectangle($dest, 0, 0, $size, $size, $transparent);
	imagecopyresampled($dest, $src, 0, 0, 0, 0, $size, $size, $width, $height);
	$result = @imagepng($dest, $targetPath);
	imagedestroy($src);
	imagedestroy($dest);
	return (bool)$result;
}

function app_logo_generate_favicon(string $sourcePath, string $targetPath): void
{
	$convert = trim((string)shell_exec('command -v convert 2>/dev/null'));
	if ($convert !== '') {
		@exec($convert . ' ' . escapeshellarg($sourcePath) . ' -define icon:auto-resize=16,32,48,64 ' . escapeshellarg($targetPath));
		if (is_file($targetPath)) {
			return;
		}
	}
	@copy($sourcePath, $targetPath);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$name = trim((string)($_POST['name'] ?? ''));
if ($name === '') {
	app_system_reply('danger', 'School name is required.');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_current_mode_mysql_schema($conn);

	$conn->beginTransaction();

	$stmt = $conn->prepare("SELECT id, logo FROM tbl_school LIMIT 1");
	$stmt->execute();
	$existing = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => null, 'logo' => 'school_logo.png'];
	$logoFile = trim((string)($existing['logo'] ?? 'school_logo.png'));
	if ($logoFile === '') {
		$logoFile = 'school_logo.png';
	}

	if (!empty($_FILES['company_logo']['name'])) {
		$uploadCheck = app_validate_upload($_FILES['company_logo'], ['jpg', 'jpeg', 'png', 'webp']);
		if (!$uploadCheck['ok']) {
			if ($conn->inTransaction()) {
				$conn->rollBack();
			}
			app_system_reply('danger', $uploadCheck['message']);
		}

		$logoDir = 'images/logo';
		if (!is_dir($logoDir) && !@mkdir($logoDir, 0755, true) && !is_dir($logoDir)) {
			throw new RuntimeException('Could not create logo directory.');
		}

		$rawBytes = @file_get_contents($_FILES['company_logo']['tmp_name']);
		if (!is_string($rawBytes) || $rawBytes === '') {
			throw new RuntimeException('Could not read uploaded logo file.');
		}

		$logoFile = 'school_logo.png';
		$logoPath = $logoDir . '/' . $logoFile;
		if (!app_logo_write_png_from_bytes($rawBytes, $logoPath)) {
			if (!@move_uploaded_file($_FILES['company_logo']['tmp_name'], $logoPath)) {
				throw new RuntimeException('Could not save uploaded logo.');
			}
		}

		app_logo_cleanup($conn, $logoFile);

		$pwaDir = 'images/pwa';
		if (!is_dir($pwaDir)) {
			@mkdir($pwaDir, 0755, true);
		}
		app_logo_resize_png($logoPath, $pwaDir . '/icon-192.png', 192);
		app_logo_resize_png($logoPath, $pwaDir . '/icon-512.png', 512);
		app_logo_generate_favicon($logoPath, 'images/icon.ico');

		$logoB64 = base64_encode((string)file_get_contents($logoPath));
		app_setting_set($conn, 'school_logo_blob_b64', $logoB64, null);
		app_setting_set($conn, 'school_logo_blob_ext', 'png', null);
		app_setting_set($conn, 'school_logo_blob_name', $logoFile, null);
	}

	if (!empty($existing['id'])) {
		$stmt = $conn->prepare("UPDATE tbl_school SET name = ?, logo = ? WHERE id = ?");
		$stmt->execute([$name, $logoFile, $existing['id']]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_school (name, logo, result_system, allow_results) VALUES (?,?,?,?)");
		$stmt->execute([$name, $logoFile, 1, 1]);
	}

	$conn->commit();
	app_system_reply('success', 'System settings updated.');
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[" . __FILE__ . ":" . __LINE__ . "] " . $e->getMessage());
	app_system_reply('danger', 'Failed to update settings: ' . $e->getMessage());
}
