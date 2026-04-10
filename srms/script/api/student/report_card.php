<?php
session_start();
require_once(__DIR__ . '/../_common.php');

api_apply_cors();
$user = api_require_portal('student');

$studentId = (string)$user['id'];
$classId = (int)$user['class_id'];
$termId = (int)($_GET['term_id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($termId < 1) {
		$stmt = $conn->prepare("SELECT t.id
			FROM tbl_terms t
			WHERE EXISTS (SELECT 1 FROM tbl_exams e WHERE e.class_id = ? AND e.term_id = t.id AND e.status = 'published')
			ORDER BY t.id DESC LIMIT 1");
		$stmt->execute([$classId]);
		$termId = (int)$stmt->fetchColumn();
	}
	if ($termId < 1 || !report_term_is_published($conn, $classId, $termId)) {
		api_fail('No published report card is available for that term.', 404);
	}

	$card = report_ensure_card_generated($conn, $studentId, (int)$classId, $termId);
	if (!$card) {
		api_fail('Report card not found.', 404);
	}

	api_json([
		'ok' => true,
		'student' => report_get_student_identity($conn, $studentId),
		'term_id' => $termId,
		'report_card' => $card,
		'download_url' => api_backend_url('/student/report_card_pdf?term=' . $termId),
	]);
} catch (Throwable $e) {
	api_fail($e->getMessage(), 500);
}
