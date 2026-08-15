<?php
chdir('../../../script');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../../');
	exit;
}

$ownerId = (int)($_POST['owner_id'] ?? 0);
$fname = trim((string)($_POST['fname'] ?? ''));
$lname = trim((string)($_POST['lname'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$status = (int)($_POST['status'] ?? 1);

if ($fname === '' || $lname === '' || $email === '') {
	$_SESSION['reply'] = [['danger', 'Owner name and email are required.']];
	header('location:../owner_account.php');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_school_subscription_schema($conn);
$stmt = $conn->prepare('SELECT id FROM tbl_staff WHERE LOWER(email) = ? AND level = 9 LIMIT 1');
$stmt->execute([strtolower($email)]);
$existingByEmail = (int)$stmt->fetchColumn();
if ($ownerId < 1 && $existingByEmail > 0) {
	$ownerId = $existingByEmail;
}
if ($ownerId > 0) {
	if ($password !== '') {
		$hash = password_hash($password, PASSWORD_DEFAULT);
		$stmt = $conn->prepare('UPDATE tbl_staff SET fname = ?, lname = ?, email = ?, password = ?, status = ? WHERE id = ? AND level = 9');
		$stmt->execute([$fname, $lname, $email, $hash, $status, $ownerId]);
	} else {
		$stmt = $conn->prepare('UPDATE tbl_staff SET fname = ?, lname = ?, email = ?, status = ? WHERE id = ? AND level = 9');
		$stmt->execute([$fname, $lname, $email, $status, $ownerId]);
	}
	$stmt = $conn->prepare('UPDATE tbl_staff SET email = ? WHERE id <> ? AND level = 9 AND LOWER(email) = ?');
	$stmt->execute([$email, $ownerId, strtolower($email)]);
	$_SESSION['reply'] = [['success', 'Owner account updated successfully.']];
} else {
	if ($password === '') {
		$_SESSION['reply'] = [['danger', 'Password is required for a new owner account.']];
		header('location:../owner_account.php');
		exit;
	}
	$hash = password_hash($password, PASSWORD_DEFAULT);
	$stmt = $conn->prepare('INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?, ?, ?, ?, ?, 9, ?)');
	$stmt->execute([$fname, $lname, 'Male', $email, $hash, $status]);
	$_SESSION['reply'] = [['success', 'Owner account created successfully.']];
}
header('location:../index.php');
