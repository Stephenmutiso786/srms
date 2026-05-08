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

function report_default_grading_system_id(PDO $conn, ?string $type = null): ?int
{
	if (!app_table_exists($conn, 'tbl_grading_systems')) {
		return null;
	}

	$type = $type !== null ? strtolower(trim($type)) : null;
	$sql = "SELECT id FROM tbl_grading_systems WHERE is_active = 1";
	$params = [];
	if ($type !== null && $type !== '' && app_column_exists($conn, 'tbl_grading_systems', 'type')) {
		$sql .= " AND type = ?";
		$params[] = $type;
	}
	$sql .= " ORDER BY is_default DESC, id ASC LIMIT 1";
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$value = $stmt->fetchColumn();
	if ($value) {
		return (int)$value;
	}

	if (!empty($params)) {
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
		$stmt->execute();
		$value = $stmt->fetchColumn();
		return $value ? (int)$value : null;
	}

	return null;
}

function report_exam_grading_system_id(PDO $conn, ?int $examId): ?int
{
	$defaultSystemId = report_default_grading_system_id($conn, 'marks');
	if (!$examId || !app_table_exists($conn, 'tbl_exams')) {
		return $defaultSystemId;
	}

	$hasGradingSystemColumn = app_column_exists($conn, 'tbl_exams', 'grading_system_id');
	$hasAssessmentModeColumn = app_column_exists($conn, 'tbl_exams', 'assessment_mode');
	$select = 'id';
	if (app_column_exists($conn, 'tbl_exams', 'class_id')) {
		$select .= ', class_id';
	}
	if ($hasGradingSystemColumn) {
		$select .= ', grading_system_id';
	}
	if ($hasAssessmentModeColumn) {
		$select .= ", assessment_mode";
	}
	$stmt = $conn->prepare("SELECT {$select} FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$examRow = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$examRow) {
		return $defaultSystemId;
	}

	$assessmentMode = strtolower(trim((string)($examRow['assessment_mode'] ?? 'normal')));
	$preferredType = $assessmentMode === 'cbe' ? 'cbe' : 'marks';
	$defaultSystemId = report_default_grading_system_id($conn, $preferredType);
	$classId = (int)($examRow['class_id'] ?? 0);
	$classSystemId = $classId > 0 && function_exists('app_class_grading_system_id')
		? app_class_grading_system_id($conn, $classId)
		: null;

	if (!$hasGradingSystemColumn) {
		return $classSystemId ?: $defaultSystemId;
	}

	$examSystemId = (int)($examRow['grading_system_id'] ?? 0);
	if ($examSystemId < 1) {
		return $classSystemId ?: $defaultSystemId;
	}

	if (!app_table_exists($conn, 'tbl_grading_systems')) {
		return $examSystemId;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE id = ? AND is_active = 1 LIMIT 1");
	$stmt->execute([$examSystemId]);
	return $stmt->fetchColumn() ? $examSystemId : ($classSystemId ?: $defaultSystemId);
}

function report_grading_scales(PDO $conn, ?int $gradingSystemId = null): array
{
	// CBE-ONLY: Remove legacy tbl_grade_system and A/B/C fallback. Only use CBE/new grading tables.
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
	if (app_table_exists($conn, 'tbl_cbe_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points FROM tbl_cbe_grading WHERE active = 1 ORDER BY min_mark DESC, sort_order ASC");
		$stmt->execute();
		$rows = [];
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$level = strtoupper(trim((string)($row['level'] ?? 'BE')));
			$remark = $level === 'EE' ? 'Exceeding Expectation' : ($level === 'ME' ? 'Meeting Expectation' : ($level === 'AE' ? 'Approaching Expectation' : 'Below Expectation'));
			$rows[] = [
				'name' => $level,
				'min' => (float)($row['min_mark'] ?? 0),
				'max' => (float)($row['max_mark'] ?? 100),
				'remark' => $remark,
				'points' => (float)($row['points'] ?? 0),
				'sort_order' => 0,
				'is_active' => 1,
			];
		}
		if (!empty($rows)) {
			return $rows;
		}
	}
	return [];
}

function report_is_legacy_grade_label(string $grade): bool
{
	$label = strtoupper(trim($grade));
	return in_array($label, ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'E'], true);
}

