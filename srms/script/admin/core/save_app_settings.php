<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../../"); exit; }
app_require_permission('academic.manage', '../system');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../system");
	exit;
}

$settings = $_POST['settings'] ?? [];
if (!is_array($settings) || !$settings) {
	app_reply_redirect('danger', 'No settings were submitted.', '../system');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (!app_table_exists($conn, 'tbl_app_settings')) {
		throw new RuntimeException('Application settings support is not installed. Run migration 030.');
	}

	$conn->beginTransaction();
	foreach ($settings as $key => $value) {
		app_setting_set($conn, (string)$key, trim((string)$value), (int)$account_id);
	}
	$conn->commit();
	app_reply_redirect('success', 'Application settings saved successfully.', '../system');
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	app_reply_redirect('danger', 'Failed to save settings: '.$e->getMessage(), '../system');
}
