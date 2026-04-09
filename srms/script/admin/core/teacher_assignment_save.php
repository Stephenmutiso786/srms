<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('staff.manage', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../teacher_allocation");
	exit;
}

$assignmentId = (int)($_POST['assignment_id'] ?? 0);
$teacherId = (int)($_POST['teacher_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$subjectId = (int)($_POST['subject_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$year = (int)($_POST['year'] ?? date('Y'));

if ($teacherId < 1 || $classId < 1 || $subjectId < 1 || $termId < 1 || $year < 2000) {
	$_SESSION['reply'] = array(array("danger", "Missing or invalid allocation details."));
	header("location:../teacher_allocation");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_teacher_assignments')) {
		throw new RuntimeException("Teacher assignment table not installed. Run migrations.");
	}

	if ($assignmentId > 0) {
		$stmt = $conn->prepare("SELECT * FROM tbl_teacher_assignments WHERE id = ? LIMIT 1");
		$stmt->execute([$assignmentId]);
		$existing = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$existing) {
			throw new RuntimeException("Assignment not found.");
		}

		$stmt = $conn->prepare("SELECT id FROM tbl_teacher_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND term_id = ? AND year = ? AND id <> ? LIMIT 1");
		$stmt->execute([$teacherId, $classId, $subjectId, $termId, $year, $assignmentId]);
		if ($stmt->fetchColumn()) {
			throw new RuntimeException("This teacher is already assigned to that class/subject for the selected term.");
		}

		$stmt = $conn->prepare("UPDATE tbl_teacher_assignments SET teacher_id = ?, class_id = ?, subject_id = ?, term_id = ?, year = ? WHERE id = ?");
		$stmt->execute([$teacherId, $classId, $subjectId, $termId, $year, $assignmentId]);

		app_sync_subject_combination($conn, (int)$existing['teacher_id'], (int)$existing['subject_id'], (int)$existing['class_id'], true);
		app_sync_subject_combination($conn, $teacherId, $subjectId, $classId, false);

		$_SESSION['reply'] = array(array("success", "Allocation updated."));
		header("location:../teacher_allocation");
		exit;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_teacher_assignments WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND term_id = ? AND year = ? LIMIT 1");
	$stmt->execute([$teacherId, $classId, $subjectId, $termId, $year]);
	if ($stmt->fetchColumn()) {
		throw new RuntimeException("This teacher is already assigned to that class/subject for the selected term.");
	}

	$stmt = $conn->prepare("INSERT INTO tbl_teacher_assignments (teacher_id, class_id, subject_id, term_id, year, created_by) VALUES (?,?,?,?,?,?)");
	$stmt->execute([$teacherId, $classId, $subjectId, $termId, $year, (int)$account_id]);

	app_sync_subject_combination($conn, $teacherId, $subjectId, $classId, false);

	$_SESSION['reply'] = array(array("success", "Teacher allocation saved."));
	header("location:../teacher_allocation");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", $e->getMessage()));
	header("location:../teacher_allocation");
	exit;
}
