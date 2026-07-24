<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

header('Content-Type: application/json; charset=utf-8');

if ($res !== '1') {
	http_response_code(401);
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

app_require_any_permission(['report.view', 'academic.manage', 'finance.view', 'bom.view']);

function app_cc_scalar(PDO $conn, string $sql, array $params = [], $default = 0)
{
	try {
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		$value = $stmt->fetchColumn();
		return $value !== false ? $value : $default;
	} catch (Throwable $e) {
		return $default;
	}
}

function app_cc_latest_backup_status(): array
{
	$roots = [
		realpath(__DIR__ . '/../backups') ?: '',
		realpath(__DIR__ . '/../../backups') ?: '',
		realpath('/opt/lampp/backups') ?: '',
	];
	$latestFile = '';
	$latestTime = 0;
	foreach (array_filter(array_unique($roots)) as $root) {
		if (!is_dir($root)) {
			continue;
		}
		$files = @glob($root . '/*.{sql,zip,gz,bak}', GLOB_BRACE) ?: [];
		foreach ($files as $file) {
			if (!is_file($file)) {
				continue;
			}
			$mtime = @filemtime($file) ?: 0;
			if ($mtime > $latestTime) {
				$latestTime = $mtime;
				$latestFile = basename($file);
			}
		}
	}
	if ($latestTime < 1) {
		return ['status' => 'warning', 'message' => 'No backup file detected in configured backup folders.'];
	}
	$ageDays = (int)floor((time() - $latestTime) / 86400);
	if ($ageDays >= 7) {
		return ['status' => 'warning', 'message' => 'Latest backup is ' . $ageDays . ' day(s) old: ' . $latestFile];
	}
	return ['status' => 'healthy', 'message' => 'Latest backup: ' . $latestFile . ' (' . date('d M Y H:i', $latestTime) . ')'];
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$summary = [
		'academics' => 0,
		'attendance' => 0,
		'finance' => 0,
		'discipline' => 0,
		'overall' => 0,
	];

	$schoolAvg = null;
	if (app_table_exists($conn, 'tbl_exam_results')) {
		$col = app_column_exists($conn, 'tbl_exam_results', 'score') ? 'score' : (app_column_exists($conn, 'tbl_exam_results', 'marks') ? 'marks' : '');
		if ($col !== '') {
			$schoolAvg = (float)app_cc_scalar($conn, "SELECT COALESCE(AVG($col), 0) FROM tbl_exam_results", [], 0);
			$summary['academics'] = max(0, min(100, (int)round($schoolAvg)));
		}
	}

	if (app_table_exists($conn, 'tbl_attendance_records')) {
		$attendanceRate = (float)app_cc_scalar($conn, "SELECT COALESCE((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*), 0), 0) FROM tbl_attendance_records", [], 0);
		$summary['attendance'] = max(0, min(100, (int)round($attendanceRate)));
	}

	if (app_table_exists($conn, 'tbl_invoices')) {
		$openInvoices = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices WHERE status IN ('draft','sent','partial','overdue')", [], 0);
		$paidInvoices = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices WHERE status = 'paid'", [], 0);
		$totalInvoices = max(1, $openInvoices + $paidInvoices);
		$collectionRate = (($paidInvoices / $totalInvoices) * 100);
		$summary['finance'] = max(0, min(100, (int)round($collectionRate)));
	}

	if (app_table_exists($conn, 'tbl_discipline_cases')) {
		$statusColumn = app_column_exists($conn, 'tbl_discipline_cases', 'case_status') ? 'case_status' : (app_column_exists($conn, 'tbl_discipline_cases', 'status') ? 'status' : '');
		if ($statusColumn !== '') {
			$openCases = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_discipline_cases WHERE COALESCE({$statusColumn}, 'Reported') IN ('Reported','Under Investigation','Hearing Scheduled','Open','Pending')", [], 0);
			$totalCases = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_discipline_cases", [], 0);
			$resolvedRate = $totalCases > 0 ? ((($totalCases - $openCases) / $totalCases) * 100) : 100;
			$summary['discipline'] = max(0, min(100, (int)round($resolvedRate)));
		}
	}

	$summary['overall'] = (int)round(($summary['academics'] + $summary['attendance'] + $summary['finance'] + $summary['discipline']) / 4);

	$alerts = [];
	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$pendingMarks = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE status IN ('draft','submitted','rejected')", [], 0);
		if ($pendingMarks > 0) {
			$alerts[] = ['severity' => 'warning', 'title' => 'Marks pending', 'detail' => $pendingMarks . ' mark submission(s) still need review or completion.'];
		}
	}
	if (app_table_exists($conn, 'tbl_invoices')) {
		$overdueFees = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices WHERE status IN ('partial','overdue')", [], 0);
		if ($overdueFees > 0) {
			$alerts[] = ['severity' => 'warning', 'title' => 'Overdue fee balances', 'detail' => $overdueFees . ' invoice(s) are overdue or partially paid.'];
		}
	}
	if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_classes')) {
		$missingAttendance = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_classes c LEFT JOIN tbl_attendance_sessions s ON s.class_id = c.id AND s.session_date = CURRENT_DATE AND COALESCE(s.session_type, 'daily') = 'daily' WHERE s.id IS NULL", [], 0);
		if ($missingAttendance > 0) {
			$alerts[] = ['severity' => 'warning', 'title' => 'Attendance missing', 'detail' => $missingAttendance . ' class(es) have not submitted attendance today.'];
		}
	}
	if (app_table_exists($conn, 'tbl_audit_logs')) {
		$markEdits = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_audit_logs WHERE action LIKE 'exam%' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)", [], 0);
		if ($markEdits >= 25) {
			$alerts[] = ['severity' => 'danger', 'title' => 'Unusual marks activity', 'detail' => $markEdits . ' exam-related audit actions were recorded in the last 24 hours.'];
		}
	}

	$riskLearners = [];
	if (app_table_exists($conn, 'tbl_students') && app_table_exists($conn, 'tbl_attendance_records')) {
		$stmt = $conn->prepare("SELECT st.id, CONCAT(st.fname, ' ', st.lname) AS learner_name,
			COALESCE((SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(ar.id), 0), 0) AS attendance_rate
			FROM tbl_students st
			JOIN tbl_attendance_records ar ON ar.student_id = st.id
			GROUP BY st.id, st.fname, st.lname
			HAVING COUNT(ar.id) >= 3 AND attendance_rate < 80
			ORDER BY attendance_rate ASC
			LIMIT 5");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$riskLearners[] = [
				'name' => (string)$row['learner_name'],
				'reason' => 'Poor attendance',
				'metric' => round((float)$row['attendance_rate'], 1) . '% attendance',
			];
		}
	}

	if (empty($riskLearners) && app_table_exists($conn, 'tbl_discipline_cases')) {
		$studentColumn = app_column_exists($conn, 'tbl_discipline_cases', 'student_id') ? 'student_id' : (app_column_exists($conn, 'tbl_discipline_cases', 'student') ? 'student' : '');
		if ($studentColumn !== '') {
			$stmt = $conn->prepare("SELECT CONCAT(st.fname, ' ', st.lname) AS learner_name, COUNT(*) AS case_count
				FROM tbl_discipline_cases dc
				JOIN tbl_students st ON st.id = dc.{$studentColumn}
				GROUP BY st.id, st.fname, st.lname
				HAVING COUNT(*) >= 2
				ORDER BY case_count DESC
				LIMIT 5");
			$stmt->execute();
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$riskLearners[] = [
					'name' => (string)$row['learner_name'],
					'reason' => 'Repeated discipline cases',
					'metric' => (int)$row['case_count'] . ' cases',
				];
			}
		}
	}

	$predictions = [];
	$predictions[] = ['title' => 'School health score', 'value' => $summary['overall'] . '%', 'detail' => 'Combined signal from academics, attendance, finance, and discipline.'];
	if ($schoolAvg !== null) {
		$predictions[] = ['title' => 'Predicted mean performance', 'value' => round((float)$schoolAvg, 1) . '%', 'detail' => 'Estimated from current stored exam result averages.'];
	}
	if (app_table_exists($conn, 'tbl_invoices')) {
		$openInvoices = (int)app_cc_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices WHERE status IN ('draft','sent','partial','overdue')", [], 0);
		$predictions[] = ['title' => 'Fee collection risk', 'value' => $openInvoices . ' open invoices', 'detail' => 'Collections pressure is likely to stay high until overdue balances drop.'];
	}
	$backup = app_cc_latest_backup_status();
	$predictions[] = ['title' => 'Backup posture', 'value' => $backup['status'] === 'healthy' ? 'Healthy' : 'Needs attention', 'detail' => $backup['message']];

	$timeline = [];
	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, created_at FROM tbl_notifications ORDER BY created_at DESC LIMIT 6");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$timeline[] = [
				'time' => (string)$row['created_at'],
				'title' => (string)$row['title'],
				'detail' => (string)$row['message'],
			];
		}
	}

	$systemStatus = [
		'database' => 'healthy',
		'notifications' => app_table_exists($conn, 'tbl_notifications') ? 'ready' : 'missing',
		'ai_provider' => 'configured',
		'backup_warning' => $backup['message'],
	];

	echo json_encode([
		'ok' => true,
		'summary' => $summary,
		'alerts' => array_slice($alerts, 0, 8),
		'risk_learners' => array_slice($riskLearners, 0, 8),
		'predictions' => array_slice($predictions, 0, 6),
		'timeline' => array_slice($timeline, 0, 8),
		'system_status' => $systemStatus,
	]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => 'Unable to load AI command center.', 'details' => $e->getMessage()]);
}
