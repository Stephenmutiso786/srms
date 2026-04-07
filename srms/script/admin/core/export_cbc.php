<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('report.view', '../import_export');

$termId = (int)($_GET['term_id'] ?? 0);

if ($termId < 1) {
	$_SESSION['reply'] = array (array("danger", "Select term."));
	header("location:../import_export");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$sql = "SELECT c.student_id, s.fname, s.lname, c.class_id, cl.name AS class_name, c.learning_area, c.strand, c.level
		FROM tbl_cbc_assessments c
		LEFT JOIN tbl_students s ON ".($isPgsql ? "s.id::text = c.student_id::text" : "s.id = c.student_id")."
		LEFT JOIN tbl_classes cl ON cl.id = c.class_id
		WHERE c.term_id = ?
		ORDER BY c.student_id";
	$stmt = $conn->prepare($sql);
	$stmt->execute([$termId]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	header('Content-Type: text/csv');
	header('Content-Disposition: attachment; filename="cbc_assessments.csv"');
	$out = fopen('php://output', 'w');
	fputcsv($out, ['student_id','student_name','class','learning_area','strand','level']);
	foreach ($rows as $r) {
		$name = trim(($r['fname'] ?? '').' '.($r['lname'] ?? ''));
		fputcsv($out, [$r['student_id'], $name, $r['class_name'], $r['learning_area'], $r['strand'], $r['level']]);
	}
	fclose($out);
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Export failed: ".$e->getMessage()));
	header("location:../import_export");
}
