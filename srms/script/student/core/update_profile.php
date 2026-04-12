<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../');
	exit;
}

$fname = ucfirst(trim((string)($_POST['fname'] ?? '')));
$mname = ucfirst(trim((string)($_POST['mname'] ?? '')));
$lname = ucfirst(trim((string)($_POST['lname'] ?? '')));
$email = trim((string)($_POST['email'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$schoolId = trim((string)($_POST['school_id'] ?? ''));
$oldPhoto = trim((string)($_POST['old_photo'] ?? 'DEFAULT'));

if ($fname === '' || $lname === '' || $email === '' || $gender === '') {
	$_SESSION['reply'] = array(array('error', 'Please complete all required profile fields.'));
	header('location:../profile');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$stmt = $isPgsql
		? $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? AND id::text != ? UNION SELECT email FROM tbl_students WHERE email = ? AND id::text != ?")
		: $conn->prepare("SELECT email FROM tbl_staff WHERE email = ? AND id != ? UNION SELECT email FROM tbl_students WHERE email = ? AND id != ?");
	$stmt->execute([$email, (string)$account_id, $email, (string)$account_id]);
	if ($stmt->fetchColumn()) {
		$_SESSION['reply'] = array(array('error', 'Email is already in use.'));
		header('location:../profile');
		exit;
	}

	$hasSchoolIdColumn = app_column_exists($conn, 'tbl_students', 'school_id');
	if ($hasSchoolIdColumn && $schoolId !== '') {
		$schoolStmt = (defined('DBDriver') && DBDriver === 'pgsql')
			? $conn->prepare('SELECT school_id FROM tbl_students WHERE school_id = ? AND id::text != ? LIMIT 1')
			: $conn->prepare('SELECT school_id FROM tbl_students WHERE school_id = ? AND id != ? LIMIT 1');
		$schoolStmt->execute([$schoolId, (string)$account_id]);
		if ($schoolStmt->fetchColumn()) {
			$_SESSION['reply'] = array(array('error', 'School ID is already assigned to another student.'));
			header('location:../profile');
			exit;
		}
	}

	$photo = $oldPhoto !== '' ? $oldPhoto : 'DEFAULT';
	if (!empty($_FILES['image']['name'])) {
		$uploadCheck = app_validate_upload($_FILES['image'], ['jpg', 'jpeg', 'png']);
		if (!$uploadCheck['ok']) {
			$_SESSION['reply'] = array(array('error', $uploadCheck['message']));
			header('location:../profile');
			exit;
		}

		$targetDir = 'images/students/';
		$imageName = basename((string)$_FILES['image']['name']);
		$imageType = strtolower(pathinfo($imageName, PATHINFO_EXTENSION));
		$newFile = 'avatar_' . time() . '.' . $imageType;
		$targetPath = $targetDir . $newFile;

		if (!in_array($imageType, ['jpg', 'jpeg', 'png'], true)) {
			$_SESSION['reply'] = array(array('error', 'Only JPG, JPEG, and PNG files are allowed.'));
			header('location:../profile');
			exit;
		}

		if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
			$_SESSION['reply'] = array(array('error', 'Failed to upload profile photo.'));
			header('location:../profile');
			exit;
		}

		if ($oldPhoto !== '' && $oldPhoto !== 'DEFAULT' && is_file($targetDir . $oldPhoto)) {
			@unlink($targetDir . $oldPhoto);
		}
		$photo = $newFile;
	}

	if ($hasSchoolIdColumn) {
		$stmt = $conn->prepare('UPDATE tbl_students SET fname = ?, mname = ?, lname = ?, gender = ?, email = ?, school_id = ?, display_image = ? WHERE id = ?');
		$stmt->execute([$fname, $mname, $lname, $gender, $email, $schoolId, $photo, $account_id]);
	} else {
		$stmt = $conn->prepare('UPDATE tbl_students SET fname = ?, mname = ?, lname = ?, gender = ?, email = ?, display_image = ? WHERE id = ?');
		$stmt->execute([$fname, $mname, $lname, $gender, $email, $photo, $account_id]);
	}

	$_SESSION['reply'] = array(array('success', 'Profile updated successfully.'));
	header('location:../profile');
	exit;
} catch (Throwable $e) {
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Unable to update profile right now.'));
	header('location:../profile');
	exit;
}