<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../parents");
	exit;
}

$fname = ucfirst(trim((string)($_POST['fname'] ?? '')));
$lname = ucfirst(trim((string)($_POST['lname'] ?? '')));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$status = (int)($_POST['status'] ?? 1);

if ($fname === '' || $lname === '' || $email === '' || $password === '') {
	$_SESSION['reply'] = array(array("error", "All required fields must be filled."));
	header("location:../parents");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parents')) {
		$_SESSION['reply'] = array(array("error", "Parent tables are not installed."));
		header("location:../parents");
		exit;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_parents WHERE email = ? LIMIT 1");
	$stmt->execute([$email]);
	if ($stmt->fetchColumn()) {
		$_SESSION['reply'] = array(array("error", "Parent email already exists."));
		header("location:../parents");
		exit;
	}

	$hash = password_hash($password, PASSWORD_DEFAULT);
	$stmt = $conn->prepare("INSERT INTO tbl_parents (fname, lname, phone, email, password, status) VALUES (?,?,?,?,?,?)");
	$stmt->execute([$fname, $lname, $phone, $email, $hash, $status]);

	app_audit_log($conn, 'staff', (string)$account_id, 'parent.create', 'parent', (string)$conn->lastInsertId());

	$_SESSION['reply'] = array(array("success", "Parent created."));
	header("location:../parents");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../parents");
	exit;
}

