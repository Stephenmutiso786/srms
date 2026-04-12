<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

if($_FILES['company_logo']['name'] == "")  {
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id FROM tbl_school LIMIT 1");
$stmt->execute();
$existingId = $stmt->fetchColumn();

if ($existingId) {
	$stmt = $conn->prepare("UPDATE tbl_school SET name = ?");
	$stmt->execute([$_POST['name']]);
} else {
	$logo = $_POST['old_logo'] ?? 'school_logo1711003619.png';
	$stmt = $conn->prepare("INSERT INTO tbl_school (name, logo, result_system, allow_results) VALUES (?,?,?,?)");
	$stmt->execute([$_POST['name'], $logo, 1, 1]);
}


$_SESSION['reply'] = array (array("success","System settings updated"));
header("location:../system");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
}else{
	$uploadCheck = app_validate_upload($_FILES['company_logo'], ['jpg', 'jpeg', 'png']);
	if (!$uploadCheck['ok']) {
		$_SESSION['reply'] = array (array("error", $uploadCheck['message']));
		header("location:../system");
		exit;
	}

$target_dir = "images/logo/";
$target_file = $target_dir . basename($_FILES["company_logo"]["name"]);
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
$destn_file = 'school_logo'.time().'.'.$imageFileType.'';
$destn_upload = $target_dir . $destn_file;
$unlink = 'images/logo/'.$_POST['old_logo'].'';

if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
$_SESSION['reply'] = array (array("error","Only JPG, PNG and JPEG files are allowed"));
header("location:../system");
}else{

if (move_uploaded_file($_FILES["company_logo"]["tmp_name"], $destn_upload)) {
if (is_file($unlink)) {
	@unlink($unlink);
}

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id FROM tbl_school LIMIT 1");
$stmt->execute();
$existingId = $stmt->fetchColumn();

if ($existingId) {
	$stmt = $conn->prepare("UPDATE tbl_school SET name = ?, logo = ?");
	$stmt->execute([$_POST['name'], $destn_file]);
} else {
	$stmt = $conn->prepare("INSERT INTO tbl_school (name, logo, result_system, allow_results) VALUES (?,?,?,?)");
	$stmt->execute([$_POST['name'], $destn_file, 1, 1]);
}

$logoBytes = @file_get_contents($destn_upload);
if (is_string($logoBytes) && $logoBytes !== '') {
	$logoB64 = base64_encode($logoBytes);
	app_setting_set($conn, 'school_logo_blob_b64', $logoB64, null);
	app_setting_set($conn, 'school_logo_blob_ext', $imageFileType, null);
	app_setting_set($conn, 'school_logo_blob_name', $destn_file, null);
}

$_SESSION['reply'] = array (array("success","System settings updated"));
header("location:../system");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
$_SESSION['reply'] = array (array("danger","Could not upload file"));
header("location:../system");
}
}

}

}else{
header("location:../");
}
?>
