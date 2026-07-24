<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
if (!isset($res) || $res !== "1" || !isset($level) || $level !== "1") { header("location:../../"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
$src = $_GET['src'];
$std = $_GET['std'];
$class = $_GET['class'];
$term = $_GET['term'];
$examId = (int)($_GET['exam'] ?? 0);

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

if (app_results_locked($conn, (int)$class, (int)$term)) {
	$_SESSION['reply'] = array (array("error",'Results are locked for this class/term.'));
	header("location:../$src");
	exit;
}

if ($useExamId && $examId > 0 && app_table_exists($conn, 'tbl_exam_mark_submissions')) {
	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE exam_id = ? AND class_id = ? AND status IN ('submitted','reviewed','finalized')");
	$stmt->execute([$examId, $class]);
	if ((int)$stmt->fetchColumn() > 0) {
		$_SESSION['reply'] = array (array("error",'Cannot delete results after marks have been submitted for review.'));
		header("location:../$src");
		exit;
	}
}

if ($std === 'all') {
	if ($useExamId && $examId > 0) {
		$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE class = ? AND term = ? AND exam_id = ?");
		$stmt->execute([$class, $term, $examId]);
	} else {
		$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE class = ? AND term = ?");
		$stmt->execute([$class, $term]);
	}
} else {
	if ($useExamId && $examId > 0) {
		$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE student = ? AND class = ? AND term = ? AND exam_id = ?");
		$stmt->execute([$std, $class, $term, $examId]);
	} else {
		$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE student = ? AND class = ? AND term = ?");
		$stmt->execute([$std, $class, $term]);
	}
}

$_SESSION['reply'] = array (array("success",'Examination result deleted'));
header("location:../$src");

}catch(PDOException $e)
{
app_reply_redirect('danger', 'Unable to delete results right now.', "../$src");
}


}else{
header("location:../");
}
?>
