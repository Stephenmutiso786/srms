<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '2') { header('location:../'); exit; }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
if ($termId < 1 || $studentId === '') { header('location:manage_results'); exit; }

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $student = report_get_student_identity($conn, $studentId);
    if (!$student || !report_teacher_has_class_access($conn, (int)$account_id, (int)$student['class_id'], $termId)) {
        header('location:manage_results');
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

    $pdf->Output('teacher-student-report.pdf', 'I');
} catch (Throwable $e) {
    header('location:manage_results');
}
