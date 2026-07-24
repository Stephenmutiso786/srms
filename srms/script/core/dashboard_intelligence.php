<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

header('Content-Type: application/json; charset=utf-8');

if ($res !== '1') {
	http_response_code(401);
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

function app_di_portal_from_level(int $level): string
{
	if ($level === 3) return 'student';
	if ($level === 4) return 'parent';
	if ($level === 2) return 'teacher';
	if ($level === 1) return 'academic';
	if ($level === 5) return 'accountant';
	if ($level === 10) return 'bom';
	return 'admin';
}

function app_di_scalar(PDO $conn, string $sql, array $params = [], $default = 0)
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

function app_di_add_card(array &$cards, string $label, string $value, string $tone = 'info', string $detail = ''): void
{
	$cards[] = [
		'label' => $label,
		'value' => $value,
		'tone' => $tone,
		'detail' => $detail,
	];
}

function app_di_add_rec(array &$recommendations, string $title, string $detail, string $tone = 'info'): void
{
	$recommendations[] = [
		'title' => $title,
		'detail' => $detail,
		'tone' => $tone,
	];
}

function app_di_latest_backup_status(): array
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
	return ['status' => 'success', 'message' => 'Latest backup: ' . $latestFile . ' (' . date('d M Y H:i', $latestTime) . ')'];
}

