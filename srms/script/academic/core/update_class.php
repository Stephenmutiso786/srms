<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$name = ucfirst($_POST['name']);
$id = $_POST['id'];
$gradingSystemId = (int)($_POST['grading_system_id'] ?? 0);

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_class_cbe_level_schema($conn);
app_ensure_exam_grading_schema($conn);
if ($gradingSystemId < 1) {
	$gradingSystemId = (int)(app_class_recommended_grading_system_id($conn, $name) ?? 0);
}

$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE name = ? AND id != ?");
$stmt->execute([$name, $id]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("UPDATE tbl_classes SET name=?, grading_system_id=? WHERE id=?");
$stmt->execute([$name, $gradingSystemId > 0 ? $gradingSystemId : null, $id]);
app_save_class_grading_system($conn, (int)$id, $gradingSystemId > 0 ? $gradingSystemId : null);

$_SESSION['reply'] = array (array("success",'Class updated successfully'));
header("location:../classes");

}else{

$_SESSION['reply'] = array (array("danger",'Class is already registered'));
header("location:../classes");

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
