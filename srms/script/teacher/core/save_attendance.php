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

$sessionId = (int)($_POST['session_id'] ?? 0);
$statuses = $_POST['status'] ?? [];

if ($sessionId < 1 || !is_array($statuses)) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
	exit;
}

$allowedStatuses = ['present', 'absent', 'late', 'excused'];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (!app_can_manage_student_attendance($conn, (string)$account_id, (string)$level)) {
		$_SESSION['reply'] = array(array("error", "Student attendance is only available to admins and staff members who are currently allocated as class teachers."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	// Ensure session belongs to one of the teacher's classes.
	$stmt = $conn->prepare("SELECT class_id FROM tbl_attendance_sessions WHERE id = ? LIMIT 1");
	$stmt->execute([$sessionId]);
	$classId = (int)($stmt->fetchColumn() ?: 0);
	if ($classId < 1) {
		$_SESSION['reply'] = array(array("error", "Attendance session not found."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	if (!app_is_attendance_admin_level((string)$level) && !app_staff_is_active_class_teacher($conn, (int)$account_id, $classId)) {
		$_SESSION['reply'] = array(array("error", "Only the assigned class teacher can edit this attendance session."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	$beforeSnapshot = app_attendance_session_archive_payload($conn, $sessionId);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');

	$conn->beginTransaction();

	if ($isPgsql) {
		$upsert = $conn->prepare("INSERT INTO tbl_attendance_records (session_id, student_id, status, marked_by) VALUES (?,?,?,?)
			ON CONFLICT (session_id, student_id) DO UPDATE SET status = EXCLUDED.status, marked_by = EXCLUDED.marked_by, marked_at = CURRENT_TIMESTAMP");
	} else {
		$upsert = $conn->prepare("INSERT INTO tbl_attendance_records (session_id, student_id, status, marked_by) VALUES (?,?,?,?)
			ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by), marked_at = CURRENT_TIMESTAMP");
	}

	foreach ($statuses as $studentId => $status) {
		$studentId = trim((string)$studentId);
		$status = trim((string)$status);
		if ($studentId === '' || !in_array($status, $allowedStatuses, true)) {
			continue;
		}
		$upsert->execute([$sessionId, $studentId, $status, (int)$account_id]);
	}

	$conn->commit();
	$afterSnapshot = app_attendance_session_archive_payload($conn, $sessionId);
	if ($afterSnapshot) {
		$sessionMeta = (array)($afterSnapshot['session'] ?? $beforeSnapshot['session'] ?? []);
		app_data_camp_store_event($conn, [
			'module_key' => 'attendance',
			'record_type' => 'attendance_session_updated',
			'entity_table' => 'tbl_attendance_sessions',
			'entity_id' => (string)$sessionId,
			'title' => 'Attendance Session ' . (string)($sessionMeta['session_date'] ?? $sessionId),
			'description' => 'Attendance session snapshot retained before and after save',
			'class_id' => (int)($sessionMeta['class_id'] ?? 0) > 0 ? (int)$sessionMeta['class_id'] : $classId,
			'owner_portal' => 'admin,academic,teacher',
			'mime_type' => 'application/json',
			'status' => 'retained',
			'payload_json' => [
				'before' => $beforeSnapshot,
				'after' => $afterSnapshot,
			],
			'created_by' => (int)$account_id,
		]);
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'attendance.session.save', 'attendance_session', (string)$sessionId);

	$_SESSION['reply'] = array(array("success", "Attendance saved."));
	$sessionPage = $originPortal === 'admin' ? 'attendance_mark_session' : 'attendance_session';
	header("location:" . app_attendance_redirect_target($originPortal, $sessionPage) . "?id=" . $sessionId);
	exit;
} catch (PDOException $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	$sessionPage = $originPortal === 'admin' ? 'attendance_mark_session' : 'attendance_session';
	header("location:" . app_attendance_redirect_target($originPortal, $sessionPage) . "?id=" . $sessionId);
	exit;
}
