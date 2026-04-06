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
	header("location:../staff_attendance");
	exit;
}

$today = date('Y-m-d');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_staff_attendance')) {
		$_SESSION['reply'] = array(array("error", "Staff attendance is not installed on the server."));
		header("location:../staff_attendance");
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');

	if ($isPgsql) {
		$stmt = $conn->prepare("INSERT INTO tbl_staff_attendance (staff_id, attendance_date, status, clock_in, marked_by)
			VALUES (?,?, 'present', CURRENT_TIMESTAMP, ?)
			ON CONFLICT (staff_id, attendance_date) DO UPDATE
			SET status = 'present',
			    clock_in = COALESCE(tbl_staff_attendance.clock_in, EXCLUDED.clock_in),
			    marked_by = EXCLUDED.marked_by");
		$stmt->execute([(int)$account_id, $today, (int)$account_id]);
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_staff_attendance (staff_id, attendance_date, status, clock_in, marked_by)
			VALUES (?,?, 'present', NOW(), ?)
			ON DUPLICATE KEY UPDATE
			status='present',
			clock_in = IFNULL(clock_in, NOW()),
			marked_by=VALUES(marked_by)");
		$stmt->execute([(int)$account_id, $today, (int)$account_id]);
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'staff_attendance.clock_in', 'staff_attendance', $today);

	$_SESSION['reply'] = array(array("success", "Clock in recorded."));
	header("location:../staff_attendance");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../staff_attendance");
	exit;
}

