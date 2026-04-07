<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('report.view', '../import_export');

$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'csv'));

if ($classId < 1 || $termId < 1) {
	$_SESSION['reply'] = array (array("danger", "Select class and term."));
	header("location:../import_export");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$sql = "SELECT er.student, st.fname, st.lname, c.name AS class_name, s.name AS subject_name, er.score
		FROM tbl_exam_results er
		LEFT JOIN tbl_students st ON ".($isPgsql ? "st.id::text = er.student::text" : "st.id = er.student")."
		LEFT JOIN tbl_classes c ON c.id = er.class
		LEFT JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination
		LEFT JOIN tbl_subjects s ON s.id = sc.subject
		WHERE er.class = ? AND er.term = ?
		ORDER BY er.student";
	$stmt = $conn->prepare($sql);
	$stmt->execute([$classId, $termId]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="results.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['student_id','student_name','class','subject','score']);
	foreach ($rows as $r) {
		$name = trim(($r['fname'] ?? '').' '.($r['lname'] ?? ''));
		fputcsv($out, [$r['student'], $name, $r['class_name'], $r['subject_name'], $r['score']]);
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Export failed: ".$e->getMessage()));
	header("location:../import_export");
}
