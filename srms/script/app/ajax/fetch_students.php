<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json');
if (!isset($res) || $res !== '1') {
	echo json_encode(['ok' => false, 'students' => []]);
	exit;
}

$classId = (int)($_GET['class_id'] ?? 0);
if ($classId < 1) {
	echo json_encode(['ok' => false, 'students' => []]);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$schoolId = function_exists('app_current_school_id') ? app_current_school_id() : 0;
	$hasTenantSchoolId = app_column_exists($conn, 'tbl_students', 'tenant_school_id');
	$sql = "SELECT st.id, st.fname, st.mname, st.lname FROM tbl_students st";
	$params = [];
	if ($hasTenantSchoolId && $schoolId > 0) {
		$sql .= " WHERE (st.tenant_school_id IS NULL OR st.tenant_school_id = ?)";
		$params[] = $schoolId;
		$sql .= " AND st.class = ?";
		$params[] = $classId;
	} elseif (app_column_exists($conn, 'tbl_classes', 'school_id') && $schoolId > 0) {
		$sql .= " INNER JOIN tbl_classes c ON c.id = st.class";
		$sql .= " WHERE st.class = ? AND (c.school_id IS NULL OR c.school_id = ?)";
		$params = [$classId, $schoolId];
	} else {
		$sql .= " WHERE st.class = ?";
		$params = [$classId];
	}
	$sql .= " ORDER BY id";
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$students = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$students[] = [
			'id' => (string)($row['id'] ?? ''),
			'name' => trim(implode(' ', array_filter([
				(string)($row['fname'] ?? ''),
				(string)($row['mname'] ?? ''),
				(string)($row['lname'] ?? ''),
			], static fn($value) => trim((string)$value) !== ''))),
		];
	}
	echo json_encode(['ok' => true, 'students' => $students], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'students' => []]);
}
