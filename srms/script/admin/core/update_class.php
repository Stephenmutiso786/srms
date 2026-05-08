<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "0" || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$name = app_build_class_name(
	(string)($_POST['grade_name'] ?? ''),
	(string)($_POST['stream_name'] ?? ''),
	(string)($_POST['name'] ?? '')
);
$id = (int)($_POST['id'] ?? 0);
$classTeacherId = (int)($_POST['class_teacher_id'] ?? 0);
$gradingSystemId = (int)($_POST['grading_system_id'] ?? 0);
$subjectIds = $_POST['subject_ids'] ?? [];
if ($name === '' || $id < 1) {
	app_reply_redirect('danger', 'Invalid class update request.', '../classes');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_class_cbe_level_schema($conn);
	app_ensure_exam_grading_schema($conn);
	$gradeLevel = app_grade_level_from_class_name($name);
	$cbeBand = app_cbe_class_band($name);
	$cbeLevel = $cbeBand !== '' ? app_cbe_band_label($cbeBand) : null;
	if ($gradingSystemId < 1) {
		$gradingSystemId = (int)(app_class_recommended_grading_system_id($conn, $name) ?? 0);
	}
	$stmt = $conn->prepare("SELECT 1 FROM tbl_classes WHERE name = ? AND id <> ? LIMIT 1");
	$stmt->execute([$name, $id]);
	if ($stmt->fetchColumn()) {
		app_reply_redirect('danger', 'Class is already registered.', '../classes');
	}
	$stmt = $conn->prepare("UPDATE tbl_classes SET name = ?, grade = ?, cbe_level = ?, grading_system_id = ? WHERE id = ?");
	$stmt->execute([$name, $gradeLevel > 0 ? $gradeLevel : null, $cbeLevel, $gradingSystemId > 0 ? $gradingSystemId : null, $id]);
	app_save_class_grading_system($conn, $id, $gradingSystemId > 0 ? $gradingSystemId : null);
	app_ensure_class_teachers_table($conn);
	$stmt = $conn->prepare("DELETE FROM tbl_class_teachers WHERE class_id = ?");
	$stmt->execute([$id]);
	if ($classTeacherId > 0) {
		$stmt = $conn->prepare("INSERT INTO tbl_class_teachers (class_id, teacher_id, active, created_by) VALUES (?,?,1,?)");
		$stmt->execute([$id, $classTeacherId, (int)$account_id]);
	}
	app_save_class_subject_assignments($conn, $id, is_array($subjectIds) ? $subjectIds : [], (int)$account_id);
	app_reply_redirect('success', 'Class updated successfully.', '../classes');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to update class.', '../classes');
}
