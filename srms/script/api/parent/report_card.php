<?php
session_start();
require_once(__DIR__ . '/../_common.php');

api_apply_cors();
$user = api_require_portal('parent');

$studentId = (string)($_GET['student_id'] ?? '');
$termId = (int)($_GET['term_id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($studentId === '') {
		api_fail('Select a child first.', 422);
	}

	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_parent_students WHERE parent_id = ? AND student_id = ?");
	$stmt->execute([(int)$user['id'], $studentId]);
	if ((int)$stmt->fetchColumn() < 1) {
		api_fail('You can only access your linked children.', 403);
	}

	$stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$studentId]);
	$classId = (int)$stmt->fetchColumn();
	if ($classId < 1) {
		api_fail('Student not found.', 404);
	}

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

	$card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
	if (!$card) {
		api_fail('Report card not found.', 404);
	}
	api_json([
		'ok' => true,
		'student' => report_get_student_identity($conn, $studentId),
		'term_id' => $termId,
		'report_card' => $card,
		'download_url' => api_backend_url('/parent/report_card_pdf?student=' . urlencode($studentId) . '&term=' . $termId),
	]);
} catch (Throwable $e) {
	api_fail($e->getMessage(), 500);
}
