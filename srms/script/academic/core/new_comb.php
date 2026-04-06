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
//$matches = preg_replace('/[A-Z0-9]/', '?', $matches);
$arr = array($subject);



foreach ($_POST['class'] as $value) {
array_push($arr, $value);
}

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// $stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations WHERE subject = ? AND class IN ($matches)");
$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations WHERE subject = ?");
$stmt->execute([$subject]);
$result = $stmt->fetchAll();

$hasCombination = false;

foreach($result as $res){
    $hasCombination = count(array_intersect($_POST['class'], app_unserialize($res["class"]))) > 0;
    if($hasCombination)
    break;
}

if (!$hasCombination) {
$stmt = $conn->prepare("INSERT INTO tbl_subject_combinations (class, subject, teacher, reg_date) VALUES (?,?,?,?)");
$stmt->execute([$class, $subject, $teacher, $reg_date]);

$_SESSION['reply'] = array (array("success",'Subject combination created successfully'));
header("location:../combinations");

}else{

$_SESSION['reply'] = array (array("danger",'Subject combination is already created'));
header("location:../combinations");

}


}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}


}else{
header("location:../");
}
?>
