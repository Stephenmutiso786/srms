<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

$reg_no = $_POST['id'];
$fname = ucfirst($_POST['fname']);
$mname = ucfirst($_POST['mname']);
$lname = ucfirst($_POST['lname']);
$email = $_POST['email'];
$gender = $_POST['gender'];
$class = $_POST['class'];
$role = '3';
$status = '1';
$photo = serialize($_FILES["image"]);



try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (trim((string)$email) === '') {
	$email = app_generate_student_login_email($conn, (string)$fname, (string)$lname, (string)$class);
}
$beforeSnapshot = app_student_archive_payload($conn, (string)$reg_no);

$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
$stmt = $isPgsql
? $conn->prepare("SELECT id::text AS id, email FROM tbl_staff WHERE email = ? AND id::text != ?
  UNION SELECT id::text AS id, email FROM tbl_students WHERE email = ? AND id::text != ?")
: $conn->prepare("SELECT id, email FROM tbl_staff WHERE email = ? AND id != ?
  UNION SELECT id, email FROM tbl_students WHERE email = ? AND id != ?");
$stmt->execute([$email, $reg_no, $email, $reg_no]);
$result = $stmt->fetchAll();

if (count($result) > 0) {
$_SESSION['reply'] = array (array("error",'Email is used'));
header("location:../students");
}else{


if($_FILES['image']['name'] == "")  {
$img = $_POST['old_photo'];
}else{
	$uploadCheck = app_validate_upload($_FILES['image'], ['jpg', 'jpeg', 'png']);
	if (!$uploadCheck['ok']) {
		$_SESSION['reply'] = array (array("error", $uploadCheck['message']));
		header("location:../students");
		exit;
	}

$target_dir = "images/students/";
$img_ = unserialize($photo);
$target_file = $target_dir . basename($img_["name"]);
$imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
$destn_file = 'avator_'.time().'.'.$imageFileType.'';
$destn_upload = $target_dir . $destn_file;

if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg") {
$img = 'DEFAULT';
}else{
if (move_uploaded_file($img_["tmp_name"], $destn_upload)) {
$img = $destn_file;
unlink('images/students/'.$_POST['old_photo'].'');
}else{
$img = 'DEFAULT';
}
}

}

$stmt = $conn->prepare("UPDATE tbl_students SET fname=?, mname=?, lname=?, gender=?, email=?, class=?, display_image=? WHERE id = ?");
$stmt->execute([$fname, $mname, $lname, $gender, $email, $class, $img, $reg_no]);

$classBand = app_class_band_by_id($conn, (int)$class);
if ($classBand === 'junior_secondary') {
	app_save_student_subject_choices(
		$conn,
		(string)$reg_no,
		(int)($_POST['language_subject_id'] ?? 0),
		(int)($_POST['religion_subject_id'] ?? 0),
		(array)($_POST['optional_subject_ids'] ?? []),
		(int)$account_id
	);
} else {
	app_clear_student_subject_choices($conn, (string)$reg_no);
}

$afterSnapshot = app_student_archive_payload($conn, (string)$reg_no);
app_data_camp_store_event($conn, [
	'module_key' => 'students',
	'record_type' => 'student_updated',
	'entity_table' => 'tbl_students',
	'entity_id' => (string)$reg_no,
	'title' => trim($fname . ' ' . $mname . ' ' . $lname) ?: ('Student ' . (string)$reg_no),
	'description' => 'Student profile snapshot retained before and after update',
	'class_id' => (int)$class > 0 ? (int)$class : null,
	'student_id' => (string)$reg_no,
	'owner_portal' => 'admin,academic',
	'mime_type' => 'application/json',
	'status' => 'retained',
	'payload_json' => [
		'before' => $beforeSnapshot,
		'after' => $afterSnapshot,
	],
	'created_by' => (int)($account_id ?? 0),
]);

$_SESSION['reply'] = array (array("success",'Student updated successfully'));
header("location:../students");
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
