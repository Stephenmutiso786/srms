<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '0' || !isset($_GET['term'], $_GET['std'])) { header('location:../'); exit; }

$termId = (int)$_GET['term'];
$studentId = trim((string)$_GET['std']);
if ($termId < 1 || $studentId === '') { header('location:report'); exit; }
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] !== '0';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $student = report_get_student_identity($conn, $studentId);
    if (!$student) {
        $_SESSION['reply'] = array(array('danger', 'Student record not found for PDF generation.'));
        header('location:report');
        exit;
    }

    $card = report_ensure_card_generated($conn, $studentId, (int)$student['class_id'], $termId, (int)$account_id);
    if (!$card) {
        $rankData = report_rank_students($conn, (int)$student['class_id'], $termId);
        $report = report_compute_for_student($conn, $studentId, (int)$student['class_id'], $termId);
        $reportId = report_store_card($conn, $studentId, (int)$student['class_id'], $termId, $report, $rankData['positions'], (int)$rankData['total_students'], (int)$account_id);
        $card = report_load_card($conn, $reportId);
    }

    if (!$card) {
        $_SESSION['reply'] = array(array('danger', 'Report card data could not be generated for this student and term.'));
        header('location:report');
        exit;
    }

    $attendance = report_attendance_summary($conn, $studentId, (int)$student['class_id'], $termId);
    $feesBalance = report_fees_balance($conn, $studentId, $termId);

    $stmt = $conn->prepare('SELECT name FROM tbl_terms WHERE id = ? LIMIT 1');
    $stmt->execute([$termId]);
    $termName = (string)$stmt->fetchColumn();

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    app_output_single_page_report_pdf($conn, $pdf, [
        'student_id' => $studentId,
        'student_name' => (string)$student['name'],
        'school_id' => ((string)($student['school_id'] ?? '') !== '' ? (string)$student['school_id'] : (string)$student['id']),
        'class_name' => (string)$student['class_name'],
        'term_name' => $termName,
        'attendance' => $attendance,
        'fees_balance' => $feesBalance,
        'card' => $card,
    ]);

    $reportId = (int)($card['id'] ?? 0);
    if ($reportId > 0) {
        $stmt = $conn->prepare('UPDATE tbl_report_cards SET downloads = downloads + 1 WHERE id = ?');
        $stmt->execute([$reportId]);
    }

    $outputMode = (isset($_GET['print']) && (string)$_GET['print'] !== '0') ? 'I' : ($forceDownload ? 'D' : 'I');
    $pdf->Output('report-card.pdf', $outputMode);
} catch (Throwable $e) {
    error_log('[admin/save_pdf] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'Failed to generate PDF: ' . $e->getMessage()));
    header('location:report?term=' . $termId . '&std=' . urlencode($studentId));
}
