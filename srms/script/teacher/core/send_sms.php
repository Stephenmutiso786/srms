<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');
require_once('const/communication_targets.php');

if ($res != "1" || $level != "2") { header("location:../../"); exit; }
app_require_permission('communication.send', '../sms');
app_require_unlocked('communication', '../sms');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../sms");
	exit;
}

$targetType = strtolower(trim((string)($_POST['target_type'] ?? '')));
$classId = (int)($_POST['class_id'] ?? 0);
$recipient = trim((string)($_POST['recipient'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($message === '') {
	$_SESSION['reply'] = array(array("danger", "Message is required."));
	header("location:../sms");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$targets = [];
	if (in_array($targetType, ['class_students', 'class_parents'], true)) {
		if ($classId < 1) {
			throw new RuntimeException('Select a class.');
		}
		$allowedClassIds = app_staff_class_teacher_ids($conn, (int)$account_id);
		if (app_table_exists($conn, 'tbl_teacher_assignments')) {
			$stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1");
			$stmt->execute([(int)$account_id]);
			$allowedClassIds = array_merge($allowedClassIds, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
		}
		$allowedClassIds = array_values(array_unique(array_filter($allowedClassIds)));
		if (!in_array($classId, $allowedClassIds, true)) {
			throw new RuntimeException('You can only send class SMS to classes allocated to you.');
		}
		$targets = app_communication_targets($conn, $targetType, (string)$classId);
	} elseif ($recipient !== '') {
		$targets[] = ['phone' => $recipient, 'name' => $recipient];
	} else {
		throw new RuntimeException('Enter a phone number or select a class target.');
	}

	$sent = 0;
	$failed = 0;
	$lastError = '';
	foreach ($targets as $target) {
		$phone = trim((string)($target['phone'] ?? ''));
		if ($phone === '') {
			$failed++;
			continue;
		}
		$result = app_send_sms($conn, $phone, $message);
		if (!empty($result['ok'])) {
			$sent++;
		} else {
			$failed++;
			$lastError = (string)($result['error'] ?? '');
		}
	}

	if ($sent > 0) {
		$_SESSION['reply'] = array(array("success", "SMS sent to $sent recipient(s)." . ($failed > 0 ? " $failed failed or had no phone." : "")));
	} else {
		$_SESSION['reply'] = [["danger", $lastError !== '' ? $lastError : 'No SMS was sent. Check phone numbers, wallet tokens, and gateway settings.']];
	}
	header("location:../sms");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", $e->getMessage()));
	header("location:../sms");
	exit;
}
