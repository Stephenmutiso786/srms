<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../../'); exit; }
app_require_permission('student.leadership.manage', '../student_leaders');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../student_leaders');
	exit;
}

$studentId = trim((string)($_POST['student_id'] ?? ''));
$classId = (int)($_POST['class_id'] ?? 0);
$roleCode = trim((string)($_POST['role_code'] ?? ''));
$termId = (int)($_POST['term_id'] ?? 0);
$year = (int)($_POST['year'] ?? date('Y'));
$responsibilities = trim((string)($_POST['responsibilities'] ?? ''));

if ($studentId === '' || $classId < 1 || $roleCode === '' || $year < 2000) {
	$_SESSION['reply'] = array(array('danger', 'Please complete all required fields.'));
	header('location:../student_leaders');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_roles_table($conn);

	$validRoles = app_student_role_catalog();
	if (!isset($validRoles[$roleCode])) {
		throw new RuntimeException('Invalid leadership role.');
	}

	if (defined('DBDriver') && DBDriver === 'pgsql') {
		$stmt = $conn->prepare("INSERT INTO tbl_student_roles (student_id, class_id, role_code, responsibilities, term_id, year, status, created_by)
			VALUES (?, ?, ?, ?, NULLIF(?, 0), ?, 1, ?)
			ON CONFLICT (student_id, class_id, role_code, term_id, year)
			DO UPDATE SET responsibilities = EXCLUDED.responsibilities, status = 1");
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_student_roles (student_id, class_id, role_code, responsibilities, term_id, year, status, created_by)
			VALUES (?, ?, ?, ?, NULLIF(?, 0), ?, 1, ?)
			ON DUPLICATE KEY UPDATE responsibilities = VALUES(responsibilities), status = 1");
	}
	$stmt->execute([$studentId, $classId, $roleCode, $responsibilities, $termId, $year, (int)$account_id]);

	$_SESSION['reply'] = array(array('success', 'Student leadership role saved.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to save student leadership role.'));
}

header('location:../student_leaders');
exit;
