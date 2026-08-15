<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('exams.manage', '../exams');
app_require_unlocked('exams', '../exams');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../exams");
	exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$examTypeId = $_POST['exam_type_id'] ?? null;
$examTypeId = $examTypeId === '' ? null : (int)$examTypeId;
$weightPercentage = (float)($_POST['weight_percentage'] ?? 100);
$subjectIds = $_POST['subject_ids'] ?? [];
$componentExamIds = $_POST['component_exam_ids'] ?? [];
$subjectIds = is_array($subjectIds) ? array_values(array_unique(array_filter(array_map('intval', $subjectIds)))) : [];
$componentExamIds = is_array($componentExamIds) ? array_values(array_unique(array_filter(array_map('intval', $componentExamIds)))) : [];

$assessmentMode = strtolower(trim((string)($_POST['assessment_mode'] ?? 'normal')));
if (!in_array($assessmentMode, ['normal', 'cbe', 'consolidated'], true)) {
	$assessmentMode = 'normal';
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_overall_grading_defaults($conn);
	app_ensure_exam_assessment_mode_column($conn);
	app_ensure_exam_subjects_table($conn);
	app_ensure_exam_components_table($conn);
	app_ensure_exam_type($conn);
	app_ensure_exam_weights_table($conn);
	if ($examId < 1 || $name === '' || $classId < 1 || $termId < 1) {
		$_SESSION['reply'] = array(array("danger", "Fill all required fields."));
		header("location:../exams");
		exit;
	}
	if ($assessmentMode !== 'consolidated' && empty($subjectIds)) {
		throw new RuntimeException("Select at least one subject.");
	}
	if ($assessmentMode === 'consolidated' && count($componentExamIds) < 2) {
		throw new RuntimeException("Choose at least two component exams for consolidated mode.");
	}
	if (!app_column_exists($conn, 'tbl_exams', 'grading_system_id')) {
		throw new RuntimeException("Exam grading support is not installed. Run migration 030.");
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		throw new RuntimeException("Exam not found.");
	}
	$beforeSnapshot = app_exam_archive_payload($conn, $examId);

	$classStmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$classStmt->execute([$classId]);
	$className = (string)($classStmt->fetchColumn() ?? '');

	$classGradingSystemId = (int)(app_class_grading_system_id($conn, $classId) ?? 0);
	if ($classGradingSystemId < 1) {
		$label = $className !== '' ? $className : ('class #' . $classId);
		throw new RuntimeException('Assign a grading system to ' . $label . ' in Class Management before editing exams.');
	}
	$gradingSystemId = $classGradingSystemId;
	if (app_table_exists($conn, 'tbl_grading_systems')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE id = ? AND is_active = 1 LIMIT 1");
		$stmt->execute([$gradingSystemId]);
		if (!(int)$stmt->fetchColumn()) {
			$label = $className !== '' ? $className : ('class #' . $classId);
			throw new RuntimeException('The grading system assigned to ' . $label . ' is inactive. Update it in Class Management first.');
		}
	}

	$hasMarks = false;
	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE exam_id = ?");
		$stmt->execute([$examId]);
		$hasMarks = ((int)$stmt->fetchColumn() > 0);
	}
	if (!$hasMarks && app_table_exists($conn, 'tbl_exam_results') && app_column_exists($conn, 'tbl_exam_results', 'exam_id')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_results WHERE exam_id = ?");
		$stmt->execute([$examId]);
		$hasMarks = ((int)$stmt->fetchColumn() > 0);
	}

	$lockedStatuses = ['finalized', 'published'];
	$isLockedExam = in_array((string)$exam['status'], $lockedStatuses, true);
	if ($hasMarks && ((int)$exam['class_id'] !== $classId || (int)$exam['term_id'] !== $termId || (int)($exam['grading_system_id'] ?? 0) !== $gradingSystemId)) {
		throw new RuntimeException("Class, term, or grading system cannot be changed after marks have been entered.");
	}

	$currentSubjects = [];
	$addedSubjects = [];
	if ($hasMarks || $isLockedExam) {
		$currentSubjects = app_exam_subject_ids($conn, $examId);
		sort($currentSubjects);
		$nextSubjects = $subjectIds;
		sort($nextSubjects);
		if ($isLockedExam) {
			$addedSubjects = array_values(array_diff($nextSubjects, $currentSubjects));
			$removedSubjects = array_values(array_diff($currentSubjects, $nextSubjects));
			if (!empty($removedSubjects)) {
				throw new RuntimeException("Finalized exams can only have subjects added, not removed.");
			}
			if (($classId !== (int)$exam['class_id'] || $termId !== (int)$exam['term_id'] || $gradingSystemId !== (int)($exam['grading_system_id'] ?? 0)) && !empty($currentSubjects)) {
				throw new RuntimeException("Finalized exams cannot change class, term, or grading system.");
			}
		} elseif ($currentSubjects !== $nextSubjects) {
			throw new RuntimeException("Subjects cannot be changed after marks have been entered.");
		}
	}

	$validSubjectIds = $subjectIds;
	$validComponentExamIds = [];
	if ($assessmentMode === 'consolidated') {
		$componentSubjects = [];
		$placeholders = implode(',', array_fill(0, count($componentExamIds), '?'));
		$params = array_merge([$classId, $termId, $examId], $componentExamIds);
		$stmt = $conn->prepare("SELECT id FROM tbl_exams WHERE class_id = ? AND term_id = ? AND id <> ? AND id IN ($placeholders) AND COALESCE(assessment_mode, 'normal') <> 'cbe' AND COALESCE(assessment_mode, 'normal') <> 'consolidated' AND status IN ('finalized', 'published')");
		$stmt->execute($params);
		$validComponentExamIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		if (count($validComponentExamIds) < 2) {
			throw new RuntimeException("Selected component exams must belong to the same class/term and be at least two.");
		}
		foreach ($validComponentExamIds as $componentExamId) {
			foreach (app_exam_subject_ids($conn, $componentExamId) as $sid) {
				$componentSubjects[(int)$sid] = (int)$sid;
			}
		}
		$validSubjectIds = array_values($componentSubjects);
	}
	if (app_table_exists($conn, 'tbl_subject_class_assignments') && !empty($validSubjectIds)) {
		$placeholders = implode(',', array_fill(0, count($validSubjectIds), '?'));
		$params = array_merge([$classId], $validSubjectIds);
		$stmt = $conn->prepare("SELECT subject_id FROM tbl_subject_class_assignments WHERE class_id = ? AND subject_id IN ($placeholders)");
		$stmt->execute($params);
		$validSubjectIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
	}

	if (empty($validSubjectIds)) {
		throw new RuntimeException("Select at least one subject assigned to the chosen class.");
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_exams WHERE name = ? AND term_id = ? AND class_id = ? AND id <> ? LIMIT 1");
	$stmt->execute([$name, $termId, $classId, $examId]);
	if ($stmt->fetchColumn()) {
		throw new RuntimeException("Another exam with the same name already exists for that class and term.");
	}

	$nextStatus = (string)$exam['status'];
	if ($isLockedExam && !empty($addedSubjects)) {
		$nextStatus = 'active';
	}
	$stmt = $conn->prepare("UPDATE tbl_exams SET name = ?, class_id = ?, term_id = ?, exam_type_id = ?, grading_system_id = ?, assessment_mode = ?, status = ? WHERE id = ?");
	$stmt->execute([$name, $classId, $termId, $examTypeId, $gradingSystemId, $assessmentMode, $nextStatus, $examId]);

	if (DBDriver === 'pgsql') {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_weights (exam_id, weight_percentage) VALUES (?, ?)
			ON CONFLICT (exam_id) DO UPDATE SET weight_percentage = EXCLUDED.weight_percentage");
		$stmt->execute([$examId, $weightPercentage > 0 ? $weightPercentage : 100]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_weights (exam_id, weight_percentage) VALUES (?, ?)
			ON DUPLICATE KEY UPDATE weight_percentage = VALUES(weight_percentage)");
		$stmt->execute([$examId, $weightPercentage > 0 ? $weightPercentage : 100]);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_exam_subjects WHERE exam_id = ?");
	$stmt->execute([$examId]);
	$stmt = $conn->prepare("DELETE FROM tbl_exam_components WHERE exam_id = ?");
	$stmt->execute([$examId]);

	$stmt = $conn->prepare("INSERT INTO tbl_exam_subjects (exam_id, subject_id) VALUES (?, ?)");
	foreach ($validSubjectIds as $subjectId) {
		$stmt->execute([$examId, $subjectId]);
	}
	if ($assessmentMode === 'consolidated') {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_components (exam_id, component_exam_id) VALUES (?, ?)");
		foreach ($validComponentExamIds as $componentExamId) {
			$stmt->execute([$examId, $componentExamId]);
		}
	}
	$afterSnapshot = app_exam_archive_payload($conn, $examId);
	app_data_camp_store_event($conn, [
		'module_key' => 'exams',
		'record_type' => 'exam_updated',
		'entity_table' => 'tbl_exams',
		'entity_id' => (string)$examId,
		'title' => $name,
		'description' => 'Exam snapshot retained before and after edit',
		'class_id' => $classId,
		'owner_portal' => 'admin,academic,teacher',
		'mime_type' => 'application/json',
		'status' => 'retained',
		'payload_json' => [
			'before' => $beforeSnapshot,
			'after' => $afterSnapshot,
		],
		'created_by' => (int)($account_id ?? 0),
	]);

	$_SESSION['reply'] = array(array("success", "Exam updated successfully."));
	header("location:../exams");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", "Failed to update exam: " . $e->getMessage()));
	header("location:../edit_exam?id=" . $examId);
	exit;
}
