<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1") { header("location:../"); exit; }
app_require_permission('teacher.allocate', '../teacher_allocation');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../teacher_allocation");
	exit;
}

$assignmentIds = $_POST['assignment_ids'] ?? [];
if (!is_array($assignmentIds)) {
	$assignmentIds = [];
}
$assignmentIds = array_values(array_unique(array_filter(array_map('intval', $assignmentIds), static fn($id) => $id > 0)));

if (empty($assignmentIds)) {
	$_SESSION['reply'] = array(array("danger", "Select at least one allocation to delete."));
	header("location:../teacher_allocation");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_teacher_assignments')) {
		throw new RuntimeException('Teacher assignment table not installed. Run migrations.');
	}

	$placeholders = implode(',', array_fill(0, count($assignmentIds), '?'));
	$stmt = $conn->prepare("SELECT id, teacher_id, class_id, subject_id FROM tbl_teacher_assignments WHERE id IN ($placeholders)");
	$stmt->execute($assignmentIds);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (empty($rows)) {
		throw new RuntimeException('No matching allocations were found.');
	}

	$conn->beginTransaction();
	$del = $conn->prepare("DELETE FROM tbl_teacher_assignments WHERE id = ?");
	foreach ($rows as $row) {
		$del->execute([(int)$row['id']]);
		if (!app_teacher_has_any_active_assignment($conn, (int)$row['teacher_id'], (int)$row['class_id'], (int)$row['subject_id'])) {
			app_sync_subject_combination($conn, (int)$row['teacher_id'], (int)$row['subject_id'], (int)$row['class_id'], true);
		}
	}
	$conn->commit();

	$_SESSION['reply'] = array(array("success", count($rows) . ' allocation' . (count($rows) === 1 ? '' : 's') . ' deleted.'));
	header("location:../teacher_allocation");
	exit;
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Unable to delete selected allocations right now."));
	header("location:../teacher_allocation");
	exit;
}
