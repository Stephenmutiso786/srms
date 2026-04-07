<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json');

if ($res != "1") {
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
	$payload = $_POST;
}

$message = trim((string)($payload['message'] ?? ''));
$category = trim((string)($payload['category'] ?? 'feedback'));
if ($message === '') {
	echo json_encode(['ok' => false, 'message' => 'Message required']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$actorType = 'staff';
	if ((int)$level === 3) { $actorType = 'student'; }
	if ((int)$level === 4) { $actorType = 'parent'; }
	$actorId = (string)$account_id;

	if (app_table_exists($conn, 'tbl_ai_feedback')) {
		$stmt = $conn->prepare("INSERT INTO tbl_ai_feedback (actor_type, actor_id, category, message, ai_response) VALUES (?,?,?,?,?)");
		$response = app_generate_ai_reply($message);
		$stmt->execute([$actorType, $actorId, $category, $message, $response]);
		echo json_encode(['ok' => true, 'response' => $response]);
		exit;
	}

	echo json_encode(['ok' => true, 'response' => 'Thanks! We received your message.']);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'message' => 'Failed to save message']);
}

function app_generate_ai_reply(string $message): string
{
	$lower = strtolower($message);
	if (strpos($lower, 'fee') !== false) {
		return 'Fee reminders, balances, and receipts are in Fees & Finance. You can also send payment links via Communication.';
	}
	if (strpos($lower, 'assignment') !== false) {
		return 'Assignments are under E-Learning. Teachers can create them; students submit from the E-Learning portal.';
	}
	if (strpos($lower, 'results') !== false || strpos($lower, 'marks') !== false) {
		return 'Marks entry is under Exam Marks Entry (teachers) and Marks Review (admin). Results unlock only by admin.';
	}
	if (strpos($lower, 'attendance') !== false) {
		return 'Attendance is available under Attendance and Staff Attendance. You can generate reports from the dashboard.';
	}
	return 'Thanks for the message. If you need help, include the module name and action you want to perform.';
}
