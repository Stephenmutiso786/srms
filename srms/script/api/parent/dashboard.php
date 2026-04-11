<?php
session_start();
require_once(__DIR__ . '/../_common.php');

api_apply_cors();
$user = api_require_portal('parent');

$selectedStudentId = (string)($_GET['student_id'] ?? '');
$selectedTermId = (int)($_GET['term_id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException('Parent module is not installed on the server.');
	}

	$stmt = $conn->prepare("SELECT st.id, st.school_id, st.class AS class_id,
		concat_ws(' ', st.fname, st.mname, st.lname) AS name, c.name AS class_name
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE ps.parent_id = ?
		ORDER BY st.id");
	$stmt->execute([(int)$user['id']]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$selectedStudent = null;
	foreach ($students as $student) {
		if ($selectedStudentId === '' || $selectedStudentId === (string)$student['id']) {
			$selectedStudent = $selectedStudent ?: $student;
		}
	}
	if (!$selectedStudent && !empty($students)) {
		$selectedStudent = $students[0];
		$selectedStudentId = (string)$selectedStudent['id'];
	}

	$publishedTerms = [];
	$subjectRows = [];
	$history = [];
	$notifications = [];
	$summary = ['children' => count($students), 'attendance_rate' => 0, 'avg_score' => 0, 'fees_balance' => 0, 'grade' => 'N/A', 'position' => '-'];

	if ($selectedStudent) {
		$stmt = $conn->prepare("SELECT t.id, t.name
			FROM tbl_terms t
			WHERE EXISTS (
				SELECT 1 FROM tbl_exams e
				WHERE e.class_id = ? AND e.term_id = t.id AND e.status = 'published'
			)
			ORDER BY t.id DESC");
		$stmt->execute([(int)$selectedStudent['class_id']]);
		$publishedTerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($selectedTermId < 1 && !empty($publishedTerms)) {
			$selectedTermId = (int)$publishedTerms[0]['id'];
		}

		if ($selectedTermId > 0 && report_term_is_published($conn, (int)$selectedStudent['class_id'], $selectedTermId)) {
			$card = report_ensure_card_generated($conn, (string)$selectedStudentId, (int)$selectedStudent['class_id'], $selectedTermId);
			if ($card) {
				$summary['avg_score'] = (float)($card['mean'] ?? 0);
				$summary['grade'] = (string)($card['grade'] ?? 'N/A');
				$summary['position'] = isset($card['position'], $card['total_students']) ? ($card['position'] . ' / ' . $card['total_students']) : '-';
			}
			$subjectRows = report_subject_breakdown($conn, $selectedStudentId, (int)$selectedStudent['class_id'], $selectedTermId);
			$history = report_student_term_history($conn, $selectedStudentId, (int)$selectedStudent['class_id'], 12);
			$attendance = report_attendance_summary($conn, $selectedStudentId, (int)$selectedStudent['class_id'], $selectedTermId);
			$summary['attendance_rate'] = $attendance['days_open'] > 0 ? round(($attendance['present'] / $attendance['days_open']) * 100, 1) : 0;
			$summary['fees_balance'] = report_fees_balance($conn, $selectedStudentId, $selectedTermId);
		}
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
			WHERE audience IN ('all','parents')
			ORDER BY created_at DESC LIMIT 5");
		$stmt->execute();
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	api_json([
		'ok' => true,
		'user' => $user,
		'students' => $students,
		'selected_student_id' => $selectedStudentId,
		'selected_term_id' => $selectedTermId,
		'selected_term_name' => api_pick_term_name($publishedTerms, $selectedTermId),
		'selected_student' => $selectedStudent,
		'terms' => $publishedTerms,
		'summary' => $summary,
		'subject_rows' => $subjectRows,
		'history' => $history,
		'notifications' => $notifications,
	]);
} catch (Throwable $e) {
	api_internal_error($e, 'api.parent.dashboard');
}
