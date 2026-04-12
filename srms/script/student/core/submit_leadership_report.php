<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== '1' || $level !== '3') { header('location:../../'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../leadership');
	exit;
}

$reportType = trim((string)($_POST['report_type'] ?? 'discipline'));
$title = trim((string)($_POST['title'] ?? ''));
$details = trim((string)($_POST['details'] ?? ''));
$termId = (int)($_POST['term_id'] ?? 0);
$year = (int)($_POST['year'] ?? date('Y'));
$classId = (int)($_POST['class_id'] ?? 0);

if ($title === '' || $details === '' || $year < 2000) {
	$_SESSION['reply'] = array(array('danger', 'Please complete all required report fields.'));
	header('location:../leadership');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_roles_table($conn);
	app_ensure_student_leadership_reports_table($conn);

	$roleCheck = $conn->prepare('SELECT role_code FROM tbl_student_roles WHERE student_id = ? AND status = 1 LIMIT 1');
	$roleCheck->execute([(string)$account_id]);
	$roleCode = (string)$roleCheck->fetchColumn();
	if ($roleCode === '') {
		$_SESSION['reply'] = array(array('danger', 'Only assigned student leaders can submit reports.'));
		header('location:../leadership');
		exit;
	}

	$stmt = $conn->prepare('INSERT INTO tbl_student_leadership_reports (student_id, class_id, role_code, term_id, year, report_type, title, details, status)
		VALUES (?, NULLIF(?,0), ?, NULLIF(?,0), ?, ?, ?, ?, ? )');
	$stmt->execute([(string)$account_id, $classId, $roleCode, $termId, $year, $reportType, $title, $details, 'open']);

	$_SESSION['reply'] = array(array('success', 'Leadership report submitted successfully.'));
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to submit leadership report.'));
}

header('location:../leadership');
exit;
