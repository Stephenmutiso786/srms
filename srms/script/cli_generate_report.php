<?php
// CLI helper to generate a single student's report PDF using the app's TCPDF template.
// Usage: php cli_generate_report.php --student=RG004 [--term=3] [--out=/tmp/report.pdf]

$options = [];
foreach ($argv as $arg) {
    if (strpos($arg, '--') === 0) {
        $p = substr($arg, 2);
        $parts = explode('=', $p, 2);
        $options[$parts[0]] = $parts[1] ?? '';
    }
}
$studentId = isset($options['student']) ? trim($options['student']) : '';
$outPath = isset($options['out']) && trim($options['out']) !== '' ? trim($options['out']) : sys_get_temp_dir() . '/report-card-preview.pdf';
$termId = isset($options['term']) && is_numeric($options['term']) ? (int)$options['term'] : 0;

if ($studentId === '') {
    echo "Usage: php cli_generate_report.php --student=RG004 [--term=3] [--out=/tmp/report.pdf]\n";
    exit(2);
}

chdir(__DIR__);
require_once 'db/config.php';
require_once 'const/school.php';
require_once 'const/report_engine.php';
require_once 'const/report_pdf_template.php';
require_once 'tcpdf/tcpdf.php';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $student = report_get_student_identity($conn, $studentId);
    if (!$student) {
        echo "Student not found: $studentId\n";
        exit(3);
    }

    $classId = (int)($student['class_id'] ?? 0);
    if ($termId < 1) {
        // pick the most recent published term for this class
        $stmt = $conn->prepare('SELECT id FROM tbl_terms ORDER BY id DESC');
        $stmt->execute();
        $terms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $found = 0;
        foreach ($terms as $t) {
            if (report_term_is_published($conn, $classId, (int)$t)) { $found = (int)$t; break; }
        }
        if ($found === 0) {
            echo "No published term found for class {$classId}\n";
            exit(4);
        }
        $termId = $found;
    }

    // Ensure card generated
    $card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
    if (!$card) {
        echo "Failed to ensure card generated for {$studentId} term {$termId}\n";
        exit(5);
    }

    $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
    $feesBalance = report_fees_balance($conn, $studentId, $termId);
    $stmt = $conn->prepare('SELECT name FROM tbl_terms WHERE id = ? LIMIT 1'); $stmt->execute([$termId]); $termName = (string)$stmt->fetchColumn();

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    app_output_single_page_report_pdf($conn, $pdf, [
        'student_id' => $studentId,
        'student_name' => (string)$student['name'],
        'school_id' => ((string)($student['school_id'] ?? '') !== '' ? (string)$student['school_id'] : (string)$student['id']),
        'class_name' => (string)($student['class_name'] ?? ''),
        'term_name' => $termName,
        'attendance' => $attendance,
        'fees_balance' => $feesBalance,
        'card' => $card,
        'exam_summary' => null,
        'exam_breakdown' => [],
    ]);

    $pdf->Output($outPath, 'F');
    echo "Saved preview PDF to: {$outPath}\n";
    exit(0);
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
