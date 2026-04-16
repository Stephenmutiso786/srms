<?php
require_once('db/config.php');

function report_grading_systems(PDO $conn): array
{
	if (!app_table_exists($conn, 'tbl_grading_systems')) {
		return [];
	}
	$stmt = $conn->prepare("SELECT * FROM tbl_grading_systems WHERE is_active = 1 ORDER BY id");
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function report_default_grading_system_id(PDO $conn): ?int
{
	if (!app_table_exists($conn, 'tbl_grading_systems')) {
		return null;
	}
	if (app_table_exists($conn, 'tbl_grading_scales')) {
		$stmt = $conn->prepare("SELECT gs.id
			FROM tbl_grading_systems gs
			JOIN tbl_grading_scales sc ON sc.grading_system_id = gs.id AND sc.is_active = 1
			WHERE gs.is_active = 1
			GROUP BY gs.id
			HAVING SUM(CASE WHEN UPPER(TRIM(sc.grade)) IN ('EE','ME','AE','BE') THEN 1 ELSE 0 END) > 0
			ORDER BY gs.is_default DESC, gs.id ASC
			LIMIT 1");
		$stmt->execute();
		$competencyId = $stmt->fetchColumn();
		if ($competencyId) {
			return (int)$competencyId;
		}
	}
	$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
	$stmt->execute();
	$value = $stmt->fetchColumn();
	return $value ? (int)$value : null;
}

function report_exam_grading_system_id(PDO $conn, ?int $examId): ?int
{
	$defaultSystemId = report_default_grading_system_id($conn);
	if (!$examId || !app_table_exists($conn, 'tbl_exams') || !app_column_exists($conn, 'tbl_exams', 'grading_system_id')) {
		return $defaultSystemId;
	}
	$stmt = $conn->prepare("SELECT grading_system_id FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$value = $stmt->fetchColumn();
	if (!$value) {
		return $defaultSystemId;
	}

	$examSystemId = (int)$value;
	if (!app_table_exists($conn, 'tbl_grading_scales')) {
		return $defaultSystemId;
	}
	$stmt = $conn->prepare("SELECT grade FROM tbl_grading_scales WHERE grading_system_id = ? AND is_active = 1");
	$stmt->execute([$examSystemId]);
	$grades = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$legacyGradeCount = 0;
	foreach ($grades as $grade) {
		$normalized = strtoupper(trim((string)$grade));
		if (in_array($normalized, ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'E'], true)) {
			$legacyGradeCount++;
		}
	}

	if ($legacyGradeCount > 0 && $defaultSystemId) {
		return $defaultSystemId;
	}

	return $examSystemId;
}

function report_grading_scales(PDO $conn, ?int $gradingSystemId = null): array
{
	if ($gradingSystemId && app_table_exists($conn, 'tbl_grading_scales')) {
		$stmt = $conn->prepare("SELECT grade AS name, min_score AS min, max_score AS max, remark, points, sort_order, is_active
			FROM tbl_grading_scales
			WHERE grading_system_id = ? AND is_active = 1
			ORDER BY min_score DESC, sort_order ASC");
		$stmt->execute([$gradingSystemId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($rows) {
			return $rows;
		}
	}

	if (!app_table_exists($conn, 'tbl_grade_system')) {
		return [];
	}
	$stmt = $conn->prepare("SELECT name, min, max, remark, 0 AS points, 0 AS sort_order, 1 AS is_active FROM tbl_grade_system ORDER BY min DESC");
	$stmt->execute();
	return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function report_get_settings(PDO $conn): array
{
	$settings = [
		'best_of' => 0,
		'use_weights' => 1,
		'require_fees_clear' => 0,
	];
	if (!app_table_exists($conn, 'tbl_result_settings')) {
		return $settings;
	}
	try {
		$stmt = $conn->prepare("SELECT best_of, use_weights, require_fees_clear FROM tbl_result_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$settings['best_of'] = (int)$row['best_of'];
			$settings['use_weights'] = (int)$row['use_weights'];
			$settings['require_fees_clear'] = (int)$row['require_fees_clear'];
		}
	} catch (Throwable $e) {
		return $settings;
	}
	return $settings;
}

function report_get_weight_map(PDO $conn): array
{
	$weights = [];
	if (!app_table_exists($conn, 'tbl_subject_weights')) {
		return $weights;
	}
	$stmt = $conn->prepare("SELECT subject_id, weight FROM tbl_subject_weights");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$weights[(int)$row['subject_id']] = (float)$row['weight'];
	}
	return $weights;
}

function report_exam_type_is_consolidated_name(string $name): bool
{
	$normalized = strtolower(trim($name));
	return $normalized !== '' && (strpos($normalized, 'consolidated') !== false || strpos($normalized, 'complex') !== false);
}

function report_exam_is_consolidated(PDO $conn, ?int $examId): bool
{
	if (!$examId || !app_table_exists($conn, 'tbl_exams') || !app_table_exists($conn, 'tbl_exam_types')) {
		return false;
	}
	$stmt = $conn->prepare("SELECT COALESCE(et.name, '') AS type_name
		FROM tbl_exams e
		LEFT JOIN tbl_exam_types et ON et.id = e.exam_type_id
		WHERE e.id = ? LIMIT 1");
	$stmt->execute([$examId]);
	return report_exam_type_is_consolidated_name((string)$stmt->fetchColumn());
}

function report_exam_weight_percentage(PDO $conn, ?int $examId): float
{
	if (!$examId) {
		return 100.0;
	}
	app_ensure_exam_weights_table($conn);
	if (!app_table_exists($conn, 'tbl_exam_weights')) {
		return 100.0;
	}
	$stmt = $conn->prepare("SELECT weight_percentage FROM tbl_exam_weights WHERE exam_id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$value = $stmt->fetchColumn();
	if ($value === false || $value === null || $value === '') {
		return 100.0;
	}
	$weight = (float)$value;
	return $weight > 0 ? $weight : 100.0;
}

function report_exam_result_matrix(PDO $conn, int $classId, int $termId, ?string $studentId = null): array
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_exam_results')) {
		return [];
	}
	app_ensure_exam_components_table($conn);

	$sql = "SELECT er.id, er.student, er.subject_combination, er.score, er.exam_id,
		COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
		COALESCE(et.name, '') AS exam_type_name,
		COALESCE(ew.weight_percentage, 100) AS weight_percentage
		FROM tbl_exam_results er
		LEFT JOIN tbl_exams e ON e.id = er.exam_id
		LEFT JOIN tbl_exam_types et ON et.id = e.exam_type_id
		LEFT JOIN tbl_exam_weights ew ON ew.exam_id = e.id
		WHERE er.class = ? AND er.term = ?";
	$args = [$classId, $termId];
	if ($studentId !== null && $studentId !== '') {
		$sql .= ' AND er.student = ?';
		$args[] = $studentId;
	}
	$sql .= ' ORDER BY er.student, er.subject_combination, er.id';

	$stmt = $conn->prepare($sql);
	$stmt->execute($args);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$latestByExam = [];
	$scoreByExam = [];
	foreach ($rows as $row) {
		$rowStudentId = (string)($row['student'] ?? '');
		if ($rowStudentId === '') {
			continue;
		}
		$subjectCombination = (int)($row['subject_combination'] ?? 0);
		if ($subjectCombination < 1) {
			continue;
		}
		$examId = isset($row['exam_id']) ? (int)$row['exam_id'] : 0;
		$latestByExam[$rowStudentId][$subjectCombination][$examId] = $row;
		$scoreByExam[$rowStudentId][$subjectCombination][$examId] = (float)($row['score'] ?? 0);
	}

	$consolidatedExams = [];
	if (app_table_exists($conn, 'tbl_exams') && app_column_exists($conn, 'tbl_exams', 'assessment_mode') && app_table_exists($conn, 'tbl_exam_components')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_exams WHERE class_id = ? AND term_id = ? AND COALESCE(assessment_mode, 'normal') = 'consolidated' AND status IN ('finalized', 'published') ORDER BY id DESC");
		$stmt->execute([$classId, $termId]);
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $consolidatedExamId) {
			$cid = (int)$consolidatedExamId;
			$stmtComp = $conn->prepare("SELECT component_exam_id FROM tbl_exam_components WHERE exam_id = ? ORDER BY component_exam_id");
			$stmtComp->execute([$cid]);
			$componentIds = array_values(array_unique(array_map('intval', $stmtComp->fetchAll(PDO::FETCH_COLUMN))));
			if (count($componentIds) >= 2) {
				$consolidatedExams[$cid] = $componentIds;
			}
		}
	}

	$matrix = [];
	foreach ($latestByExam as $rowStudentId => $subjectRows) {
		foreach ($subjectRows as $subjectCombination => $examRows) {
			$computed = null;
			foreach ($consolidatedExams as $consolidatedExamId => $componentExamIds) {
				$componentScores = [];
				foreach ($componentExamIds as $componentExamId) {
					if (isset($scoreByExam[$rowStudentId][$subjectCombination][$componentExamId])) {
						$componentScores[] = (float)$scoreByExam[$rowStudentId][$subjectCombination][$componentExamId];
					}
				}
				if (count($componentScores) >= 2) {
					$computed = [
						'score' => round(array_sum($componentScores) / count($componentScores), 2),
						'exam_id' => $consolidatedExamId,
						'is_consolidated' => true,
						'row_count' => count($componentScores),
					];
					break;
				}
			}

			if ($computed !== null) {
				$matrix[$rowStudentId][$subjectCombination] = $computed;
				continue;
			}

			$latestRow = end($examRows);
			$matrix[$rowStudentId][$subjectCombination] = [
				'score' => (float)($latestRow['score'] ?? 0),
				'exam_id' => isset($latestRow['exam_id']) ? (int)$latestRow['exam_id'] : null,
				'is_consolidated' => false,
				'row_count' => 1,
			];
		}
	}

	return $matrix;
}

function report_grade_for_score(PDO $conn, float $score, ?int $gradingSystemId = null): array
{
	$grade = 'BE';
	$remark = 'Needs improvement';
	$points = 0;
	if (!$gradingSystemId) {
		$gradingSystemId = report_default_grading_system_id($conn);
	}
	$rows = report_grading_scales($conn, $gradingSystemId);
	if (!$rows) {
		return [$grade, $remark, $points];
	}
	foreach ($rows as $row) {
		if ($score >= (float)$row['min'] && $score <= (float)$row['max']) {
			$grade = $row['name'];
			$remark = $row['remark'];
			$points = (float)($row['points'] ?? 0);
			return [$grade, $remark, $points];
		}
	}
	return [$grade, $remark, $points];
}

function report_fetch_subjects_for_class(PDO $conn, int $classId): array
{
	$allowedSubjectIds = app_class_subject_ids($conn, $classId);
	$allowedSubjectLookup = !empty($allowedSubjectIds) ? array_fill_keys(array_map('intval', $allowedSubjectIds), true) : [];
	$stmt = $conn->prepare("SELECT sc.id AS combination_id, sc.class, sc.subject, sc.teacher, s.name AS subject_name, st.fname, st.lname
		FROM tbl_subject_combinations sc
		LEFT JOIN tbl_subjects s ON s.id = sc.subject
		LEFT JOIN tbl_staff st ON st.id = sc.teacher");
	$stmt->execute();
	$subjects = [];
	$seenSubjects = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$classList = app_unserialize($row['class']);
		if (in_array((string)$classId, $classList, true) || in_array($classId, $classList, true)) {
			$subjectId = (int)$row['subject'];
			if (!empty($allowedSubjectLookup) && !isset($allowedSubjectLookup[$subjectId])) {
				continue;
			}
			if (isset($seenSubjects[$subjectId])) {
				continue;
			}
			$seenSubjects[$subjectId] = true;
			$subjects[] = $row;
		}
	}
	return $subjects;
}

function report_cbc_level_to_score(string $level): float
{
	$level = strtoupper(trim($level));
	if ($level === 'EE') return 85.0;
	if ($level === 'ME') return 70.0;
	if ($level === 'AE') return 50.0;
	if ($level === 'BE') return 30.0;
	return 0.0;
}

function report_cbc_grading_rows(PDO $conn): array
{
	if (app_table_exists($conn, 'tbl_cbc_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points FROM tbl_cbc_grading WHERE active = 1 ORDER BY min_mark DESC, sort_order ASC");
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!empty($rows)) {
			return $rows;
		}
	}

	return [
		['level' => 'EE', 'min_mark' => 90, 'max_mark' => 100, 'points' => 4],
		['level' => 'ME', 'min_mark' => 75, 'max_mark' => 89, 'points' => 3],
		['level' => 'AE', 'min_mark' => 50, 'max_mark' => 74, 'points' => 2],
		['level' => 'BE', 'min_mark' => 0, 'max_mark' => 49, 'points' => 1],
	];
}

function report_cbc_grade_for_score(PDO $conn, float $score): array
{
	$rows = report_cbc_grading_rows($conn);
	foreach ($rows as $row) {
		$min = (float)($row['min_mark'] ?? 0);
		$max = (float)($row['max_mark'] ?? 100);
		if ($score >= $min && $score <= $max) {
			$level = strtoupper((string)($row['level'] ?? 'BE'));
			$points = (float)($row['points'] ?? 0);
			$remark = $level === 'EE' ? 'Exceeding Expectation' : ($level === 'ME' ? 'Meeting Expectation' : ($level === 'AE' ? 'Approaching Expectation' : 'Below Expectation'));
			return [$level, $remark, $points];
		}
	}
	return ['BE', 'Below Expectation', 1.0];
}

function report_term_assessment_mode(PDO $conn, int $classId, int $termId): string
{
	static $modeCache = [];
	$cacheKey = $classId . ':' . $termId;
	if (isset($modeCache[$cacheKey])) {
		return $modeCache[$cacheKey];
	}

	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		$modeCache[$cacheKey] = 'normal';
		return $modeCache[$cacheKey];
	}
	if (!app_column_exists($conn, 'tbl_exams', 'assessment_mode')) {
		$modeCache[$cacheKey] = 'normal';
		return $modeCache[$cacheKey];
	}

	$stmt = $conn->prepare("SELECT COALESCE(assessment_mode, 'normal') AS assessment_mode FROM tbl_exams WHERE class_id = ? AND term_id = ?");
	$stmt->execute([$classId, $termId]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (empty($rows)) {
		$modeCache[$cacheKey] = 'normal';
		return $modeCache[$cacheKey];
	}

	$hasCbc = false;
	$hasNormal = false;
	foreach ($rows as $row) {
		$mode = strtolower(trim((string)($row['assessment_mode'] ?? 'normal')));
		if ($mode === 'cbc') {
			$hasCbc = true;
		} else {
			$hasNormal = true;
		}
	}

	if ($hasCbc && !$hasNormal) {
		$modeCache[$cacheKey] = 'cbc';
		return $modeCache[$cacheKey];
	}

	$modeCache[$cacheKey] = 'normal';
	return $modeCache[$cacheKey];
}

function report_attach_computed_metrics(PDO $conn, array $card): array
{
	$subjects = is_array($card['subjects'] ?? null) ? $card['subjects'] : [];
	$pointSum = 0.0;
	$pointCount = 0;
	foreach ($subjects as $subject) {
		if (isset($subject['grade_points']) && $subject['grade_points'] !== '') {
			$pointSum += (float)$subject['grade_points'];
			$pointCount++;
		}
	}
	$card['mean_points'] = $pointCount > 0 ? round($pointSum / $pointCount, 2) : 0.0;
	$card['average_score'] = (float)($card['mean'] ?? 0);

	$classId = (int)($card['class_id'] ?? 0);
	$termId = (int)($card['term_id'] ?? 0);
	$mode = report_term_assessment_mode($conn, $classId, $termId);
	$card['assessment_mode'] = $mode;

	if ($mode === 'cbc') {
		list($cbcGrade, $cbcRemark,) = report_cbc_grade_for_score($conn, (float)($card['mean'] ?? 0));
		$card['grade'] = $cbcGrade;
		if (empty($card['remark'])) {
			$card['remark'] = $cbcRemark;
		}
	}

	return $card;
}

function report_cbc_score_matrix(PDO $conn, int $classId, int $termId, array $subjects, ?string $studentId = null): array
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_cbc_assessments')) {
		return [];
	}

	$hasSubjectId = app_column_exists($conn, 'tbl_cbc_assessments', 'subject_id');
	$hasMarks = app_column_exists($conn, 'tbl_cbc_assessments', 'marks');

	$subjectNameMap = [];
	foreach ($subjects as $subject) {
		$subjectNameMap[strtolower(trim((string)$subject['subject_name']))] = (int)$subject['subject'];
	}

	$selectCols = 'student_id, level';
	if ($hasMarks) {
		$selectCols .= ', marks';
	}
	if ($hasSubjectId) {
		$selectCols .= ', subject_id';
	} else {
		$selectCols .= ', learning_area';
	}

	$sql = "SELECT $selectCols FROM tbl_cbc_assessments WHERE class_id = ? AND term_id = ?";
	$args = [$classId, $termId];
	if ($studentId !== null && $studentId !== '') {
		$sql .= ' AND student_id = ?';
		$args[] = $studentId;
	}

	$stmt = $conn->prepare($sql);
	$stmt->execute($args);

	$sum = [];
	$count = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$rowStudentId = (string)($row['student_id'] ?? '');
		if ($rowStudentId === '') {
			continue;
		}

		$subjectId = 0;
		if ($hasSubjectId) {
			$subjectId = (int)($row['subject_id'] ?? 0);
		} else {
			$learningArea = strtolower(trim((string)($row['learning_area'] ?? '')));
			$subjectId = (int)($subjectNameMap[$learningArea] ?? 0);
		}
		if ($subjectId < 1) {
			continue;
		}

		$score = null;
		if ($hasMarks && isset($row['marks']) && $row['marks'] !== null && $row['marks'] !== '') {
			$score = (float)$row['marks'];
		} else {
			$score = report_cbc_level_to_score((string)($row['level'] ?? ''));
		}

		if (!isset($sum[$rowStudentId])) {
			$sum[$rowStudentId] = [];
			$count[$rowStudentId] = [];
		}
		$sum[$rowStudentId][$subjectId] = (float)($sum[$rowStudentId][$subjectId] ?? 0) + $score;
		$count[$rowStudentId][$subjectId] = (int)($count[$rowStudentId][$subjectId] ?? 0) + 1;
	}

	$matrix = [];
	foreach ($sum as $sid => $subjectRows) {
		foreach ($subjectRows as $subjectId => $total) {
			$den = (int)($count[$sid][$subjectId] ?? 0);
			if ($den > 0) {
				$matrix[$sid][$subjectId] = round($total / $den, 2);
			}
		}
	}

	return $matrix;
}

function report_fetch_scores(PDO $conn, string $studentId, int $classId, int $termId, array $subjects): array
{
	static $classTermCbcCache = [];
	$cbcKey = $classId . ':' . $termId;
	if (!isset($classTermCbcCache[$cbcKey])) {
		$classTermCbcCache[$cbcKey] = report_cbc_score_matrix($conn, $classId, $termId, $subjects, null);
	}
	static $classTermExamCache = [];
	if (!isset($classTermExamCache[$cbcKey])) {
		$classTermExamCache[$cbcKey] = report_exam_result_matrix($conn, $classId, $termId, null);
	}

	$subjectMap = [];
	foreach ($subjects as $subject) {
		$subjectMap[(int)$subject['combination_id']] = $subject;
	}
	$latest = $classTermExamCache[$cbcKey][$studentId] ?? [];

	$cbcMatrix = $classTermCbcCache[$cbcKey];
	$cbcSubjectScores = $cbcMatrix[$studentId] ?? [];

	$scores = [];
	$gradingCache = [];
	foreach ($subjects as $subject) {
		$score = 0;
		$value = $latest[(int)$subject['combination_id']] ?? null;
		if ($value && $value['score'] !== null && $value['score'] !== '') {
			$score = (float)$value['score'];
		} else {
			$subjectId = (int)$subject['subject'];
			if (isset($cbcSubjectScores[$subjectId])) {
				$score = (float)$cbcSubjectScores[$subjectId];
			}
		}
		$examId = isset($value['exam_id']) ? (int)$value['exam_id'] : null;
		if (!array_key_exists((int)$examId, $gradingCache)) {
			$gradingCache[(int)$examId] = report_exam_grading_system_id($conn, $examId);
		}
		$gradingSystemId = $gradingCache[(int)$examId];
		$usedCbcFallback = (!$value || $value['score'] === null || $value['score'] === '') && isset($cbcSubjectScores[(int)$subject['subject']]);
		if ($usedCbcFallback) {
			list($gradeLabel, $gradeRemark, $gradePoints) = report_cbc_grade_for_score($conn, $score);
		} else {
			list($gradeLabel, $gradeRemark, $gradePoints) = report_grade_for_score($conn, $score, $gradingSystemId);
		}
		$storedGrade = $value['grade_label'] ?? null;
		if (is_string($storedGrade)) {
			$storedGrade = trim($storedGrade);
		}
		if (!is_string($storedGrade) || $storedGrade === '' || strtolower($storedGrade) === 'null') {
			$storedGrade = $gradeLabel;
		}

		$storedPoints = $value['grade_points'] ?? null;
		if (is_string($storedPoints)) {
			$storedPoints = trim($storedPoints);
		}
		if ($storedPoints === null || $storedPoints === '' || (is_string($storedPoints) && strtolower($storedPoints) === 'null')) {
			$storedPoints = $gradePoints;
		}

		$scores[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => $subject['subject_name'],
			'teacher_id' => $subject['teacher'] ? (int)$subject['teacher'] : null,
			'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
			'score' => $score,
			'exam_id' => $examId,
			'grade' => (string)$storedGrade,
			'grade_points' => (float)$storedPoints,
			'grade_remark' => $gradeRemark
		];
	}
	return $scores;
}

function report_compute_totals(PDO $conn, array $scores, array $weights, array $settings): array
{
	$rows = [];
	foreach ($scores as $row) {
		$weight = 1.0;
		if (!empty($settings['use_weights']) && isset($weights[$row['subject_id']])) {
			$weight = (float)$weights[$row['subject_id']];
		}
		$weighted = $row['score'] * $weight;
		$rows[] = $row + ['weight' => $weight, 'weighted_score' => $weighted];
	}
	usort($rows, function ($a, $b) {
		return $b['weighted_score'] <=> $a['weighted_score'];
	});

	$bestOf = (int)$settings['best_of'];
	if ($bestOf > 0 && count($rows) > $bestOf) {
		$rows = array_slice($rows, 0, $bestOf);
	}

	$total = 0;
	$gradingSystemId = null;
	foreach ($rows as $row) {
		$total += $row['weighted_score'];
		if ($gradingSystemId === null && !empty($row['exam_id'])) {
			$gradingSystemId = report_exam_grading_system_id($conn, (int)$row['exam_id']);
		}
	}
	$count = count($rows);
	$mean = $count > 0 ? round($total / $count, 2) : 0;

	list($grade, $remark) = report_grade_for_score($conn, $mean, $gradingSystemId);

	return [
		'rows' => $rows,
		'total' => round($total, 2),
		'mean' => $mean,
		'grade' => $grade,
		'remark' => $remark
	];
}

function report_fees_balance(PDO $conn, string $studentId, int $termId): float
{
	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines')) {
		return 0;
	}
	$stmt = $conn->prepare("SELECT id FROM tbl_invoices WHERE student_id = ? AND term_id = ? AND status <> 'void' LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	$invoiceId = $stmt->fetchColumn();
	if (!$invoiceId) {
		return 0;
	}
	$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_invoice_lines WHERE invoice_id = ?");
	$stmt->execute([$invoiceId]);
	$total = (float)$stmt->fetchColumn();
	$paid = 0;
	if (app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE invoice_id = ?");
		$stmt->execute([$invoiceId]);
		$paid = (float)$stmt->fetchColumn();
	}
	return max(0, round($total - $paid, 2));
}

function report_attendance_summary(PDO $conn, string $studentId, int $classId, int $termId): array
{
	$summary = ['days_open' => 0, 'present' => 0, 'absent' => 0];
	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		return $summary;
	}
	$stmt = $conn->prepare("SELECT id FROM tbl_attendance_sessions WHERE class_id = ? AND term_id = ?");
	$stmt->execute([$classId, $termId]);
	$sessionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
	if (!$sessionIds) {
		return $summary;
	}
	$summary['days_open'] = count($sessionIds);
	$placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
	$params = $sessionIds;
	$params[] = $studentId;
	$stmt = $conn->prepare("SELECT status, COUNT(*) AS count FROM tbl_attendance_records WHERE session_id IN ($placeholders) AND student_id = ? GROUP BY status");
	$stmt->execute($params);
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		if ($row['status'] === 'present') {
			$summary['present'] = (int)$row['count'];
		}
		if ($row['status'] === 'absent') {
			$summary['absent'] = (int)$row['count'];
		}
	}
	return $summary;
}

