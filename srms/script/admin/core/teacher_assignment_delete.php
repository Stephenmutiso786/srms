<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1") { header("location:../"); exit; }
app_require_permission('teacher.allocate', '../');

$id = (int)($_GET['id'] ?? 0);
if ($id < 1) {
	$_SESSION['reply'] = array(array("danger", "Invalid allocation."));
	header("location:../teacher_allocation");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_teacher_assignments')) {
		throw new RuntimeException("Teacher assignment table not installed. Run migrations.");
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_teacher_assignments WHERE id = ? LIMIT 1");
	$stmt->execute([$id]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		throw new RuntimeException("Allocation not found.");
	}

	$stmt = $conn->prepare("DELETE FROM tbl_teacher_assignments WHERE id = ?");
	$stmt->execute([$id]);

	app_sync_subject_combination($conn, (int)$row['teacher_id'], (int)$row['subject_id'], (int)$row['class_id'], true);

	$_SESSION['reply'] = array(array("success", "Allocation deleted."));
	header("location:../teacher_allocation");
	exit;
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
	header("location:../teacher_allocation");
	exit;
}
