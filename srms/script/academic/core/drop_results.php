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

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (app_results_locked($conn, (int)$class, (int)$term)) {
	$_SESSION['reply'] = array (array("error",'Results are locked for this class/term.'));
	header("location:../$src");
	exit;
}

if ($std === 'all') {
	$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE class = ? AND term = ?");
	$stmt->execute([$class, $term]);
} else {
	$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE student = ? AND class = ? AND term = ?");
	$stmt->execute([$std, $class, $term]);
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
