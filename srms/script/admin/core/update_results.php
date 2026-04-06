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


try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (app_results_locked($conn, (int)$class, (int)$term)) {
	$_SESSION['reply'] = array (array("error","Results are locked for this class/term."));
	header("location:../single_results");
	exit;
}

foreach ($_POST as $key => $value) {
if ($key !== "student" AND $key !== "term" AND $key !== "class") {

$reg_no = $std;
$score = $value;
$subject = $key;

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE student = ? AND class=? AND subject_combination=? AND term = ?");
$stmt->execute([$reg_no, $class, $subject, $term]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
$stmt->execute([$reg_no, $class, $subject, $term, $score]);
}else{
$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE student = ? AND class=? AND subject_combination=? AND term = ?");
$stmt->execute([$score, $reg_no, $class, $subject, $term]);
}

}
}

$_SESSION['reply'] = array (array("success",'Results updated successfully'));
header("location:../single_results");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
