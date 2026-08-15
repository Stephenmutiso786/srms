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

$schoolId = (int)($_POST['school_id'] ?? 0);
if ($schoolId < 1) {
	$_SESSION['reply'] = [['danger', 'Select a school to resend the email.']];
	header('location:../index.php');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_subscription_schema($conn);

	$stmt = $conn->prepare('SELECT s.id, s.name, s.package_tier, st.email AS admin_email, st.phone AS admin_phone FROM tbl_school s LEFT JOIN tbl_staff st ON st.school_id = s.id AND st.level = 0 WHERE s.id = ? LIMIT 1');
	$stmt->execute([$schoolId]);
	$school = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$school) {
		throw new RuntimeException('School not found.');
	}

	$adminEmail = trim((string)($school['admin_email'] ?? ''));
	if ($adminEmail === '') {
		throw new RuntimeException('No applicant email found for this school.');
	}

	$message = '<h2>School Application Status</h2><p>Your school application for <strong>' . htmlspecialchars((string)$school['name']) . '</strong> is still pending approval.</p><p><strong>Package:</strong> ' . htmlspecialchars((string)($school['package_tier'] ?? 'elimu_hub')) . '</p><p>Please wait for super-admin review.</p>';
	app_send_email($conn, $adminEmail, 'School application update', $message);

	$_SESSION['reply'] = [['success', 'Pending application email resent successfully.']];
	header('location:../index.php');
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = [['danger', 'Unable to resend email: ' . $e->getMessage()]];
	header('location:../index.php');
	exit;
}
