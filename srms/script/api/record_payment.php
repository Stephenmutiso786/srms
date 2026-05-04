<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

// Only accountant can record payments
if ((int)$level !== 5) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$studentId = (int)($input['student_id'] ?? 0);
$amount = (float)($input['amount'] ?? 0);
$method = $_POST['method'] ?? ($input['method'] ?? '');

if (!$studentId || $amount <= 0 || !$method) {
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => 'Invalid student, amount, or payment method']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	
	// Check student exists
	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE id = ?");
	$stmt->execute([$studentId]);
	if (!$stmt->fetch()) {
		throw new RuntimeException("Student not found");
	}
	
	// Get first open invoice for student
	$stmt = $conn->prepare("
		SELECT id FROM tbl_invoices
		WHERE student_id = ? AND status = 'open'
		LIMIT 1
	");
	$stmt->execute([$studentId]);
	$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if (!$invoice) {
		throw new RuntimeException("No open invoices found for this student");
	}
	
	// Record payment
	$conn->beginTransaction();
	
	$stmt = $conn->prepare("
		INSERT INTO tbl_payments (invoice_id, amount, method, paid_at, recorded_by)
		VALUES (?, ?, ?, NOW(), ?)
	");
	$stmt->execute([$invoice['id'], $amount, $method, $account_id]);
	
	$conn->commit();
	
	header('Content-Type: application/json');
	echo json_encode([
		'success' => true,
		'message' => 'Payment recorded successfully: Ksh ' . number_format($amount, 2)
	]);
	
} catch (Throwable $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . "] " . $e->getMessage());
	http_response_code(400);
	echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
