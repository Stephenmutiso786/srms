<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/notify.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../school-register.php');
	exit;
}

$schoolName = trim((string)($_POST['school_name'] ?? ''));
$adminEmail = trim((string)($_POST['admin_email'] ?? ''));
$adminPassword = trim((string)($_POST['admin_password'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$packageTier = trim((string)($_POST['package_tier'] ?? 'elimu_hub'));
$resultSystem = (int)($_POST['result_system'] ?? 1);
$allowResults = (int)($_POST['allow_results'] ?? 1);

if ($schoolName === '' || $adminEmail === '' || $adminPassword === '') {
	$_SESSION['reply'] = [['danger', 'School name, admin email, and password are required.']];
	header('location:../school-register.php');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_subscription_schema($conn);

	if (!app_table_exists($conn, 'tbl_school')) {
		throw new RuntimeException('School table is not available.');
	}

	$packageTier = in_array($packageTier, ['elimu_hub', 'elimu_hub_pro'], true) ? $packageTier : 'elimu_hub';
	$hash = password_hash($adminPassword, PASSWORD_DEFAULT);
	$conn->beginTransaction();

	$stmt = $conn->prepare('INSERT INTO tbl_school (name, logo, result_system, allow_results, package_tier, term_start_date, term_end_date, is_locked, sms_balance, support_plan, mpesa_enabled) VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, 0, ?, 1)');
	$stmt->execute([$schoolName, 'school_logo.png', $resultSystem === 1 ? 1 : 0, $allowResults === 1 ? 1 : 0, $packageTier, $packageTier === 'elimu_hub_pro' ? 'pro' : 'basic']);
	$schoolId = (int)$conn->lastInsertId();

	if ($schoolId < 1) {
		throw new RuntimeException('Could not create the school record.');
	}
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

		$seed = app_seed_school_workspace($conn, $schoolId, $schoolName, false);
		$schoolScopedId = app_generate_school_id($conn, 'ADM', (int)date('Y'), 'tbl_staff');
		$ownerStmt = $conn->prepare('INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id, force_password_change) VALUES (?,?,?,?,?,?,?,?,?)');
		$ownerStmt->execute(['School', 'Administrator', 'Male', $adminEmail, $hash, 0, 1, $schoolScopedId, 1]);
		$subject = 'School application received';
		$message = '<h2>School Application Received</h2><p>Your school application for <strong>' . htmlspecialchars($schoolName) . '</strong> has been submitted successfully.</p><p>Status: Pending approval.</p><p>You will receive another email once the application is approved.</p>';
		app_send_email($conn, $adminEmail, $subject, $message);
		$superAdminEmail = strtolower(trim(app_super_admin_owner_email()));
		if ($superAdminEmail !== '' && $superAdminEmail !== strtolower($adminEmail)) {
			app_send_email($conn, $superAdminEmail, 'New school application pending', '<h2>New School Application</h2><p><strong>' . htmlspecialchars($schoolName) . '</strong> has applied and is waiting for approval.</p><p>Admin email: ' . htmlspecialchars($adminEmail) . '</p>');
		}

	$conn->commit();
	$_SESSION['reply'] = [['success', 'School application submitted. The school is waiting for approval.']];
	header('location:../index.php');
	exit;
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = [['danger', 'School registration failed: ' . $e->getMessage()]];
	header('location:../school-register.php');
	exit;
}