function report_card_has_legacy_grades(array $card): bool
{
	if (report_is_legacy_grade_label((string)($card['grade'] ?? ''))) {
		return true;
	}

	foreach ((array)($card['subjects'] ?? []) as $subjectRow) {
		if (report_is_legacy_grade_label((string)($subjectRow['grade'] ?? ''))) {
			return true;
		}
	}

	return false;
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

/**
 * Compute weighted total across multiple exams for a student.
 * Combines exam scores using their weight_percentage, useful for term-level grades.
 *
 * @param PDO $conn Database connection
 * @param string $studentId Student ID
 * @param int $classId Class ID  
 * @param int $termId Term ID
 * @param array $examIds Optional array of exam IDs to include; if empty, uses all published exams
 * @return array ['total' => weighted score, 'examCount' => number of exams, 'weightUsed' => total weight %]
 */
function report_weighted_exam_total(PDO $conn, string $studentId, int $classId, int $termId, ?array $examIds = null): array
{
	if ($studentId === '' || $classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_exam_results')) {
		return ['total' => 0.0, 'examCount' => 0, 'weightUsed' => 0.0];
	}

	// If no exam IDs specified, fetch published exams for this class/term
	if ($examIds === null || empty($examIds)) {
		if (!app_table_exists($conn, 'tbl_exams')) {
			return ['total' => 0.0, 'examCount' => 0, 'weightUsed' => 0.0];
		}
		$stmt = $conn->prepare("SELECT id FROM tbl_exams WHERE class_id = ? AND term_id = ? AND COALESCE(status, 'draft') = 'published' ORDER BY id ASC");
		$stmt->execute([$classId, $termId]);
		$examIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
	}

	if (empty($examIds)) {
		return ['total' => 0.0, 'examCount' => 0, 'weightUsed' => 0.0];
	}

	$placeholders = implode(',', array_fill(0, count($examIds), '?'));
	$stmt = $conn->prepare("SELECT exam_id, AVG(CAST(score AS FLOAT)) as avg_score FROM tbl_exam_results 
		WHERE student = ? AND class = ? AND term = ? AND exam_id IN ($placeholders)
		GROUP BY exam_id");
	$params = array_merge([$studentId, $classId, $termId], $examIds);
	$stmt->execute($params);
	$examAverages = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$examAverages[(int)$row['exam_id']] = (float)$row['avg_score'];
	}

	if (empty($examAverages)) {
		return ['total' => 0.0, 'examCount' => 0, 'weightUsed' => 0.0];
	}

	$weightedSum = 0.0;
	$totalWeight = 0.0;
	foreach ($examIds as $examId) {
		if (isset($examAverages[$examId])) {
			$weight = report_exam_weight_percentage($conn, $examId) / 100.0;
			$weightedSum += $examAverages[$examId] * $weight;
			$totalWeight += $weight;
		}
	}

	$weightedTotal = ($totalWeight > 0) ? ($weightedSum / $totalWeight) : 0.0;

	return [
		'total' => round($weightedTotal, 2),
		'examCount' => count($examAverages),
		'weightUsed' => round($totalWeight * 100, 1),
	];
}

function report_exam_result_matrix(PDO $conn, int $classId, int $termId, ?string $studentId = null): array
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_exam_results')) {
		return [];
	}
	app_ensure_exam_components_table($conn);
	$hasExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
	$hasGradeLabel = app_column_exists($conn, 'tbl_exam_results', 'grade_label');
	$hasGradePoints = app_column_exists($conn, 'tbl_exam_results', 'grade_points');
	$subjects = report_fetch_subjects_for_class($conn, $classId);
	if (empty($subjects)) {
		return [];
	}

	$sql = "SELECT er.id, er.student, er.subject_combination, er.score, sc.subject AS subject_id";
	$sql .= $hasExamId ? ", er.exam_id" : ", NULL AS exam_id";
	$sql .= $hasGradeLabel ? ", er.grade_label" : ", NULL AS grade_label";
	$sql .= $hasGradePoints ? ", er.grade_points" : ", NULL AS grade_points";
	$sql .= " FROM tbl_exam_results er LEFT JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination WHERE er.class = ? AND er.term = ?";
	$args = [$classId, $termId];
	if ($studentId !== null && $studentId !== '') {
		$sql .= ' AND er.student = ?';
		$args[] = $studentId;
	}
	$sql .= ' ORDER BY er.student, er.id DESC';

	$stmt = $conn->prepare($sql);
	$stmt->execute($args);

	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$latestByCombo = [];
	$latestBySubject = [];
	$scoreByCombo = [];
	$scoreBySubject = [];
	foreach ($rows as $row) {
		$rowStudentId = (string)($row['student'] ?? '');
		if ($rowStudentId === '') {
			continue;
		}
		$subjectCombination = (int)($row['subject_combination'] ?? 0);
		if ($subjectCombination < 1) {
			continue;
		}
		$subjectId = (int)($row['subject_id'] ?? 0);
		$examId = isset($row['exam_id']) ? (int)$row['exam_id'] : 0;
		$rowId = (int)($row['id'] ?? 0);
		if (!isset($latestByCombo[$rowStudentId][$subjectCombination]) || $rowId > (int)($latestByCombo[$rowStudentId][$subjectCombination]['id'] ?? 0)) {
			$latestByCombo[$rowStudentId][$subjectCombination] = $row;
		}
		if ($subjectId > 0 && (!isset($latestBySubject[$rowStudentId][$subjectId]) || $rowId > (int)($latestBySubject[$rowStudentId][$subjectId]['id'] ?? 0))) {
			$latestBySubject[$rowStudentId][$subjectId] = $row;
		}
		$scoreByCombo[$rowStudentId][$subjectCombination][$examId] = (float)($row['score'] ?? 0);
		if ($subjectId > 0) {
			$scoreBySubject[$rowStudentId][$subjectId][$examId] = (float)($row['score'] ?? 0);
		}
	}

	$consolidatedExams = [];
	if ($hasExamId && app_table_exists($conn, 'tbl_exams') && app_column_exists($conn, 'tbl_exams', 'assessment_mode') && app_table_exists($conn, 'tbl_exam_components')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_exams WHERE class_id = ? AND term_id = ? AND COALESCE(assessment_mode, 'normal') = 'consolidated' AND COALESCE(status, 'draft') = 'published' ORDER BY id DESC");
		$stmt->execute([$classId, $termId]);
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $consolidatedExamId) {
			$cid = (int)$consolidatedExamId;
			$stmtComp = $conn->prepare("SELECT component_exam_id FROM tbl_exam_components WHERE exam_id = ? ORDER BY component_exam_id");
			$stmtComp->execute([$cid]);
			$componentIds = array_values(array_unique(array_map('intval', $stmtComp->fetchAll(PDO::FETCH_COLUMN))));
			if (!empty($componentIds)) {
				$ph = implode(',', array_fill(0, count($componentIds), '?'));
				$stmtPublished = $conn->prepare("SELECT id FROM tbl_exams WHERE id IN ($ph) AND COALESCE(status, 'draft') = 'published'");
				$stmtPublished->execute($componentIds);
				$componentIds = array_values(array_unique(array_map('intval', $stmtPublished->fetchAll(PDO::FETCH_COLUMN))));
			}
			if (count($componentIds) >= 2) {
				$consolidatedExams[$cid] = $componentIds;
			}
		}
	}

	$subjectLookup = [];
	foreach ($subjects as $subject) {
		$subjectLookup[(int)$subject['combination_id']] = $subject;
	}

	$matrix = [];
	foreach ($subjects as $subject) {
		$subjectCombination = (int)$subject['combination_id'];
		$subjectId = (int)$subject['subject'];
		foreach ($latestByCombo as $rowStudentId => $comboRows) {
			$computed = null;
			foreach ($consolidatedExams as $consolidatedExamId => $componentExamIds) {
				$componentScores = [];
				foreach ($componentExamIds as $componentExamId) {
					if (isset($scoreByCombo[$rowStudentId][$subjectCombination][$componentExamId])) {
						$componentScores[] = (float)$scoreByCombo[$rowStudentId][$subjectCombination][$componentExamId];
					} elseif ($subjectId > 0 && isset($scoreBySubject[$rowStudentId][$subjectId][$componentExamId])) {
						$componentScores[] = (float)$scoreBySubject[$rowStudentId][$subjectId][$componentExamId];
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

			$latestRow = $latestByCombo[$rowStudentId][$subjectCombination] ?? null;
			if ($latestRow === null && $subjectId > 0 && isset($latestBySubject[$rowStudentId][$subjectId])) {
				$latestRow = $latestBySubject[$rowStudentId][$subjectId];
			}
			if ($latestRow === null) {
				continue;
			}

			$matrix[$rowStudentId][$subjectCombination] = [
				'score' => (float)($latestRow['score'] ?? 0),
				'exam_id' => isset($latestRow['exam_id']) ? (int)$latestRow['exam_id'] : null,
				'grade_label' => (string)($latestRow['grade_label'] ?? ''),
				'grade_points' => isset($latestRow['grade_points']) ? (float)$latestRow['grade_points'] : null,
				'is_consolidated' => false,
				'row_count' => 1,
			];
		}
	}

	return $matrix;
}

function report_ensure_exam_batch_schema(PDO $conn): void
{
	static $done = false;
	if ($done || !app_table_exists($conn, 'tbl_report_cards')) {
		return;
	}

	if (!app_column_exists($conn, 'tbl_report_cards', 'exam_id')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("ALTER TABLE tbl_report_cards ADD COLUMN exam_id integer NULL");
		} else {
			$conn->exec("ALTER TABLE tbl_report_cards ADD COLUMN exam_id int NULL");
		}
	}

	try {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$conn->exec("CREATE INDEX IF NOT EXISTS tbl_report_cards_exam_id_idx ON tbl_report_cards (exam_id)");
		} else {
			$conn->exec("CREATE INDEX tbl_report_cards_exam_id_idx ON tbl_report_cards (exam_id)");
		}
	} catch (Throwable $e) {
		// best effort; index may already exist
	}

	$done = true;
}

function report_find_card_id(PDO $conn, string $studentId, int $termId, int $examId = 0): int
{
	if ($studentId === '' || $termId < 1 || !app_table_exists($conn, 'tbl_report_cards')) {
		return 0;
	}

	report_ensure_exam_batch_schema($conn);
	if (app_column_exists($conn, 'tbl_report_cards', 'exam_id')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? AND COALESCE(exam_id, 0) = ? LIMIT 1");
		$stmt->execute([$studentId, $termId, $examId > 0 ? $examId : 0]);
		return (int)$stmt->fetchColumn();
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	return (int)$stmt->fetchColumn();
}

function report_visible_exam_statuses(): array
{
	return ['published'];
}

function report_term_exam_options(PDO $conn, int $classId, int $termId): array
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		return [];
	}
	$hasExamTypes = app_table_exists($conn, 'tbl_exam_types');
	$hasCreatedAt = app_column_exists($conn, 'tbl_exams', 'created_at');
	$hasAssessmentMode = app_column_exists($conn, 'tbl_exams', 'assessment_mode');

	$statuses = report_visible_exam_statuses();
	$fetchRows = function () use ($conn, $classId, $termId, $hasAssessmentMode, $hasExamTypes, $hasCreatedAt, $statuses): array {
		$sql = "SELECT e.id, e.name, COALESCE(e.status, 'draft') AS status,";
		$sql .= $hasAssessmentMode
			? " COALESCE(e.assessment_mode, 'normal') AS assessment_mode,"
			: " 'normal' AS assessment_mode,";
		$sql .= $hasExamTypes
			? " COALESCE(et.name, '') AS type_name FROM tbl_exams e LEFT JOIN tbl_exam_types et ON et.id = e.exam_type_id"
			: " '' AS type_name FROM tbl_exams e";
		$placeholders = implode(',', array_fill(0, count($statuses), '?'));
		$sql .= " WHERE e.class_id = ? AND e.term_id = ? AND COALESCE(e.status, 'draft') IN ($placeholders)";
		$args = array_merge([$classId, $termId], $statuses);
		$sql .= "
			ORDER BY
				" . ($hasCreatedAt ? 'e.created_at DESC,' : '') . "
				e.id DESC";
		$stmt = $conn->prepare($sql);
		$stmt->execute($args);
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	};

	$rows = $fetchRows();

	return array_map(function ($row) {
		$status = strtolower(trim((string)($row['status'] ?? 'draft')));
		if ($status === 'open') {
			$status = 'active';
		}
		return [
			'id' => (int)($row['id'] ?? 0),
			'name' => (string)($row['name'] ?? ''),
			'status' => $status,
			'assessment_mode' => strtolower(trim((string)($row['assessment_mode'] ?? 'normal'))),
			'type_name' => (string)($row['type_name'] ?? ''),
		];
	}, $rows);
}

