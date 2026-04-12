<?php

function app_communication_row_contact(PDO $conn, string $table, array $row): array
{
	$contact = [
		'id' => (string)($row['id'] ?? ''),
		'name' => trim((string)($row['name'] ?? '')),
		'email' => '',
		'phone' => '',
	];

	if ($table === 'tbl_staff') {
		$contact['email'] = (string)($row['email'] ?? '');
		if (array_key_exists('phone', $row)) {
			$contact['phone'] = (string)($row['phone'] ?? '');
		}
	} elseif ($table === 'tbl_students') {
		$contact['email'] = (string)($row['email'] ?? '');
		if (array_key_exists('phone', $row)) {
			$contact['phone'] = (string)($row['phone'] ?? '');
		}
	} elseif ($table === 'tbl_parents') {
		if (array_key_exists('email', $row)) {
			$contact['email'] = (string)($row['email'] ?? '');
		}
		if (array_key_exists('phone', $row)) {
			$contact['phone'] = (string)($row['phone'] ?? '');
		}
	}

	return $contact;
}

function app_communication_fetch_rows(PDO $conn, string $sql, array $params, string $table): array
{
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$rows[] = app_communication_row_contact($conn, $table, $row);
	}
	return $rows;
}

function app_communication_targets(PDO $conn, string $targetType, string $targetValue = ''): array
{
	$targetType = strtolower(trim($targetType));
	$targetValue = trim($targetValue);

	if (!app_table_exists($conn, 'tbl_staff')) {
		return [];
	}

	$hasStaffPhone = app_column_exists($conn, 'tbl_staff', 'phone');
	$hasStudentPhone = app_column_exists($conn, 'tbl_students', 'phone');
	$hasParentPhone = app_column_exists($conn, 'tbl_parents', 'phone');
	$hasParentEmail = app_column_exists($conn, 'tbl_parents', 'email');
	$hasStudentEmail = app_column_exists($conn, 'tbl_students', 'email');
	$hasStaffEmail = app_column_exists($conn, 'tbl_staff', 'email');

	if ($targetType === 'student' || $targetType === 'individual_student') {
		if (!app_table_exists($conn, 'tbl_students') || $targetValue === '') return [];
		$sql = 'SELECT id, concat_ws(\' \' , fname, mname, lname) AS name';
		if ($hasStudentEmail) $sql .= ', email';
		if ($hasStudentPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_students WHERE id = ? LIMIT 1';
		return app_communication_fetch_rows($conn, $sql, [$targetValue], 'tbl_students');
	}

	if ($targetType === 'parent' || $targetType === 'individual_parent') {
		if (!app_table_exists($conn, 'tbl_parents') || $targetValue === '') return [];
		$sql = 'SELECT id, concat_ws(\' \' , fname, lname) AS name';
		if ($hasParentEmail) $sql .= ', email';
		if ($hasParentPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_parents WHERE id = ? LIMIT 1';
		return app_communication_fetch_rows($conn, $sql, [$targetValue], 'tbl_parents');
	}

	if ($targetType === 'staff' || $targetType === 'individual_staff') {
		$sql = 'SELECT id, concat_ws(\' \' , fname, lname) AS name';
		if ($hasStaffEmail) $sql .= ', email';
		if ($hasStaffPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_staff WHERE id = ? LIMIT 1';
		return app_communication_fetch_rows($conn, $sql, [$targetValue], 'tbl_staff');
	}

	if ($targetType === 'all_students') {
		$sql = 'SELECT id, concat_ws(\' \' , fname, mname, lname) AS name';
		if ($hasStudentEmail) $sql .= ', email';
		if ($hasStudentPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_students ORDER BY id';
		return app_communication_fetch_rows($conn, $sql, [], 'tbl_students');
	}

	if ($targetType === 'all_parents') {
		if (!app_table_exists($conn, 'tbl_parents')) return [];
		$sql = 'SELECT id, concat_ws(\' \' , fname, lname) AS name';
		if ($hasParentEmail) $sql .= ', email';
		if ($hasParentPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_parents ORDER BY id';
		return app_communication_fetch_rows($conn, $sql, [], 'tbl_parents');
	}

	if ($targetType === 'all_staff') {
		$sql = 'SELECT id, concat_ws(\' \' , fname, lname) AS name';
		if ($hasStaffEmail) $sql .= ', email';
		if ($hasStaffPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_staff ORDER BY id';
		return app_communication_fetch_rows($conn, $sql, [], 'tbl_staff');
	}

	if ($targetType === 'class_students' && app_table_exists($conn, 'tbl_students') && $targetValue !== '') {
		$sql = 'SELECT id, concat_ws(\' \' , fname, mname, lname) AS name';
		if ($hasStudentEmail) $sql .= ', email';
		if ($hasStudentPhone) $sql .= ', phone';
		$sql .= ' FROM tbl_students WHERE class = ? ORDER BY fname, lname';
		return app_communication_fetch_rows($conn, $sql, [(int)$targetValue], 'tbl_students');
	}

	if ($targetType === 'class_parents' && app_table_exists($conn, 'tbl_parent_students') && app_table_exists($conn, 'tbl_students') && app_table_exists($conn, 'tbl_parents') && $targetValue !== '') {
		$sql = 'SELECT DISTINCT p.id, concat_ws(\' \' , p.fname, p.lname) AS name';
		if ($hasParentEmail) $sql .= ', p.email';
		if ($hasParentPhone) $sql .= ', p.phone';
		$sql .= ' FROM tbl_parent_students ps JOIN tbl_parents p ON p.id = ps.parent_id JOIN tbl_students s ON s.id = ps.student_id WHERE s.class = ? ORDER BY p.fname, p.lname';
		return app_communication_fetch_rows($conn, $sql, [(int)$targetValue], 'tbl_parents');
	}

	if ($targetType === 'role_staff' && app_table_exists($conn, 'tbl_user_roles') && app_table_exists($conn, 'tbl_roles') && $targetValue !== '') {
		$sql = 'SELECT DISTINCT s.id, concat_ws(\' \' , s.fname, s.lname) AS name';
		if ($hasStaffEmail) $sql .= ', s.email';
		if ($hasStaffPhone) $sql .= ', s.phone';
		$sql .= ' FROM tbl_user_roles ur JOIN tbl_staff s ON s.id = ur.staff_id JOIN tbl_roles r ON r.id = ur.role_id WHERE r.id = ? ORDER BY s.fname, s.lname';
		return app_communication_fetch_rows($conn, $sql, [(int)$targetValue], 'tbl_staff');
	}

	return [];
}
