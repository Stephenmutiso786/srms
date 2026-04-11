<?php
session_start();
require_once(__DIR__ . '/../_common.php');

api_apply_cors();
$user = api_require_portal('teacher');

$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$selectedTerm = (int)($_GET['term_id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$year = (int)date('Y');

	$assignments = [];
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT ta.class_id, ta.subject_id, ta.term_id,
			c.name AS class_name, s.name AS subject_name, t.name AS term_name
			FROM tbl_teacher_assignments ta
			LEFT JOIN tbl_classes c ON c.id = ta.class_id
			LEFT JOIN tbl_subjects s ON s.id = ta.subject_id
			LEFT JOIN tbl_terms t ON t.id = ta.term_id
			WHERE ta.teacher_id = ? AND ta.status = 1 AND ta.year = ?
			ORDER BY ta.class_id, ta.subject_id");
		$stmt->execute([(int)$user['id'], $year]);
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$classOptions = [];
	$subjectOptions = [];
	$termOptions = [];
	foreach ($assignments as $assignment) {
		if (!empty($assignment['class_id'])) {
			$classOptions[(int)$assignment['class_id']] = (string)$assignment['class_name'];
		}
		if (!empty($assignment['subject_id'])) {
			$subjectOptions[(int)$assignment['subject_id']] = (string)$assignment['subject_name'];
		}
		if (!empty($assignment['term_id'])) {
			$termOptions[(int)$assignment['term_id']] = (string)$assignment['term_name'];
		}
	}

	if ($selectedClass < 1 && !empty($classOptions)) {
		$selectedClass = (int)array_key_first($classOptions);
	}
	if ($selectedSubject < 1 && !empty($subjectOptions)) {
		$selectedSubject = (int)array_key_first($subjectOptions);
	}
	if ($selectedTerm < 1 && !empty($termOptions)) {
		$selectedTerm = (int)array_key_first($termOptions);
	}

	$rows = [];
	$trendPoints = [];
	$summary = ['subjects' => count($subjectOptions), 'classes' => count($classOptions), 'students' => 0, 'avg' => 0, 'best' => 0];

	if (!empty($classOptions)) {
		$placeholders = implode(',', array_fill(0, count($classOptions), '?'));
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders)");
		$stmt->execute(array_keys($classOptions));
		$summary['students'] = (int)$stmt->fetchColumn();
	}

	if ($selectedClass > 0 && $selectedSubject > 0 && $selectedTerm > 0) {
		$stmt = $conn->prepare("SELECT sc.id FROM tbl_subject_combinations sc WHERE sc.teacher = ? AND sc.subject = ? LIMIT 1");
		$stmt->execute([(int)$user['id'], $selectedSubject]);
		$combinationId = (int)$stmt->fetchColumn();
		if ($combinationId > 0) {
			$stmt = $conn->prepare("SELECT st.id AS student_id, st.school_id,
				concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
				COALESCE(er.score, 0) AS score
				FROM tbl_students st
				LEFT JOIN tbl_exam_results er
					ON er.student = st.id
					AND er.class = st.class
					AND er.term = ?
					AND er.subject_combination = ?
				WHERE st.class = ?
				ORDER BY student_name");
			$stmt->execute([$selectedTerm, $combinationId, $selectedClass]);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				list($grade,) = report_grade_for_score($conn, (float)$row['score']);
				$rows[] = [
					'student_id' => (string)$row['student_id'],
					'school_id' => (string)($row['school_id'] ?? ''),
					'student_name' => (string)$row['student_name'],
					'score' => (float)$row['score'],
					'grade' => $grade,
				];
			}
			if ($rows) {
				$scores = array_column($rows, 'score');
				$summary['avg'] = round(array_sum($scores) / count($scores), 2);
				$summary['best'] = max($scores);
			}
		}

		$stmt = $conn->prepare("SELECT t.id, t.name FROM tbl_terms t WHERE t.id <= ? ORDER BY t.id ASC");
		$stmt->execute([$selectedTerm]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $term) {
			$stmt2 = $conn->prepare("SELECT AVG(er.score)
				FROM tbl_exam_results er
				JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination
				WHERE er.class = ? AND er.term = ? AND sc.teacher = ? AND sc.subject = ?");
			$stmt2->execute([$selectedClass, (int)$term['id'], (int)$user['id'], $selectedSubject]);
			$trendPoints[] = [
				'term_name' => (string)$term['name'],
				'mean' => round((float)$stmt2->fetchColumn(), 2),
			];
		}
	}

	api_json([
		'ok' => true,
		'user' => $user,
		'options' => [
			'classes' => $classOptions,
			'subjects' => $subjectOptions,
			'terms' => $termOptions,
		],
		'selected' => [
			'class_id' => $selectedClass,
			'subject_id' => $selectedSubject,
			'term_id' => $selectedTerm,
		],
		'summary' => $summary,
		'rows' => $rows,
		'trend' => $trendPoints,
	]);
} catch (Throwable $e) {
	api_internal_error($e, 'api.teacher.dashboard');
}