function report_exam_subject_breakdown(PDO $conn, string $studentId, int $classId, int $termId, int $examId): array
{
	if ($studentId === '' || $classId < 1 || $termId < 1 || $examId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		return [];
	}
	$hasExamId = app_table_exists($conn, 'tbl_exam_results') && app_column_exists($conn, 'tbl_exam_results', 'exam_id');

	$stmt = $conn->prepare("SELECT id, COALESCE(assessment_mode, 'normal') AS assessment_mode
		FROM tbl_exams
		WHERE id = ? AND class_id = ? AND term_id = ?
		LIMIT 1");
	$stmt->execute([$examId, $classId, $termId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		return [];
	}

	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$rows = [];
	$gradingSystemId = report_exam_grading_system_id($conn, $examId);
	$assessmentMode = strtolower(trim((string)($exam['assessment_mode'] ?? 'normal')));
	$loadLegacyTermBuckets = function () use ($conn, $classId, $termId): array {
		$scoreBuckets = [];
		if (!app_table_exists($conn, 'tbl_exam_results')) {
			return $scoreBuckets;
		}
		$stmt = $conn->prepare("SELECT id, student, subject_combination, score
			FROM tbl_exam_results
			WHERE class = ? AND term = ?
			ORDER BY id DESC");
		$stmt->execute([$classId, $termId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$rowStudentId = (string)($row['student'] ?? '');
			$combinationId = (int)($row['subject_combination'] ?? 0);
			if ($rowStudentId === '' || $combinationId < 1) {
				continue;
			}
			if (!isset($scoreBuckets[$rowStudentId][$combinationId])) {
				$scoreBuckets[$rowStudentId][$combinationId] = [(float)($row['score'] ?? 0)];
			}
		}
		return $scoreBuckets;
	};

	if ($assessmentMode === 'cbe') {
		$studentMatrix = report_cbe_score_matrix($conn, $classId, $termId, $subjects, null);
		$classTotals = [];
		$classCounts = [];
		$subjectRankMaps = [];
		foreach ($studentMatrix as $subjectScores) {
			foreach ($subjectScores as $subjectKey => $score) {
				$subjectId = (int)$subjectKey;
				$classTotals[$subjectId] = (float)($classTotals[$subjectId] ?? 0) + (float)$score;
				$classCounts[$subjectId] = (int)($classCounts[$subjectId] ?? 0) + 1;
			}
		}

		foreach ($subjects as $subject) {
			$subjectId = (int)$subject['subject'];
			$studentScores = [];
			foreach ($studentMatrix as $rowStudentId => $subjectScores) {
				if (isset($subjectScores[$subjectId])) {
					$studentScores[(string)$rowStudentId] = (float)$subjectScores[$subjectId];
				}
			}
			arsort($studentScores, SORT_NUMERIC);
			$rank = 0;
			$position = 0;
			$prev = null;
			$total = count($studentScores);
			$subjectRankMaps[$subjectId] = [];
			foreach ($studentScores as $rowStudentId => $rowScore) {
				$position++;
				if ($prev === null || (float)$rowScore !== (float)$prev) {
					$rank = $position;
					$prev = (float)$rowScore;
				}
				$subjectRankMaps[$subjectId][(string)$rowStudentId] = $rank . '/' . $total;
			}
		}

		foreach ($subjects as $subject) {
			$subjectId = (int)$subject['subject'];
			$hasScore = array_key_exists($studentId, $studentMatrix) && array_key_exists($subjectId, $studentMatrix[$studentId]);
			$score = $hasScore ? (float)($studentMatrix[$studentId][$subjectId] ?? 0) : null;
			$classMean = isset($classCounts[$subjectId]) && $classCounts[$subjectId] > 0
				? round((float)$classTotals[$subjectId] / (int)$classCounts[$subjectId], 2)
				: 0.0;
			if ($hasScore) {
				list($gradeLabel, $gradeRemark, $gradePoints) = report_cbe_grade_for_score($conn, (float)$score);
				list(, , $classMeanPoints) = report_cbe_grade_for_score($conn, $classMean);
				$deviation = round((float)$gradePoints - (float)$classMeanPoints, 2);
			} else {
				$gradeLabel = 'N/A';
				$gradeRemark = 'No marks entered';
				$gradePoints = null;
				$deviation = null;
			}
			$rankLabel = (string)($subjectRankMaps[$subjectId][$studentId] ?? '-');
			$rows[] = [
				'subject_id' => $subjectId,
				'subject_name' => (string)$subject['subject_name'],
				'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
				'score' => $score,
				'has_score' => $hasScore,
				'cat1' => '-',
				'cat2' => '-',
				'class_mean' => $classMean,
				'deviation' => $deviation,
				'rank' => $rankLabel,
				'position' => $rankLabel,
				'grade' => $gradeLabel,
				'grade_points' => (float)$gradePoints,
				'remark' => (string)$gradeRemark,
				'progress' => max(0, min(100, $score)),
				'source' => 'CBE assessment',
			];
		}

		return $rows;
	}

	$scoreBuckets = [];
	if (!$hasExamId) {
		$scoreBuckets = $loadLegacyTermBuckets();
	} elseif ($assessmentMode === 'consolidated') {
		$componentExamIds = array_values(array_unique(array_map('intval', app_exam_component_ids($conn, $examId))));
		if (empty($componentExamIds) || !app_table_exists($conn, 'tbl_exam_results')) {
			return [];
		}
		$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
		$stmt = $conn->prepare("SELECT student, subject_combination, score
			FROM tbl_exam_results
			WHERE class = ? AND term = ? AND exam_id IN ($placeholders)");
		$stmt->execute(array_merge([$classId, $termId], $componentExamIds));
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$rowStudentId = (string)($row['student'] ?? '');
			$combinationId = (int)($row['subject_combination'] ?? 0);
			if ($rowStudentId === '' || $combinationId < 1) {
				continue;
			}
			$scoreBuckets[$rowStudentId][$combinationId][] = (float)($row['score'] ?? 0);
		}
	} else {
		if (!app_table_exists($conn, 'tbl_exam_results')) {
			return [];
		}
		$stmt = $conn->prepare("SELECT student, subject_combination, score
			FROM tbl_exam_results
			WHERE class = ? AND term = ? AND exam_id = ?");
		$stmt->execute([$classId, $termId, $examId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$rowStudentId = (string)($row['student'] ?? '');
			$combinationId = (int)($row['subject_combination'] ?? 0);
			if ($rowStudentId === '' || $combinationId < 1) {
				continue;
			}
			$scoreBuckets[$rowStudentId][$combinationId] = [(float)($row['score'] ?? 0)];
		}
		if (empty($scoreBuckets)) {
			$scoreBuckets = $loadLegacyTermBuckets();
		}
	}

	$classTotals = [];
	$classCounts = [];
	$scoreAverages = [];
	foreach ($scoreBuckets as $rowStudentId => $subjectScores) {
		foreach ($subjectScores as $combinationId => $scores) {
			if (empty($scores)) {
				continue;
			}
			$average = round(array_sum($scores) / count($scores), 2);
			$scoreAverages[(string)$rowStudentId][(int)$combinationId] = $average;
			$classTotals[$combinationId] = (float)($classTotals[$combinationId] ?? 0) + $average;
			$classCounts[$combinationId] = (int)($classCounts[$combinationId] ?? 0) + 1;
		}
	}

	$subjectRankMaps = [];
	foreach ($subjects as $subject) {
		$combinationId = (int)$subject['combination_id'];
		$studentScores = [];
		foreach ($scoreAverages as $rowStudentId => $subjectScores) {
			if (isset($subjectScores[$combinationId])) {
				$studentScores[(string)$rowStudentId] = (float)$subjectScores[$combinationId];
			}
		}
		arsort($studentScores, SORT_NUMERIC);
		$rank = 0;
		$position = 0;
		$prev = null;
		$total = count($studentScores);
		$subjectRankMaps[$combinationId] = [];
		foreach ($studentScores as $rowStudentId => $rowScore) {
			$position++;
			if ($prev === null || (float)$rowScore !== (float)$prev) {
				$rank = $position;
				$prev = (float)$rowScore;
			}
			$subjectRankMaps[$combinationId][(string)$rowStudentId] = $rank . '/' . $total;
		}
	}

	foreach ($subjects as $subject) {
		$combinationId = (int)$subject['combination_id'];
		$scores = $scoreBuckets[$studentId][$combinationId] ?? [];
		$hasScore = !empty($scores);
		$score = $hasScore ? round(array_sum($scores) / count($scores), 2) : null;
		$classMean = isset($classCounts[$combinationId]) && $classCounts[$combinationId] > 0
			? round((float)$classTotals[$combinationId] / (int)$classCounts[$combinationId], 2)
			: 0.0;
		if ($hasScore) {
			list($gradeLabel, $gradeRemark, $gradePoints) = report_grade_for_score($conn, (float)$score, $gradingSystemId);
			list(, , $classMeanPoints) = report_grade_for_score($conn, $classMean, $gradingSystemId);
			$deviation = round((float)$gradePoints - (float)$classMeanPoints, 2);
		} else {
			$gradeLabel = 'N/A';
			$gradeRemark = 'No marks entered';
			$gradePoints = null;
			$deviation = null;
		}
		$rankLabel = (string)($subjectRankMaps[$combinationId][$studentId] ?? '-');
		$rows[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => (string)$subject['subject_name'],
			'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
			'cat1' => isset($scores[0]) ? round((float)$scores[0], 2) : null,
			'cat2' => isset($scores[1]) ? round((float)$scores[1], 2) : null,
			'score' => $score,
			'has_score' => $hasScore,
			'class_mean' => $classMean,
			'deviation' => $deviation,
			'rank' => $rankLabel,
			'position' => $rankLabel,
			'grade' => $gradeLabel,
			'grade_points' => (float)$gradePoints,
			'remark' => (string)$gradeRemark,
			'progress' => max(0, min(100, $score)),
			'source' => !$hasExamId ? 'Term result (legacy)' : ($assessmentMode === 'consolidated' ? 'Average of selected exams' : 'Exam result'),
		];
	}

	return $rows;
}

function report_exam_summary(PDO $conn, string $studentId, int $classId, int $termId, int $examId): ?array
{
	if ($studentId === '' || $classId < 1 || $termId < 1 || $examId < 1 || !app_table_exists($conn, 'tbl_exams')) {
		return null;
	}

	$stmt = $conn->prepare("SELECT id, name, COALESCE(status, 'draft') AS status, COALESCE(assessment_mode, 'normal') AS assessment_mode
		FROM tbl_exams
		WHERE id = ? AND class_id = ? AND term_id = ?
		LIMIT 1");
	$stmt->execute([$examId, $classId, $termId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		return null;
	}

	$rows = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, $examId);
	if (empty($rows)) {
		$rows = report_subject_breakdown($conn, $studentId, $classId, $termId);
	}
	if (empty($rows)) {
		return [
			'exam_id' => $examId,
			'exam_name' => (string)$exam['name'],
			'status' => strtolower(trim((string)$exam['status'])),
			'assessment_mode' => strtolower(trim((string)$exam['assessment_mode'])),
			'mean' => 0.0,
			'grade' => 'N/A',
			'position' => '-',
			'total' => 0.0,
			'total_students' => 0,
		];
	}

	$total = 0.0;
	foreach ($rows as $row) {
		$total += (float)($row['score'] ?? 0);
	}
	$mean = round($total / max(1, count($rows)), 2);
	$assessmentMode = strtolower(trim((string)($exam['assessment_mode'] ?? 'normal')));
	if ($assessmentMode === 'cbe') {
		list($gradeLabel) = report_cbe_grade_for_score($conn, $mean);
	} else {
		list($gradeLabel) = report_grade_for_score($conn, $mean, report_exam_grading_system_id($conn, $examId));
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$studentIds = array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
	$totals = [];
	foreach ($studentIds as $rowStudentId) {
		$studentRows = report_exam_subject_breakdown($conn, $rowStudentId, $classId, $termId, $examId);
		$studentTotal = 0.0;
		foreach ($studentRows as $row) {
			$studentTotal += (float)($row['score'] ?? 0);
		}
		$totals[] = ['student_id' => $rowStudentId, 'total' => round($studentTotal, 2)];
	}

	usort($totals, function ($a, $b) {
		return $b['total'] <=> $a['total'];
	});
	$position = '-';
	$rank = 0;
	$prevTotal = null;
	foreach ($totals as $index => $row) {
		if ($prevTotal === null || (float)$row['total'] !== (float)$prevTotal) {
			$rank = $index + 1;
			$prevTotal = (float)$row['total'];
		}
		if ((string)$row['student_id'] === $studentId) {
			$position = $rank . '/' . count($totals);
			break;
		}
	}

	return [
		'exam_id' => $examId,
		'exam_name' => (string)$exam['name'],
		'status' => strtolower(trim((string)$exam['status'])),
		'assessment_mode' => $assessmentMode,
		'mean' => $mean,
		'grade' => $gradeLabel,
		'position' => $position,
		'total' => round($total, 2),
		'total_students' => count($totals),
	];
}

function report_consolidated_cycle_breakdown(PDO $conn, string $studentId, int $classId, int $termId, int $examId): array
{
	if ($studentId === '' || $classId < 1 || $termId < 1 || $examId < 1 || !app_table_exists($conn, 'tbl_exams') || !app_table_exists($conn, 'tbl_exam_results')) {
		return ['cycle_labels' => [], 'rows' => []];
	}

	$stmt = $conn->prepare("SELECT id, name, COALESCE(assessment_mode, 'normal') AS assessment_mode
		FROM tbl_exams
		WHERE id = ? AND class_id = ? AND term_id = ?
		LIMIT 1");
	$stmt->execute([$examId, $classId, $termId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam || strtolower(trim((string)($exam['assessment_mode'] ?? 'normal'))) !== 'consolidated') {
		return ['cycle_labels' => [], 'rows' => []];
	}

	$componentExamIds = array_values(array_unique(array_map('intval', app_exam_component_ids($conn, $examId))));
	if (count($componentExamIds) < 1) {
		return ['cycle_labels' => [], 'rows' => []];
	}

	$cycleLabels = [];
	$labelStmt = $conn->prepare('SELECT id, name FROM tbl_exams WHERE id IN (' . implode(',', array_fill(0, count($componentExamIds), '?')) . ') ORDER BY id');
	$labelStmt->execute($componentExamIds);
	foreach ($labelStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$cycleLabels[(int)$row['id']] = (string)$row['name'];
	}
	if (empty($cycleLabels)) {
		foreach ($componentExamIds as $index => $componentExamId) {
			$cycleLabels[$componentExamId] = 'Cycle ' . ($index + 1);
		}
	}

	$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
	$query = "SELECT student, subject_combination, exam_id, score FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id IN ($placeholders) ORDER BY student, subject_combination, exam_id";
	$stmt = $conn->prepare($query);
	$stmt->execute(array_merge([$classId, $termId], $componentExamIds));

	$scoreBuckets = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$rowStudentId = (string)($row['student'] ?? '');
		$combinationId = (int)($row['subject_combination'] ?? 0);
		$rowExamId = (int)($row['exam_id'] ?? 0);
		if ($rowStudentId === '' || $combinationId < 1 || $rowExamId < 1) {
			continue;
		}
		$scoreBuckets[$rowStudentId][$combinationId][$rowExamId] = (float)($row['score'] ?? 0);
	}

	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$gradingSystemId = report_exam_grading_system_id($conn, $examId);

	$combinedTotals = [];
	$combinedCounts = [];
	foreach ($scoreBuckets as $rowStudentId => $subjectScores) {
		foreach ($subjectScores as $combinationId => $examScores) {
			if (empty($examScores)) {
				continue;
			}
			$combinedScore = round(array_sum($examScores) / count($examScores), 2);
			$combinedTotals[$combinationId] = (float)($combinedTotals[$combinationId] ?? 0) + $combinedScore;
			$combinedCounts[$combinationId] = (int)($combinedCounts[$combinationId] ?? 0) + 1;
		}
	}

	$rows = [];
	foreach ($subjects as $subject) {
		$combinationId = (int)$subject['combination_id'];
		$examScores = $scoreBuckets[$studentId][$combinationId] ?? [];
		$cycleValues = [];
		foreach ($componentExamIds as $componentExamId) {
			$cycleValues[] = isset($examScores[$componentExamId]) ? round((float)$examScores[$componentExamId], 2) : 0.0;
		}
		$combinedScore = !empty($cycleValues) ? round(array_sum($cycleValues) / count($cycleValues), 2) : 0.0;
		$classMean = isset($combinedCounts[$combinationId]) && $combinedCounts[$combinationId] > 0
			? round((float)$combinedTotals[$combinationId] / (int)$combinedCounts[$combinationId], 2)
			: 0.0;
		$subjectTotals = [];
		foreach ($scoreBuckets as $sid => $subjectRows) {
			$subjectExamScores = $subjectRows[$combinationId] ?? [];
			if (empty($subjectExamScores)) {
				continue;
			}
			$subjectTotals[$sid] = round(array_sum($subjectExamScores) / count($subjectExamScores), 2);
		}
		arsort($subjectTotals, SORT_NUMERIC);
		$position = '-';
		$rank = 0;
		$prevTotal = null;
		foreach ($subjectTotals as $sid => $subjectTotal) {
			if ($prevTotal === null || (float)$subjectTotal !== (float)$prevTotal) {
				$rank = count(array_slice($subjectTotals, 0, array_search($sid, array_keys($subjectTotals), true) + 1, true));
				$prevTotal = (float)$subjectTotal;
			}
			if ((string)$sid === $studentId) {
				$position = $rank . '/' . count($subjectTotals);
				break;
			}
		}
		list($gradeLabel, $remark, $gradePoints) = report_grade_for_score($conn, $combinedScore, $gradingSystemId);
		$rows[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => (string)$subject['subject_name'],
			'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
			'cycle_scores' => array_combine(array_values($cycleLabels), $cycleValues) ?: [],
			'combined_score' => $combinedScore,
			'class_mean' => $classMean,
			'grade' => $gradeLabel,
			'grade_points' => $gradePoints,
			'remark' => $remark,
			'progress' => max(0, min(100, $combinedScore)),
			'position' => $position,
		];
	}

	return ['cycle_labels' => array_values($cycleLabels), 'rows' => $rows];
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

function report_cbe_level_to_score(string $level): float
{
	$level = strtoupper(trim($level));
	if ($level === 'EE') return 85.0;
	if ($level === 'ME') return 70.0;
	if ($level === 'AE') return 50.0;
	if ($level === 'BE') return 30.0;
	return 0.0;
}

function report_cbe_grading_rows(PDO $conn): array
{
	if (app_table_exists($conn, 'tbl_cbe_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points FROM tbl_cbe_grading WHERE active = 1 ORDER BY min_mark DESC, sort_order ASC");
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

function report_cbe_grade_for_score(PDO $conn, float $score): array
{
	$rows = report_cbe_grading_rows($conn);
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

function report_grade_points_from_label(string $grade): float
{
	$label = strtoupper(trim($grade));
	$map = [
		'EE' => 4.0,
		'ME' => 3.0,
		'AE' => 2.0,
		'BE' => 1.0,
		'A+' => 12.0,
		'A' => 11.0,
		'A-' => 10.0,
		'B+' => 9.0,
		'B' => 8.0,
		'B-' => 7.0,
		'C+' => 6.0,
		'C' => 5.0,
		'C-' => 4.0,
		'D+' => 3.0,
		'D' => 2.0,
		'D-' => 1.0,
		'E' => 0.0,
	];

	return (float)($map[$label] ?? 0.0);
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

	$hasCbe = false;
	$hasNormal = false;
	foreach ($rows as $row) {
		$mode = strtolower(trim((string)($row['assessment_mode'] ?? 'normal')));
		if ($mode === 'cbe') {
			$hasCbe = true;
		} else {
			$hasNormal = true;
		}
	}

	if ($hasCbe && !$hasNormal) {
		$modeCache[$cacheKey] = 'cbe';
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
		if (isset($subject['has_score']) && !$subject['has_score']) {
			continue;
		}
		if (isset($subject['grade']) && strtoupper(trim((string)$subject['grade'])) === 'N/A') {
			continue;
		}
		if (isset($subject['grade_points']) && $subject['grade_points'] !== '') {
			$pointSum += (float)$subject['grade_points'];
			$pointCount++;
		}
	}
	$card['total_points'] = round($pointSum, 2);
	$card['mean_points'] = $pointCount > 0 ? round($pointSum / $pointCount, 2) : 0.0;
	$card['average_score'] = (float)($card['mean'] ?? 0);

	$classId = (int)($card['class_id'] ?? 0);
	$termId = (int)($card['term_id'] ?? 0);
	$mode = report_term_assessment_mode($conn, $classId, $termId);
	$card['assessment_mode'] = $mode;

	if ($mode === 'cbe') {
		list($cbeGrade, $cbeRemark,) = report_cbe_grade_for_score($conn, (float)($card['mean'] ?? 0));
		$card['grade'] = $cbeGrade;
		if (empty($card['remark'])) {
			$card['remark'] = $cbeRemark;
		}
	}

	return $card;
}

function report_cbe_score_matrix(PDO $conn, int $classId, int $termId, array $subjects, ?string $studentId = null): array
{
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_cbe_assessments')) {
		return [];
	}

	$hasSubjectId = app_column_exists($conn, 'tbl_cbe_assessments', 'subject_id');
	$hasMarks = app_column_exists($conn, 'tbl_cbe_assessments', 'marks');

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

	$sql = "SELECT $selectCols FROM tbl_cbe_assessments WHERE class_id = ? AND term_id = ?";
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
			$score = report_cbe_level_to_score((string)($row['level'] ?? ''));
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
	static $classTermCbeCache = [];
	$cbeKey = $classId . ':' . $termId;
	if (!isset($classTermCbeCache[$cbeKey])) {
		$classTermCbeCache[$cbeKey] = report_cbe_score_matrix($conn, $classId, $termId, $subjects, null);
	}
	static $classTermExamCache = [];
	if (!isset($classTermExamCache[$cbeKey])) {
		$classTermExamCache[$cbeKey] = report_exam_result_matrix($conn, $classId, $termId, null);
	}

	$subjectMap = [];
	foreach ($subjects as $subject) {
		$subjectMap[(int)$subject['combination_id']] = $subject;
	}
	$latest = $classTermExamCache[$cbeKey][$studentId] ?? [];

	$cbeMatrix = $classTermCbeCache[$cbeKey];
	$cbeSubjectScores = $cbeMatrix[$studentId] ?? [];

	$scores = [];
	$gradingCache = [];
	foreach ($subjects as $subject) {
		$score = null;
		$hasScore = false;
		$value = $latest[(int)$subject['combination_id']] ?? null;
		if (is_array($value) && array_key_exists('score', $value) && $value['score'] !== null && $value['score'] !== '') {
			$score = (float)$value['score'];
			$hasScore = true;
		} else {
			$subjectId = (int)$subject['subject'];
			if (isset($cbeSubjectScores[$subjectId])) {
				$score = (float)$cbeSubjectScores[$subjectId];
				$hasScore = true;
			}
		}
		$examId = isset($value['exam_id']) ? (int)$value['exam_id'] : null;
		if (!array_key_exists((int)$examId, $gradingCache)) {
			$gradingCache[(int)$examId] = report_exam_grading_system_id($conn, $examId);
		}
		$gradingSystemId = $gradingCache[(int)$examId];
		if (!$hasScore || $score === null) {
			$gradeLabel = 'N/A';
			$gradeRemark = 'No marks entered';
			$gradePoints = null;
		} else {
			$usedCbeFallback = (is_array($value) && (!array_key_exists('score', $value) || $value['score'] === null || $value['score'] === '')) && isset($cbeSubjectScores[(int)$subject['subject']]);
			if ($usedCbeFallback) {
				list($gradeLabel, $gradeRemark, $gradePoints) = report_cbe_grade_for_score($conn, (float)$score);
			} else {
				list($gradeLabel, $gradeRemark, $gradePoints) = report_grade_for_score($conn, (float)$score, $gradingSystemId);
			}
		}

		$scores[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => $subject['subject_name'],
			'teacher_id' => $subject['teacher'] ? (int)$subject['teacher'] : null,
			'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
			'score' => $score,
			'has_score' => $hasScore,
			'exam_id' => $examId,
			'grade' => (string)$gradeLabel,
			'grade_points' => $gradePoints !== null ? (float)$gradePoints : null,
			'points' => $gradePoints !== null ? (float)$gradePoints : report_grade_points_from_label((string)$gradeLabel),
			'grade_remark' => $gradeRemark
		];
	}
	return $scores;
}

function report_compute_totals(PDO $conn, array $scores, array $weights, array $settings): array
{
	$rows = [];
	$scoredRows = [];
	foreach ($scores as $row) {
		$weight = 1.0;
		if (!empty($settings['use_weights']) && isset($weights[$row['subject_id']])) {
			$weight = (float)$weights[$row['subject_id']];
		}
		$scoreValue = isset($row['score']) && $row['score'] !== null ? (float)$row['score'] : null;
		$weighted = $scoreValue !== null ? ($scoreValue * $weight) : 0.0;
		$rows[] = $row + ['weight' => $weight, 'weighted_score' => $weighted];
		if (!empty($row['has_score']) && $scoreValue !== null) {
			$scoredRows[] = $row + ['weight' => $weight, 'weighted_score' => $weighted];
		}
	}
	usort($rows, function ($a, $b) {
		return $b['weighted_score'] <=> $a['weighted_score'];
	});
	usort($scoredRows, function ($a, $b) {
		return $b['weighted_score'] <=> $a['weighted_score'];
	});

	$bestOf = (int)$settings['best_of'];
	if ($bestOf > 0 && count($scoredRows) > $bestOf) {
		$scoredRows = array_slice($scoredRows, 0, $bestOf);
	}

	$total = 0;
	$totalWeight = 0.0;
	$gradingSystemId = null;
	foreach ($scoredRows as $row) {
		$total += $row['weighted_score'];
		$totalWeight += (float)($row['weight'] ?? 1);
		if ($gradingSystemId === null && !empty($row['exam_id'])) {
			$gradingSystemId = report_exam_grading_system_id($conn, (int)$row['exam_id']);
		}
	}
	$count = count($scoredRows);
	$mean = ($count > 0 && $totalWeight > 0) ? round($total / $totalWeight, 2) : 0;

	if ($count > 0) {
		list($grade, $remark) = report_grade_for_score($conn, $mean, $gradingSystemId);
	} else {
		$grade = 'N/A';
		$remark = 'No marks entered';
	}

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
	static $cache = [];
	$cacheKey = $studentId . '|' . $termId;
	if (array_key_exists($cacheKey, $cache)) {
		return $cache[$cacheKey];
	}
	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines')) {
		$cache[$cacheKey] = 0;
		return 0;
	}
	$stmt = $conn->prepare("SELECT id FROM tbl_invoices WHERE student_id = ? AND term_id = ? AND status <> 'void' LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	$invoiceId = $stmt->fetchColumn();
	if (!$invoiceId) {
		$cache[$cacheKey] = 0;
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
	$cache[$cacheKey] = max(0, round($total - $paid, 2));
	return $cache[$cacheKey];
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
	// Use GMT year to avoid timezone edge-cases on year boundary
	$year = (int)gmdate('Y');
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
	$subjectByCombination = [];
	foreach ($subjects as $subject) {
		$subjectByCombination[(int)$subject['combination_id']] = (int)$subject['subject'];
	}
	$examMatrix = report_exam_result_matrix($conn, $classId, $termId, null);
	$cbeMatrix = report_cbe_score_matrix($conn, $classId, $termId, $subjects, null);

	if (!empty($students) && !empty($subjectByCombination)) {
		foreach ($students as $studentId) {
			$totalPoints = 0.0;
			$examBySubject = $examMatrix[$studentId] ?? [];
			$cbeBySubject = $cbeMatrix[$studentId] ?? [];
			foreach ($subjectByCombination as $combinationId => $subjectId) {
				$points = null;
				if (isset($examBySubject[$combinationId])) {
					$examRow = $examBySubject[$combinationId];
					if (isset($examRow['grade_points']) && $examRow['grade_points'] !== null && $examRow['grade_points'] !== '') {
						$points = (float)$examRow['grade_points'];
					} elseif (isset($examRow['grade_label']) && trim((string)$examRow['grade_label']) !== '') {
						$points = report_grade_points_from_label((string)$examRow['grade_label']);
					} elseif (array_key_exists('score', $examRow) && $examRow['score'] !== null && $examRow['score'] !== '') {
						list(, , $gradePoints) = report_grade_for_score($conn, (float)$examRow['score'], report_exam_grading_system_id($conn, (int)($examRow['exam_id'] ?? 0)));
						$points = (float)$gradePoints;
					}
				} elseif (isset($cbeBySubject[$subjectId])) {
					list(, , $gradePoints) = report_cbe_grade_for_score($conn, (float)$cbeBySubject[$subjectId]);
					$points = (float)$gradePoints;
				}
				if ($points !== null) {
					$totalPoints += $points;
				}
			}
			$rankings[] = [
				'student_id' => $studentId,
				'total' => round($totalPoints, 2),
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
				'grade_points' => report_grade_points_from_label((string)($row['grade'] ?? '')),
				'points' => report_grade_points_from_label((string)($row['grade'] ?? '')),
				'weight' => (float)$row['weight'],
				'teacher_name' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
			];
		}
	}
	if (!empty($subjects) && !empty($card['student_id']) && !empty($card['class_id']) && !empty($card['term_id'])) {
		try {
			$breakdown = report_subject_breakdown($conn, (string)$card['student_id'], (int)$card['class_id'], (int)$card['term_id']);
			$bySubjectId = [];
			foreach ($breakdown as $row) {
				$bySubjectId[(int)($row['subject_id'] ?? 0)] = $row;
			}
			foreach ($subjects as &$subject) {
				$subjectId = (int)($subject['subject_id'] ?? 0);
				if (!isset($bySubjectId[$subjectId])) {
					continue;
				}
				$row = $bySubjectId[$subjectId];
				$subject['rank'] = (string)($row['rank'] ?? ($row['position'] ?? '-'));
				$subject['position'] = (string)($row['position'] ?? ($row['rank'] ?? '-'));
				$subject['class_mean'] = isset($row['class_mean']) ? (float)$row['class_mean'] : ($subject['class_mean'] ?? null);
				$subject['deviation'] = isset($row['deviation']) ? (float)$row['deviation'] : ($subject['deviation'] ?? null);
				$subject['trend'] = (string)($row['trend'] ?? ($subject['trend'] ?? '-'));
				$subject['grade_points'] = isset($row['grade_points']) && $row['grade_points'] !== null && $row['grade_points'] !== ''
					? (float)$row['grade_points']
					: ($subject['grade_points'] ?? null);
				$subject['points'] = isset($row['points']) && $row['points'] !== null && $row['points'] !== ''
					? (float)$row['points']
					: ($subject['points'] ?? $subject['grade_points'] ?? null);
				$subject['remark'] = (string)($row['remark'] ?? ($subject['remark'] ?? ''));
			}
			unset($subject);
		} catch (Throwable $e) {
			// keep existing subjects if the live breakdown merge fails
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
	return $state === 'published';
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
	$classGradingSystemId = function_exists('app_class_grading_system_id')
		? app_class_grading_system_id($conn, $classId)
		: null;
	$rows = [];
	$subjectRankMaps = [];
	$normalSubjectScores = [];
	$previousStudentScores = [];
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
	$cbeCurrent = report_cbe_score_matrix($conn, $classId, $termId, $subjects, null);
	$cbePrevious = $prevTermId > 0 ? report_cbe_score_matrix($conn, $classId, $prevTermId, $subjects, null) : [];

	if (!empty($combinationIds)) {
		foreach ($combinationIds as $combinationId) {
			$currentStudentScores[$combinationId] = (float)($currentMatrix[$studentId][$combinationId]['score'] ?? 0.0);
			$previousStudentScores[$combinationId] = isset($previousMatrix[$studentId][$combinationId])
				? (float)($previousMatrix[$studentId][$combinationId]['score'] ?? 0.0)
				: null;
			$normalSubjectScores[$combinationId] = [];
		}

		$subjectTotals = [];
		$subjectCounts = [];
		foreach ($currentMatrix as $sid => $subjectRows) {
			foreach ($subjectRows as $combinationId => $row) {
				$subjectTotals[$combinationId] = (float)($subjectTotals[$combinationId] ?? 0) + (float)($row['score'] ?? 0);
				$subjectCounts[$combinationId] = (int)($subjectCounts[$combinationId] ?? 0) + 1;
				$normalSubjectScores[(int)$combinationId][(string)$sid] = (float)($row['score'] ?? 0);
			}
		}
		foreach ($subjectTotals as $combinationId => $total) {
			$currentMeans[(int)$combinationId] = round($total / max(1, (int)$subjectCounts[$combinationId]), 2);
		}

		foreach ($normalSubjectScores as $combinationId => $studentScores) {
			arsort($studentScores, SORT_NUMERIC);
			$rank = 0;
			$position = 0;
			$prev = null;
			$total = count($studentScores);
			$subjectRankMaps[(int)$combinationId] = [];
			foreach ($studentScores as $rowStudentId => $rowScore) {
				$position++;
				if ($prev === null || (float)$rowScore !== (float)$prev) {
					$rank = $position;
					$prev = (float)$rowScore;
				}
				$subjectRankMaps[(int)$combinationId][(string)$rowStudentId] = $rank . '/' . $total;
			}
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

	$cbeCurrentStudent = $cbeCurrent[$studentId] ?? [];
	$cbePreviousStudent = $cbePrevious[$studentId] ?? [];
	$cbeCurrentClassMeans = [];
	$cbeRankMaps = [];
	if (!empty($cbeCurrent)) {
		$sum = [];
		$cnt = [];
		$scoresBySubject = [];
		foreach ($cbeCurrent as $sid => $subjectScores) {
			foreach ($subjectScores as $subjectId => $score) {
				$sum[$subjectId] = (float)($sum[$subjectId] ?? 0) + (float)$score;
				$cnt[$subjectId] = (int)($cnt[$subjectId] ?? 0) + 1;
				$scoresBySubject[(int)$subjectId][(string)$sid] = (float)$score;
			}
		}
		foreach ($sum as $subjectId => $total) {
			if ((int)$cnt[$subjectId] > 0) {
				$cbeCurrentClassMeans[$subjectId] = round($total / (int)$cnt[$subjectId], 2);
			}
		}
		foreach ($scoresBySubject as $subjectId => $studentScores) {
			arsort($studentScores, SORT_NUMERIC);
			$rank = 0;
			$position = 0;
			$prev = null;
			$total = count($studentScores);
			$cbeRankMaps[(int)$subjectId] = [];
			foreach ($studentScores as $rowStudentId => $rowScore) {
				$position++;
				if ($prev === null || (float)$rowScore !== (float)$prev) {
					$rank = $position;
					$prev = (float)$rowScore;
				}
				$cbeRankMaps[(int)$subjectId][(string)$rowStudentId] = $rank . '/' . $total;
			}
		}
	}

	$cbePreviousClassMeans = [];
	if ($prevTermId > 0 && !empty($cbePrevious)) {
		$sum = [];
		$cnt = [];
		foreach ($cbePrevious as $sid => $subjectScores) {
			foreach ($subjectScores as $subjectId => $score) {
				$sum[$subjectId] = (float)($sum[$subjectId] ?? 0) + (float)$score;
				$cnt[$subjectId] = (int)($cnt[$subjectId] ?? 0) + 1;
			}
		}
		foreach ($sum as $subjectId => $total) {
			if ((int)$cnt[$subjectId] > 0) {
				$cbePreviousClassMeans[$subjectId] = round($total / (int)$cnt[$subjectId], 2);
			}
		}
	}

	foreach ($subjects as $subject) {
		$combinationId = (int)$subject['combination_id'];
		$subjectId = (int)$subject['subject'];
		$hasExamCurrentScore = isset($currentMatrix[$studentId][$combinationId]);
		$hasCbeCurrentScore = array_key_exists($subjectId, $cbeCurrentStudent);
		$hasCurrentScore = $hasExamCurrentScore || $hasCbeCurrentScore;
		$currentScore = $hasCurrentScore ? (float)($currentStudentScores[$combinationId] ?? ($cbeCurrentStudent[$subjectId] ?? 0.0)) : 0.0;
		$hasExamPreviousScore = array_key_exists($combinationId, $previousStudentScores) && $previousStudentScores[$combinationId] !== null;
		$hasCbePreviousScore = array_key_exists($subjectId, $cbePreviousStudent);
		$hasPreviousScore = $hasExamPreviousScore || $hasCbePreviousScore;
		$previousScore = $hasPreviousScore
			? (float)($previousStudentScores[$combinationId] ?? $cbePreviousStudent[$subjectId])
			: null;
		$classMean = (float)($currentMeans[$combinationId] ?? ($cbeCurrentClassMeans[$subjectId] ?? 0.0));
		$previousMean = (float)($previousMeans[$combinationId] ?? ($cbePreviousClassMeans[$subjectId] ?? 0.0));

		$weight = (!empty($settings['use_weights']) && isset($weights[(int)$subject['subject']])) ? (float)$weights[(int)$subject['subject']] : 1.0;
		$gradePoints = null;
		$previousGradePoints = null;
		$classMeanPoints = null;
		if ($hasCurrentScore) {
			if ($hasExamCurrentScore) {
				list($grade, $remark, $gradePoints) = report_grade_for_score($conn, $currentScore, $classGradingSystemId);
				list(, , $classMeanPoints) = report_grade_for_score($conn, $classMean, $classGradingSystemId);
			} else {
				list($grade, $remark, $gradePoints) = report_cbe_grade_for_score($conn, $currentScore);
				list(, , $classMeanPoints) = report_cbe_grade_for_score($conn, $classMean);
			}
		} else {
			$grade = 'N/A';
			$remark = 'No marks entered';
		}
		if ($hasPreviousScore) {
			if ($hasExamPreviousScore) {
				list(, , $previousGradePoints) = report_grade_for_score($conn, (float)$previousScore, $classGradingSystemId);
			} else {
				list(, , $previousGradePoints) = report_cbe_grade_for_score($conn, (float)$previousScore);
			}
		}
		$change = ($gradePoints !== null && $previousGradePoints !== null)
			? round((float)$gradePoints - (float)$previousGradePoints, 2)
			: 0.0;
		if (!$hasCurrentScore) {
			$trend = '-';
		} elseif ($previousGradePoints === null) {
			$trend = 'new';
		} else {
			$trend = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'steady');
		}
		$deviation = ($gradePoints !== null && $classMeanPoints !== null)
			? round((float)$gradePoints - (float)$classMeanPoints, 2)
			: null;
		$rankLabel = isset($subjectRankMaps[$combinationId][$studentId])
			? (string)$subjectRankMaps[$combinationId][$studentId]
			: '-';

		// Ensure teacher name is present; fall back to class subject assignments if missing
		$teacherFullName = trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? ''));
		if ($teacherFullName === '') {
			try {
				$stmt = $conn->prepare("SELECT st.fname, st.lname FROM tbl_subject_combinations sc LEFT JOIN tbl_staff st ON st.id = sc.teacher WHERE sc.subject = ? LIMIT 1");
				$stmt->execute([$subjectId]);
				$tr = $stmt->fetch(PDO::FETCH_ASSOC);
				if ($tr) {
					$teacherFullName = trim(($tr['fname'] ?? '') . ' ' . ($tr['lname'] ?? ''));
				}
			} catch (Throwable $e) {
				// ignore and leave teacher name empty
			}
		}

		// Generate AI-style per-subject comment to avoid nulls
		$aiSubjectComment = '';
		if ($hasCurrentScore) {
			if ($currentScore >= 75) {
				$aiSubjectComment = 'Excellent performance in ' . ($subject['subject_name'] ?? 'this subject') . '.';
			} elseif ($currentScore >= 50) {
				$aiSubjectComment = 'Satisfactory performance; keep improving in ' . ($subject['subject_name'] ?? 'this subject') . '.';
			} else {
				$aiSubjectComment = 'Needs targeted support in ' . ($subject['subject_name'] ?? 'this subject') . '.';
			}
			// include rank if available
			if ($rankLabel !== '-') {
				$aiSubjectComment .= ' Ranked ' . $rankLabel . '.';
			}
		} else {
			$aiSubjectComment = 'No marks entered for ' . ($subject['subject_name'] ?? 'this subject') . ' yet.';
		}

		$rows[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => (string)$subject['subject_name'],
			'teacher_name' => $teacherFullName,
			'cat1' => '-',
			'cat2' => '-',
			'score' => $hasCurrentScore ? round($currentScore, 2) : null,
			'has_score' => $hasCurrentScore,
			'class_mean' => $classMean,
			'deviation' => $deviation,
			'rank' => $rankLabel,
			'position' => $rankLabel,
			'previous_mean' => $previousGradePoints ?? 0.0,
			'previous_score' => $previousScore,
			'change' => $change,
			'trend' => $trend,
			'grade' => $grade,
			'grade_points' => $gradePoints !== null ? (float)$gradePoints : null,
			'points' => $gradePoints !== null ? (float)$gradePoints : report_grade_points_from_label((string)$grade),
			'remark' => $remark,
			'ai_comment' => $aiSubjectComment,
			'weight' => $weight,
			'progress' => max(0, min(100, $classMean)),
		];
	}

	return $rows;
}

function report_store_card(PDO $conn, string $studentId, int $classId, int $termId, array $report, array $positions, int $totalStudents, ?int $generatedBy = null, int $examId = 0): int
{
	report_ensure_exam_batch_schema($conn);
	$position = $positions[$studentId] ?? 0;
	$code = report_generate_code($studentId);
	$payload = [
		'student_id' => $studentId,
		'class_id' => $classId,
		'term_id' => $termId,
		'exam_id' => $examId > 0 ? $examId : 0,
		'total' => $report['total'],
		'mean' => $report['mean'],
		'grade' => $report['grade'],
		'position' => $position
	];
	$hash = report_generate_hash($payload);
	$trend = $report['trend'];

	$reportId = report_find_card_id($conn, $studentId, $termId, $examId);
	$existing = null;
	if ($reportId > 0) {
		$stmt = $conn->prepare("SELECT id, verification_code FROM tbl_report_cards WHERE id = ? LIMIT 1");
		$stmt->execute([$reportId]);
		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
	}
	if ($existing) {
		$reportId = (int)$existing['id'];
		$existingCode = trim((string)($existing['verification_code'] ?? ''));
		if ($existingCode === '') {
			$existingCode = $code;
		}
		if (app_column_exists($conn, 'tbl_report_cards', 'exam_id')) {
			$stmt = $conn->prepare("UPDATE tbl_report_cards
				SET total = ?, mean = ?, grade = ?, remark = ?, position = ?, total_students = ?, trend = ?, report_hash = ?, verification_code = ?, generated_by = ?, exam_id = ?, generated_at = CURRENT_TIMESTAMP
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
				$examId > 0 ? $examId : null,
				$reportId
			]);
		} else {
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
		}
	} else {
		if (app_column_exists($conn, 'tbl_report_cards', 'exam_id')) {
			$stmt = $conn->prepare("INSERT INTO tbl_report_cards (student_id, class_id, term_id, exam_id, total, mean, grade, remark, position, total_students, trend, verification_code, report_hash, generated_by)
				VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
			$stmt->execute([
				$studentId,
				$classId,
				$termId,
				$examId > 0 ? $examId : null,
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
		}
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

function report_ensure_card_generated(PDO $conn, string $studentId, int $classId, int $termId, ?int $generatedBy = null, int $examId = 0): ?array
{
	report_ensure_exam_batch_schema($conn);
	if (!app_table_exists($conn, 'tbl_report_cards') || !report_term_is_published($conn, $classId, $termId)) {
		return null;
	}

	$reportId = report_find_card_id($conn, $studentId, $termId, $examId);
	if ($reportId > 0) {
		$card = report_load_card($conn, $reportId);
		if ($card && !empty($card['subjects'])) {
			$current = report_compute_for_student($conn, $studentId, $classId, $termId);
			$cachedTotal = round((float)($card['total'] ?? 0), 2);
			$cachedMean = round((float)($card['mean'] ?? 0), 2);
			$cachedGrade = trim((string)($card['grade'] ?? ''));
			$cachedPosition = trim((string)($card['position'] ?? ''));
			$cachedTotalStudents = (int)($card['total_students'] ?? 0);
			$currentTotal = round((float)($current['total'] ?? 0), 2);
			$currentMean = round((float)($current['mean'] ?? 0), 2);
			$currentGrade = trim((string)($current['grade'] ?? ''));
			$needsRefresh = report_card_has_legacy_grades($card)
				|| $cachedTotal !== $currentTotal
				|| $cachedMean !== $currentMean
				|| $cachedGrade !== $currentGrade
				|| $cachedPosition === ''
				|| $cachedTotalStudents < 1
				|| count((array)($card['subjects'] ?? [])) !== count((array)($current['subjects'] ?? []));
			if (!$needsRefresh) {
				return $card;
			}
		}
	}

	$rankData = report_rank_students($conn, $classId, $termId);
	$report = report_compute_for_student($conn, $studentId, $classId, $termId);
	$reportId = report_store_card($conn, $studentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], $generatedBy, $examId);
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

function report_class_merit_list(PDO $conn, int $classId, int $termId, ?int $generatedBy = null, int $examId = 0): array
{
	report_ensure_exam_batch_schema($conn);
	if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_students') || !app_table_exists($conn, 'tbl_report_cards')) {
		return [
			'rows' => [],
			'total_students' => 0,
			'positions' => [],
			'subjects' => [],
		];
	}

	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$stmt = $conn->prepare("SELECT id, school_id, fname, mname, lname, gender FROM tbl_students WHERE class = ? ORDER BY fname, lname, id");
	$stmt->execute([$classId]);
	$studentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (!$studentRows) {
		return [
			'rows' => [],
			'total_students' => 0,
			'positions' => [],
			'subjects' => $subjects,
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
			'gender' => (string)($studentRow['gender'] ?? ''),
		];
	}

	$rankData = report_rank_students($conn, $classId, $termId);
	$rows = [];
	foreach ($studentIds as $studentId) {
		$report = report_compute_for_student($conn, $studentId, $classId, $termId);
		$breakdown = report_subject_breakdown($conn, $studentId, $classId, $termId);
		$subjectScores = [];
		$subjectGrades = [];
		$subjectLookup = [];
		foreach ($breakdown as $subjectRow) {
			$subjectLookup[(int)($subjectRow['subject_id'] ?? 0)] = $subjectRow;
		}
		foreach ($subjects as $subjectMeta) {
			$subjectId = (int)($subjectMeta['subject'] ?? 0);
			$subjectRow = $subjectLookup[$subjectId] ?? null;
			$scoreValue = null;
			$gradeValue = '';
			if (is_array($subjectRow)) {
				if (isset($subjectRow['points']) && $subjectRow['points'] !== null && $subjectRow['points'] !== '') {
					$scoreValue = (float)$subjectRow['points'];
				} elseif (isset($subjectRow['grade_points']) && $subjectRow['grade_points'] !== null && $subjectRow['grade_points'] !== '') {
					$scoreValue = (float)$subjectRow['grade_points'];
				} elseif (isset($subjectRow['score']) && $subjectRow['score'] !== null && $subjectRow['score'] !== '') {
					$scoreValue = (float)$subjectRow['score'];
				}
				$gradeValue = (string)($subjectRow['grade'] ?? '');
			}
			$subjectScores[$subjectId] = $scoreValue;
			$subjectGrades[$subjectId] = $gradeValue;
		}
		$reportId = report_store_card($conn, $studentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], $generatedBy, $examId);
		$stmt = $conn->prepare("SELECT verification_code FROM tbl_report_cards WHERE id = ? LIMIT 1");
		$stmt->execute([$reportId]);
		$verificationCode = (string)$stmt->fetchColumn();
		$identity = $identityMap[$studentId] ?? ['school_id' => '', 'name' => '', 'class_name' => $className, 'gender' => ''];
			$rows[] = [
				'report_id' => $reportId,
				'student_id' => $studentId,
				'school_id' => (string)($identity['school_id'] ?? ''),
				'student_name' => (string)($identity['name'] ?? ''),
				'gender' => (string)($identity['gender'] ?? ''),
				'class_name' => (string)($identity['class_name'] ?? ''),
				'position' => (int)($rankData['positions'][$studentId] ?? 0),
				'position_text' => ((int)($rankData['positions'][$studentId] ?? 0)) . '/' . (int)$rankData['total_students'],
				'total_students' => (int)$rankData['total_students'],
				'total' => (float)($report['total'] ?? 0),
				'mean' => (float)($report['mean'] ?? 0),
				'total_points' => (float)($report['total_points'] ?? 0),
				'mean_points' => (float)($report['mean_points'] ?? 0),
				'cbe_band' => (string)($report['grade'] ?? ''),
				'grade' => (string)($report['grade'] ?? ''),
				'remark' => (string)($report['remark'] ?? ''),
				'trend' => (string)($report['trend'] ?? ''),
				'verification_code' => $verificationCode,
				'subject_scores' => $subjectScores,
				'subject_grades' => $subjectGrades,
			];
		}

		usort($rows, function ($a, $b) {
			if ((int)$a['position'] === (int)$b['position']) {
				if ((float)$a['mean_points'] === (float)$b['mean_points']) {
					return strcmp((string)$a['student_id'], (string)$b['student_id']);
				}
				return (float)$b['mean_points'] <=> (float)$a['mean_points'];
			}
			return (int)$a['position'] <=> (int)$b['position'];
		});

	return [
		'rows' => $rows,
		'total_students' => count($studentIds),
		'positions' => $rankData['positions'],
		'subjects' => $subjects,
	];
}