function report_trend(PDO $conn, string $studentId, int $currentTermId, float $mean): string
{
	if (!app_table_exists($conn, 'tbl_report_cards')) {
		return 'New';
	}
	$stmt = $conn->prepare("SELECT term_id, mean FROM tbl_report_cards WHERE student_id = ? AND term_id < ? ORDER BY term_id DESC LIMIT 1");
	$stmt->execute([$studentId, $currentTermId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return 'New';
	}
	$prevMean = (float)$row['mean'];
	if ($mean > $prevMean) {
		return 'Improved';
	}
	if ($mean < $prevMean) {
		return 'Dropped';
	}
	return 'No change';
}

function report_previous_mean(PDO $conn, string $studentId, int $currentTermId): ?float
{
	if (!app_table_exists($conn, 'tbl_report_cards')) {
		return null;
	}
	$stmt = $conn->prepare("SELECT mean FROM tbl_report_cards WHERE student_id = ? AND term_id < ? ORDER BY term_id DESC LIMIT 1");
	$stmt->execute([$studentId, $currentTermId]);
	$value = $stmt->fetchColumn();
	if ($value === false || $value === null || $value === '') {
		return null;
	}
	return (float)$value;
}

function report_ai_comment_bundle(array $subjects, float $mean, ?float $previousMean, string $grade, string $trend): array
{
	$strengths = [];
	$weaknesses = [];
	foreach ($subjects as $subject) {
		$score = (float)($subject['score'] ?? 0);
		$name = (string)($subject['subject_name'] ?? 'Subject');
		if ($score >= 75) {
			$strengths[] = $name;
		}
		if ($score < 50) {
			$weaknesses[] = $name;
		}
	}

	$summaryParts = [];
	$summaryParts[] = 'Overall average: ' . number_format($mean, 2) . '%. Grade: ' . $grade . '.';
	if ($previousMean !== null) {
		if ($mean > $previousMean) {
			$summaryParts[] = 'There is a positive improvement from last term.';
		} elseif ($mean < $previousMean) {
			$summaryParts[] = 'Performance has slightly dropped. More effort is needed.';
		} else {
			$summaryParts[] = 'Performance remains consistent.';
		}
	} else {
		$summaryParts[] = 'This is the first published report in the current trend window.';
	}

	$teacherParts = [];
	if (!empty($strengths)) {
		$teacherParts[] = 'The learner shows strong performance in ' . implode(', ', $strengths) . '.';
	}
	if (!empty($weaknesses)) {
		$teacherParts[] = 'Improvement is needed in ' . implode(', ', $weaknesses) . '.';
	}
	if (empty($weaknesses)) {
		$teacherParts[] = 'Keep up the excellent work across all learning areas.';
	}

	$headParts = [];
	if ($trend === 'Improved') {
		$headParts[] = 'Good progress this term. Maintain the momentum.';
	} elseif ($trend === 'Dropped') {
		$headParts[] = 'More support and focused revision are recommended next term.';
	} else {
		$headParts[] = 'Steady progress noted. Continue working consistently.';
	}
	if (!empty($weaknesses)) {
		$headParts[] = 'Priority support areas: ' . implode(', ', $weaknesses) . '.';
	}

	$summary = trim(implode(' ', $summaryParts));
	$teacherComment = trim(implode(' ', $teacherParts));
	$headComment = trim(implode(' ', $headParts));

	return [
		'ai_summary' => $summary,
		'teacher_comment' => $teacherComment,
		'headteacher_comment' => $headComment,
		'strengths' => $strengths,
		'weaknesses' => $weaknesses,
	];
}

function report_attach_ai_comments(PDO $conn, array $card): array
{
	$subjects = is_array($card['subjects'] ?? null) ? $card['subjects'] : [];
	$mean = (float)($card['mean'] ?? 0);
	$grade = (string)($card['grade'] ?? 'N/A');
	$trend = (string)($card['trend'] ?? 'New');
	$studentId = (string)($card['student_id'] ?? '');
	$termId = (int)($card['term_id'] ?? 0);

	$previousMean = null;
	if ($studentId !== '' && $termId > 0) {
		$previousMean = report_previous_mean($conn, $studentId, $termId);
	}

	$bundle = report_ai_comment_bundle($subjects, $mean, $previousMean, $grade, $trend);
	$card['ai_summary'] = $bundle['ai_summary'];
	$card['teacher_comment'] = $bundle['teacher_comment'];
	$card['headteacher_comment'] = $bundle['headteacher_comment'];
	$card['strengths'] = $bundle['strengths'];
	$card['weaknesses'] = $bundle['weaknesses'];
	return $card;
}

function report_generate_code(string $studentId): string
{
	$year = date('Y');
	$rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
	return 'ELIMU-' . $year . '-' . $studentId . '-' . $rand;
}

function report_generate_hash(array $payload): string
{
	$secret = APP_SECRET !== '' ? APP_SECRET : 'elimu-hub';
	$raw = json_encode($payload) . '|' . $secret;
	return hash('sha256', $raw);
}

function report_compute_for_student(PDO $conn, string $studentId, int $classId, int $termId): array
{
	static $settingsCache = null;
	static $weightCache = null;
	static $subjectCache = [];

	if ($settingsCache === null) {
		$settingsCache = report_get_settings($conn);
	}
	if ($weightCache === null) {
		$weightCache = report_get_weight_map($conn);
	}
	if (!isset($subjectCache[$classId])) {
		$subjectCache[$classId] = report_fetch_subjects_for_class($conn, $classId);
	}

	$settings = $settingsCache;
	$weights = $weightCache;
	$subjects = $subjectCache[$classId];
	$scores = report_fetch_scores($conn, $studentId, $classId, $termId, $subjects);
	$totals = report_compute_totals($conn, $scores, $weights, $settings);
	$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
	$fees = report_fees_balance($conn, $studentId, $termId);
	$trend = report_trend($conn, $studentId, $termId, $totals['mean']);

	$card = [
		'subjects' => $totals['rows'],
		'total' => $totals['total'],
		'mean' => $totals['mean'],
		'grade' => $totals['grade'],
		'remark' => $totals['remark'],
		'attendance' => $attendance,
		'fees_balance' => $fees,
		'trend' => $trend,
		'settings' => $settings
	];

	$bundle = report_ai_comment_bundle($totals['rows'], (float)$totals['mean'], report_previous_mean($conn, $studentId, $termId), (string)$totals['grade'], (string)$trend);
	$card['ai_summary'] = $bundle['ai_summary'];
	$card['teacher_comment'] = $bundle['teacher_comment'];
	$card['headteacher_comment'] = $bundle['headteacher_comment'];
	$card['strengths'] = $bundle['strengths'];
	$card['weaknesses'] = $bundle['weaknesses'];

	return report_attach_computed_metrics($conn, $card);
}

function report_rank_students(PDO $conn, int $classId, int $termId): array
{
	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$students = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
	$rankings = [];
	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$weights = report_get_weight_map($conn);
	$settings = report_get_settings($conn);
	$subjectByCombination = [];
	foreach ($subjects as $subject) {
		$subjectByCombination[(int)$subject['combination_id']] = (int)$subject['subject'];
	}
	$examMatrix = report_exam_result_matrix($conn, $classId, $termId, null);
	$cbcMatrix = report_cbc_score_matrix($conn, $classId, $termId, $subjects, null);

	if (!empty($students) && !empty($subjectByCombination)) {
		foreach ($students as $studentId) {
			$weightedScores = [];
			$examBySubject = $examMatrix[$studentId] ?? [];
			$cbcBySubject = $cbcMatrix[$studentId] ?? [];
			foreach ($subjectByCombination as $combinationId => $subjectId) {
				$score = null;
				if (isset($examBySubject[$combinationId])) {
					$score = (float)$examBySubject[$combinationId]['score'];
				} elseif (isset($cbcBySubject[$subjectId])) {
					$score = (float)$cbcBySubject[$subjectId];
				}
				if ($score === null) {
					continue;
				}
				$weight = (!empty($settings['use_weights']) && isset($weights[$subjectId])) ? (float)$weights[$subjectId] : 1.0;
				$weightedScores[] = $score * $weight;
			}
			rsort($weightedScores, SORT_NUMERIC);
			$bestOf = (int)$settings['best_of'];
			if ($bestOf > 0 && count($weightedScores) > $bestOf) {
				$weightedScores = array_slice($weightedScores, 0, $bestOf);
			}
			$rankings[] = [
				'student_id' => $studentId,
				'total' => round(array_sum($weightedScores), 2),
			];
		}
	} else {
		foreach ($students as $studentId) {
			$rankings[] = ['student_id' => $studentId, 'total' => 0];
		}
	}
	usort($rankings, function ($a, $b) {
		return $b['total'] <=> $a['total'];
	});
	$positions = [];
	$position = 0;
	$prevTotal = null;
	foreach ($rankings as $index => $row) {
		if ($prevTotal === null || $row['total'] != $prevTotal) {
			$position = $index + 1;
			$prevTotal = $row['total'];
		}
		$positions[$row['student_id']] = $position;
	}
	return [
		'positions' => $positions,
		'total_students' => count($students)
	];
}

function report_load_card(PDO $conn, int $reportId): ?array
{
	if (!app_table_exists($conn, 'tbl_report_cards')) {
		return null;
	}
	$stmt = $conn->prepare("SELECT * FROM tbl_report_cards WHERE id = ? LIMIT 1");
	$stmt->execute([$reportId]);
	$card = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$card) {
		return null;
	}
	$subjects = [];
	if (app_table_exists($conn, 'tbl_report_card_subjects')) {
		$stmt = $conn->prepare("SELECT r.subject_id, r.score, r.grade, r.weight, s.name AS subject_name, st.fname, st.lname
			FROM tbl_report_card_subjects r
			LEFT JOIN tbl_subjects s ON s.id = r.subject_id
			LEFT JOIN tbl_staff st ON st.id = r.teacher_id
			WHERE r.report_id = ?
			ORDER BY s.name");
		$stmt->execute([$reportId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$subjects[] = [
				'subject_id' => (int)($row['subject_id'] ?? 0),
				'subject_name' => $row['subject_name'],
				'score' => (float)$row['score'],
				'grade' => $row['grade'],
				'weight' => (float)$row['weight'],
				'teacher_name' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
			];
		}
	}
	$card['subjects'] = $subjects;
	$card = report_attach_ai_comments($conn, $card);
	return report_attach_computed_metrics($conn, $card);
}

function report_term_publish_state(PDO $conn, int $classId, int $termId): string
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		if (app_table_exists($conn, 'tbl_exam_results')) {
			try {
				$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ?");
				$stmt->execute([$classId, $termId]);
				if ((int)$stmt->fetchColumn() > 0) {
					return 'published';
				}
			} catch (Throwable $e) {
				// fall through
			}
		}
		return 'draft';
	}

	$stmt = $conn->prepare("SELECT status, COUNT(*) AS total
		FROM tbl_exams
		WHERE class_id = ? AND term_id = ?
		GROUP BY status");
	$stmt->execute([$classId, $termId]);
	$counts = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$counts[(string)$row['status']] = (int)$row['total'];
	}
	if (empty($counts)) {
		if (app_table_exists($conn, 'tbl_exam_results')) {
			try {
				$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ?");
				$stmt->execute([$classId, $termId]);
				if ((int)$stmt->fetchColumn() > 0) {
					return 'published';
				}
			} catch (Throwable $e) {
				// fall through
			}
		}
		return 'draft';
	}
	foreach (['published', 'finalized', 'reviewed', 'active', 'draft'] as $status) {
		if (!empty($counts[$status])) {
			return $status;
		}
	}
	return 'draft';
}

