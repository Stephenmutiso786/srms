<?php
require_once('db/config.php');

function report_get_settings(PDO $conn): array
{
	$settings = [
		'best_of' => 0,
		'use_weights' => 1,
		'require_fees_clear' => 0,
	];
	if (!app_table_exists($conn, 'tbl_result_settings')) {
		return $settings;
	}
	try {
		$stmt = $conn->prepare("SELECT best_of, use_weights, require_fees_clear FROM tbl_result_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$settings['best_of'] = (int)$row['best_of'];
			$settings['use_weights'] = (int)$row['use_weights'];
			$settings['require_fees_clear'] = (int)$row['require_fees_clear'];
		}
	} catch (Throwable $e) {
		return $settings;
	}
	return $settings;
}

function report_get_weight_map(PDO $conn): array
{
	$weights = [];
	if (!app_table_exists($conn, 'tbl_subject_weights')) {
		return $weights;
	}
	$stmt = $conn->prepare("SELECT subject_id, weight FROM tbl_subject_weights");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$weights[(int)$row['subject_id']] = (float)$row['weight'];
	}
	return $weights;
}

function report_grade_for_score(PDO $conn, float $score): array
{
	$grade = 'E';
	$remark = 'Needs improvement';
	if (!app_table_exists($conn, 'tbl_grade_system')) {
		return [$grade, $remark];
	}
	$stmt = $conn->prepare("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		if ($score >= (float)$row['min'] && $score <= (float)$row['max']) {
			$grade = $row['name'];
			$remark = $row['remark'];
			return [$grade, $remark];
		}
	}
	return [$grade, $remark];
}

function report_fetch_subjects_for_class(PDO $conn, int $classId): array
{
	$stmt = $conn->prepare("SELECT sc.id AS combination_id, sc.class, sc.subject, sc.teacher, s.name AS subject_name, st.fname, st.lname
		FROM tbl_subject_combinations sc
		LEFT JOIN tbl_subjects s ON s.id = sc.subject
		LEFT JOIN tbl_staff st ON st.id = sc.teacher");
	$stmt->execute();
	$subjects = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$classList = app_unserialize($row['class']);
		if (in_array((string)$classId, $classList, true) || in_array($classId, $classList, true)) {
			$subjects[] = $row;
		}
	}
	return $subjects;
}

function report_fetch_scores(PDO $conn, string $studentId, int $classId, int $termId, array $subjects): array
{
	$scores = [];
	foreach ($subjects as $subject) {
		$score = 0;
		$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? LIMIT 1");
		$stmt->execute([$classId, $subject['combination_id'], $termId, $studentId]);
		$value = $stmt->fetchColumn();
		if ($value !== false && $value !== null && $value !== '') {
			$score = (float)$value;
		}
		$scores[] = [
			'subject_id' => (int)$subject['subject'],
			'subject_name' => $subject['subject_name'],
			'teacher_id' => $subject['teacher'] ? (int)$subject['teacher'] : null,
			'teacher_name' => trim(($subject['fname'] ?? '') . ' ' . ($subject['lname'] ?? '')),
			'score' => $score
		];
	}
	return $scores;
}

function report_compute_totals(PDO $conn, array $scores, array $weights, array $settings): array
{
	$rows = [];
	foreach ($scores as $row) {
		$weight = 1.0;
		if (!empty($settings['use_weights']) && isset($weights[$row['subject_id']])) {
			$weight = (float)$weights[$row['subject_id']];
		}
		$weighted = $row['score'] * $weight;
		$rows[] = $row + ['weight' => $weight, 'weighted_score' => $weighted];
	}
	usort($rows, function ($a, $b) {
		return $b['weighted_score'] <=> $a['weighted_score'];
	});

	$bestOf = (int)$settings['best_of'];
	if ($bestOf > 0 && count($rows) > $bestOf) {
		$rows = array_slice($rows, 0, $bestOf);
	}

	$total = 0;
	foreach ($rows as $row) {
		$total += $row['weighted_score'];
	}
	$count = count($rows);
	$mean = $count > 0 ? round($total / $count, 2) : 0;

	list($grade, $remark) = report_grade_for_score($conn, $mean);

	return [
		'rows' => $rows,
		'total' => round($total, 2),
		'mean' => $mean,
		'grade' => $grade,
		'remark' => $remark
	];
}

function report_fees_balance(PDO $conn, string $studentId, int $termId): float
{
	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines')) {
		return 0;
	}
	$stmt = $conn->prepare("SELECT id FROM tbl_invoices WHERE student_id = ? AND term_id = ? AND status <> 'void' LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	$invoiceId = $stmt->fetchColumn();
	if (!$invoiceId) {
		return 0;
	}
	$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_invoice_lines WHERE invoice_id = ?");
	$stmt->execute([$invoiceId]);
	$total = (float)$stmt->fetchColumn();
	$paid = 0;
	if (app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE invoice_id = ?");
		$stmt->execute([$invoiceId]);
		$paid = (float)$stmt->fetchColumn();
	}
	return max(0, round($total - $paid, 2));
}

