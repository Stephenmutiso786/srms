<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '1' || !isset($_GET['term'], $_GET['class'])) { header('location:../'); exit; }

$termId = (int)$_GET['term'];
$classId = (int)$_GET['class'];
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
if ($termId < 1 || $classId < 1) { header('location:report'); exit; }
$forceDownload = isset($_GET['download']) && (string)$_GET['download'] !== '0';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!report_term_is_published($conn, $classId, $termId)) {
        $_SESSION['reply'] = array(array('warning', 'Results are not published for the selected class and term.'));
        header('location:report');
        exit;
    }

    $stmt = $conn->prepare("SELECT s.id AS student_id, CONCAT(COALESCE(s.fname, ''), ' ', COALESCE(s.lname, '')) AS student_name, c.name AS class_name, s.school_id, s.class
        FROM tbl_students s
        LEFT JOIN tbl_classes c ON c.id = s.class
        WHERE s.class = ? AND COALESCE(s.status,1) = 1
        ORDER BY COALESCE(s.lname, ''), COALESCE(s.fname, '')");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        $_SESSION['reply'] = array(array('warning', 'No active students found in the selected class.'));
        header('location:report');
        exit;
    }

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    foreach ($students as $student) {
        $studentId = (string)($student['student_id'] ?? '');
        if ($studentId === '') { continue; }

        $card = report_ensure_card_generated($conn, $studentId, $classId, $termId, (int)$account_id, $examId);
        if (!$card) {
            $rankData = report_rank_students($conn, $classId, $termId, $examId);
            $report = report_compute_for_student($conn, $studentId, $classId, $termId, $examId);
            $reportId = report_store_card($conn, $studentId, $classId, $termId, $report, $rankData['positions'], (int)$rankData['total_students'], (int)$account_id, $examId);
            $card = report_load_card($conn, $reportId);
        }

        if (!$card) {
            continue;
        }

        $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
        $feesBalance = report_fees_balance($conn, $studentId, $termId);
        $examSummary = null;
        $examBreakdown = [];
        $examOptions = report_term_exam_options($conn, $classId, $termId);
        $selectedExamId = $examId;
        if ($selectedExamId < 1 && !empty($examOptions)) {
            $selectedExamId = (int)$examOptions[0]['id'];
        }
        if ($selectedExamId > 0) {
            foreach ($examOptions as $option) {
                if ((int)$option['id'] === $selectedExamId) {
                    $examSummary = report_exam_summary($conn, $studentId, $classId, $termId, $selectedExamId);
                    $examBreakdown = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, $selectedExamId);
                    break;
                }
            }
        }

        $stmt = $conn->prepare('SELECT name FROM tbl_terms WHERE id = ? LIMIT 1');
        $stmt->execute([$termId]);
        $termName = (string)$stmt->fetchColumn();

        app_output_single_page_report_pdf($conn, $pdf, [
            'student_id' => $studentId,
            'student_name' => (string)$student['student_name'],
            'school_id' => ((string)($student['school_id'] ?? '') !== '' ? (string)$student['school_id'] : $studentId),
            'class_name' => (string)($student['class_name'] ?? ''),
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
    }

    $outputMode = (isset($_GET['print']) && (string)$_GET['print'] !== '0') ? 'I' : ($forceDownload ? 'D' : 'I');
    $pdf->Output('class-report.pdf', $outputMode);

} catch (Throwable $e) {
    error_log('[academic/class_report_pdf] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'Failed to generate class PDF: ' . $e->getMessage()));
    header('location:report?term=' . $termId . '&class=' . $classId);
}