function report_term_is_published(PDO $conn, int $classId, int $termId): bool
{
	$state = report_term_publish_state($conn, $classId, $termId);
	return in_array($state, ['published', 'finalized', 'reviewed', 'active'], true);
}

function report_student_term_history(PDO $conn, string $studentId, int $classId, int $limit = 6): array
{
	$limit = max(1, $limit);
	if (!app_table_exists($conn, 'tbl_report_cards')) {
		return [];
	}
	$stmt = $conn->prepare("SELECT rc.term_id, rc.mean, t.name AS term_name
		FROM tbl_report_cards rc
		LEFT JOIN tbl_terms t ON t.id = rc.term_id
		WHERE rc.student_id = ? AND rc.class_id = ?
		ORDER BY rc.term_id DESC
		LIMIT $limit");
	$stmt->execute([$studentId, $classId]);
	$history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
	return array_map(function ($row) {
		return [
			'term_id' => (int)$row['term_id'],
			'term_name' => (string)($row['term_name'] ?? ('Term ' . $row['term_id'])),
			'mean' => (float)($row['mean'] ?? 0),
		];
	}, $history);
}

function report_subject_breakdown(PDO $conn, string $studentId, int $classId, int $termId): array
{
	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$weights = report_get_weight_map($conn);
	$settings = report_get_settings($conn);
	$rows = [];
	$combinationIds = array_map(function ($subject) {
		return (int)$subject['combination_id'];
	}, $subjects);

	$prevTermId = 0;
	if (app_table_exists($conn, 'tbl_terms')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_terms WHERE id < ? ORDER BY id DESC LIMIT 1");
		$stmt->execute([$termId]);
		$prevTermId = (int)$stmt->fetchColumn();
	}

	$currentStudentScores = [];
	$currentMeans = [];
	$previousMeans = [];
	$currentMatrix = report_exam_result_matrix($conn, $classId, $termId, null);
	$previousMatrix = $prevTermId > 0 ? report_exam_result_matrix($conn, $classId, $prevTermId, null) : [];
	$cbcCurrent = report_cbc_score_matrix($conn, $classId, $termId, $subjects, null);
	$cbcPrevious = $prevTermId > 0 ? report_cbc_score_matrix($conn, $classId, $prevTermId, $subjects, null) : [];

	if (!empty($combinationIds)) {
		foreach ($combinationIds as $combinationId) {
			$currentStudentScores[$combinationId] = (float)($currentMatrix[$studentId][$combinationId]['score'] ?? 0.0);
		}

		$subjectTotals = [];
		$subjectCounts = [];
		foreach ($currentMatrix as $sid => $subjectRows) {
			foreach ($subjectRows as $combinationId => $row) {
				$subjectTotals[$combinationId] = (float)($subjectTotals[$combinationId] ?? 0) + (float)($row['score'] ?? 0);
				$subjectCounts[$combinationId] = (int)($subjectCounts[$combinationId] ?? 0) + 1;
			}
		}
		foreach ($subjectTotals as $combinationId => $total) {
			$currentMeans[(int)$combinationId] = round($total / max(1, (int)$subjectCounts[$combinationId]), 2);
		}

		if ($prevTermId > 0) {
			$prevTotals = [];
			$prevCounts = [];
			foreach ($previousMatrix as $sid => $subjectRows) {
				foreach ($subjectRows as $combinationId => $row) {
					$prevTotals[$combinationId] = (float)($prevTotals[$combinationId] ?? 0) + (float)($row['score'] ?? 0);
					$prevCounts[$combinationId] = (int)($prevCounts[$combinationId] ?? 0) + 1;
				}
			}
			foreach ($prevTotals as $combinationId => $total) {
				$previousMeans[(int)$combinationId] = round($total / max(1, (int)$prevCounts[$combinationId]), 2);
			}
		}
	}

	$cbcCurrentStudent = $cbcCurrent[$studentId] ?? [];
	$cbcCurrentClassMeans = [];
	if (!empty($cbcCurrent)) {
		$sum = [];
		$cnt = [];
		foreach ($cbcCurrent as $sid => $subjectScores) {
			foreach ($subjectScores as $subjectId => $score) {
				$sum[$subjectId] = (float)($sum[$subjectId] ?? 0) + (float)$score;
				$cnt[$subjectId] = (int)($cnt[$subjectId] ?? 0) + 1;
			}
		}
		foreach ($sum as $subjectId => $total) {
			if ((int)$cnt[$subjectId] > 0) {
				$cbcCurrentClassMeans[$subjectId] = round($total / (int)$cnt[$subjectId], 2);
			}
		}
	}

	$cbcPreviousClassMeans = [];
	if ($prevTermId > 0 && !empty($cbcPrevious)) {
		$sum = [];
		$cnt = [];
		foreach ($cbcPrevious as $sid => $subjectScores) {
			foreach ($subjectScores as $subjectId => $score) {
				$sum[$subjectId] = (float)($sum[$subjectId] ?? 0) + (float)$score;
				$cnt[$subjectId] = (int)($cnt[$subjectId] ?? 0) + 1;
			}
		}
		foreach ($sum as $subjectId => $total) {
			if ((int)$cnt[$subjectId] > 0) {
				$cbcPreviousClassMeans[$subjectId] = round($total / (int)$cnt[$subjectId], 2);
			}
		}
	}

	foreach ($subjects as $subject) {
		$combinationId = (int)$subject['combination_id'];
		$subjectId = (int)$subject['subject'];
		$currentScore = (float)($currentStudentScores[$combinationId] ?? ($cbcCurrentStudent[$subjectId] ?? 0.0));
		$classMean = (float)($currentMeans[$combinationId] ?? ($cbcCurrentClassMeans[$subjectId] ?? 0.0));
		$previousMean = (float)($previousMeans[$combinationId] ?? ($cbcPreviousClassMeans[$subjectId] ?? 0.0));

		$change = round($classMean - $previousMean, 2);
		$trend = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'steady');
		$weight = (!empty($settings['use_weights']) && isset($weights[(int)$subject['subject']])) ? (float)$weights[(int)$subject['subject']] : 1.0;
		list($grade, $remark) = report_grade_for_score($conn, $currentScore);

		$rows[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => (string)$subject['subject_name'],
			'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
			'score' => round($currentScore, 2),
			'class_mean' => $classMean,
			'previous_mean' => $previousMean,
			'change' => $change,
			'trend' => $trend,
			'grade' => $grade,
			'remark' => $remark,
			'weight' => $weight,
			'progress' => max(0, min(100, $classMean)),
		];
	}

	usort($rows, function ($a, $b) {
		return $b['class_mean'] <=> $a['class_mean'];
	});

	return $rows;
}

