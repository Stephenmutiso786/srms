<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system");
$stmt->execute();
$grades = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_division_system");
$stmt->execute();
$divisions = $stmt->fetchAll();


}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}


function get_points($marks) {
$marks_array = $marks;
$grades = $GLOBALS['divisions'];
$points = 0;
foreach ($marks_array as $mark) {

foreach ($grades as $gradee) {

$min_mark = $gradee[1];
$max_mark = $gradee[2];
if ($mark >= $min_mark && $mark <= $max_mark) {
$points = $points + $gradee[5];
}
}
}
return $points;
}

function get_division($marks) {
$the_points = get_points($marks);
$divisions = $GLOBALS['divisions'];
$division = '0';
foreach ($divisions as $divisions_) {
$min_point = intval($divisions_[3]);
$max_point = intval($divisions_[4]);
if ($the_points >= $min_point && $the_points <= $max_point) {
$division = $divisions_[0];
}
}
return $division;
}
?>
