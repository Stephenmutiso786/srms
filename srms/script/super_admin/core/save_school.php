<?php
chdir('../../../script');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/notify.php');

$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../../');
	exit;
}

$name = trim((string)($_POST['name'] ?? ''));
$schoolId = (int)($_POST['school_id'] ?? 0);
$resultSystem = (int)($_POST['result_system'] ?? 1);
$allowResults = (int)($_POST['allow_results'] ?? 1);
$packageTier = trim((string)($_POST['package_tier'] ?? 'elimu_hub'));
$supportPlan = trim((string)($_POST['support_plan'] ?? 'basic'));
$mpesaEnabled = (int)($_POST['mpesa_enabled'] ?? 1);
$termStartDate = trim((string)($_POST['term_start_date'] ?? ''));
$termEndDate = trim((string)($_POST['term_end_date'] ?? ''));
$isLocked = (int)($_POST['is_locked'] ?? 0);
$schoolCode = trim((string)($_POST['school_code'] ?? ''));
$adminEmail = trim((string)($_POST['admin_email'] ?? ''));
$adminPassword = trim((string)($_POST['admin_password'] ?? ''));
if ($name === '') {
	$_SESSION['reply'] = [['danger', 'School name is required.']];
	header('location:../new_school.php');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_school_subscription_schema($conn);
if (!app_table_exists($conn, 'tbl_school')) {
	$_SESSION['reply'] = [['danger', 'School table is missing.']];
	header('location:../');
	exit;
}

$logo = 'school_logo.png';
$packageTier = in_array($packageTier, ['elimu_hub', 'elimu_hub_pro'], true) ? $packageTier : 'elimu_hub';
$supportPlan = in_array($supportPlan, ['basic', 'pro'], true) ? $supportPlan : ($packageTier === 'elimu_hub_pro' ? 'pro' : 'basic');
$mpesaEnabled = $mpesaEnabled === 1 ? 1 : 0;

try {
	if ($schoolId > 0) {
		$stmt = $conn->prepare("UPDATE tbl_school SET name = ?, logo = COALESCE(NULLIF(logo, ''), ?), result_system = ?, allow_results = ?, package_tier = ?, support_plan = ?, mpesa_enabled = ?, term_start_date = ?, term_end_date = ?, is_locked = ? WHERE id = ?");
		$stmt->execute([
			$name,
			$logo,
			$resultSystem === 1 ? 1 : 0,
			$allowResults === 1 ? 1 : 0,
			$packageTier,
			$supportPlan,
			$mpesaEnabled,
			$termStartDate !== '' ? $termStartDate : null,
			$termEndDate !== '' ? $termEndDate : null,
			$isLocked === 1 ? 1 : 0,
			$schoolId
		]);

		$_SESSION['reply'] = [['success', 'School updated successfully.']];
		header('location:../index.php');
		exit;
	}

	$conn->beginTransaction();
	$stmt = $conn->prepare("INSERT INTO tbl_school (name, logo, result_system, allow_results, package_tier, support_plan, mpesa_enabled, term_start_date, term_end_date, is_locked) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$stmt->execute([
		$name,
		$logo,
		$resultSystem === 1 ? 1 : 0,
		$allowResults === 1 ? 1 : 0,
		$packageTier,
		$supportPlan,
		$mpesaEnabled,
		$termStartDate !== '' ? $termStartDate : null,
		$termEndDate !== '' ? $termEndDate : null,
		$isLocked === 1 ? 1 : 0
	]);
	$schoolId = (int)$conn->lastInsertId();

	if ($schoolId > 0) {
		app_school_mark_pending($conn, $schoolId);
		if (app_table_exists($conn, 'tbl_classes') && !app_column_exists($conn, 'tbl_classes', 'school_id')) {
			$conn->exec("ALTER TABLE tbl_classes ADD COLUMN school_id int DEFAULT NULL");
		}
		if (app_table_exists($conn, 'tbl_subjects') && !app_column_exists($conn, 'tbl_subjects', 'school_id')) {
			$conn->exec("ALTER TABLE tbl_subjects ADD COLUMN school_id int DEFAULT NULL");
		}
		if (app_table_exists($conn, 'tbl_staff') && !app_column_exists($conn, 'tbl_staff', 'school_id')) {
			$conn->exec("ALTER TABLE tbl_staff ADD COLUMN school_id varchar(50) DEFAULT NULL");
		}
		$seed = app_seed_school_workspace($conn, $schoolId, $name, false);
		if ($adminEmail === '') {
			$adminEmail = 'school.admin@local';
		}
		if ($adminPassword === '') {
			$adminPassword = bin2hex(random_bytes(4));
		}
		$schoolScopedId = app_generate_school_id($conn, 'ADM', (int)date('Y'), 'tbl_staff');
		$ownerStmt = $conn->prepare('INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id, force_password_change) VALUES (?,?,?,?,?,?,?,?,?)');
		$ownerStmt->execute(['School', 'Administrator', 'Male', $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT), 0, 1, $schoolScopedId, 1]);
		$adminMessage = '<h2>School Application Received</h2><p>Your school application for <strong>' . htmlspecialchars($name) . '</strong> is pending approval.</p><p>You will receive another email once the super admin approves it.</p>';
		app_send_email($conn, $adminEmail, 'School application received', $adminMessage);
		$superAdminEmail = strtolower(trim(app_super_admin_owner_email()));
		if ($superAdminEmail !== '' && $superAdminEmail !== strtolower($adminEmail)) {
			app_send_email($conn, $superAdminEmail, 'New school application pending', '<h2>New School Application</h2><p><strong>' . htmlspecialchars($name) . '</strong> is waiting for approval.</p><p>Admin email: ' . htmlspecialchars($adminEmail) . '</p>');
		}
	}

	$conn->commit();
	$message = 'School application saved and marked pending approval.';
	if ($adminEmail !== '' || $adminPassword !== '') {
		$message .= ' Admin email: ' . $adminEmail . ($adminPassword !== '' ? ' / temporary password sent by email' : '');
	}
	$_SESSION['reply'] = [['success', $message]];
} catch (Throwable $e) {
	if ($conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	throw $e;
}
header('location:../index.php');
