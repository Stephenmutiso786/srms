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

	// Ensure session belongs to one of the teacher's classes.
	$stmt = $conn->prepare("SELECT class_id FROM tbl_attendance_sessions WHERE id = ? LIMIT 1");
	$stmt->execute([$sessionId]);
	$classId = (int)($stmt->fetchColumn() ?: 0);
	if ($classId < 1) {
		$_SESSION['reply'] = array(array("error", "Attendance session not found."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

	if (!app_staff_is_active_class_teacher($conn, (int)$account_id, $classId)) {
		$_SESSION['reply'] = array(array("error", "Only the assigned class teacher can edit this attendance session."));
		header("location:" . app_attendance_redirect_target($originPortal, $originPortal === 'admin' ? 'attendance_mark' : 'attendance'));
		exit;
	}

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
