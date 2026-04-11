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
$gradingSystemId = (int)($_POST['grading_system_id'] ?? 0);
$assessmentMode = strtolower(trim((string)($_POST['assessment_mode'] ?? 'normal'))) === 'cbc' ? 'cbc' : 'normal';
$examTypeId = $_POST['exam_type_id'] ?? null;
$examTypeId = $examTypeId === '' ? null : (int)$examTypeId;
$subjectIds = $_POST['subject_ids'] ?? [];
$subjectIds = is_array($subjectIds) ? array_values(array_unique(array_filter(array_map('intval', $subjectIds)))) : [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_overall_grading_defaults($conn);
	app_ensure_exam_assessment_mode_column($conn);
	app_ensure_exam_subjects_table($conn);

	if ($gradingSystemId < 1 && app_table_exists($conn, 'tbl_grading_systems')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
		$stmt->execute();
		$gradingSystemId = (int)$stmt->fetchColumn();
	}
	if ($examId < 1 || $name === '' || $classId < 1 || $termId < 1 || $gradingSystemId < 1 || empty($subjectIds)) {
		$_SESSION['reply'] = array(array("danger", "Fill all required fields."));
		header("location:../exams");
		exit;
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

	if (in_array((string)$exam['status'], ['finalized', 'published'], true)) {
		throw new RuntimeException("Finalized or published exams cannot be edited.");
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

	if ($hasMarks && ((int)$exam['class_id'] !== $classId || (int)$exam['term_id'] !== $termId || (int)($exam['grading_system_id'] ?? 0) !== $gradingSystemId)) {
		throw new RuntimeException("Class, term, or grading system cannot be changed after marks have been entered.");
	}

	if ($hasMarks) {
		$currentSubjects = app_exam_subject_ids($conn, $examId);
		sort($currentSubjects);
		$nextSubjects = $subjectIds;
		sort($nextSubjects);
		if ($currentSubjects !== $nextSubjects) {
			throw new RuntimeException("Subjects cannot be changed after marks have been entered.");
		}
	}

	$validSubjectIds = $subjectIds;
	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$placeholders = implode(',', array_fill(0, count($subjectIds), '?'));
		$params = array_merge([$classId], $subjectIds);
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

	$stmt = $conn->prepare("UPDATE tbl_exams SET name = ?, class_id = ?, term_id = ?, exam_type_id = ?, grading_system_id = ?, assessment_mode = ? WHERE id = ?");
	$stmt->execute([$name, $classId, $termId, $examTypeId, $gradingSystemId, $assessmentMode, $examId]);

	$stmt = $conn->prepare("DELETE FROM tbl_exam_subjects WHERE exam_id = ?");
	$stmt->execute([$examId]);

	$stmt = $conn->prepare("INSERT INTO tbl_exam_subjects (exam_id, subject_id) VALUES (?, ?)");
	foreach ($validSubjectIds as $subjectId) {
		$stmt->execute([$examId, $subjectId]);
	}

	$_SESSION['reply'] = array(array("success", "Exam updated successfully."));
	header("location:../exams");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", "Failed to update exam: " . $e->getMessage()));
	header("location:../edit_exam?id=" . $examId);
	exit;
}
