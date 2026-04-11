<?php
$my_subject = 0;
$my_class = 0;
$my_students = 0;

$academic_terms = 0;
$teachers = 0;
$students = 0;
$subjects = 0;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$usable_classes = array();
$my_very_classes = array();

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$terms = $stmt->fetchAll();
$academic_terms = count($terms);

$stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE level = '2'");
$stmt->execute();
$tch = $stmt->fetchAll();
$teachers= count($tch);

$stmt = $conn->prepare("SELECT * FROM tbl_subjects");
$stmt->execute();
$sbj = $stmt->fetchAll();
$subjects = count($sbj);


$stmt = $conn->prepare("SELECT class FROM tbl_students GROUP BY class");
$stmt->execute();
$_classes = $stmt->fetchAll();

foreach ($_classes as $key => $value) {
array_push($usable_classes, $value[0]);
}

$stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
$class_list = app_unserialize($row[0]);

foreach ($class_list as $key => $value) {
if (in_array($value, $usable_classes))
{
$my_class++;
array_push($my_very_classes, $value);
}

}
if (in_array($value, $usable_classes))
{
$my_subject++;
}
}


$matches = implode(',', $my_very_classes);
$matches = preg_replace('/[A-Z0-9]/', '?', $matches);

$stmt = $conn->prepare("SELECT class FROM tbl_students WHERE class IN ($matches)");
$stmt->execute($my_very_classes);
$result = $stmt->fetchAll();

$my_students = count($result);
}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
