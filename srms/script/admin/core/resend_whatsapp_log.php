<?php
session_start();
require_once('../../db/config.php');
require_once('../../const/check_session.php');
require_once('../../const/rbac.php');
require_once('../../const/results_notifications.php');

if ($res != "1" || $level != "0") { header("location:../../"); exit; }
app_require_permission('communication.manage', 'admin');

$logId = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
if ($logId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid WhatsApp log selected.'));
	header("location:../communication");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_whatsapp_tables($conn);

	$stmt = $conn->prepare("SELECT * FROM tbl_whatsapp_logs WHERE id = ? LIMIT 1");
	$stmt->execute([$logId]);
	$log = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$log) {
		throw new RuntimeException('WhatsApp log not found.');
	}

	$metadata = json_decode((string)($log['metadata_json'] ?? ''), true);
	if (!is_array($metadata) || (string)($metadata['entity_type'] ?? '') !== 'result_notification') {
		throw new RuntimeException('This WhatsApp log cannot be resent automatically.');
	}

	$examId = (int)($metadata['exam_id'] ?? 0);
	$studentId = trim((string)($metadata['student_id'] ?? ''));
	$recipient = trim((string)($log['recipient'] ?? ''));
	if ($examId < 1 || $studentId === '' || $recipient === '') {
		throw new RuntimeException('Missing resend context for this WhatsApp log.');
	}

	$result = app_results_resend_single_whatsapp($conn, $examId, $studentId, $recipient);
	if (empty($result['ok'])) {
		throw new RuntimeException((string)($result['error'] ?? 'WhatsApp resend failed.'));
	}

	$_SESSION['reply'] = array(array('success', 'WhatsApp result resent successfully.'));
	header("location:../communication");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to resend WhatsApp log: ' . $e->getMessage()));
	header("location:../communication");
	exit;
}
