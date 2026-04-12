<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('bom.manage', '../bom');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../bom');
	exit;
}

$memberId = (int)($_POST['member_id'] ?? 0);
$gender = trim((string)($_POST['gender'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$passwordRaw = (string)($_POST['password'] ?? '');

if ($memberId < 1 || $email === '' || strlen($passwordRaw) < 6 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
	$_SESSION['reply'] = array(array('danger', 'Provide valid member, email and password (min 6 chars).'));
	header('location:../bom');
	exit;
}
if (!in_array($gender, ['Male', 'Female'], true)) {
	$gender = 'Male';
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_bom_tables($conn);
	app_ensure_school_roles($conn);

	$conn->beginTransaction();

	$stmt = $conn->prepare('SELECT id, full_name, role_code, staff_id, email FROM tbl_bom_members WHERE id = ? LIMIT 1');
	$stmt->execute([$memberId]);
	$member = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$member) {
		throw new RuntimeException('Selected BOM member was not found.');
	}
	if ((int)($member['staff_id'] ?? 0) > 0) {
		throw new RuntimeException('This BOM member already has a portal account.');
	}

	$hasParents = app_table_exists($conn, 'tbl_parents');
	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	if ($isPgsql) {
		$sql = 'SELECT email FROM tbl_staff WHERE email = ? UNION SELECT email FROM tbl_students WHERE email = ?';
		$params = [$email, $email];
		if ($hasParents) {
			$sql .= ' UNION SELECT email FROM tbl_parents WHERE email = ?';
			$params[] = $email;
		}
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
	} else {
		$sql = 'SELECT email FROM tbl_staff WHERE email = ? UNION SELECT email FROM tbl_students WHERE email = ?';
		$params = [$email, $email];
		if ($hasParents) {
			$sql .= ' UNION SELECT email FROM tbl_parents WHERE email = ?';
			$params[] = $email;
		}
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
	}
	if ($stmt->fetch(PDO::FETCH_ASSOC)) {
		throw new RuntimeException('Email is already in use.');
	}

	$fullName = trim((string)$member['full_name']);
	$nameParts = preg_split('/\s+/', $fullName) ?: [];
	$firstName = trim((string)($nameParts[0] ?? 'BOM'));
	$lastName = trim((string)(count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : 'Member'));
	$password = password_hash($passwordRaw, PASSWORD_DEFAULT);
	$level = '10';
	$status = '1';

	if (app_column_exists($conn, 'tbl_staff', 'school_id')) {
		$schoolId = app_generate_school_id($conn, 'BOM', (int)date('Y'), 'tbl_staff');
		$stmt = $conn->prepare('INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id) VALUES (?,?,?,?,?,?,?,?)');
		$stmt->execute([$firstName, $lastName, $gender, $email, $password, $level, $status, $schoolId]);
	} else {
		$stmt = $conn->prepare('INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?,?,?,?,?,?,?)');
		$stmt->execute([$firstName, $lastName, $gender, $email, $password, $level, $status]);
	}
	$staffId = (int)$conn->lastInsertId();
	if ($staffId < 1) {
		throw new RuntimeException('Could not create staff account.');
	}

	$roleMap = [
		'chairperson' => 'BOM Chairperson',
		'treasurer' => 'BOM Treasurer',
		'secretary' => 'BOM Member',
		'member' => 'BOM Member',
		'pta_rep' => 'BOM Member',
		'sponsor_rep' => 'BOM Member',
		'community_rep' => 'BOM Member',
	];
	$roleName = (string)($roleMap[(string)($member['role_code'] ?? '')] ?? 'BOM Member');
	$stmt = $conn->prepare('SELECT id FROM tbl_roles WHERE name = ? LIMIT 1');
	$stmt->execute([$roleName]);
	$roleId = (int)$stmt->fetchColumn();
	if ($roleId > 0 && app_table_exists($conn, 'tbl_user_roles')) {
		if ($isPgsql) {
			$stmt = $conn->prepare('INSERT INTO tbl_user_roles (staff_id, role_id) VALUES (?,?) ON CONFLICT DO NOTHING');
		} else {
			$stmt = $conn->prepare('INSERT IGNORE INTO tbl_user_roles (staff_id, role_id) VALUES (?,?)');
		}
		$stmt->execute([$staffId, $roleId]);
	}

	$stmt = $conn->prepare('UPDATE tbl_bom_members SET staff_id = ?, email = ? WHERE id = ?');
	$stmt->execute([$staffId, $email, $memberId]);

	$conn->commit();
	$_SESSION['reply'] = array(array('success', 'BOM portal profile created. Login email: '.$email));
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage()));
}

header('location:../bom');
exit;
