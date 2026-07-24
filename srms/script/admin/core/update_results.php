<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") { header("location:../../"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$std = $_POST['student'];
$term = $_POST['term'];
$class = $_POST['class'];
$examId = (int)($_POST['exam'] ?? 0);
$editModeSubject = (int)($_POST['edit_mode'] ?? 0);


try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

if ($useExamId && $examId < 1) {
	$_SESSION['reply'] = array (array("error","Select a valid exam before saving results."));
	header("location:../single_results");
	exit;
}

if ($useExamId && $examId > 0 && app_table_exists($conn, 'tbl_exams')) {
	$stmt = $conn->prepare("SELECT COALESCE(status, 'draft') AS status FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$examStatus = strtolower(trim((string)$stmt->fetchColumn()));
	if ($examStatus === 'published') {
		$_SESSION['reply'] = array (array("error","Published exams are view-only. Only finalized exams can be corrected here."));
		header("location:../single_results");
		exit;
	}
}

if ($editModeSubject < 1) {
	$_SESSION['reply'] = array (array("error","Choose one subject to edit before saving."));
	header("location:../single_results");
	exit;
}

foreach ($_POST as $key => $value) {
if ($key !== "student" AND $key !== "term" AND $key !== "class" AND $key !== "exam" AND $key !== "edit_mode") {

$reg_no = $std;
$score = is_numeric($value) ? (float)$value : $value;
$subject = $key;

if ((int)$subject !== $editModeSubject) {
	continue;
}

if ($useExamId) {
	$stmt = $conn->prepare("SELECT id, score FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ? LIMIT 1");
	$stmt->execute([$reg_no, $class, $subject, $term, $examId]);
	$existingResult = $stmt->fetch(PDO::FETCH_ASSOC);
	$existingResultId = (int)($existingResult['id'] ?? 0);
	$previousScore = $existingResult['score'] ?? null;
	if ($existingResultId < 1) {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
		$stmt->execute([$reg_no, $class, $subject, $term, $score, $examId]);
		app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.admin_edit', 'exam_result', (string)$conn->lastInsertId(), [
			'student' => (string)$reg_no,
			'class' => (string)$class,
			'term' => (string)$term,
			'exam_id' => (string)$examId,
			'subject_combination' => (string)$subject,
			'previous_score' => null,
			'new_score' => $score,
			'source' => 'admin.single_results',
		]);
	} else {
		if ((string)$previousScore === (string)$score) {
			$_SESSION['reply'] = array (array("info",'No mark change was made.'));
			header("location:../single_results");
			exit;
		}
		$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ?");
		$stmt->execute([$score, $reg_no, $class, $subject, $term, $examId]);
		app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.admin_edit', 'exam_result', (string)$existingResultId, [
			'student' => (string)$reg_no,
			'class' => (string)$class,
			'term' => (string)$term,
			'exam_id' => (string)$examId,
			'subject_combination' => (string)$subject,
			'previous_score' => $previousScore,
			'new_score' => $score,
			'source' => 'admin.single_results',
		]);
	}
}else{
	$stmt = $conn->prepare("SELECT id, score FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
	$stmt->execute([$reg_no, $class, $subject, $term]);
	$existingResult = $stmt->fetch(PDO::FETCH_ASSOC);
	$existingResultId = (int)($existingResult['id'] ?? 0);
	$previousScore = $existingResult['score'] ?? null;
	if ($existingResultId < 1) {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
		$stmt->execute([$reg_no, $class, $subject, $term, $score]);
		app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.admin_edit', 'exam_result', (string)$conn->lastInsertId(), [
			'student' => (string)$reg_no,
			'class' => (string)$class,
			'term' => (string)$term,
			'subject_combination' => (string)$subject,
			'previous_score' => null,
			'new_score' => $score,
			'source' => 'admin.single_results',
		]);
	} else {
		if ((string)$previousScore === (string)$score) {
			$_SESSION['reply'] = array (array("info",'No mark change was made.'));
			header("location:../single_results");
			exit;
		}
		$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class = ? AND subject_combination = ? AND term = ?");
		$stmt->execute([$score, $reg_no, $class, $subject, $term]);
		app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.admin_edit', 'exam_result', (string)$existingResultId, [
			'student' => (string)$reg_no,
			'class' => (string)$class,
			'term' => (string)$term,
			'subject_combination' => (string)$subject,
			'previous_score' => $previousScore,
			'new_score' => $score,
			'source' => 'admin.single_results',
		]);
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
