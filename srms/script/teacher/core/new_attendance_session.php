<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "2") {
	header("location:../../");
	exit;
}

function app_attendance_redirect_target(string $portal, string $page): string
{
	$portal = strtolower(trim($portal));
	if (!in_array($portal, ['admin', 'academic', 'teacher'], true)) {
		$portal = 'teacher';
	}
	return '../../' . $portal . '/' . ltrim($page, '/');
}

$originPortal = strtolower(trim((string)($_POST['origin_portal'] ?? ($_SESSION['attendance_portal'] ?? 'teacher'))));
if (!in_array($originPortal, ['admin', 'academic', 'teacher'], true)) {
	$originPortal = 'teacher';
}
$_SESSION['attendance_portal'] = $originPortal;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
	exit;
}

$classId = (int)($_POST['class_id'] ?? 0);
$termIdRaw = trim((string)($_POST['term_id'] ?? ''));
$termId = $termIdRaw === '' ? null : (int)$termIdRaw;
$sessionDate = trim((string)($_POST['session_date'] ?? ''));

if ($classId < 1 || $sessionDate === '') {
	$_SESSION['reply'] = array(array("error", "Please select class and date."));
	header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		$_SESSION['reply'] = array(array("error", "Attendance tables are not installed. Run the Postgres migration 001_rbac_attendance.sql."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	if (!app_staff_is_active_class_teacher($conn, (int)$account_id, $classId)) {
		$_SESSION['reply'] = array(array("error", "Only the assigned class teacher can start class attendance for this class."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	// Reuse existing session if it already exists for that class/date.
	$stmt = $conn->prepare("SELECT id FROM tbl_attendance_sessions WHERE class_id = ? AND session_date = ? AND session_type = 'daily' AND subject_id IS NULL LIMIT 1");
	$stmt->execute([$classId, $sessionDate]);
	$sessionId = (int)($stmt->fetchColumn() ?: 0);

	if ($sessionId < 1) {
		$stmt = $conn->prepare("INSERT INTO tbl_attendance_sessions (class_id, term_id, session_date, session_type, subject_id, created_by) VALUES (?,?,?,?,?,?)");
		$stmt->execute([$classId, $termId, $sessionDate, 'daily', null, (int)$account_id]);
		$sessionId = (int)$conn->lastInsertId();
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'attendance.session.create', 'attendance_session', (string)$sessionId);

	$sessionPage = $originPortal === 'admin' ? 'attendance_mark_session' : 'attendance_session';
	header("location:" . app_attendance_redirect_target($originPortal, $sessionPage) . "?id=" . $sessionId);
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
	exit;
}
