<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}
require_once(__DIR__ . '/../../db/config.php');
require_once(__DIR__ . '/../../const/school.php');
require_once(__DIR__ . '/../../const/check_session.php');
require_once(__DIR__ . '/../../const/rbac.php');
require_once(__DIR__ . '/../../const/report_engine.php');
require_once(__DIR__ . '/../../const/report_pdf_template.php');
require_once(__DIR__ . '/../../tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '0') { header('location:../'); exit; }
app_require_permission('report.generate', '../report');
app_require_unlocked('reports', '../report');

$termId = (int)($_GET['batch_term_id'] ?? $_GET['list_term_id'] ?? 0);
$listClassId = (int)($_GET['list_class_id'] ?? 0);
$listExamId = (int)($_GET['list_exam_id'] ?? 0);
$batchExamName = trim((string)($_GET['batch_exam_name'] ?? ''));
$batchExamType = trim((string)($_GET['batch_exam_type'] ?? ''));
$selectedClassIds = isset($_GET['class_ids']) && is_array($_GET['class_ids']) ? array_values(array_unique(array_filter(array_map('intval', $_GET['class_ids'])))) : [];
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] !== '0';
$triggerPrint = isset($_GET['print']) && (string)$_GET['print'] !== '0';

if ($termId < 1) {
	$_SESSION['reply'] = array(array('warning', 'Select a report batch first.'));
	header('location:../report');
	exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '-1');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	report_ensure_exam_batch_schema($conn);

	if (!app_table_exists($conn, 'tbl_report_cards')) {
		throw new RuntimeException('Report cards table not available.');
	}

	$where = ['rc.term_id = ?'];
	$params = [$termId];

	if ($listClassId > 0) {
		$where[] = 'rc.class_id = ?';
		$params[] = $listClassId;
	}
	if ($listExamId > 0 && app_column_exists($conn, 'tbl_report_cards', 'exam_id')) {
		$where[] = 'rc.exam_id = ?';
		$params[] = $listExamId;
	}
	if ($batchExamName !== '') {
		$where[] = "COALESCE(ex.name, 'Unclassified') = ?";
		$params[] = $batchExamName;
	}
	if ($batchExamType !== '') {
		$where[] = 'COALESCE(et.name, \'\') = ?';
		$params[] = $batchExamType;
	}
	if (!empty($selectedClassIds)) {
		$placeholders = implode(',', array_fill(0, count($selectedClassIds), '?'));
		$where[] = "rc.class_id IN ($placeholders)";
		$params = array_merge($params, $selectedClassIds);
	}

	$sql = "SELECT rc.id, rc.student_id, rc.class_id, rc.term_id, COALESCE(rc.exam_id, 0) AS exam_id,
		COALESCE(ex.name, 'Unclassified') AS exam_name, COALESCE(et.name, '') AS exam_type,
		COALESCE(c.name, '') AS class_name, COALESCE(t.name, '') AS term_name
		FROM tbl_report_cards rc
		LEFT JOIN tbl_exams ex ON ex.id = rc.exam_id
		LEFT JOIN tbl_exam_types et ON et.id = ex.exam_type_id
		LEFT JOIN tbl_classes c ON c.id = rc.class_id
		LEFT JOIN tbl_terms t ON t.id = rc.term_id
		WHERE " . implode(' AND ', $where) . "
		ORDER BY c.name ASC, rc.position ASC, rc.generated_at DESC, rc.id DESC";
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$cards) {
		$_SESSION['reply'] = array(array('warning', 'No generated report cards found for the selected exam batch.'));
		header('location:../report');
		exit;
	}

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator((string)APP_NAME);
	$pdf->SetAuthor((string)APP_NAME);
	$pdf->SetTitle('Bulk Report Cards');

	$termNameForFile = (string)($cards[0]['term_name'] ?? ('term_' . $termId));
	$examNameForFile = (string)($cards[0]['exam_name'] ?? 'report_batch');

	foreach ($cards as $cardRow) {
		$studentId = (string)($cardRow['student_id'] ?? '');
		$classId = (int)($cardRow['class_id'] ?? 0);
		$examId = (int)($cardRow['exam_id'] ?? 0);
		if ($studentId === '' || $classId < 1) {
			continue;
		}

		$student = report_get_student_identity($conn, $studentId);
		if (!$student) {
			continue;
		}

		$card = report_load_card($conn, (int)$cardRow['id']);
		if (!$card || empty($card['subjects'])) {
			$card = report_ensure_card_generated($conn, $studentId, $classId, $termId, (int)$account_id, $examId);
		}
		if (!$card) {
			continue;
		}

		$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
		$feesBalance = report_fees_balance($conn, $studentId, $termId);
		$examSummary = null;
		$examBreakdown = [];
		if ($examId > 0) {
			$examSummary = report_exam_summary($conn, $studentId, $classId, $termId, $examId);
			$examBreakdown = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, $examId);
		}

		app_output_single_page_report_pdf($conn, $pdf, [
			'student_id' => $studentId,
			'student_name' => (string)$student['name'],
			'school_id' => ((string)($student['school_id'] ?? '') !== '' ? (string)$student['school_id'] : (string)$student['id']),
			'class_name' => (string)$student['class_name'],
			'term_name' => (string)($cardRow['term_name'] ?? ''),
			'attendance' => $attendance,
			'fees_balance' => $feesBalance,
			'card' => $card,
			'exam_summary' => $examSummary,
			'exam_breakdown' => $examBreakdown,
		]);

		$stmt = $conn->prepare('UPDATE tbl_report_cards SET downloads = downloads + 1 WHERE id = ?');
		$stmt->execute([(int)$cardRow['id']]);
	}

	if ($triggerPrint) {
		$pdf->IncludeJS('print(true);');
	}

	$filename = 'report_cards_' . preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($termNameForFile . '_' . $examNameForFile)) . '.pdf';
	$pdf->Output($filename, $forceDownload ? 'D' : 'I');
	exit;
} catch (Throwable $e) {
	error_log('[admin/core/download_all_reports] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to prepare bulk report PDF: ' . $e->getMessage()));
	header('location:../report');
	exit;
}
?>
