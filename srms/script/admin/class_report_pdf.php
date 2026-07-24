<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once(__DIR__ . '/../db/config.php');
require_once(__DIR__ . '/../const/school.php');
require_once(__DIR__ . '/../const/check_session.php');
require_once(__DIR__ . '/../const/report_engine.php');
require_once(__DIR__ . '/../const/report_pdf_template.php');
require_once(__DIR__ . '/../tcpdf/tcpdf.php');

@set_time_limit(0);
@ini_set('memory_limit', '-1');

if ($res !== '1' || $level !== '0' || !isset($_GET['term'], $_GET['class'])) { header('location:../'); exit; }

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

    // Pre-generate the class batch once so we don't recompute ranking/report data per student.
    $batch = report_class_merit_list($conn, $classId, $termId, (int)$account_id, $examId);
    if (empty($batch['rows'])) {
        throw new RuntimeException('No report cards could be generated for the selected class and term.');
    }

    // Fetch students in class
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
    $examOptions = report_term_exam_options($conn, $classId, $termId);
    if ($examId < 1 && !empty($examOptions)) {
        $examId = (int)$examOptions[0]['id'];
    }
    $selectedExamId = $examId;
    $stmt = $conn->prepare('SELECT name FROM tbl_terms WHERE id = ? LIMIT 1');
    $stmt->execute([$termId]);
    $termName = (string)$stmt->fetchColumn();

    foreach ($students as $student) {
        $studentId = (string)($student['student_id'] ?? '');
        if ($studentId === '') { continue; }

        $reportId = report_find_card_id($conn, $studentId, $termId, $selectedExamId);
        $card = $reportId > 0 ? report_load_card($conn, $reportId) : null;

        if (!$card) {
            // skip students we couldn't generate cards for
            continue;
        }

        $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
        $feesBalance = report_fees_balance($conn, $studentId, $termId);
        $examSummary = null;
        $examBreakdown = [];
        if ($selectedExamId > 0) {
            foreach ($examOptions as $option) {
                if ((int)$option['id'] === $selectedExamId) {
                    $examSummary = report_exam_summary($conn, $studentId, $classId, $termId, $selectedExamId);
                    $examBreakdown = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, $selectedExamId);
                    break;
                }
            }
        }

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

        // Increment download counter for the card if stored
        $reportId = (int)($card['id'] ?? 0);
        if ($reportId > 0) {
            $stmt = $conn->prepare('UPDATE tbl_report_cards SET downloads = downloads + 1 WHERE id = ?');
            $stmt->execute([$reportId]);
        }
    }

    $outputMode = (isset($_GET['print']) && (string)$_GET['print'] !== '0') ? 'I' : ($forceDownload ? 'D' : 'I');
    $pdf->Output('class-report.pdf', $outputMode);

} catch (Throwable $e) {
    error_log('[admin/class_report_pdf] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'Failed to generate class PDF: ' . $e->getMessage()));
    header('location:report?term=' . $termId . '&class=' . $classId);
}
