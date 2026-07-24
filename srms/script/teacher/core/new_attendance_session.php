<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if (!isset($res) || $res !== "1" || !isset($level) || !in_array((string)$level, ['0', '1', '2', '9'], true)) {
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
	if (!app_can_manage_student_attendance($conn, (string)$account_id, (string)$level)) {
		$_SESSION['reply'] = array(array("error", "Student attendance is only available to admins and staff members who are currently allocated as class teachers."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		$_SESSION['reply'] = array(array("error", "Attendance tables are not installed. Run the Postgres migration 001_rbac_attendance.sql."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	if (!app_is_attendance_admin_level((string)$level) && !app_staff_is_active_class_teacher($conn, (int)$account_id, $classId)) {
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
		$attendanceSnapshot = app_attendance_session_archive_payload($conn, $sessionId);
		if ($attendanceSnapshot) {
			$sessionMeta = (array)($attendanceSnapshot['session'] ?? []);
			app_data_camp_store_event($conn, [
				'module_key' => 'attendance',
				'record_type' => 'attendance_session_created',
				'entity_table' => 'tbl_attendance_sessions',
				'entity_id' => (string)$sessionId,
				'title' => 'Attendance Session ' . $sessionDate,
				'description' => 'Attendance session snapshot retained at creation',
				'class_id' => (int)($sessionMeta['class_id'] ?? 0) > 0 ? (int)$sessionMeta['class_id'] : $classId,
				'owner_portal' => 'admin,academic,teacher',
				'mime_type' => 'application/json',
				'status' => 'retained',
				'payload_json' => $attendanceSnapshot,
				'created_by' => (int)$account_id,
			]);
		}
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
