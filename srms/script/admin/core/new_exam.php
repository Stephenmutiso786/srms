<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('exams.manage', '../exams');
app_require_unlocked('exams', '../exams');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../exams");
	exit;
}

$name = trim($_POST['name'] ?? '');
$classIds = $_POST['class_ids'] ?? [];
$subjectIds = $_POST['subject_ids'] ?? [];
$termId = (int)($_POST['term_id'] ?? 0);
$gradingSystemId = (int)($_POST['grading_system_id'] ?? 0);
$examTypeId = $_POST['exam_type_id'] ?? null;
$examTypeId = $examTypeId === '' ? null : (int)$examTypeId;
$classIds = is_array($classIds) ? array_values(array_unique(array_filter(array_map('intval', $classIds)))) : [];
$subjectIds = is_array($subjectIds) ? array_values(array_unique(array_filter(array_map('intval', $subjectIds)))) : [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_overall_grading_defaults($conn);

	if ($gradingSystemId < 1 && app_table_exists($conn, 'tbl_grading_systems')) {
		$stmt = $conn->prepare("SELECT id FROM tbl_grading_systems WHERE is_active = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
		$stmt->execute();
		$gradingSystemId = (int)$stmt->fetchColumn();
	}

	if ($name === '' || empty($classIds) || empty($subjectIds) || $termId < 1 || $gradingSystemId < 1) {
		$_SESSION['reply'] = array (array("danger", "Fill all required fields."));
		header("location:../exams");
		exit;
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
	$created = 0;
	$skippedClasses = [];
	foreach ($classIds as $classId) {
		if ($classId < 1) {
			continue;
		}

		$validSubjects = $subjectIds;
		if (!empty($classSubjectMap)) {
			$allowed = $classSubjectMap[$classId] ?? [];
			$validSubjects = array_values(array_intersect($subjectIds, $allowed));
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
			$stmt = $conn->prepare("INSERT INTO tbl_exams (name, term_id, class_id, exam_type_id, grading_system_id, status, created_by) VALUES (?,?,?,?,?,?,?) RETURNING id");
			$stmt->execute([$name, $termId, $classId, $examTypeId, $gradingSystemId, 'draft', $myid]);
			$examId = (int)$stmt->fetchColumn();
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_exams (name, term_id, class_id, exam_type_id, grading_system_id, status, created_by) VALUES (?,?,?,?,?,?,?)");
			$stmt->execute([$name, $termId, $classId, $examTypeId, $gradingSystemId, 'draft', $myid]);
			$examId = (int)$conn->lastInsertId();
		}
		foreach ($validSubjects as $subjectId) {
			$subjectStmt->execute([$examId, $subjectId]);
		}
		$created++;
	}

	if ($created < 1) {
		throw new RuntimeException("These exam structures already exist for the selected classes.");
	}

	$message = "Exam structure created for " . $created . " class(es). Activate it when teachers are ready.";
	if (!empty($skippedClasses)) {
		$message .= " Some classes were skipped because none of the selected subjects are assigned to them.";
	}
	$_SESSION['reply'] = array (array("success", $message));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to create exam: " . $e->getMessage()));
	header("location:../exams");
}
