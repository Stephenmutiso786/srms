<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('const/rbac.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '2') { header('location:../'); exit; }
app_require_permission('report.view', '../');

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
if ($termId < 1 || $studentId === '') { header('location:manage_results'); exit; }
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] !== '0';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $student = report_get_student_identity($conn, $studentId);
    if (!$student || !report_teacher_has_class_access($conn, (int)$account_id, (int)$student['class_id'], $termId)) {
        header('location:manage_results');
        exit;
    }

    if (!report_term_is_published($conn, (int)$student['class_id'], $termId)) {
        header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
        exit;
    }

    if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, (int)$student['class_id'], $termId)) {
        header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
        exit;
    }

    $card = report_ensure_card_generated($conn, $studentId, (int)$student['class_id'], $termId, (int)$account_id);
    if (!$card) {
        header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
        exit;
    }

    $attendance = report_attendance_summary($conn, $studentId, (int)$student['class_id'], $termId);
    $feesBalance = report_fees_balance($conn, $studentId, $termId);

    $examSummary = null;
    $examBreakdown = [];
    $examOptions = report_term_exam_options($conn, (int)$student['class_id'], $termId);
    if ($examId < 1 && !empty($examOptions)) {
        $examId = (int)$examOptions[0]['id'];
    }
    if ($examId > 0) {
        foreach ($examOptions as $option) {
            if ((int)$option['id'] === $examId) {
                $examSummary = report_exam_summary($conn, $studentId, (int)$student['class_id'], $termId, $examId);
                $examBreakdown = report_exam_subject_breakdown($conn, $studentId, (int)$student['class_id'], $termId, $examId);
                break;
            }
        }
    }

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
        'exam_summary' => $examSummary,
        'exam_breakdown' => $examBreakdown,
    ]);

    $reportId = (int)($card['id'] ?? 0);
    if ($reportId > 0) {
        $stmt = $conn->prepare('UPDATE tbl_report_cards SET downloads = downloads + 1 WHERE id = ?');
        $stmt->execute([$reportId]);
    }

    if (isset($_GET['print']) && (string)$_GET['print'] !== '0') {
        $pdf->IncludeJS('print(true);');
    }

    $outputMode = (isset($_GET['print']) && (string)$_GET['print'] !== '0') ? 'I' : ($forceDownload ? 'D' : 'I');
    $pdf->Output('teacher-student-report.pdf', $outputMode);
} catch (Throwable $e) {
    error_log('[teacher/report_card_pdf] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'Failed to generate PDF: ' . $e->getMessage()));
    header('location:manage_results');
    exit;
}