function report_attendance_summary(PDO $conn, string $studentId, int $classId, int $termId): array
{
	$summary = ['days_open' => 0, 'present' => 0, 'absent' => 0];
	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		return $summary;
	}
	$stmt = $conn->prepare("SELECT id FROM tbl_attendance_sessions WHERE class_id = ? AND term_id = ?");
	$stmt->execute([$classId, $termId]);
	$sessionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
	if (!$sessionIds) {
		return $summary;
	}
	$summary['days_open'] = count($sessionIds);
	$placeholders = implode(',', array_fill(0, count($sessionIds), '?'));
	$params = $sessionIds;
	$params[] = $studentId;
	$stmt = $conn->prepare("SELECT status, COUNT(*) AS count FROM tbl_attendance_records WHERE session_id IN ($placeholders) AND student_id = ? GROUP BY status");
	$stmt->execute($params);
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		if ($row['status'] === 'present') {
			$summary['present'] = (int)$row['count'];
		}
		if ($row['status'] === 'absent') {
			$summary['absent'] = (int)$row['count'];
		}
	}
	return $summary;
}

function report_trend(PDO $conn, string $studentId, int $currentTermId, float $mean): string
{
	if (!app_table_exists($conn, 'tbl_report_cards')) {
		return 'New';
	}
	$stmt = $conn->prepare("SELECT term_id, mean FROM tbl_report_cards WHERE student_id = ? AND term_id < ? ORDER BY term_id DESC LIMIT 1");
	$stmt->execute([$studentId, $currentTermId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$row) {
		return 'New';
	}
	$prevMean = (float)$row['mean'];
	if ($mean > $prevMean) {
		return 'Improved';
	}
	if ($mean < $prevMean) {
		return 'Dropped';
	}
	return 'No change';
}

function report_generate_code(string $studentId): string
{
	$year = date('Y');
	$rand = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
	return 'ELIMU-' . $year . '-' . $studentId . '-' . $rand;
}

function report_generate_hash(array $payload): string
{
	$secret = APP_SECRET !== '' ? APP_SECRET : 'elimu-hub';
	$raw = json_encode($payload) . '|' . $secret;
	return hash('sha256', $raw);
}

function report_compute_for_student(PDO $conn, string $studentId, int $classId, int $termId): array
{
	$settings = report_get_settings($conn);
	$weights = report_get_weight_map($conn);
	$subjects = report_fetch_subjects_for_class($conn, $classId);
	$scores = report_fetch_scores($conn, $studentId, $classId, $termId, $subjects);
	$totals = report_compute_totals($conn, $scores, $weights, $settings);
	$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
	$fees = report_fees_balance($conn, $studentId, $termId);
	$trend = report_trend($conn, $studentId, $termId, $totals['mean']);

	return [
		'subjects' => $totals['rows'],
		'total' => $totals['total'],
		'mean' => $totals['mean'],
		'grade' => $totals['grade'],
		'remark' => $totals['remark'],
		'attendance' => $attendance,
		'fees_balance' => $fees,
		'trend' => $trend,
		'settings' => $settings
	];
}

function report_rank_students(PDO $conn, int $classId, int $termId): array
{
	$stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
	$stmt->execute([$classId]);
	$students = $stmt->fetchAll(PDO::FETCH_COLUMN);
	$rankings = [];
	foreach ($students as $studentId) {
		$report = report_compute_for_student($conn, (string)$studentId, $classId, $termId);
		$rankings[] = [
			'student_id' => (string)$studentId,
			'total' => $report['total']
		];
	}
	usort($rankings, function ($a, $b) {
		return $b['total'] <=> $a['total'];
	});
	$positions = [];
	$position = 0;
	$prevTotal = null;
	foreach ($rankings as $index => $row) {
		if ($prevTotal === null || $row['total'] != $prevTotal) {
			$position = $index + 1;
			$prevTotal = $row['total'];
		}
		$positions[$row['student_id']] = $position;
	}
	return [
		'positions' => $positions,
		'total_students' => count($students)
	];
}

function report_load_card(PDO $conn, int $reportId): ?array
{
	if (!app_table_exists($conn, 'tbl_report_cards')) {
		return null;
	}
	$stmt = $conn->prepare("SELECT * FROM tbl_report_cards WHERE id = ? LIMIT 1");
	$stmt->execute([$reportId]);
	$card = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$card) {
		return null;
	}
	$subjects = [];
	if (app_table_exists($conn, 'tbl_report_card_subjects')) {
		$stmt = $conn->prepare("SELECT r.subject_id, r.score, r.grade, r.weight, s.name AS subject_name, st.fname, st.lname
			FROM tbl_report_card_subjects r
			LEFT JOIN tbl_subjects s ON s.id = r.subject_id
			LEFT JOIN tbl_staff st ON st.id = r.teacher_id
			WHERE r.report_id = ?
			ORDER BY s.name");
		$stmt->execute([$reportId]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$subjects[] = [
				'subject_name' => $row['subject_name'],
				'score' => (float)$row['score'],
				'grade' => $row['grade'],
				'weight' => (float)$row['weight'],
				'teacher_name' => trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? '')),
			];
		}
	}
	$card['subjects'] = $subjects;
	return $card;
}
