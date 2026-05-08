<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$name = ucfirst(trim((string)($_POST['name'] ?? '')));
$academicYear = trim((string)($_POST['academic_year'] ?? ''));
$status = (string)($_POST['status'] ?? '0');
$id = (int)($_POST['id'] ?? 0);

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_terms_academic_year_schema($conn);

$termName = app_term_base_name($name);
if ($termName === '') {
	$termName = trim($name);
}
if ($termName === '' || $academicYear === '') {
	$_SESSION['reply'] = array (array("danger",'Term name and academic year are required'));
	header("location:../terms");
	exit;
}
$storedName = app_term_compose_name($termName, $academicYear);

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE name = ? AND id != ?");
$stmt->execute([$storedName, $id]);
$result = $stmt->fetchAll();

if (count($result) < 1) {
$stmt = $conn->prepare("UPDATE tbl_terms SET name=?, academic_year=?, status=? WHERE id = ?");
$stmt->execute([$storedName, $academicYear, $status, $id]);

$_SESSION['reply'] = array (array("success",'Academic term updated successfully'));
header("location:../terms");

}else{

$_SESSION['reply'] = array (array("danger",'Academic term is already registered'));
header("location:../terms");

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
