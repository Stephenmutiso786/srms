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
if ($name === '') {
	$_SESSION['reply'] = array (array("danger", "Enter exam type name."));
	header("location:../exams");
	exit;
}

$normalizedName = strtolower($name);
if (strpos($normalizedName, 'consolidated') !== false || strpos($normalizedName, 'complex') !== false) {
	$name = 'Consolidated / Complex Exam';
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_type($conn);

	if (!app_table_exists($conn, 'tbl_exam_types')) {
		$_SESSION['reply'] = array (array("danger", "Exam types table missing. Run migration 007."));
		header("location:../exams");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_exam_types (name, status) VALUES (?,1)");
	$stmt->execute([$name]);
	$_SESSION['reply'] = array (array("success", "Exam type saved."));
	header("location:../exams");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to save: " . $e->getMessage()));
	header("location:../exams");
}
