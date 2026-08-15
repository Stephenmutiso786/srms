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

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['backup_file']['tmp_name'])) {
	$_SESSION['reply'] = [['danger', 'Select a backup file first.']];
	header('location:../backup_restore.php');
	exit;
}

$raw = file_get_contents($_FILES['backup_file']['tmp_name']);
$data = json_decode((string)$raw, true);
if (!is_array($data)) {
	$_SESSION['reply'] = [['danger', 'Invalid backup file.']];
	header('location:../backup_restore.php');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$conn->beginTransaction();
try {
	foreach ((array)($data['schools'] ?? []) as $school) {
		$name = trim((string)($school['name'] ?? ''));
		if ($name === '') {
			continue;
		}
		$schoolId = (int)($school['id'] ?? 0);
		$logo = trim((string)($school['logo'] ?? 'school_logo.png'));
		$resultSystem = (int)($school['result_system'] ?? 1);
		$allowResults = (int)($school['allow_results'] ?? 1);
		$stmt = $conn->prepare('SELECT id FROM tbl_school WHERE id = ? LIMIT 1');
		$stmt->execute([$schoolId]);
		if ($stmt->fetchColumn()) {
			$upd = $conn->prepare('UPDATE tbl_school SET name = ?, logo = ?, result_system = ?, allow_results = ? WHERE id = ?');
			$upd->execute([$name, $logo, $resultSystem, $allowResults, $schoolId]);
		} else {
			$ins = $conn->prepare('INSERT INTO tbl_school (id, name, logo, result_system, allow_results) VALUES (?, ?, ?, ?, ?)');
			$ins->execute([$schoolId, $name, $logo, $resultSystem, $allowResults]);
		}
	}

	foreach ((array)($data['owners'] ?? []) as $owner) {
		$email = trim((string)($owner['email'] ?? ''));
		if ($email === '') {
			continue;
		}
		$ownerId = (int)($owner['id'] ?? 0);
		$fname = trim((string)($owner['fname'] ?? 'Super'));
		$lname = trim((string)($owner['lname'] ?? 'Admin'));
		$gender = trim((string)($owner['gender'] ?? 'Male'));
		$password = (string)($owner['password'] ?? '');
		$status = (int)($owner['status'] ?? 1);

		$stmt = $conn->prepare('SELECT id FROM tbl_staff WHERE id = ? AND level = 9 LIMIT 1');
		$stmt->execute([$ownerId]);
		if ($stmt->fetchColumn()) {
			$upd = $conn->prepare('UPDATE tbl_staff SET fname = ?, lname = ?, gender = ?, email = ?, password = ?, status = ? WHERE id = ? AND level = 9');
			$upd->execute([$fname, $lname, $gender, $email, $password, $status, $ownerId]);
		} else {
			$ins = $conn->prepare('INSERT INTO tbl_staff (id, fname, lname, gender, email, password, level, status) VALUES (?, ?, ?, ?, ?, ?, 9, ?)');
			$ins->execute([$ownerId, $fname, $lname, $gender, $email, $password, $status]);
		}
	}

	$conn->commit();
	$_SESSION['reply'] = [['success', 'Backup restored successfully.']];
	header('location:../backup_restore.php');
} catch (Throwable $e) {
	if ($conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = [['danger', 'Restore failed: ' . $e->getMessage()]];
	header('location:../backup_restore.php');
}