function report_store_card(PDO $conn, string $studentId, int $classId, int $termId, array $report, array $positions, int $totalStudents, ?int $generatedBy = null): int
{
	$position = $positions[$studentId] ?? 0;
	$code = report_generate_code($studentId);
	$payload = [
		'student_id' => $studentId,
		'class_id' => $classId,
		'term_id' => $termId,
		'total' => $report['total'],
		'mean' => $report['mean'],
		'grade' => $report['grade'],
		'position' => $position
	];
	$hash = report_generate_hash($payload);
	$trend = $report['trend'];

	$stmt = $conn->prepare("SELECT id, verification_code FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	$existing = $stmt->fetch(PDO::FETCH_ASSOC);
	$reportId = 0;
	if ($existing) {
		$reportId = (int)$existing['id'];
		$existingCode = trim((string)($existing['verification_code'] ?? ''));
		if ($existingCode === '') {
			$existingCode = $code;
		}
		$stmt = $conn->prepare("UPDATE tbl_report_cards
			SET total = ?, mean = ?, grade = ?, remark = ?, position = ?, total_students = ?, trend = ?, report_hash = ?, verification_code = ?, generated_by = ?, generated_at = CURRENT_TIMESTAMP
			WHERE id = ?");
		$stmt->execute([
			$report['total'],
			$report['mean'],
			$report['grade'],
			$report['remark'],
			$position,
			$totalStudents,
			$trend,
			$hash,
			$existingCode,
			$generatedBy,
			$reportId
		]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_report_cards (student_id, class_id, term_id, total, mean, grade, remark, position, total_students, trend, verification_code, report_hash, generated_by)
			VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)");
		$stmt->execute([
			$studentId,
			$classId,
			$termId,
			$report['total'],
			$report['mean'],
			$report['grade'],
			$report['remark'],
			$position,
			$totalStudents,
			$trend,
			$code,
			$hash,
			$generatedBy
		]);
		$reportId = (int)$conn->lastInsertId();
	}

	if (app_table_exists($conn, 'tbl_report_card_subjects')) {
		$stmt = $conn->prepare("DELETE FROM tbl_report_card_subjects WHERE report_id = ?");
		$stmt->execute([$reportId]);
		$insert = $conn->prepare("INSERT INTO tbl_report_card_subjects (report_id, subject_id, score, grade, weight, teacher_id) VALUES (?,?,?,?,?,?)");
		foreach ($report['subjects'] as $subject) {
			$insert->execute([
				$reportId,
				$subject['subject_id'],
				$subject['score'],
				$subject['grade'],
				$subject['weight'],
				$subject['teacher_id']
			]);
		}
	}

	return $reportId;
}

function report_ensure_card_generated(PDO $conn, string $studentId, int $classId, int $termId, ?int $generatedBy = null): ?array
{
	if (!app_table_exists($conn, 'tbl_report_cards') || !report_term_is_published($conn, $classId, $termId)) {
		return null;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	$reportId = (int)$stmt->fetchColumn();
	if ($reportId > 0) {
		$card = report_load_card($conn, $reportId);
		if ($card && !empty($card['subjects'])) {
			return $card;
		}
	}

	$rankData = report_rank_students($conn, $classId, $termId);
	$report = report_compute_for_student($conn, $studentId, $classId, $termId);
	$reportId = report_store_card($conn, $studentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], $generatedBy);
	return report_load_card($conn, $reportId);
}

function report_teacher_has_class_access(PDO $conn, int $teacherId, int $classId, int $termId = 0): bool
{
	try {
		app_ensure_class_teachers_table($conn);
		$stmt = $conn->prepare("SELECT 1 FROM tbl_class_teachers WHERE teacher_id = ? AND class_id = ? AND active = 1 LIMIT 1");
		$stmt->execute([$teacherId, $classId]);
		if ($stmt->fetchColumn()) {
			return true;
		}
	} catch (Throwable $e) {
		// ignore and continue to subject-based access checks
	}

	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		if ($termId > 0) {
			$stmt = $conn->prepare("SELECT 1 FROM tbl_teacher_assignments WHERE teacher_id = ? AND class_id = ? AND term_id = ? AND status = 1 LIMIT 1");
			$stmt->execute([$teacherId, $classId, $termId]);
		} else {
			$stmt = $conn->prepare("SELECT 1 FROM tbl_teacher_assignments WHERE teacher_id = ? AND class_id = ? AND status = 1 LIMIT 1");
			$stmt->execute([$teacherId, $classId]);
		}
		return (bool)$stmt->fetchColumn();
	}

	$stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations WHERE teacher = ?");
	$stmt->execute([$teacherId]);
	foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $classSet) {
		$classList = app_unserialize($classSet);
		if (in_array((string)$classId, $classList, true) || in_array($classId, $classList, true)) {
			return true;
		}
	}
	return false;
}

