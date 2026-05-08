<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json');

// Only accountant can search
if (!isset($res) || $res !== "1" || (int)$level !== 5) {
	http_response_code(403);
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

$q = $_GET['q'] ?? '';
$students = [];

if (strlen($q) < 2) {
	echo json_encode(['students' => []]);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	// Search students by name or ID
	$stmt = $conn->prepare("
		SELECT id, CONCAT_WS(' ', fname, mname, lname) AS name
		FROM tbl_students
		WHERE (id LIKE ? OR fname LIKE ? OR mname LIKE ? OR lname LIKE ?)
		AND status = 1
		LIMIT 20
	");
	
	$searchTerm = '%' . $q . '%';
	$stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
	
} catch (Throwable $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . "] " . $e->getMessage());
}
echo json_encode(['students' => $students]);
