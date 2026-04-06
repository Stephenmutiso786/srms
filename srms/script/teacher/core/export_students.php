<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$class = $_POST['class'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
$stmt->execute([$class]);
$result = $stmt->fetchAll();


$fileName = $result[0][1].'.csv';
$_SESSION['export_file'] = $fileName;

if (file_exists('import_sheets/'.$fileName)) {
unlink('import_sheets/'.$fileName);
}

$fp = fopen('import_sheets/'.$fileName, 'w');

$rowData = array('REGISTRATION NUMBER', 'STUDENT NAME', 'SCORE');
fputcsv($fp, $rowData);


$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class = ?");
$stmt->execute([$class]);
$result = $stmt->fetchAll();

foreach($result as $row)
{

$rowData = array($row[0], ''.$row[1].' '.$row[2].' '.$row[3].'', "0");
fputcsv($fp, $rowData);

}



header("location:../export_students");

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

}else{
header("location:../");
}
?>
