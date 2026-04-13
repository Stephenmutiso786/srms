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

if (isset($settings['top_banner_type'])) {
	$type = strtolower(trim((string)$settings['top_banner_type']));
	$settings['top_banner_type'] = ($type === 'warning') ? 'warning' : 'info';
}
foreach (['top_banner_enabled', 'maintenance_mode_enabled'] as $toggleKey) {
	if (isset($settings[$toggleKey])) {
		$settings[$toggleKey] = ((string)$settings[$toggleKey] === '1') ? '1' : '0';
	}
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
if ($sessionStartDate !== '' && $sessionEndDate !== '' && strtotime($sessionStartDate) > strtotime($sessionEndDate)) {
	app_reply_redirect('danger', 'Session start date cannot be later than session end date.', '../system');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (!app_table_exists($conn, 'tbl_app_settings')) {
		throw new RuntimeException('Application settings support is not installed. Run migration 030.');
	}
	if ($currentTermId !== '') {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_terms WHERE id = ?");
		$stmt->execute([(int)$currentTermId]);
		if ((int)$stmt->fetchColumn() < 1) {
			throw new RuntimeException('Select a valid current term.');
		}
	}

	foreach ($settings as $key => $value) {
		app_setting_set($conn, (string)$key, trim((string)$value), (int)$account_id, false);
	}
	app_reply_redirect('success', 'Application settings saved successfully.', '../system');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to save settings: '.$e->getMessage(), '../system');
}
