<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$class = $_POST['class'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
$stmt->execute([$class]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);


$fileName = (string)($result[0]['name'] ?? 'students').'.csv';
$_SESSION['export_file'] = $fileName;

if (file_exists('import_sheets/'.$fileName)) {
unlink('import_sheets/'.$fileName);
}

$fp = fopen('import_sheets/'.$fileName, 'w');

$rowData = array('REGISTRATION NUMBER', 'STUDENT NAME', 'SCORE');
fputcsv($fp, $rowData);


$stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ?");
$stmt->execute([$class]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $row)
{

$rowData = array(
	(string)($row['id'] ?? ''),
	trim((string)($row['fname'] ?? '') . ' ' . (string)($row['mname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
	"0"
);
fputcsv($fp, $rowData);

}



header("location:../export_students");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
header("location:../");
}
?>
