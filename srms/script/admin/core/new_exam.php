<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res !== "1") { header("location:../../"); exit; }
$portalHome = ((string)$level === '1') ? '../../academic' : '../exams';
app_require_permission('exams.manage', $portalHome);
app_require_unlocked('exams', $portalHome);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../exams");
	exit;
}

$name = trim($_POST['name'] ?? '');
$classIds = $_POST['class_ids'] ?? [];
$subjectIds = $_POST['subject_ids'] ?? [];
$componentExamIds = $_POST['component_exam_ids'] ?? [];
$termId = (int)($_POST['term_id'] ?? 0);
$gradingSystemId = (int)($_POST['grading_system_id'] ?? 0);
$assessmentMode = trim((string)($_POST['assessment_mode'] ?? 'normal'));
$assessmentModeLower = strtolower($assessmentMode);
if ($assessmentModeLower === 'kpsea') {
	$assessmentMode = 'KPSEA';
} elseif ($assessmentModeLower === 'kjsea') {
	$assessmentMode = 'KJSEA';
} elseif (in_array($assessmentModeLower, ['normal', 'cbe', 'consolidated'], true)) {
	$assessmentMode = $assessmentModeLower;
} else {
	$assessmentMode = 'normal';
}
$examTypeId = $_POST['exam_type_id'] ?? null;
$examTypeId = $examTypeId === '' ? null : (int)$examTypeId;
$weightPercentage = (float)($_POST['weight_percentage'] ?? 100);
$classIds = is_array($classIds) ? array_values(array_unique(array_filter(array_map('intval', $classIds)))) : [];
$subjectIds = is_array($subjectIds) ? array_values(array_unique(array_filter(array_map('intval', $subjectIds)))) : [];
$componentExamIds = is_array($componentExamIds) ? array_values(array_unique(array_filter(array_map('intval', $componentExamIds)))) : [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_grading_schema($conn);
	app_ensure_overall_grading_defaults($conn);
	app_ensure_exam_type($conn);
	app_ensure_exam_weights_table($conn);
	app_ensure_exam_components_table($conn);
	$createdBy = isset($account_id) ? (int)$account_id : null;

	if ($gradingSystemId < 1 && app_table_exists($conn, 'tbl_grading_systems')) {
		$preferredType = in_array($assessmentMode, ['cbe', 'KPSEA', 'KJSEA', 'consolidated'], true) ? 'cbe' : 'marks';
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 AND type = ? ORDER BY is_default DESC, id ASC LIMIT 1");
		$stmt->execute([$preferredType]);
		$gradingSystemId = (int)$stmt->fetchColumn();
		if ($gradingSystemId < 1) {
			$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
			$stmt->execute();
			$gradingSystemId = (int)$stmt->fetchColumn();
		}
	}

	if ($name === '' || empty($classIds) || $termId < 1 || $gradingSystemId < 1) {
		$_SESSION['reply'] = array (array("danger", "Fill all required fields."));
		header("location:../exams");
		exit;
	}
	if ($assessmentMode !== 'consolidated' && empty($subjectIds)) {
		$_SESSION['reply'] = array (array("danger", "Select at least one subject."));
		header("location:../exams");
		exit;
	}
	if ($assessmentMode === 'consolidated' && count($componentExamIds) < 2) {
		$_SESSION['reply'] = array (array("danger", "Choose at least two component exams for consolidated mode."));
		header("location:../exams");
		exit;
	}

	// Validate national assessment modes (KPSEA, KJSEA)
	if (in_array($assessmentMode, ['KPSEA', 'KJSEA'], true)) {
		$validationError = null;
		foreach ($classIds as $classId) {
			$validationError = app_validate_assessment_mode_for_class($conn, $assessmentMode, $classId);
			if ($validationError) {
				break;
			}
		}
		if ($validationError) {
			$_SESSION['reply'] = array (array("danger", htmlspecialchars($validationError)));
			header("location:../exams");
			exit;
		}
	}

	if (!app_table_exists($conn, 'tbl_exams')) {
		$_SESSION['reply'] = array (array("danger", "Exams table missing. Run migration 007."));
		header("location:../exams");
		exit;
	}
	if (!app_column_exists($conn, 'tbl_exams', 'grading_system_id')) {
		$_SESSION['reply'] = array (array("danger", "Exam grading support is not installed. Run migration 030."));
		header("location:../exams");
		exit;
	}
	app_ensure_exam_assessment_mode_column($conn);
	app_ensure_exam_subjects_table($conn);

	if (app_table_exists($conn, 'tbl_grading_systems')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE id = ? AND is_active = 1 LIMIT 1");
		$stmt->execute([$gradingSystemId]);
		if (!$stmt->fetchColumn()) {
			throw new RuntimeException("Select an active grading system.");
		}
	}

	$classSubjectMap = [];
	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("SELECT class_id, subject_id FROM tbl_subject_class_assignments");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$classSubjectMap[(int)$row['class_id']][] = (int)$row['subject_id'];
		}
	}

	$subjectStmt = $conn->prepare("INSERT INTO tbl_exam_subjects (exam_id, subject_id) VALUES (?, ?)");
	$weightStmt = $conn->prepare("INSERT INTO tbl_exam_weights (exam_id, weight_percentage) VALUES (?, ?)");
	$componentStmt = $conn->prepare("INSERT INTO tbl_exam_components (exam_id, component_exam_id) VALUES (?, ?)");
	$created = 0;
	$skippedClasses = [];
	foreach ($classIds as $classId) {
		if ($classId < 1) {
			continue;
		}

		$className = '';
		$classStmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$classStmt->execute([$classId]);
		$className = (string)($classStmt->fetchColumn() ?? '');

		$effectiveAssessmentMode = $assessmentMode;

		$classGradingSystemId = (int)(app_class_grading_system_id($conn, $classId) ?? 0);
		$effectiveGradingSystemId = $classGradingSystemId > 0 ? $classGradingSystemId : $gradingSystemId;
		if ($classGradingSystemId < 1 && $assessmentMode === 'KJSEA' && app_table_exists($conn, 'tbl_grading_systems')) {
			$systemStmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 AND name = ? LIMIT 1");
			$systemStmt->execute(['CBE KJSEA System']);
			$kjseaSystemId = (int)$systemStmt->fetchColumn();
			if ($kjseaSystemId > 0) {
				$effectiveGradingSystemId = $kjseaSystemId;
			}
		}
		$recommendedGradingSystemId = app_class_recommended_grading_system_id($conn, $className);
		if ($classGradingSystemId < 1 && $recommendedGradingSystemId && app_class_recommended_exam_mode($className) === 'cbe') {
			$selectedTypeStmt = $conn->prepare("SELECT type FROM tbl_grading_systems WHERE id = ? LIMIT 1");
			$selectedTypeStmt->execute([$effectiveGradingSystemId]);
			$selectedType = strtolower(trim((string)($selectedTypeStmt->fetchColumn() ?? '')));
			if (($effectiveGradingSystemId < 1 || $selectedType === '' || $selectedType === 'marks')) {
				$effectiveGradingSystemId = $recommendedGradingSystemId;
			}
		}

		$validSubjects = $subjectIds;
		$validComponentExamIds = [];

		if ($effectiveAssessmentMode === 'consolidated') {
			$componentSubjects = [];
			if (empty($componentExamIds)) {
				throw new RuntimeException("Choose at least two component exams for consolidated mode.");
			}

			$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
			$params = array_merge([$classId, $termId], $componentExamIds);
			$stmt = $conn->prepare("SELECT id FROM tbl_exams
				WHERE class_id = ? AND term_id = ? AND id IN ($placeholders)
				AND id <> 0
				AND COALESCE(assessment_mode, 'normal') <> 'cbe'
				AND COALESCE(assessment_mode, 'normal') <> 'consolidated'
				AND COALESCE(status, 'draft') = 'published'");
			$stmt->execute($params);
			$validComponentExamIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

			if (count($validComponentExamIds) !== count($componentExamIds)) {
				throw new RuntimeException("All selected source exams must belong to the same class/term and be published before creating a consolidated exam.");
			}

			if (!app_table_exists($conn, 'tbl_exam_results') || !app_column_exists($conn, 'tbl_exam_results', 'exam_id')) {
				throw new RuntimeException("Exam results table is not ready for consolidated validation.");
			}

			foreach ($validComponentExamIds as $componentExamId) {
				$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id = ?");
				$stmt->execute([$classId, $termId, $componentExamId]);
				if ((int)$stmt->fetchColumn() < 1) {
					throw new RuntimeException("Each selected source exam must already contain marks before creating a consolidated exam.");
				}
			}

			foreach ($validComponentExamIds as $componentExamId) {
				foreach (app_exam_subject_ids($conn, $componentExamId) as $sid) {
					$componentSubjects[(int)$sid] = (int)$sid;
				}
			}
			$validSubjects = array_values($componentSubjects);
		}

		if (!empty($classSubjectMap)) {
			$allowed = $classSubjectMap[$classId] ?? [];
			$validSubjects = array_values(array_intersect($validSubjects, $allowed));
		}
		if (empty($validSubjects)) {
			$skippedClasses[] = $classId;
			continue;
		}

		$check = $conn->prepare("SELECT id FROM tbl_exams WHERE name = ? AND term_id = ? AND class_id = ? LIMIT 1");
		$check->execute([$name, $termId, $classId]);
		if ($check->fetchColumn()) {
			continue;
		}
		if (DBDriver === 'pgsql') {
			$stmt = $conn->prepare("INSERT INTO tbl_exams (name, term_id, class_id, exam_type_id, grading_system_id, assessment_mode, status, created_by) VALUES (?,?,?,?,?,?,?,?) RETURNING id");
			$stmt->execute([$name, $termId, $classId, $examTypeId, $effectiveGradingSystemId, $effectiveAssessmentMode, 'active', $createdBy]);
			$examId = (int)$stmt->fetchColumn();
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_exams (name, term_id, class_id, exam_type_id, grading_system_id, assessment_mode, status, created_by) VALUES (?,?,?,?,?,?,?,?)");
			$stmt->execute([$name, $termId, $classId, $examTypeId, $effectiveGradingSystemId, $effectiveAssessmentMode, 'active', $createdBy]);
			$examId = (int)$conn->lastInsertId();
		}
		foreach ($validSubjects as $subjectId) {
			$subjectStmt->execute([$examId, $subjectId]);
		}
		if ($effectiveAssessmentMode === 'consolidated') {
			foreach ($validComponentExamIds as $componentExamId) {
				$componentStmt->execute([$examId, $componentExamId]);
			}
		}
		$weightStmt->execute([$examId, $weightPercentage > 0 ? $weightPercentage : 100]);
		$created++;
	}

	if ($created < 1) {
		throw new RuntimeException("These exam structures already exist for the selected classes.");
	}

	$message = "Exam structure created and activated for " . $created . " class(es).";
	if (!empty($skippedClasses)) {
		$message .= " Some classes were skipped because none of the selected subjects are assigned to them.";
	}
	$_SESSION['reply'] = array (array("success", $message));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to create exam: " . $e->getMessage()));
	header("location:../exams");
}
