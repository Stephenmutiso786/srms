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
	$_SESSION['reply'] = [['danger', 'Select a school to approve.']];
	header('location:../index.php');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_subscription_schema($conn);

	$stmt = $conn->prepare('SELECT id, name, approval_status FROM tbl_school WHERE id = ? LIMIT 1');
	$stmt->execute([$schoolId]);
	$school = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$school) {
		throw new RuntimeException('School not found.');
	}

	app_school_mark_approved($conn, $schoolId, (int)($account_id ?? 0));

	$stmt = $conn->prepare('SELECT s.email, s.phone, sc.name, sc.package_tier FROM tbl_staff s JOIN tbl_school sc ON sc.id = s.school_id WHERE s.school_id = ? AND s.level = 0 ORDER BY s.id ASC LIMIT 1');
	$stmt->execute([$schoolId]);
	$owner = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($owner && trim((string)$owner['email']) !== '') {
		$message = '<h2>School Approved</h2><p>Your school application for <strong>' . htmlspecialchars((string)$owner['name']) . '</strong> has been approved.</p><p><strong>Package:</strong> ' . htmlspecialchars((string)($owner['package_tier'] ?? 'elimu_hub')) . '</p><p>You can now log in to the system using your admin credentials.</p>';
		app_send_email($conn, (string)$owner['email'], 'School application approved', $message);
	}

	$_SESSION['reply'] = [['success', 'School approved successfully. The school admin has been emailed.']];
	header('location:../index.php');
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = [['danger', 'Unable to approve school: ' . $e->getMessage()]];
	header('location:../index.php');
	exit;
}
