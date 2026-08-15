<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/phpexcel/SimpleXLSX.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$uploadCheck = app_validate_upload($_FILES['file'], ['xlsx', 'xls']);
if (!$uploadCheck['ok']) {
$_SESSION['reply'] = array (array("danger", $uploadCheck['message']));
header("location:../teachers");
exit;
}
$file = $_FILES['file']['tmp_name'];
$st_rec = 0;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_staff_password_policy_columns($conn);

if ( $xlsx = SimpleXLSX::parse($file) ) {
$headers = [];
foreach( $xlsx->rows() as $r ) {

if ($st_rec == 0) {
	$headers = array_map(function ($h) { return strtolower(trim((string)$h)); }, $r);
}else{

$cells = array_pad($r, 6, '');
$cellByHeader = function (array $keys, int $fallbackIndex, string $default = '') use ($headers, $cells) {
	foreach ($keys as $key) {
		$pos = array_search($key, $headers, true);
		if ($pos !== false && array_key_exists($pos, $cells)) {
			return (string)$cells[$pos];
		}
	}
	return (string)($cells[$fallbackIndex] ?? $default);
};
$importRow = [
	'fname' => ucfirst(trim($cellByHeader(['first_name', 'firstname', 'first name', 'fname'], 0))),
	'lname' => ucfirst(trim($cellByHeader(['last_name', 'lastname', 'last name', 'lname', 'surname'], 1))),
	'gender' => trim($cellByHeader(['gender', 'sex', 'gender_optional'], 2, 'Male')),
	'email' => trim($cellByHeader(['email', 'email_address', 'email_optional'], 3)),
	'password' => $cellByHeader(['password', 'password_optional'], 4),
	'status' => trim($cellByHeader(['status', 'status_optional'], 5, 'Active')),
];

$fname = $importRow['fname'];
$lname = $importRow['lname'];
$email = $importRow['email'];
$gender = $importRow['gender'];
if ($gender === '') {
	$gender = 'Male';
}
$role = '2';
$plainPassword = trim((string)$importRow['password']);
if ($plainPassword === '') {
	$plainPassword = getenv('DEFAULT_STAFF_PASSWORD') ?: 'Password123';
}
$pass = password_hash($plainPassword, PASSWORD_DEFAULT);
$status = $importRow['status'];
if ($status === '' || $status == "Active") {
$status = 1;
}else{
$status = 0;
}

if ($fname === '' || $lname === '') {
	$st_rec++;
	continue;
}
if ($email === '') {
	$email = strtolower(preg_replace('/[^a-z0-9]+/', '.', $fname.'.'.$lname.'.'.$st_rec)).'@teachers.local';
}

$stmt = $conn->prepare("SELECT 1 FROM tbl_staff WHERE email = ? UNION SELECT 1 FROM tbl_students WHERE email = ?");
$stmt->execute([$email, $email]);
$result = $stmt->fetchColumn();

if ($result) {

}else{

$stmt = app_column_exists($conn, 'tbl_staff', 'force_password_change')
? $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, force_password_change) VALUES (?,?,?,?,?,?,?,?)")
: $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?,?,?,?,?,?,?)");
$params = [$fname, $lname, $gender, $email, $pass, $role, $status];
if (app_column_exists($conn, 'tbl_staff', 'force_password_change')) { $params[] = 1; }
$stmt->execute($params);

}

}
$st_rec++;
}


$_SESSION['reply'] = array (array("success",'Data import completed'));
header("location:../teachers");

} else {
echo SimpleXLSX::parseError();
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