function report_get_student_identity(PDO $conn, string $studentId): ?array
{
	$stmt = $conn->prepare("SELECT st.id, st.school_id, st.fname, st.mname, st.lname, st.class, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE st.id = ?
		LIMIT 1");
	$stmt->execute([$studentId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}
	return [
		'id' => (string)$row['id'],
		'school_id' => (string)($row['school_id'] ?? ''),
		'name' => trim(($row['fname'] ?? '') . ' ' . ($row['mname'] ?? '') . ' ' . ($row['lname'] ?? '')),
		'class_id' => (int)$row['class'],
		'class_name' => (string)($row['class_name'] ?? ''),
	];
}

function report_class_merit_list(PDO $conn, int $classId, int $termId, ?int $generatedBy = null): array
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_students') || !app_table_exists($conn, 'tbl_report_cards')) {
		return [
			'rows' => [],
			'total_students' => 0,
			'positions' => [],
		];
	}

	$stmt = $conn->prepare("SELECT id, school_id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY fname, lname, id");
	$stmt->execute([$classId]);
	$studentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (!$studentRows) {
		return [
			'rows' => [],
			'total_students' => 0,
			'positions' => [],
		];
	}
	$studentIds = array_map(static function ($row) {
		return (string)$row['id'];
	}, $studentRows);
	$className = '';
	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$classId]);
	$className = (string)$stmt->fetchColumn();
	$identityMap = [];
	foreach ($studentRows as $studentRow) {
		$studentId = (string)$studentRow['id'];
		$identityMap[$studentId] = [
			'school_id' => (string)($studentRow['school_id'] ?? ''),
			'name' => trim((string)($studentRow['fname'] ?? '') . ' ' . (string)($studentRow['mname'] ?? '') . ' ' . (string)($studentRow['lname'] ?? '')),
			'class_name' => $className,
		];
	}

	$rankData = report_rank_students($conn, $classId, $termId);
	$rows = [];
	foreach ($studentIds as $studentId) {
		$report = report_compute_for_student($conn, $studentId, $classId, $termId);
		$reportId = report_store_card($conn, $studentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], $generatedBy);
		$stmt = $conn->prepare("SELECT verification_code FROM tbl_report_cards WHERE id = ? LIMIT 1");
		$stmt->execute([$reportId]);
		$verificationCode = (string)$stmt->fetchColumn();
		$identity = $identityMap[$studentId] ?? ['school_id' => '', 'name' => '', 'class_name' => $className];
		$rows[] = [
			'report_id' => $reportId,
			'student_id' => $studentId,
			'school_id' => (string)($identity['school_id'] ?? ''),
			'student_name' => (string)($identity['name'] ?? ''),
			'class_name' => (string)($identity['class_name'] ?? ''),
			'position' => (int)($rankData['positions'][$studentId] ?? 0),
			'total_students' => (int)$rankData['total_students'],
			'total' => (float)($report['total'] ?? 0),
			'mean' => (float)($report['mean'] ?? 0),
			'grade' => (string)($report['grade'] ?? ''),
			'remark' => (string)($report['remark'] ?? ''),
			'trend' => (string)($report['trend'] ?? ''),
			'verification_code' => $verificationCode,
		];
	}

	usort($rows, function ($a, $b) {
		if ((int)$a['position'] === (int)$b['position']) {
			if ((float)$a['mean'] === (float)$b['mean']) {
				return strcmp((string)$a['student_id'], (string)$b['student_id']);
			}
			return (float)$b['mean'] <=> (float)$a['mean'];
		}
		return (int)$a['position'] <=> (int)$b['position'];
	});

	return [
		'rows' => $rows,
		'total_students' => count($studentIds),
		'positions' => $rankData['positions'],
	];
}
