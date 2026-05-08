<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$subject = $_POST['subject'];
$class = serialize($_POST['class']);
$teacher = $_POST['teacher'];
$reg_date = date('Y-m-d G:i:s');
$matches = implode(',', $_POST['class']);
$matches = preg_replace('/[A-Z0-9]/', '?', $matches);
$arr = array($subject);
$id = $_POST['id'];

foreach ($_POST['class'] as $value) {
array_push($arr, $value);
}

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id FROM tbl_staff WHERE id = ? AND COALESCE(status, 1) = 1 AND COALESCE(level, '') IN ('0', '1', '2') LIMIT 1");
$stmt->execute([$teacher]);
if (!$stmt->fetchColumn()) {
throw new RuntimeException('Select a valid instructional staff account for this subject combination.');
}

$stmt = $conn->prepare("UPDATE tbl_subject_combinations SET class=?, subject=?, teacher=? WHERE id = ?");
$stmt->execute([$class, $subject, $teacher, $id]);

$_SESSION['reply'] = array (array("success",'Subject combination updated successfully'));
header("location:../combinations");


}catch(Throwable $e)
{
error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
$_SESSION['reply'] = array (array("danger", $e->getMessage() !== '' ? $e->getMessage() : 'Failed to update subject combination'));
header("location:../combinations");
}


}else{
header("location:../");
}
?>
