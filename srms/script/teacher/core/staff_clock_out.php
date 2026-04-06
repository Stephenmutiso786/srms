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

	$stmt = $conn->prepare("SELECT clock_in, clock_out FROM tbl_staff_attendance WHERE staff_id = ? AND attendance_date = ? LIMIT 1");
	$stmt->execute([(int)$account_id, $today]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$row || ($row['clock_in'] ?? null) === null) {
		$_SESSION['reply'] = array(array("error", "Please clock in first."));
		header("location:../staff_attendance");
		exit;
	}

	if (($row['clock_out'] ?? null) !== null) {
		$_SESSION['reply'] = array(array("info", "You already clocked out."));
		header("location:../staff_attendance");
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$stmt = $isPgsql
		? $conn->prepare("UPDATE tbl_staff_attendance SET clock_out = CURRENT_TIMESTAMP, marked_by = ? WHERE staff_id = ? AND attendance_date = ?")
		: $conn->prepare("UPDATE tbl_staff_attendance SET clock_out = NOW(), marked_by = ? WHERE staff_id = ? AND attendance_date = ?");
	$stmt->execute([(int)$account_id, (int)$account_id, $today]);

	app_audit_log($conn, 'staff', (string)$account_id, 'staff_attendance.clock_out', 'staff_attendance', $today);

	$_SESSION['reply'] = array(array("success", "Clock out recorded."));
	header("location:../staff_attendance");
	exit;
} catch (PDOException $e) {
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../staff_attendance");
	exit;
}

