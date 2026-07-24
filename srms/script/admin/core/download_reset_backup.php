<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true)) { header("location:../"); exit; }
app_require_permission('system.manage', '../');

$file = trim((string)($_GET['file'] ?? ''));
$file = basename($file);
if ($file === '' || strpos($file, 'new_school_reset_backup_') !== 0) {
	http_response_code(404);
	echo 'Backup file not found.';
	exit;
}

$path = dirname(__DIR__, 2) . '/backups/reset_exports/' . $file;
if (!is_file($path)) {
	http_response_code(404);
	echo 'Backup file not found.';
	exit;
}

header('Content-Type: application/json');
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: attachment; filename="' . $file . '"');
readfile($path);
exit;
