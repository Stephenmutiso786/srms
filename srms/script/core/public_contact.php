<?php
chdir('../');
require_once('db/config.php');

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
	exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
	$payload = $_POST;
}

$name = trim((string)($payload['name'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$phone = trim((string)($payload['phone'] ?? ''));
$message = trim((string)($payload['message'] ?? ''));

if ($name === '' || $email === '' || $phone === '' || $message === '') {
	echo json_encode(['ok' => false, 'message' => 'Please fill in name, email, phone, and message.']);
	exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
	echo json_encode(['ok' => false, 'message' => 'Please enter a valid email address.']);
	exit;
}

if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $phone)) {
	echo json_encode(['ok' => false, 'message' => 'Please enter a valid phone number.']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_ai_feedback')) {
		echo json_encode(['ok' => false, 'message' => 'Feedback module is not ready yet.']);
		exit;
	}

	$subject = 'Public website contact: ' . (function_exists('mb_substr') ? mb_substr($name, 0, 60) : substr($name, 0, 60));
	$fullMessage = "Name: {$name}\nEmail: {$email}\nPhone: {$phone}\nSource: Public Website Contact Form\n\nMessage:\n{$message}";
	$aiResponse = 'Thanks! Your message has been received. Admin and headteacher will review it and follow up.';
	$actorType = 'public_visitor';
	$actorId = strtolower($email);

	$columns = ['actor_type', 'actor_id', 'category', 'message', 'ai_response'];
	$values = [$actorType, $actorId, 'feedback', $fullMessage, $aiResponse];
	$placeholders = ['?', '?', '?', '?', '?'];

	if (app_column_exists($conn, 'tbl_ai_feedback', 'subject')) {
		$columns[] = 'subject';
		$values[] = $subject;
		$placeholders[] = '?';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'status')) {
		$columns[] = 'status';
		$values[] = 'open';
		$placeholders[] = '?';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'reply_message')) {
		$columns[] = 'reply_message';
		$values[] = '';
		$placeholders[] = '?';
	}
	if (app_column_exists($conn, 'tbl_ai_feedback', 'replied_by')) {
		$columns[] = 'replied_by';
		$values[] = null;
		$placeholders[] = '?';
	}

	$sql = 'INSERT INTO tbl_ai_feedback (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
	$stmt = $conn->prepare($sql);
	$stmt->execute($values);

	echo json_encode(['ok' => true, 'response' => $aiResponse]);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'message' => 'Failed to submit your message right now.']);
}
