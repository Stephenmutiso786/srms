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
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$examTypeId = $_POST['exam_type_id'] ?? null;
$examTypeId = $examTypeId === '' ? null : (int)$examTypeId;

if ($name === '' || $classId < 1 || $termId < 1) {
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
	$stmt->execute([$name, $termId, $classId, $examTypeId, 'open', $myid]);

	$_SESSION['reply'] = array (array("success", "Exam created."));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to create exam: " . $e->getMessage()));
	header("location:../exams");
}
