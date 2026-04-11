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

	$terms = [];
	$stmt = $conn->prepare("SELECT t.id, t.name
		FROM tbl_terms t
		WHERE EXISTS (
			SELECT 1 FROM tbl_exams e
			WHERE e.term_id = t.id AND e.class_id = ? AND e.status = 'published'
		)
		ORDER BY t.id DESC");
	$stmt->execute([$classId]);
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($termId < 1 && !empty($terms)) {
		$termId = (int)$terms[0]['id'];
	}

	$publicationState = 'draft';
	$isPublished = false;
	$subjectRows = [];
	$history = report_student_term_history($conn, $studentId, $classId, 12);
	$summary = ['mean' => 0, 'grade' => 'N/A', 'position' => '-', 'total' => 0, 'attendance_rate' => 0, 'fees_balance' => 0];
	$reportCard = null;

	if ($termId > 0) {
		$publicationState = report_term_publish_state($conn, $classId, $termId);
		$isPublished = report_term_is_published($conn, $classId, $termId);
		if ($isPublished) {
			$reportCard = report_ensure_card_generated($conn, $studentId, $classId, $termId);
			if ($reportCard) {
				$summary['mean'] = (float)($reportCard['mean'] ?? 0);
				$summary['grade'] = (string)($reportCard['grade'] ?? 'N/A');
				$summary['position'] = isset($reportCard['position'], $reportCard['total_students']) ? ($reportCard['position'] . '/' . $reportCard['total_students']) : '-';
				$summary['total'] = (float)($reportCard['total'] ?? 0);
			}
			$subjectRows = report_subject_breakdown($conn, $studentId, $classId, $termId);
			$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
			$summary['attendance_rate'] = $attendance['days_open'] > 0 ? round(($attendance['present'] / $attendance['days_open']) * 100, 1) : 0;
			$summary['fees_balance'] = report_fees_balance($conn, $studentId, $termId);
		}
	}

	api_json([
		'ok' => true,
		'user' => $user,
		'student' => report_get_student_identity($conn, $studentId),
		'selected_term_id' => $termId,
		'selected_term_name' => api_pick_term_name($terms, $termId),
		'terms' => $terms,
		'is_published' => $isPublished,
		'publication_state' => $publicationState,
		'summary' => $summary,
		'subject_rows' => $subjectRows,
		'history' => $history,
		'report_card' => $reportCard ? [
			'id' => (int)$reportCard['id'],
			'download_url' => api_backend_url('/student/report_card_pdf?term=' . $termId),
		] : null,
	]);
} catch (Throwable $e) {
	api_internal_error($e, 'api.student.dashboard');
}
