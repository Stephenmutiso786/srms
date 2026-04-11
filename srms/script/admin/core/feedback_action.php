<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}
app_require_permission('system.manage', '../feedback');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../feedback");
	exit;
}

$id = (int)($_POST['id'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'open'));
$replyMessage = trim((string)($_POST['reply_message'] ?? ''));

if ($id < 1) {
	$_SESSION['reply'] = array(array('danger', 'Invalid feedback item.'));
	header("location:../feedback");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_ai_feedback')) {
		throw new RuntimeException('Feedback table is not installed. Please run migrations.');
	}

	$fields = [];
	$values = [];
	if (app_column_exists($conn, 'tbl_ai_feedback', 'status')) {
		$fields[] = 'status = ?';
		$values[] = $status !== '' ? $status : 'open';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'reply_message')) {
		$fields[] = 'reply_message = ?';
		$values[] = $replyMessage;
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'replied_by')) {
		$fields[] = 'replied_by = ?';
		$values[] = (int)$account_id;
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'replied_at')) {
		$fields[] = 'replied_at = CURRENT_TIMESTAMP';
	}
	if (!$fields) {
		throw new RuntimeException('Feedback table is missing update columns.');
	}

	$sql = 'UPDATE tbl_ai_feedback SET ' . implode(', ', $fields) . ' WHERE id = ?';
	$values[] = $id;
	$stmt = $conn->prepare($sql);
	$stmt->execute($values);

	if (function_exists('app_audit_log')) {
		app_audit_log($conn, 'staff', (string)$account_id, 'update', 'ai_feedback', (string)$id, ['status' => $status]);
	}

	$_SESSION['reply'] = array(array('success', 'Feedback updated.'));
	header("location:../feedback");
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to update feedback.'));
	header("location:../feedback");
}