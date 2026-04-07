<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('exams.manage', '../');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../subjects");
	exit;
}

$subjectId = (int)($_POST['subject_id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$classIds = $_POST['class_ids'] ?? [];

if ($name === '' || empty($classIds)) {
	$_SESSION['reply'] = array(array("danger", "Subject name and classes are required."));
	header("location:../subjects");
	exit;
}

$classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_subjects')) {
		throw new RuntimeException("Subjects table not installed.");
	}

	if ($subjectId > 0) {
		$stmt = $conn->prepare("UPDATE tbl_subjects SET name = ? WHERE id = ?");
		$stmt->execute([$name, $subjectId]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_subjects (name) VALUES (?)");
		$stmt->execute([$name]);
		$subjectId = (int)$conn->lastInsertId();
	}

	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_subject_class_assignments WHERE subject_id = ?");
		$stmt->execute([$subjectId]);

		$stmt = $conn->prepare("INSERT INTO tbl_subject_class_assignments (subject_id, class_id, created_by) VALUES (?,?,?)");
		foreach ($classIds as $classId) {
			$stmt->execute([$subjectId, $classId, (int)$account_id]);
		}
	}

	$_SESSION['reply'] = array(array("success", "Subject saved."));
	header("location:../subjects");
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", $e->getMessage()));
	header("location:../subjects");
	exit;
}
