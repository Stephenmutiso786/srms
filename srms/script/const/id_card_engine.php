<?php
require_once('db/config.php');

function idcard_school_meta(PDO $conn): array
{
	$meta = [
		'name' => defined('WBName') ? WBName : (defined('APP_NAME') ? APP_NAME : 'School'),
		'logo' => defined('WBLogo') ? WBLogo : '',
		'tagline' => 'Learner Identity Card',
	];
	try {
		$stmt = $conn->prepare("SELECT name, logo FROM tbl_school LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$meta['name'] = (string)($row['name'] ?? $meta['name']);
			$meta['logo'] = (string)($row['logo'] ?? $meta['logo']);
		}
	} catch (Throwable $e) {
	}
	return $meta;
}

function idcard_student_payload(PDO $conn, string $studentId): ?array
{
	$stmt = $conn->prepare("SELECT st.id, st.school_id, st.fname, st.mname, st.lname, st.gender, st.email, st.class, st.display_image, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE st.id = ?
		LIMIT 1");
	$stmt->execute([$studentId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}

	$photo = 'images/students/' . (($row['display_image'] ?? 'DEFAULT') === 'DEFAULT' ? (($row['gender'] ?? 'Male') . '.png') : $row['display_image']);
	$fullName = trim(($row['fname'] ?? '') . ' ' . ($row['mname'] ?? '') . ' ' . ($row['lname'] ?? ''));
	$schoolId = (string)($row['school_id'] ?? '');
	if ($schoolId === '') {
		$schoolId = (string)$row['id'];
	}

	return [
		'type' => 'student',
		'id' => (string)$row['id'],
		'school_id' => $schoolId,
		'name' => $fullName,
		'role_label' => 'Student ID',
		'subtitle' => 'Learner',
		'class_name' => (string)($row['class_name'] ?? ''),
		'aux_label' => 'Admission No',
		'aux_value' => (string)$row['id'],
		'email' => (string)($row['email'] ?? ''),
		'photo_path' => $photo,
		'photo_exists' => file_exists($photo),
		'initials' => strtoupper(substr((string)($row['fname'] ?? 'S'), 0, 1) . substr((string)($row['lname'] ?? 'T'), 0, 1)),
	];
}

function idcard_staff_payload(PDO $conn, string $staffId): ?array
{
	$stmt = $conn->prepare("SELECT id, school_id, fname, lname, gender, email, level
		FROM tbl_staff
		WHERE id = ?
		LIMIT 1");
	$stmt->execute([$staffId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return null;
	}

	$roleMap = [
		'0' => 'Administrator',
		'1' => 'Academic Office',
		'2' => 'Teacher',
		'5' => 'Accountant',
	];
	$role = $roleMap[(string)($row['level'] ?? '')] ?? 'Staff';
	$fullName = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
	$schoolId = (string)($row['school_id'] ?? '');
	if ($schoolId === '') {
		$schoolId = (string)$row['id'];
	}

	return [
		'type' => 'staff',
		'id' => (string)$row['id'],
		'school_id' => $schoolId,
		'name' => $fullName,
		'role_label' => 'Staff ID',
		'subtitle' => $role,
		'class_name' => $role,
		'aux_label' => 'Work Email',
		'aux_value' => (string)($row['email'] ?? ''),
		'email' => (string)($row['email'] ?? ''),
		'photo_path' => '',
		'photo_exists' => false,
		'initials' => strtoupper(substr((string)($row['fname'] ?? 'S'), 0, 1) . substr((string)($row['lname'] ?? 'F'), 0, 1)),
	];
}

function idcard_verify_url(string $schoolId): string
{
	$base = defined('APP_URL') && APP_URL !== '' ? APP_URL : ('http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
	return rtrim($base, '/') . '/verify_report?code=' . urlencode($schoolId);
}

