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

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (app_results_locked($conn, (int)$class, (int)$term)) {
	$_SESSION['reply'] = array (array("error","Results are locked for this class/term. Contact admin."));
	header("location:../import_results");
	exit;
}

while (($r = fgetcsv($file, 10000, ",")) !== FALSE) {

if ($st_rec == 0) {

}else{

$reg_no = $r[0];
$score = $r[2];

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE student = ? AND class=? AND subject_combination=? AND term = ?");
$stmt->execute([$reg_no, $class, $subject, $term]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
$stmt->execute([$reg_no, $class, $subject, $term, $score]);
}


}
$st_rec++;
}


if (count($result) < 1) {
$_SESSION['reply'] = array (array("success",'Results import completed'));
header("location:../import_results");
}else{
$_SESSION['reply'] = array (array("success",'Results import completed, previous results were not changed'));
header("location:../import_results");
}


}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}




}else{
header("location:../");
}
?>
