<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
if (!isset($res) || $res !== "1" || !isset($level) || $level !== "1") { header("location:../../"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$std = $_POST['student'];
$term = $_POST['term'];
$class = $_POST['class'];
$examId = (int)($_POST['exam'] ?? 0);


try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

if (app_results_locked($conn, (int)$class, (int)$term, $examId)) {
	$_SESSION['reply'] = array (array("error","Results are locked for this class/term."));
	header("location:../single_results");
	exit;
}

if ($useExamId && $examId < 1) {
	$_SESSION['reply'] = array (array("error","Select a valid exam before saving results."));
	header("location:../single_results");
	exit;
}

if ($useExamId) {
	$stmt = $conn->prepare("SELECT COALESCE(status, 'draft') AS status FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$examId, $class, $term]);
	$examStatus = strtolower(trim((string)$stmt->fetchColumn()));
	if ($examStatus === '') {
		$_SESSION['reply'] = array (array("error","Selected exam was not found for this class and term."));
		header("location:../single_results");
		exit;
	}
	if (in_array($examStatus, ['finalized', 'published'], true)) {
		$_SESSION['reply'] = array (array("error","Published or finalized exams cannot be edited."));
		header("location:../single_results");
		exit;
	}
}

$lockedStatuses = ['submitted', 'reviewed', 'finalized'];

foreach ($_POST as $key => $value) {
if ($key !== "student" AND $key !== "term" AND $key !== "class" AND $key !== "exam") {

$reg_no = $std;
$score = $value;
$subject = $key;

	if ($useExamId) {
		$submissionStatus = app_exam_submission_status($conn, $examId, (int)$subject);
		if (in_array($submissionStatus, $lockedStatuses, true)) {
			$_SESSION['reply'] = array (array("error","Cannot edit submitted marks. This subject mark sheet is already " . $submissionStatus . "."));
			header("location:../single_results");
			exit;
		}
	}

if ($useExamId) {
	$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ? LIMIT 1");
	$stmt->execute([$reg_no, $class, $subject, $term, $examId]);
	$existingResultId = $stmt->fetchColumn();
	if (!$existingResultId) {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
		$stmt->execute([$reg_no, $class, $subject, $term, $score, $examId]);
	}else{
		$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ?");
		$stmt->execute([$score, $reg_no, $class, $subject, $term, $examId]);
	}
}else{
	$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
	$stmt->execute([$reg_no, $class, $subject, $term]);
	$existingResultId = $stmt->fetchColumn();
	if (!$existingResultId) {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
		$stmt->execute([$reg_no, $class, $subject, $term, $score]);
	}else{
		$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");
		$stmt->execute([$score, $reg_no, $class, $subject, $term]);
	}
}

}
}

$_SESSION['reply'] = array (array("success",'Results updated successfully'));
header("location:../single_results");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}


}else{
header("location:../");
}
?>
