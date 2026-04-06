<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	header("location:../../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../staff_attendance");
	exit;
}

$date = trim((string)($_POST['date'] ?? ''));
$statuses = $_POST['status'] ?? [];
$clockIns = $_POST['clock_in'] ?? [];
$clockOuts = $_POST['clock_out'] ?? [];

if ($date === '' || !is_array($statuses)) {
	$_SESSION['reply'] = array(array("error", "Invalid request."));
	header("location:../staff_attendance");
	exit;
}

$allowedStatuses = ['not_marked', 'present', 'absent', 'late'];

function parse_dt_local($value): ?string {
	$value = trim((string)$value);
	if ($value === '') return null;
	// value is like 2026-04-07T13:45
	$value = str_replace('T', ' ', $value) . ':00';
	return $value;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_staff_attendance')) {
		$_SESSION['reply'] = array(array("error", "Staff attendance tables are not installed."));
		header("location:../staff_attendance?date=" . urlencode($date));
		exit;
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$conn->beginTransaction();

	foreach ($statuses as $staffIdRaw => $status) {
		$staffId = (int)$staffIdRaw;
		$status = trim((string)$status);
		if ($staffId < 1 || !in_array($status, $allowedStatuses, true)) continue;

		$clockIn = is_array($clockIns) ? parse_dt_local($clockIns[$staffIdRaw] ?? '') : null;
		$clockOut = is_array($clockOuts) ? parse_dt_local($clockOuts[$staffIdRaw] ?? '') : null;

		if ($status === 'not_marked') {
			$stmt = $conn->prepare("DELETE FROM tbl_staff_attendance WHERE staff_id = ? AND attendance_date = ?");
			$stmt->execute([$staffId, $date]);
			continue;
		}

		if ($isPgsql) {
			$stmt = $conn->prepare("INSERT INTO tbl_staff_attendance (staff_id, attendance_date, status, clock_in, clock_out, marked_by)
				VALUES (?,?,?,?,?,?)
				ON CONFLICT (staff_id, attendance_date) DO UPDATE SET
				  status = EXCLUDED.status,
				  clock_in = EXCLUDED.clock_in,
				  clock_out = EXCLUDED.clock_out,
				  marked_by = EXCLUDED.marked_by,
				  created_at = tbl_staff_attendance.created_at");
			$stmt->execute([$staffId, $date, $status, $clockIn, $clockOut, (int)$account_id]);
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_staff_attendance (staff_id, attendance_date, status, clock_in, clock_out, marked_by)
				VALUES (?,?,?,?,?,?)
				ON DUPLICATE KEY UPDATE
				  status=VALUES(status),
				  clock_in=VALUES(clock_in),
				  clock_out=VALUES(clock_out),
				  marked_by=VALUES(marked_by)");
			$stmt->execute([$staffId, $date, $status, $clockIn, $clockOut, (int)$account_id]);
		}
	}

	$conn->commit();

	app_audit_log($conn, 'staff', (string)$account_id, 'staff_attendance.save', 'staff_attendance', $date);

	$_SESSION['reply'] = array(array("success", "Staff attendance saved."));
	header("location:../staff_attendance?date=" . urlencode($date));
	exit;
} catch (PDOException $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	$_SESSION['reply'] = array(array("error", $e->getMessage()));
	header("location:../staff_attendance?date=" . urlencode($date));
	exit;
}

