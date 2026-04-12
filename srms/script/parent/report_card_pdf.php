<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '4') { header('location:../'); exit; }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
if ($termId < 1 || $studentId === '') { header('location:report_card'); exit; }

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare('SELECT 1 FROM tbl_parent_students WHERE parent_id = ? AND student_id = ? LIMIT 1');
    $stmt->execute([$account_id, $studentId]);
    if (!$stmt->fetchColumn()) {
        header('location:report_card');
        exit;
    }

    $stmt = $conn->prepare('SELECT class, fname, lname, school_id FROM tbl_students WHERE id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$studentRow) {
        header('location:report_card');
        exit;
    }

    $classId = (int)$studentRow['class'];
    $studentName = trim((string)($studentRow['fname'] ?? '') . ' ' . (string)($studentRow['lname'] ?? ''));
    $schoolId = (string)($studentRow['school_id'] ?? '');

    if (!report_term_is_published($conn, $classId, $termId)) {
        header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
        exit;
    }

    $card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
    if (!$card) {
        header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
        exit;
    }

    $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
    $feesBalance = report_fees_balance($conn, $studentId, $termId);
    $settings = report_get_settings($conn);
    if ((int)$settings['require_fees_clear'] === 1 && $feesBalance > 0) {
        header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
        exit;
    }

    $stmt = $conn->prepare('SELECT name FROM tbl_terms WHERE id = ? LIMIT 1');
    $stmt->execute([$termId]);
    $termName = (string)$stmt->fetchColumn();

    $stmt = $conn->prepare('SELECT name FROM tbl_classes WHERE id = ? LIMIT 1');
    $stmt->execute([$classId]);
    $className = (string)$stmt->fetchColumn();

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    app_output_single_page_report_pdf($conn, $pdf, [
        'student_id' => $studentId,
        'student_name' => $studentName,
        'school_id' => ($schoolId !== '' ? $schoolId : $studentId),
        'class_name' => $className,
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

    if (isset($_GET['print']) && (string)$_GET['print'] !== '0') {
        $pdf->IncludeJS('print(true);');
    }

    $pdf->Output('report-card.pdf', 'I');
} catch (Throwable $e) {
    header('location:report_card?term=' . $termId . '&student=' . urlencode($studentId));
}
