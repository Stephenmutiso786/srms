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
	$tableExists = static function (PDO $conn, string $table): bool {
		return app_table_exists($conn, $table);
	};
	$columnExists = static function (PDO $conn, string $table, string $column): bool {
		return app_column_exists($conn, $table, $column);
	};
	$safeScalar = static function (callable $callback, $default = 0) {
		try {
			return $callback();
		} catch (Throwable $e) {
			return $default;
		}
	};
	$safeRows = static function (callable $callback): array {
		try {
			$rows = $callback();
			return is_array($rows) ? $rows : [];
		} catch (Throwable $e) {
			return [];
		}
	};

	$counts = [];
	$counts['students'] = app_table_exists($conn, 'tbl_students')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn(), 0)
		: 0;
	$counts['teachers'] = 0;
	if (app_table_exists($conn, 'tbl_staff')) {
		$counts['teachers'] = (int)$safeScalar(function () use ($conn) {
			if (app_column_exists($conn, 'tbl_staff', 'designation')) {
				return $conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2 OR designation LIKE '%Teacher%' OR designation LIKE '%Lecturer%'")->fetchColumn();
			}
			return $conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
		}, 0);
	}
	$counts['staff'] = app_table_exists($conn, 'tbl_staff')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn(), 0)
		: 0;
	$counts['classes'] = app_table_exists($conn, 'tbl_classes')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_classes")->fetchColumn(), 0)
		: 0;
	$counts['subjects'] = app_table_exists($conn, 'tbl_subjects')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_subjects")->fetchColumn(), 0)
		: 0;
	$counts['terms_active'] = (app_table_exists($conn, 'tbl_terms') && app_column_exists($conn, 'tbl_terms', 'status'))
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_terms WHERE status = 1")->fetchColumn(), 0)
		: 0;
	$counts['timetables'] = app_table_exists($conn, 'tbl_timetables')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_timetables WHERE is_active = 1")->fetchColumn(), 0)
		: 0;
	$counts['boarders'] = app_table_exists($conn, 'tbl_student_dorms')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_student_dorms WHERE is_current = 1")->fetchColumn(), 0)
		: 0;
	$counts['active_dorms'] = app_table_exists($conn, 'tbl_dorms')
		? (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_dorms")->fetchColumn(), 0)
		: 0;

	$studentsByClass = [];
	if (app_table_exists($conn, 'tbl_classes') && app_table_exists($conn, 'tbl_students')) {
		$studentsByClass = $safeRows(function () use ($conn) {
			$studentClassColumn = app_column_exists($conn, 'tbl_students', 'class_id') ? 'class_id' : 'class';
			$stmt = $conn->prepare("SELECT c.id, c.name, COUNT(s.id) AS count
				FROM tbl_classes c
				LEFT JOIN tbl_students s ON s.$studentClassColumn = c.id
				GROUP BY c.id, c.name
				ORDER BY c.id");
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});
	}

	$avgScoreByTerm = [];
	if (app_table_exists($conn, 'tbl_terms') && app_table_exists($conn, 'tbl_exam_results')) {
		$avgScoreByTerm = $safeRows(function () use ($conn) {
			$scoreColumn = app_column_exists($conn, 'tbl_exam_results', 'score') ? 'score' : 'marks';
			$stmt = $conn->prepare("SELECT t.id, t.name, COALESCE(AVG(r.$scoreColumn), 0) AS avg_score
				FROM tbl_terms t
				LEFT JOIN tbl_exam_results r ON r.term = t.id
				GROUP BY t.id, t.name
				ORDER BY t.id");
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});
	}

	$studentsByGender = [];
	if (app_table_exists($conn, 'tbl_students') && app_column_exists($conn, 'tbl_students', 'gender')) {
		$studentsByGender = $safeRows(function () use ($conn) {
			$stmt = $conn->prepare("SELECT gender, COUNT(*) AS count FROM tbl_students GROUP BY gender ORDER BY gender");
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});
	}

	$attendanceSummary = [
		"present" => 0,
		"absent" => 0,
		"late" => 0,
		"excused" => 0
	];

	if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
		$attendanceRows = $safeRows(function () use ($conn) {
			$stmt = $conn->prepare("SELECT r.status, COUNT(*) AS count
				FROM tbl_attendance_records r
				INNER JOIN tbl_attendance_sessions s ON s.id = r.session_id
				WHERE s.session_date = CURRENT_DATE
				GROUP BY r.status");
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});
		foreach ($attendanceRows as $row) {
			$key = $row['status'] ?? '';
			if ($key !== '') {
				$attendanceSummary[$key] = (int)$row['count'];
			}
		}
	}

	$staffAttendanceToday = 0;
	if (app_table_exists($conn, 'tbl_staff_attendance')) {
		$staffAttendanceToday = (int)$safeScalar(function () use ($conn) {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_staff_attendance WHERE attendance_date = CURRENT_DATE AND status = 'present'");
			$stmt->execute();
			return $stmt->fetchColumn();
		}, 0);
	}

	$feeSummary = [
		"open_invoices" => 0,
		"paid_invoices" => 0,
		"outstanding_total" => 0,
		"payments_today" => 0
	];
	$paymentsByDay = [];
	$paymentsByMethod = [];

	if (app_table_exists($conn, 'tbl_student_invoices')) {
		$feeSummary['open_invoices'] = (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_student_invoices WHERE status IN ('draft','sent','partial','overdue')")->fetchColumn(), 0);
		$feeSummary['paid_invoices'] = (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_student_invoices WHERE status = 'paid'")->fetchColumn(), 0);
	} elseif (app_table_exists($conn, 'tbl_invoices')) {
		$feeSummary['open_invoices'] = (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'open'")->fetchColumn(), 0);
		$feeSummary['paid_invoices'] = (int)$safeScalar(fn() => $conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'paid'")->fetchColumn(), 0);
	}

	if (app_table_exists($conn, 'tbl_student_invoices') && app_table_exists($conn, 'tbl_payments')) {
		$feeSummary['outstanding_total'] = (float)$safeScalar(function () use ($conn) {
			$stmt = $conn->prepare("SELECT COALESCE(SUM(COALESCE(balance_due, 0)), 0) FROM tbl_student_invoices WHERE status <> 'paid'");
			$stmt->execute();
			return $stmt->fetchColumn();
		}, 0);
	} elseif (app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_invoices')) {
		if (app_table_exists($conn, 'tbl_payments')) {
			$feeSummary['outstanding_total'] = (float)$safeScalar(function () use ($conn) {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(invoice_totals.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
				FROM (
					SELECT i.id, SUM(l.amount) AS total_amount
					FROM tbl_invoices i
					INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.status <> 'void'
					GROUP BY i.id
				) invoice_totals
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = invoice_totals.id
			");
				$stmt->execute();
				return $stmt->fetchColumn();
			}, 0);
		} else {
			$feeSummary['outstanding_total'] = (float)$safeScalar(function () use ($conn) {
				$stmt = $conn->prepare("
				SELECT COALESCE(SUM(l.amount), 0) AS outstanding
				FROM tbl_invoices i
				INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
				WHERE i.status <> 'void'
				");
				$stmt->execute();
				return $stmt->fetchColumn();
			}, 0);
		}
	}

	if (app_table_exists($conn, 'tbl_payments')) {
		if ($driver === 'mysql') {
			$dateExpr = app_column_exists($conn, 'tbl_payments', 'payment_date') ? "DATE(payment_date)" : "DATE(paid_at)";
			$rangeExpr = "DATE_SUB(CURDATE(), INTERVAL 6 DAY)";
			$todayExpr = "CURDATE()";
		} else {
			$dateExpr = app_column_exists($conn, 'tbl_payments', 'payment_date') ? "payment_date::date" : "paid_at::date";
			$rangeExpr = "CURRENT_DATE - INTERVAL '6 days'";
			$todayExpr = "CURRENT_DATE";
		}

		$amountColumn = app_column_exists($conn, 'tbl_payments', 'amount_paid') ? 'amount_paid' : 'amount';
		$methodColumn = app_column_exists($conn, 'tbl_payments', 'payment_method_id') ? 'payment_method_id' : 'method';
		$feeSummary['payments_today'] = (float)$safeScalar(fn() => $conn->query("SELECT COALESCE(SUM($amountColumn),0) FROM tbl_payments WHERE $dateExpr = $todayExpr")->fetchColumn(), 0);

			$paymentsByDay = $safeRows(function () use ($conn, $dateExpr, $rangeExpr, $amountColumn) {
			$stmt = $conn->prepare("SELECT $dateExpr AS day, COALESCE(SUM($amountColumn),0) AS total
				FROM tbl_payments
				WHERE $dateExpr >= $rangeExpr
				GROUP BY $dateExpr
				ORDER BY $dateExpr");
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});

		$paymentsByMethod = $safeRows(function () use ($conn, $amountColumn, $methodColumn) {
			$stmt = $conn->prepare("SELECT $methodColumn AS method, COALESCE(SUM($amountColumn),0) AS total
				FROM tbl_payments
				GROUP BY $methodColumn
				ORDER BY total DESC");
			$stmt->execute();
			return $stmt->fetchAll(PDO::FETCH_ASSOC);
		});
	}

	$boardingSummary = [
		'active_boarders' => $counts['boarders'],
		'active_dorms' => $counts['active_dorms']
	];

	$response = json_encode([
		"counts" => $counts,
		"studentsByClass" => $studentsByClass,
		"avgScoreByTerm" => $avgScoreByTerm,
		"studentsByGender" => $studentsByGender,
		"attendanceToday" => $attendanceSummary,
		"staffAttendanceToday" => $staffAttendanceToday,
		"fees" => $feeSummary,
		"boarding" => $boardingSummary,
		"paymentsByDay" => $paymentsByDay,
		"paymentsByMethod" => $paymentsByMethod
	]);
	if (!is_string($response)) {
		throw new RuntimeException('Failed to encode dashboard stats.');
	}
	@file_put_contents($cacheFile, $response, LOCK_EX);
	echo $response;
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(["error" => $e->getMessage()]);
}
