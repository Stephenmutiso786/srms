<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
if (!isset($res) || $res !== "1" || !isset($level) || $level !== "2") { header("location:../../"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$uploadCheck = app_validate_upload($_FILES['file'], ['csv']);
if (!$uploadCheck['ok']) {
$_SESSION['reply'] = array (array("error", $uploadCheck['message']));
header("location:../import_results");
exit;
}
$file = $_FILES['file']['tmp_name'];
$file = fopen($file, "r");
$st_rec = 0;

$term = $_POST['term'];
$class = $_POST['class'];
$subject = $_POST['subject'];
$examId = (int)($_POST['exam'] ?? 0);
$hadExistingResults = false;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

if (app_results_locked($conn, (int)$class, (int)$term)) {
	$_SESSION['reply'] = array (array("error","Results are locked for this class/term. Contact admin."));
	header("location:../import_results");
	exit;
}

if ($useExamId && $examId < 1) {
	$_SESSION['reply'] = array (array("error","Select a valid exam before importing results."));
	header("location:../import_results");
	exit;
}

while (($r = fgetcsv($file, 10000, ",")) !== FALSE) {

if ($st_rec == 0) {

}else{

$cells = array_pad($r, 3, '');
$csvRow = [
	'reg_no' => trim((string)$cells[0]),
	'student_name' => trim((string)$cells[1]),
	'score' => trim((string)$cells[2]),
];

$reg_no = $csvRow['reg_no'];
$score = $csvRow['score'];

if ($reg_no === '' || $score === '') {
	$st_rec++;
	continue;
}

if ($useExamId) {
	$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ? LIMIT 1");
	$stmt->execute([$reg_no, $class, $subject, $term, $examId]);
	$existingId = $stmt->fetchColumn();
	if (!$existingId) {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
		$stmt->execute([$reg_no, $class, $subject, $term, $score, $examId]);
	} else {
		$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
		$stmt->execute([$score, $existingId]);
		$hadExistingResults = true;
	}
} else {
	$stmt = $conn->prepare("SELECT 1 FROM tbl_exam_results WHERE student = ? AND class=? AND subject_combination=? AND term = ? LIMIT 1");
	$stmt->execute([$reg_no, $class, $subject, $term]);
	$exists = (bool)$stmt->fetchColumn();

	if (!$exists) {
		$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
		$stmt->execute([$reg_no, $class, $subject, $term, $score]);
	} else {
		$hadExistingResults = true;
	}
}


}
$st_rec++;
}


if (!$hadExistingResults) {
$_SESSION['reply'] = array (array("success",'Results import completed'));
header("location:../import_results");
}else{
$_SESSION['reply'] = array (array("success",'Results import completed, previous results were not changed'));
header("location:../import_results");
}


}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}




}else{
header("location:../");
}
?>
