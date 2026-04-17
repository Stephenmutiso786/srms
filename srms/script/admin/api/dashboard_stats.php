<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($res) || $res !== "1" || !isset($level) || $level !== "0") {
	http_response_code(401);
	echo json_encode(["error" => "unauthorized"]);
	exit;
}

$cacheTtl = 60;
$cacheFile = sys_get_temp_dir() . '/srms_dashboard_stats_' . (defined('DBDriver') ? DBDriver : 'default') . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
	$cached = file_get_contents($cacheFile);
	if (is_string($cached) && $cached !== '') {
		echo $cached;
		exit;
	}
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);

	$counts = [];
	$counts['students'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
	$counts['teachers'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
	$counts['staff'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn();
	$counts['classes'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn();
	$counts['subjects'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn();
	$counts['terms_active'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_terms WHERE status = 1")->fetchColumn();

	$stmt = $conn->prepare("SELECT c.id, c.name, COUNT(s.id) AS count
		FROM tbl_classes c
		LEFT JOIN tbl_students s ON s.class = c.id
		GROUP BY c.id, c.name
		ORDER BY c.id");
	$stmt->execute();
	$studentsByClass = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT t.id, t.name, COALESCE(AVG(r.score), 0) AS avg_score
		FROM tbl_terms t
		LEFT JOIN tbl_exam_results r ON r.term = t.id
		GROUP BY t.id, t.name
		ORDER BY t.id");
	$stmt->execute();
	$avgScoreByTerm = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT gender, COUNT(*) AS count FROM tbl_students GROUP BY gender ORDER BY gender");
	$stmt->execute();
	$studentsByGender = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$attendanceSummary = [
		"present" => 0,
		"absent" => 0,
		"late" => 0,
		"excused" => 0
	];

	if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
		$stmt = $conn->prepare("SELECT r.status, COUNT(*) AS count
			FROM tbl_attendance_records r
			INNER JOIN tbl_attendance_sessions s ON s.id = r.session_id
			WHERE s.session_date = CURRENT_DATE
			GROUP BY r.status");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$key = $row['status'] ?? '';
			if ($key !== '') {
				$attendanceSummary[$key] = (int)$row['count'];
			}
		}
	}

	$staffAttendanceToday = 0;
	if (app_table_exists($conn, 'tbl_staff_attendance')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_staff_attendance WHERE attendance_date = CURRENT_DATE AND status = 'present'");
		$stmt->execute();
		$staffAttendanceToday = (int)$stmt->fetchColumn();
	}

	$feeSummary = [
		"open_invoices" => 0,
		"paid_invoices" => 0,
		"outstanding_total" => 0,
		"payments_today" => 0
	];
	$paymentsByDay = [];
	$paymentsByMethod = [];

	if (app_table_exists($conn, 'tbl_invoices')) {
		$feeSummary['open_invoices'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'open'")->fetchColumn();
		$feeSummary['paid_invoices'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'paid'")->fetchColumn();
	}

	if (app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_invoices')) {
		if (app_table_exists($conn, 'tbl_payments')) {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(lines.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
				FROM (
					SELECT i.id, SUM(l.amount) AS total_amount
					FROM tbl_invoices i
					INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.status <> 'void'
					GROUP BY i.id
				) lines
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = lines.id
			");
			$stmt->execute();
			$feeSummary['outstanding_total'] = (float)$stmt->fetchColumn();
		} else {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(l.amount), 0) AS outstanding
				FROM tbl_invoices i
				INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
				WHERE i.status <> 'void'
			");
			$stmt->execute();
			$feeSummary['outstanding_total'] = (float)$stmt->fetchColumn();
		}
	}

	if (app_table_exists($conn, 'tbl_payments')) {
		if ($driver === 'mysql') {
			$dateExpr = "DATE(paid_at)";
			$rangeExpr = "DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
			$todayExpr = "CURDATE()";
		} else {
			$dateExpr = "paid_at::date";
			$rangeExpr = "CURRENT_DATE - INTERVAL '6 days'";
			$todayExpr = "CURRENT_DATE";
		}

		$feeSummary['payments_today'] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE $dateExpr = $todayExpr")->fetchColumn();

		$stmt = $conn->prepare("SELECT $dateExpr AS day, COALESCE(SUM(amount),0) AS total
			FROM tbl_payments
			WHERE $dateExpr >= $rangeExpr
			GROUP BY $dateExpr
			ORDER BY $dateExpr");
		$stmt->execute();
		$paymentsByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $conn->prepare("SELECT method, COALESCE(SUM(amount),0) AS total
			FROM tbl_payments
			GROUP BY method
			ORDER BY total DESC");
		$stmt->execute();
		$paymentsByMethod = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$response = json_encode([
		"counts" => $counts,
		"studentsByClass" => $studentsByClass,
		"avgScoreByTerm" => $avgScoreByTerm,
		"studentsByGender" => $studentsByGender,
		"attendanceToday" => $attendanceSummary,
		"staffAttendanceToday" => $staffAttendanceToday,
		"fees" => $feeSummary,
		"paymentsByDay" => $paymentsByDay,
		"paymentsByMethod" => $paymentsByMethod
	]);
	if (!is_string($response)) {
		throw new RuntimeException('Failed to encode dashboard stats.');
	}
	@file_put_contents($cacheFile, $response, LOCK_EX);
	echo $response;
} catch (PDOException $e) {
	http_response_code(500);
	echo json_encode(["error" => $e->getMessage()]);
}
