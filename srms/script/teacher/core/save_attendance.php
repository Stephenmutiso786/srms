<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "2") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../attendance");
	exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);
$statuses = $_POST['status'] ?? [];

if ($sessionId < 1 || !is_array($statuses)) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../attendance");
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
		header("location:../attendance");
		exit;
	}

	$stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations WHERE teacher = ?");
	$stmt->execute([(int)$account_id]);
	$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	$allowed = [];
	foreach ($rows as $r) {
		foreach (app_unserialize($r[0]) as $c) {
			$allowed[] = (int)$c;
		}
	}
	$allowed = array_values(array_unique($allowed));
	if (!in_array($classId, $allowed, true)) {
		$_SESSION['reply'] = array(array("error", "You are not allowed to edit this session."));
		header("location:../attendance");
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
	header("location:../attendance_session?id=" . $sessionId);
	exit;
} catch (PDOException $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../attendance_session?id=" . $sessionId);
	exit;
}

