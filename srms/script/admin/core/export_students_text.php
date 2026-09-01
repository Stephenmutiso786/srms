<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('students.manage', '../admin');

$classIds = $_POST['class'] ?? [];
if (!is_array($classIds)) {
	$classIds = [$classIds];
}
$classIds = array_values(array_filter(array_map('intval', $classIds), static fn($value) => $value > 0));
if (empty($classIds)) {
	$_SESSION['reply'] = array(array('warning', 'Please select at least one class to export.'));
	header('location:../manage_students');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$schoolId = function_exists('app_current_school_id') ? app_current_school_id() : 0;

	$classPlaceholders = implode(',', array_fill(0, count($classIds), '?'));
	$classSql = "SELECT id, name FROM tbl_classes WHERE id IN ($classPlaceholders)";
	$params = $classIds;
	if (app_column_exists($conn, 'tbl_classes', 'school_id') && $schoolId > 0) {
		$classSql .= " AND (school_id IS NULL OR school_id = ?)";
		$params[] = $schoolId;
	}
	$classSql .= " ORDER BY name ASC";
	$stmt = $conn->prepare($classSql);
	$stmt->execute($params);
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (!$classes) {
		$_SESSION['reply'] = array(array('warning', 'No classes available for export.'));
		header('location:../manage_students');
		exit;
	}

	$classNames = [];
	foreach ($classes as $classRow) {
		$classNames[(int)$classRow['id']] = (string)$classRow['name'];
	}

	$studentSql = "SELECT st.id, st.fname, st.mname, st.lname, st.class
		FROM tbl_students st
		WHERE st.class IN ($classPlaceholders)";
	$studentParams = $classIds;
	if (app_column_exists($conn, 'tbl_students', 'tenant_school_id') && $schoolId > 0) {
		$studentSql .= " AND (st.tenant_school_id IS NULL OR st.tenant_school_id = ?)";
		$studentParams[] = $schoolId;
	} elseif (app_column_exists($conn, 'tbl_students', 'school_id') && $schoolId > 0) {
		$studentSql .= " AND (st.school_id IS NULL OR st.school_id = ?)";
		$studentParams[] = $schoolId;
	}
	$studentSql .= " ORDER BY st.class ASC, st.fname ASC, st.mname ASC, st.lname ASC, st.id ASC";
	$stmt = $conn->prepare($studentSql);
	$stmt->execute($studentParams);

	$studentsByClass = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$className = (string)($classNames[(int)($row['class'] ?? 0)] ?? 'Unassigned');
		$studentsByClass[$className][] = trim((string)($row['fname'] ?? '') . ' ' . (string)($row['mname'] ?? '') . ' ' . (string)($row['lname'] ?? ''));
	}

	header('Content-Type: text/plain; charset=UTF-8');
	header('Content-Disposition: attachment; filename="students-by-class.txt"');

	foreach ($studentsByClass as $className => $students) {
		echo $className . PHP_EOL;
		foreach ($students as $student) {
			echo '- ' . $student . PHP_EOL;
		}
		echo PHP_EOL;
	}
	exit;
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Text export failed: ' . $e->getMessage()));
	header('location:../manage_students');
}
