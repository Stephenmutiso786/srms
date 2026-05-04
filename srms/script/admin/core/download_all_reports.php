<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if ($res !== '1' || $level !== '0') { header('location:../'); exit; }
app_require_permission('report.generate', 'admin');

$listClassId = isset($_GET['list_class_id']) ? (int)$_GET['list_class_id'] : 0;
$listTermId = isset($_GET['list_term_id']) ? (int)$_GET['list_term_id'] : 0;

if ($listClassId < 1 && $listTermId < 1) {
    header('Location: ../report');
    exit;
}

set_time_limit(0);

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Gather students to include
    $params = [];
    if ($listClassId > 0) {
        $stmt = $conn->prepare("SELECT s.id AS student_id, CONCAT(COALESCE(s.fname,''),' ',COALESCE(s.lname,'')) AS student_name, c.name AS class_name FROM tbl_students s LEFT JOIN tbl_classes c ON c.id = s.class WHERE s.class = ? AND COALESCE(s.status,1)=1 ORDER BY COALESCE(s.lname,''), COALESCE(s.fname,'')");
        $stmt->execute([$listClassId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // If class not provided, fall back to report_cards for the term
        $stmt = $conn->prepare("SELECT rc.student_id, CONCAT(COALESCE(st.fname,''),' ',COALESCE(st.lname,'')) AS student_name, c.name AS class_name FROM tbl_report_cards rc LEFT JOIN tbl_students st ON st.id = rc.student_id LEFT JOIN tbl_classes c ON c.id = rc.class_id WHERE rc.term_id = ? ORDER BY c.id, rc.position, st.lname");
        $stmt->execute([$listTermId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($students)) {
        $_SESSION['reply'] = array(array('warning', 'No students or report cards found for the selected filters.'));
        header('Location: ../report');
        exit;
    }

    $tmpDir = sys_get_temp_dir() . '/srms_reports_' . uniqid();
    if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
        throw new RuntimeException('Failed to create temp directory for zipping reports');
    }

    $files = [];
    foreach ($students as $s) {
        $studentId = (string)($s['student_id'] ?? '');
        if ($studentId === '') continue;

        // Ensure card exists
        $card = report_ensure_card_generated($conn, $studentId, $listClassId, $listTermId, (int)$account_id);
        if (!$card) {
            $rankData = report_rank_students($conn, $listClassId, $listTermId);
            $report = report_compute_for_student($conn, $studentId, $listClassId, $listTermId);
            $reportId = report_store_card($conn, $studentId, $listClassId, $listTermId, $report, $rankData['positions'], (int)$rankData['total_students'], (int)$account_id);
            $card = report_load_card($conn, $reportId);
        }

        if (!$card) continue;

        $attendance = report_attendance_summary($conn, $studentId, $listClassId, $listTermId);
        $feesBalance = report_fees_balance($conn, $studentId, $listTermId);

        $stmt = $conn->prepare('SELECT name FROM tbl_terms WHERE id = ? LIMIT 1');
        $termName = '';
        if ($listTermId > 0) { $stmt->execute([$listTermId]); $termName = (string)$stmt->fetchColumn(); }

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        app_output_single_page_report_pdf($conn, $pdf, [
            'student_id' => $studentId,
            'student_name' => (string)$s['student_name'],
            'school_id' => ((string)($s['school_id'] ?? '') !== '' ? (string)$s['school_id'] : $studentId),
            'class_name' => (string)($s['class_name'] ?? ''),
            'term_name' => $termName,
            'attendance' => $attendance,
            'fees_balance' => $feesBalance,
            'card' => $card,
        ]);

        $safeName = preg_replace('/[^A-Za-z0-9 _-]/', '', trim((string)$s['student_name']));
        if ($safeName === '') $safeName = $studentId;
        $filePath = $tmpDir . '/' . $safeName . '-' . $studentId . '.pdf';
        $pdf->Output($filePath, 'F');
        $files[] = $filePath;
    }

    if (empty($files)) {
        $_SESSION['reply'] = array(array('warning', 'No report PDFs were generated.'));
        header('Location: ../report');
        exit;
    }

    $zipPath = sys_get_temp_dir() . '/srms_reports_' . uniqid() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Could not create ZIP file');
    }

    foreach ($files as $f) {
        $zip->addFile($f, basename($f));
    }
    $zip->close();

    // Stream the ZIP to the client
    header('Content-Type: application/zip');
    header('Content-disposition: attachment; filename=srms_reports_' . date('Ymd_His') . '.zip');
    header('Content-Length: ' . filesize($zipPath));
    readfile($zipPath);

    // Cleanup
    foreach ($files as $f) { if (is_file($f)) @unlink($f); }
    if (is_file($zipPath)) @unlink($zipPath);
    if (is_dir($tmpDir)) @rmdir($tmpDir);
    exit;

} catch (Throwable $e) {
    error_log('[admin/core/download_all_reports] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'An error occurred while preparing the reports.'));
    header('Location: ../report');
    exit;
}

?>
<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('tcpdf/tcpdf.php');

if (!isset($res) || $res !== '1' || !isset($level) || $level !== '0') {
    header('location:../');
    exit;
}
app_require_permission('report.generate', '../report');
app_require_unlocked('reports', '../report');

$listClassId = (int)($_GET['list_class_id'] ?? 0);
$listTermId = (int)($_GET['list_term_id'] ?? 0);

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (!app_table_exists($conn, 'tbl_report_cards')) {
        throw new RuntimeException('Report cards table not available.');
    }

    $where = [];
    $params = [];
    if ($listClassId > 0) {
        $where[] = 'rc.class_id = ?';
        $params[] = $listClassId;
    }
    if ($listTermId > 0) {
        $where[] = 'rc.term_id = ?';
        $params[] = $listTermId;
    }

    $sql = "SELECT rc.id, rc.student_id, rc.class_id, rc.term_id FROM tbl_report_cards rc";
    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY rc.generated_at DESC, rc.id DESC';

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$cards) {
        $_SESSION['reply'] = array(array('warning', 'No generated report cards found for the selected filter.'));
        header('location:../report');
        exit;
    }

    $tmpZip = sys_get_temp_dir() . '/report_cards_' . time() . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmpZip, ZipArchive::CREATE) !== true) {
        throw new RuntimeException('Failed to create temporary zip archive.');
    }

    foreach ($cards as $cardRow) {
        $studentId = (string)$cardRow['student_id'];
        $termId = (int)$cardRow['term_id'];

        $student = report_get_student_identity($conn, $studentId);
        if (!$student) {
            continue;
        }

        // Ensure card exists
        $card = report_ensure_card_generated($conn, $studentId, (int)$student['class_id'], $termId, (int)$account_id);
        if (!$card) {
            $rankData = report_rank_students($conn, (int)$student['class_id'], $termId);
            $report = report_compute_for_student($conn, $studentId, (int)$student['class_id'], $termId);
            $reportId = report_store_card($conn, $studentId, (int)$student['class_id'], $termId, $report, $rankData['positions'], (int)$rankData['total_students'], (int)$account_id);
            $card = report_load_card($conn, $reportId);
        }

        if (!$card) {
            continue;
        }

        $attendance = report_attendance_summary($conn, $studentId, (int)$student['class_id'], $termId);
        $feesBalance = report_fees_balance($conn, $studentId, $termId);
        $examSummary = null;
        $examBreakdown = [];
        $examOptions = report_term_exam_options($conn, (int)$student['class_id'], $termId);
        $examId = 0;
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

        $fileNameSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)($student['name'] ?? $studentId));
        $pdfFileName = $fileNameSafe . '_' . $studentId . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        $zip->addFromString($pdfFileName, $pdfContent);
    }

    $zip->close();

    if (!file_exists($tmpZip)) {
        throw new RuntimeException('Failed to create zip file.');
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="report-cards-' . date('Ymd-His') . '.zip"');
    header('Content-Length: ' . filesize($tmpZip));
    readfile($tmpZip);
    @unlink($tmpZip);
    exit;

} catch (Throwable $e) {
    error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'Failed to prepare ZIP: ' . $e->getMessage()));
    header('location:../report');
    exit;
}