function app_di_resolve_active_term_id(PDO $conn): int
{
	$settingTermId = (int)app_setting_get($conn, 'current_term_id', '0');
	if ($settingTermId > 0 && app_table_exists($conn, 'tbl_terms')) {
		$exists = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_terms WHERE id = ?", [$settingTermId], 0);
		if ($exists > 0) {
			return $settingTermId;
		}
	}

	if (app_table_exists($conn, 'tbl_terms') && app_column_exists($conn, 'tbl_terms', 'status')) {
		$activeTermId = (int)app_di_scalar($conn, "SELECT id FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1", [], 0);
		if ($activeTermId > 0) {
			return $activeTermId;
		}
	}

	if (app_table_exists($conn, 'tbl_exams') && app_column_exists($conn, 'tbl_exams', 'term_id')) {
		$publishedTermId = (int)app_di_scalar($conn, "SELECT term_id FROM tbl_exams WHERE status = 'published' AND term_id IS NOT NULL ORDER BY id DESC LIMIT 1", [], 0);
		if ($publishedTermId > 0) {
			return $publishedTermId;
		}
	}

	return $settingTermId;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$portal = app_di_portal_from_level((int)$level);
	$userId = (int)($account_id ?? 0);
	$cards = [];
	$recommendations = [];
	$timeline = [];

	if (app_table_exists($conn, 'tbl_notifications')) {
		$sql = "SELECT title, message, created_at FROM tbl_notifications WHERE audience IN ('all','staff') ORDER BY created_at DESC LIMIT 5";
		$params = [];
		if ($portal === 'student') {
			$classId = (int)app_di_scalar($conn, "SELECT class FROM tbl_students WHERE id = ? LIMIT 1", [$userId], 0);
			$sql = "SELECT title, message, created_at FROM tbl_notifications WHERE audience IN ('all','students') OR (audience = 'class' AND class_id = ?) ORDER BY created_at DESC LIMIT 5";
			$params = [$classId];
		} elseif ($portal === 'parent') {
			$sql = "SELECT title, message, created_at FROM tbl_notifications WHERE audience IN ('all','parents') ORDER BY created_at DESC LIMIT 5";
		} elseif ($portal === 'accountant') {
			$sql = "SELECT title, message, created_at FROM tbl_notifications WHERE audience IN ('all','staff') ORDER BY created_at DESC LIMIT 5";
		}
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$timeline[] = [
				'title' => (string)$row['title'],
				'detail' => (string)$row['message'],
				'time' => (string)$row['created_at'],
			];
		}
	}

	if (in_array($portal, ['admin', 'academic', 'bom'], true)) {
		$currentTermId = app_di_resolve_active_term_id($conn);
		$currentAcademicYear = trim(app_setting_get($conn, 'current_academic_year', date('Y')));

		$avgScore = 0.0;
		$hasAcademicData = false;
		if (app_table_exists($conn, 'tbl_exam_results')) {
			$scoreColumn = app_column_exists($conn, 'tbl_exam_results', 'score') ? 'score' : (app_column_exists($conn, 'tbl_exam_results', 'marks') ? 'marks' : '');
			if ($scoreColumn !== '') {
				if ($currentTermId > 0 && app_column_exists($conn, 'tbl_exam_results', 'term')) {
					$hasAcademicData = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_exam_results WHERE term = ? AND $scoreColumn IS NOT NULL", [$currentTermId], 0) > 0;
					$avgScore = (float)app_di_scalar($conn, "SELECT COALESCE(AVG($scoreColumn), 0) FROM tbl_exam_results WHERE term = ?", [$currentTermId], 0);
				} else {
					$hasAcademicData = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_exam_results WHERE $scoreColumn IS NOT NULL", [], 0) > 0;
					$avgScore = (float)app_di_scalar($conn, "SELECT COALESCE(AVG($scoreColumn), 0) FROM tbl_exam_results", [], 0);
				}
			}
		}
		$attendanceRate = 0.0;
		$hasAttendanceData = false;
		$attendanceCoverage = null;
		$attendanceCoverageCount = 0;
		$totalClasses = app_table_exists($conn, 'tbl_classes')
			? (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_classes", [], 0)
			: 0;
		if (app_table_exists($conn, 'tbl_attendance_sessions') && $totalClasses > 0) {
			$attendanceCoverageCount = (int)app_di_scalar(
				$conn,
				"SELECT COUNT(DISTINCT class_id) FROM tbl_attendance_sessions WHERE session_type = 'daily' AND subject_id IS NULL AND session_date = CURDATE()",
				[],
				0
			);
			$attendanceCoverage = ($attendanceCoverageCount / max(1, $totalClasses)) * 100.0;
			$hasAttendanceData = true;
			$attendanceRate = $attendanceCoverage;
		} elseif (app_table_exists($conn, 'tbl_attendance_records')) {
			$hasAttendanceData = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_attendance_records", [], 0) > 0;
			$attendanceRate = (float)app_di_scalar($conn, "SELECT COALESCE((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*), 0), 0) FROM tbl_attendance_records", [], 0);
		}
		$openInvoices = 0;
		$collectionRate = 100.0;
		$hasFinanceData = false;
		if (app_table_exists($conn, 'tbl_invoices')) {
			$invoiceParams = [];
			$invoiceWhere = '';
			if ($currentTermId > 0 && app_column_exists($conn, 'tbl_invoices', 'term_id')) {
				$invoiceWhere = ' WHERE term_id = ?';
				$invoiceParams[] = $currentTermId;
			}
			$openInvoices = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices" . $invoiceWhere . ($invoiceWhere === '' ? ' WHERE ' : ' AND ') . "status IN ('draft','sent','partial','overdue')", $invoiceParams, 0);
			$paidInvoices = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices" . $invoiceWhere . ($invoiceWhere === '' ? ' WHERE ' : ' AND ') . "status = 'paid'", $invoiceParams, 0);
			$totalInvoices = $openInvoices + $paidInvoices;
			$hasFinanceData = $totalInvoices > 0;
			if ($totalInvoices > 0) {
				$collectionRate = ($paidInvoices / $totalInvoices) * 100;
			}
		}
		$pendingMarks = 0;
		$workflowScore = 100.0;
		$hasWorkflowData = false;
		if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
			$markParams = [];
			$markWhere = " WHERE status IN ('draft','submitted','rejected')";
			if ($currentTermId > 0 && app_column_exists($conn, 'tbl_exam_mark_submissions', 'term_id')) {
				$markWhere .= " AND term_id = ?";
				$markParams[] = $currentTermId;
			}
			$pendingMarks = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_exam_mark_submissions" . $markWhere, $markParams, 0);
			$totalMarkRows = (int)app_di_scalar(
				$conn,
				"SELECT COUNT(*) FROM tbl_exam_mark_submissions" . ($currentTermId > 0 && app_column_exists($conn, 'tbl_exam_mark_submissions', 'term_id') ? " WHERE term_id = ?" : ''),
				$currentTermId > 0 && app_column_exists($conn, 'tbl_exam_mark_submissions', 'term_id') ? [$currentTermId] : [],
				0
			);
			$hasWorkflowData = $totalMarkRows > 0;
			if ($totalMarkRows > 0) {
				$workflowScore = max(0.0, 100.0 - (($pendingMarks / max(1, $totalMarkRows)) * 100.0));
			}
		}

		$healthSignals = [];
		if ($hasWorkflowData) {
			$healthSignals[] = round($workflowScore);
		}
		if ($hasAcademicData) {
			$healthSignals[] = round($avgScore);
		}
		if ($hasAttendanceData) {
			$healthSignals[] = round($attendanceRate);
		}
		if ($hasFinanceData) {
			$healthSignals[] = round($collectionRate);
		}
		$health = count($healthSignals) > 0 ? (int)round(array_sum($healthSignals) / count($healthSignals)) : 100;
		app_di_add_card($cards, 'School Health', $health . '%', $health >= 75 ? 'success' : ($health >= 50 ? 'warning' : 'danger'), 'Combined from current real signals only: attendance coverage, marks workflow, finance, and stored results.');
		app_di_add_card($cards, 'Average Score', round($avgScore, 1) . '%', !$hasAcademicData ? 'info' : ($avgScore >= 60 ? 'success' : 'warning'), $hasAcademicData ? 'Current mean from stored results for the active term.' : 'No stored results yet for the active term.');
		app_di_add_card($cards, 'Attendance', round($attendanceRate, 1) . '%', !$hasAttendanceData ? 'info' : ($attendanceRate >= 85 ? 'success' : 'warning'), $attendanceCoverage !== null ? ($attendanceCoverageCount . ' of ' . $totalClasses . ' classes have a daily attendance session today.') : ($hasAttendanceData ? 'Attendance signal from attendance records.' : 'No attendance records yet.'));
		app_di_add_card($cards, 'Open Invoices', (string)$openInvoices, $openInvoices > 0 ? 'warning' : 'success', 'Finance pressure still pending collection.');
		app_di_add_card($cards, 'Marks Pending', (string)$pendingMarks, $pendingMarks > 0 ? 'warning' : 'success', $hasWorkflowData ? 'Current active-term submissions still waiting completion or review.' : 'No current marks workflow items yet.');

		if ($pendingMarks > 0) {
			app_di_add_rec($recommendations, 'Prioritize marks workflow', $pendingMarks . ' mark submission(s) are still blocking complete academic reporting.', 'warning');
		}
		if ($attendanceRate < 85) {
			app_di_add_rec($recommendations, 'Strengthen attendance follow-up', $attendanceCoverage !== null ? ('Only ' . $attendanceCoverageCount . ' of ' . $totalClasses . ' classes have taken daily attendance today.') : 'Attendance has dropped below the 85% healthy threshold.', 'warning');
		}
		if ($collectionRate < 70 && $openInvoices > 0) {
			app_di_add_rec($recommendations, 'Push fee follow-ups', 'Fee collection is below 70%. Consider reminders for overdue families.', 'warning');
		}
		$backup = app_di_latest_backup_status();
		app_di_add_rec($recommendations, 'Backup monitor', $backup['message'], $backup['status']);
		if (app_table_exists($conn, 'tbl_audit_logs')) {
			$markEdits = (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_audit_logs WHERE action LIKE 'exam%' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)", [], 0);
			if ($markEdits >= 25) {
				app_di_add_rec($recommendations, 'Anomaly watch', 'High marks-edit activity detected in the last 24 hours (' . $markEdits . ' actions). Review audit logs.', 'danger');
			}
		}
	} elseif ($portal === 'accountant') {
		$openInvoices = app_table_exists($conn, 'tbl_invoices')
			? (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices WHERE status IN ('draft','sent','partial','overdue')", [], 0)
			: 0;
		$overdueInvoices = app_table_exists($conn, 'tbl_invoices')
			? (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_invoices WHERE status = 'overdue'", [], 0)
			: 0;
		$paymentsToday = app_table_exists($conn, 'tbl_payments')
			? (float)app_di_scalar($conn, "SELECT COALESCE(SUM(amount), 0) FROM tbl_payments WHERE DATE(created_at) = CURDATE()", [], 0)
			: 0.0;
		$outstanding = 0.0;
		if (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_payments')) {
			$outstanding = (float)app_di_scalar($conn, "SELECT COALESCE(SUM(inv.total_amount - COALESCE(pay.total_paid, 0)), 0)
				FROM (
					SELECT i.id, SUM(l.amount) AS total_amount
					FROM tbl_invoices i
					JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.status <> 'void'
					GROUP BY i.id
				) inv
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) pay ON pay.invoice_id = inv.id", [], 0);
		}
		app_di_add_card($cards, 'Open Invoices', (string)$openInvoices, $openInvoices > 0 ? 'warning' : 'success', 'Invoices still not fully settled.');
		app_di_add_card($cards, 'Overdue Invoices', (string)$overdueInvoices, $overdueInvoices > 0 ? 'danger' : 'success', 'Highest-priority balances requiring follow-up.');
		app_di_add_card($cards, 'Payments Today', number_format($paymentsToday, 2), 'success', 'Recorded collections for today.');
		app_di_add_card($cards, 'Outstanding', number_format($outstanding, 2), $outstanding > 0 ? 'warning' : 'success', 'Remaining unpaid balances across invoices.');
		if ($overdueInvoices > 0) {
			app_di_add_rec($recommendations, 'Send fee reminders', $overdueInvoices . ' overdue invoice(s) need immediate follow-up.', 'warning');
		}
		if ($paymentsToday <= 0) {
			app_di_add_rec($recommendations, 'Monitor today’s collections', 'No payment has been recorded yet today.', 'info');
		}
	} elseif ($portal === 'teacher') {
		$assignedClasses = 0;
		$assignedSubjects = 0;
		$classIds = [];
		if (app_table_exists($conn, 'tbl_teacher_assignments')) {
			$assignedClasses = (int)app_di_scalar($conn, "SELECT COUNT(DISTINCT class_id) FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1 AND year = ?", [$userId, (int)date('Y')], 0);
			$assignedSubjects = (int)app_di_scalar($conn, "SELECT COUNT(DISTINCT subject_id) FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1 AND year = ?", [$userId, (int)date('Y')], 0);
			$stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1 AND year = ?");
			$stmt->execute([$userId, (int)date('Y')]);
			$classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		}
		$pendingMarks = app_table_exists($conn, 'tbl_exam_mark_submissions')
			? (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE teacher_id = ? AND status IN ('draft','submitted')", [$userId], 0)
			: 0;
		$rejectedMarks = app_table_exists($conn, 'tbl_exam_mark_submissions')
			? (int)app_di_scalar($conn, "SELECT COUNT(*) FROM tbl_exam_mark_submissions WHERE teacher_id = ? AND status = 'rejected'", [$userId], 0)
			: 0;
		$classMean = 0.0;
		if ($classIds && app_table_exists($conn, 'tbl_exam_results')) {
			$placeholders = implode(',', array_fill(0, count($classIds), '?'));
			$classMean = (float)app_di_scalar($conn, "SELECT COALESCE(AVG(score), 0) FROM tbl_exam_results WHERE class IN ($placeholders)", $classIds, 0);
		}
		app_di_add_card($cards, 'Assigned Classes', (string)$assignedClasses, 'info', 'Classes currently assigned to you.');
		app_di_add_card($cards, 'Subjects', (string)$assignedSubjects, 'info', 'Subjects currently allocated this year.');
		app_di_add_card($cards, 'Pending Marks', (string)$pendingMarks, $pendingMarks > 0 ? 'warning' : 'success', 'Marks still in draft or submitted state.');
		app_di_add_card($cards, 'Rejected Marks', (string)$rejectedMarks, $rejectedMarks > 0 ? 'danger' : 'success', 'Submissions that need correction.');
		app_di_add_card($cards, 'Class Mean', round($classMean, 1) . '%', $classMean >= 60 ? 'success' : 'warning', 'Approximate mean across your active classes.');
		if ($pendingMarks > 0) {
			app_di_add_rec($recommendations, 'Complete pending marks', 'You still have ' . $pendingMarks . ' mark workflow item(s) pending.', 'warning');
		}
		if ($rejectedMarks > 0) {
			app_di_add_rec($recommendations, 'Correct rejected submissions', 'Review and re-submit rejected marks before report processing.', 'danger');
		}
		if ($classMean > 0 && $classMean < 55) {
			app_di_add_rec($recommendations, 'Plan remedial support', 'Average performance is below 55%. Consider targeted revision on weak topics.', 'warning');
		}
	} elseif ($portal === 'student') {
		$classId = (int)app_di_scalar($conn, "SELECT class FROM tbl_students WHERE id = ? LIMIT 1", [$userId], 0);
		$attendanceRate = 0.0;
		if (app_table_exists($conn, 'tbl_attendance_records')) {
			$attendanceRate = (float)app_di_scalar($conn, "SELECT COALESCE((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*), 0), 0) FROM tbl_attendance_records WHERE student_id = ?", [$userId], 0);
		}
		$mean = 0.0;
		$scoreColumn = app_table_exists($conn, 'tbl_exam_results') && app_column_exists($conn, 'tbl_exam_results', 'score') ? 'score' : '';
		if ($scoreColumn !== '') {
			$mean = (float)app_di_scalar($conn, "SELECT COALESCE(AVG(score), 0) FROM tbl_exam_results WHERE student = ?", [$userId], 0);
		}
		$feeBalance = report_fees_balance($conn, (string)$userId, 0);
		$publishedTerms = app_table_exists($conn, 'tbl_exams') ? (int)app_di_scalar($conn, "SELECT COUNT(DISTINCT term_id) FROM tbl_exams WHERE class_id = ? AND status = 'published'", [$classId], 0) : 0;
		app_di_add_card($cards, 'Attendance', round($attendanceRate, 1) . '%', $attendanceRate >= 85 ? 'success' : 'warning', 'Your attendance trend.');
		app_di_add_card($cards, 'Average Score', round($mean, 1) . '%', $mean >= 60 ? 'success' : 'warning', 'Your current average from published records.');
		app_di_add_card($cards, 'Fee Balance', number_format((float)$feeBalance, 2), $feeBalance > 0 ? 'warning' : 'success', 'Outstanding balance linked to your account.');
		app_di_add_card($cards, 'Published Terms', (string)$publishedTerms, 'info', 'Terms currently available for review.');
		if ($attendanceRate < 85) {
			app_di_add_rec($recommendations, 'Improve attendance consistency', 'Your attendance has dropped below the healthy level.', 'warning');
		}
		if ($mean > 0 && $mean < 55) {
			app_di_add_rec($recommendations, 'Revise weak subjects with Edu AI', 'Your average is below 55%. Use the study assistant for targeted revision.', 'warning');
		}
		if ($feeBalance > 0) {
			app_di_add_rec($recommendations, 'Check fee balance with your parent or guardian', 'There is an outstanding balance linked to your learner account.', 'info');
		}
	} elseif ($portal === 'parent') {
		$children = [];
		if (app_table_exists($conn, 'tbl_parent_students')) {
			$stmt = $conn->prepare("SELECT student_id FROM tbl_parent_students WHERE parent_id = ?");
			$stmt->execute([$userId]);
			$children = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
		}
		$childCount = count($children);
		$attendanceRate = 0.0;
		$mean = 0.0;
		$feeBalance = 0.0;
		if ($children) {
			$placeholders = implode(',', array_fill(0, $childCount, '?'));
			if (app_table_exists($conn, 'tbl_attendance_records')) {
				$attendanceRate = (float)app_di_scalar($conn, "SELECT COALESCE((SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) * 100.0) / NULLIF(COUNT(*), 0), 0) FROM tbl_attendance_records WHERE student_id IN ($placeholders)", $children, 0);
			}
			if (app_table_exists($conn, 'tbl_exam_results') && app_column_exists($conn, 'tbl_exam_results', 'score')) {
				$mean = (float)app_di_scalar($conn, "SELECT COALESCE(AVG(score), 0) FROM tbl_exam_results WHERE student IN ($placeholders)", $children, 0);
			}
			foreach ($children as $studentId) {
				$feeBalance += (float)report_fees_balance($conn, (string)$studentId, 0);
			}
		}
		app_di_add_card($cards, 'Children', (string)$childCount, 'info', 'Learners linked to your portal.');
		app_di_add_card($cards, 'Attendance', round($attendanceRate, 1) . '%', $attendanceRate >= 85 ? 'success' : 'warning', 'Combined attendance trend across your children.');
		app_di_add_card($cards, 'Average Score', round($mean, 1) . '%', $mean >= 60 ? 'success' : 'warning', 'Combined academic signal from stored results.');
		app_di_add_card($cards, 'Fee Balance', number_format($feeBalance, 2), $feeBalance > 0 ? 'warning' : 'success', 'Outstanding fee balance across linked learners.');
		if ($attendanceRate < 85) {
			app_di_add_rec($recommendations, 'Follow up on attendance early', 'Attendance is below the target range for at least one linked learner.', 'warning');
		}
		if ($feeBalance > 0) {
			app_di_add_rec($recommendations, 'Clear outstanding fee balance', 'There is an active fee balance that may affect services or report access.', 'warning');
		}
		if ($mean > 0 && $mean < 55) {
			app_di_add_rec($recommendations, 'Plan extra study support', 'Performance is below 55%. Use Edu AI and teacher consultation for targeted support.', 'warning');
		}
	}

	if (empty($recommendations)) {
		app_di_add_rec($recommendations, 'System looks steady', 'No major risk signal stands out right now. Keep monitoring the live dashboard.', 'success');
	}

	echo json_encode([
		'ok' => true,
		'portal' => $portal,
		'cards' => array_slice($cards, 0, 6),
		'recommendations' => array_slice($recommendations, 0, 6),
		'timeline' => array_slice($timeline, 0, 5),
	]);
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'message' => 'Unable to load dashboard intelligence.', 'details' => $e->getMessage()]);
}
