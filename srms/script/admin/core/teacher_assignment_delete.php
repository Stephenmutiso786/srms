<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('staff.manage', '../');

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
	$_SESSION['reply'] = array(array("danger", $e->getMessage()));
	header("location:../teacher_allocation");
	exit;
}

function app_sync_subject_combination(PDO $conn, int $teacherId, int $subjectId, int $classId, bool $remove): void
{
	if (!app_table_exists($conn, 'tbl_subject_combinations')) {
		return;
	}
	$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations WHERE teacher = ? AND subject = ? LIMIT 1");
	$stmt->execute([$teacherId, $subjectId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return;
	}
	$classList = app_unserialize($row['class']);
	$classList = array_values(array_unique(array_map('strval', $classList)));
	$classIdStr = (string)$classId;

	if ($remove) {
		$classList = array_values(array_filter($classList, function ($val) use ($classIdStr) {
			return (string)$val !== $classIdStr;
		}));
	}

	if (count($classList) < 1) {
		$stmt = $conn->prepare("DELETE FROM tbl_subject_combinations WHERE id = ?");
		$stmt->execute([(int)$row['id']]);
		return;
	}
	$stmt = $conn->prepare("UPDATE tbl_subject_combinations SET class = ? WHERE id = ?");
	$stmt->execute([serialize($classList), (int)$row['id']]);
}
