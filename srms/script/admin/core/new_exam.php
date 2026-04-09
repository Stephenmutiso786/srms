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
$termId = (int)($_POST['term_id'] ?? 0);
$examTypeId = $_POST['exam_type_id'] ?? null;
$examTypeId = $examTypeId === '' ? null : (int)$examTypeId;
$classIds = is_array($classIds) ? array_values(array_unique(array_filter(array_map('intval', $classIds)))) : [];

if ($name === '' || empty($classIds) || $termId < 1) {
	$_SESSION['reply'] = array (array("danger", "Fill all required fields."));
	header("location:../exams");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_exams')) {
		$_SESSION['reply'] = array (array("danger", "Exams table missing. Run migration 007."));
		header("location:../exams");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_exams (name, term_id, class_id, exam_type_id, status, created_by) VALUES (?,?,?,?,?,?)");
	$created = 0;
	foreach ($classIds as $classId) {
		if ($classId < 1) {
			continue;
		}
		$check = $conn->prepare("SELECT id FROM tbl_exams WHERE name = ? AND term_id = ? AND class_id = ? LIMIT 1");
		$check->execute([$name, $termId, $classId]);
		if ($check->fetchColumn()) {
			continue;
		}
		$stmt->execute([$name, $termId, $classId, $examTypeId, 'draft', $myid]);
		$created++;
	}

	if ($created < 1) {
		throw new RuntimeException("These exam structures already exist for the selected classes.");
	}

	$_SESSION['reply'] = array (array("success", "Exam structure created for " . $created . " class(es). Activate it when teachers are ready."));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to create exam: " . $e->getMessage()));
	header("location:../exams");
}
