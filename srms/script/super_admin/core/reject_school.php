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
	$_SESSION['reply'] = [['danger', 'Select a school to reject.']];
	header('location:../index.php');
	exit;
}
$rejectReason = trim((string)($_POST['reject_reason'] ?? ''));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_subscription_schema($conn);

	$stmt = $conn->prepare('SELECT id, name FROM tbl_school WHERE id = ? LIMIT 1');
	$stmt->execute([$schoolId]);
	$school = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$school) {
		throw new RuntimeException('School not found.');
	}

	$stmt = $conn->prepare("UPDATE tbl_school SET approval_status = 'rejected', is_locked = 1, is_suspended = 0, approved_at = NULL, approved_by = NULL WHERE id = ?");
	$stmt->execute([$schoolId]);

	$stmt = $conn->prepare('SELECT email FROM tbl_staff WHERE school_id = ? AND level = 0 ORDER BY id ASC LIMIT 1');
	$stmt->execute([$schoolId]);
	$ownerEmail = trim((string)$stmt->fetchColumn());
	if ($ownerEmail !== '') {
		$message = '<h2>School Application Rejected</h2><p>Your school application for <strong>' . htmlspecialchars((string)$school['name']) . '</strong> was rejected by the super admin.</p>';
		if ($rejectReason !== '') {
			$message .= '<p><strong>Reason:</strong> ' . htmlspecialchars($rejectReason) . '</p>';
		}
		$message .= '<p>Please contact support for more details or submit a new application if needed.</p>';
		app_send_email($conn, $ownerEmail, 'School application rejected', $message);
	}

	$_SESSION['reply'] = [['success', 'School rejected successfully. The applicant has been emailed.']];
	header('location:../index.php');
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = [['danger', 'Unable to reject school: ' . $e->getMessage()]];
	header('location:../index.php');
	exit;
}
