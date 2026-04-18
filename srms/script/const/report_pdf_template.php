<?php

require_once(__DIR__ . '/report_engine.php');
require_once(__DIR__ . '/school.php');
require_once(__DIR__ . '/pdf_branding.php');

function app_report_verify_url(string $verificationCode): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim((string)APP_URL, '/') . '/verify_report?code=' . urlencode($verificationCode);
    }

    return 'http://' . $host . '/verify_report?code=' . urlencode($verificationCode);
}

function app_report_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_report_student_photo_html(PDO $conn, string $studentId): string
{
    try {
        $hasDisplayImage = app_column_exists($conn, 'tbl_students', 'display_image');
        $hasLegacyImage = app_column_exists($conn, 'tbl_students', 'image');

        $columns = ['gender'];
        if ($hasDisplayImage) {
            $columns[] = 'display_image';
        }
        if ($hasLegacyImage) {
            $columns[] = 'image';
        }

        $stmt = $conn->prepare('SELECT ' . implode(', ', $columns) . ' FROM tbl_students WHERE id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }

        $image = trim((string)($row['display_image'] ?? ''));
        if ($image === '' || strtoupper($image) === 'DEFAULT') {
            $image = trim((string)($row['image'] ?? ''));
        }

        $gender = trim((string)($row['gender'] ?? 'male'));
        $path = ($image !== '' && strtoupper($image) !== 'DEFAULT')
            ? 'images/students/' . $image
            : 'images/students/' . $gender . '.png';

        if (!is_file($path)) {
            return '';
        }

        return '<img src="' . htmlspecialchars($path, ENT_QUOTES, 'UTF-8') . '" style="width:76px;height:88px;object-fit:cover;border:1px solid #8ea0b2;" />';
    } catch (Throwable $e) {
        return '';
    }
}

function app_report_grade_descriptors_html(PDO $conn, ?int $gradingSystemId): string
{
    if ($gradingSystemId === null || $gradingSystemId < 1) {
        return '';
    }

    $rows = report_grading_scales($conn, $gradingSystemId);
    if (empty($rows)) {
        return '';
    }

    $cells = '';
    foreach ($rows as $row) {
        $cells .= '<td style="border:1px solid #555;padding:4px 5px;vertical-align:top;font-size:7.4pt;">'
            . '<div style="font-weight:bold;">' . app_report_html((string)($row['name'] ?? '')) . '</div>'
            . '<div>' . number_format((float)($row['min'] ?? 0), 0) . '% - ' . number_format((float)($row['max'] ?? 0), 0) . '%</div>'
            . '<div>' . app_report_html((string)($row['remark'] ?? '')) . '</div>'
            . '</td>';
    }

    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'
        . '<tr><td colspan="' . count($rows) . '" style="padding:3px 0 4px 0;font-size:8pt;font-weight:bold;">Grade Descriptors</td></tr>'
        . '<tr>' . $cells . '</tr>'
        . '</table>';
}

function app_report_summary_box(string $title, string $value, string $subtitle = ''): string
{
    return '<td style="border:1px solid #888;padding:4px 5px;font-size:7.6pt;vertical-align:top;">'
        . '<div style="text-transform:uppercase;">' . app_report_html($title) . '</div>'
        . '<div style="font-size:12pt;font-weight:bold;line-height:1.1;">' . app_report_html($value) . '</div>'
        . ($subtitle !== '' ? '<div>' . app_report_html($subtitle) . '</div>' : '')
        . '</td>';
}

function app_report_metric_box(string $title, string $value): string
{
    return '<td style="border:1px solid #888;padding:4px 5px;font-size:7.6pt;vertical-align:top;text-align:center;">'
        . '<div style="text-transform:uppercase;">' . app_report_html($title) . '</div>'
        . '<div style="font-size:12pt;font-weight:bold;line-height:1.1;">' . app_report_html($value) . '</div>'
        . '</td>';
}

function app_report_combined_cycles_html(PDO $conn, array $payload): string
{
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : null;
    $breakdownData = report_consolidated_cycle_breakdown(
        $conn,
        (string)($payload['student_id'] ?? ''),
        (int)($card['class_id'] ?? 0),
        (int)($card['term_id'] ?? 0),
        (int)($examSummary['exam_id'] ?? 0)
    );

    $rows = is_array($breakdownData['rows'] ?? null) ? $breakdownData['rows'] : [];
    $cycleLabels = array_values(array_filter(array_map('strval', $breakdownData['cycle_labels'] ?? [])));
    $cycleTitle = !empty($cycleLabels) ? implode(' / ', $cycleLabels) : 'COMBINED CYCLES';

    $studentName = (string)($payload['student_name'] ?? '');
    $schoolId = (string)($payload['school_id'] ?? '');
    $className = (string)($payload['class_name'] ?? '');
    $termName = (string)($payload['term_name'] ?? '');
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $schoolAddress = defined('WBAddress') ? (string)WBAddress : '';
    $schoolPhone = defined('WBPhone') ? (string)WBPhone : (defined('WBContact') ? (string)WBContact : '');
    $schoolEmail = defined('WBEmail') ? (string)WBEmail : '';

    $photoHtml = app_report_student_photo_html($conn, (string)($payload['student_id'] ?? ''));
    if ($photoHtml === '') {
        $photoHtml = '<div style="width:76px;height:88px;border:1px solid #8ea0b2;text-align:center;line-height:88px;font-size:8pt;color:#555;">PHOTO</div>';
    }

    $totalMarks = 0.0;
    $totalPoints = 0.0;
    foreach ($rows as $row) {
        $totalMarks += (float)($row['combined_score'] ?? 0);
        $totalPoints += (float)($row['grade_points'] ?? 0);
    }

    $subjectCount = count($rows);
    $maxMarks = max(1, $subjectCount * 100);
    $maxPoints = max(1, $subjectCount * 12);
    $meanScore = $subjectCount > 0 ? round($totalMarks / $subjectCount, 2) : 0.0;
    $gradingSystemId = report_exam_grading_system_id($conn, (int)($examSummary['exam_id'] ?? 0));
    [$meanGrade, $meanRemark] = report_grade_for_score($conn, $meanScore, $gradingSystemId);
    $overallPosition = (string)($card['position'] ?? '-') . '/' . (string)($card['total_students'] ?? 0);
    $streamPosition = $overallPosition;
    $cycleHeaders = '';
    foreach ($cycleLabels as $cycleLabel) {
        $cycleHeaders .= '<th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">' . app_report_html($cycleLabel) . '</th>';
    }

    $subjectRows = '';
    foreach ($rows as $row) {
        $cycleCells = '';
        foreach ($cycleLabels as $cycleLabel) {
            $cycleCells .= '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">'
                . number_format((float)($row['cycle_scores'][$cycleLabel] ?? 0), 1) . '%</td>';
        }

        $subjectRows .= '<tr>'
            . '<td style="border:1px solid #555;padding:3px 4px;font-size:7.8pt;">' . app_report_html((string)($row['subject_name'] ?? '')) . '</td>'
            . $cycleCells
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . number_format((float)($row['combined_score'] ?? 0), 1) . '%</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . app_report_html((string)($row['position'] ?? '-')) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . app_report_html((string)($row['remark'] ?? '')) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;font-size:7.8pt;">' . app_report_html((string)($row['teacher_name'] ?? '')) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . number_format(((float)($row['combined_score'] ?? 0)) - (float)($row['class_mean'] ?? 0), 1) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . app_report_html((string)($row['grade'] ?? '')) . '</td>'
            . '</tr>';
    }

    if ($subjectRows === '') {
        $subjectRows = '<tr><td colspan="' . (7 + count($cycleLabels)) . '" style="border:1px solid #555;padding:6px;text-align:center;font-size:8pt;">No subject data available.</td></tr>';
    }

    $remarksLeft = app_report_html((string)($card['teacher_comment'] ?? $card['remark'] ?? ''));
    $remarksRight = app_report_html((string)($card['headteacher_comment'] ?? $card['remark'] ?? ''));
    $graderHtml = app_report_grade_descriptors_html($conn, $gradingSystemId);
    $verificationCode = app_report_html((string)($card['verification_code'] ?? ''));
    $userName = $schoolId !== ''
        ? $schoolId . '@' . strtolower(preg_replace('/[^a-z0-9]+/i', '', $schoolName))
        : (string)($payload['student_id'] ?? '');

    return '
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:3px;">
    <tr><td style="font-size:10.8pt;font-weight:bold;">' . app_report_html($schoolName) . '</td></tr>
    <tr><td style="font-size:7.6pt;">Address: ' . app_report_html($schoolAddress) . '</td></tr>
    <tr><td style="font-size:7.6pt;">Tel: ' . app_report_html($schoolPhone) . ' &nbsp; Email: ' . app_report_html($schoolEmail) . '</td></tr>
</table>
<div style="text-align:center;font-size:11pt;font-weight:bold;padding:4px 0;border-top:1px solid #666;border-bottom:1px solid #666;">ACADEMIC REPORT FORM - ' . app_report_html($className) . ' - ' . app_report_html($cycleTitle) . ' - (' . app_report_html($termName) . ')</div>
<div style="font-size:9pt;font-weight:bold;margin:4px 0 3px 0;">Subject Performance - Student vs Class</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:4px;border-collapse:collapse;">
    <tr>
        <td width="15%" style="border:1px solid #666;padding:4px;vertical-align:top;">' . $photoHtml . '</td>
        <td width="42%" style="border:1px solid #666;padding:4px;vertical-align:top;">
            <div style="font-size:10pt;font-weight:bold;">' . app_report_html($studentName) . '</div>
            <div style="font-size:8.2pt;">ADMNO: ' . app_report_html($schoolId !== '' ? $schoolId : (string)($payload['student_id'] ?? '')) . '</div>
            <div style="font-size:8.2pt;">FORM: ' . app_report_html($className) . '</div>
        </td>
        <td width="43%" style="border:1px solid #666;padding:4px;vertical-align:top;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>' . app_report_metric_box('Mean Grade', (string)$meanGrade) . app_report_metric_box('Total Marks', number_format($totalMarks, 0)) . '</tr>
                <tr>' . app_report_metric_box('Total Points', number_format($totalPoints, 0)) . app_report_metric_box('Stream Position', $streamPosition) . '</tr>
                <tr><td colspan="2" style="border:1px solid #888;padding:4px 5px;font-size:7.6pt;text-align:center;">Overall Position<br><b style="font-size:12pt;">' . app_report_html($overallPosition) . '</b></td></tr>
            </table>
        </td>
    </tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr style="background:#e9edf1;">
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">SUBJECT</th>
        ' . $cycleHeaders . '
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">MARKS</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">RANK</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">PERFORMANCE LEVEL</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">TEACHER</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">DEV.</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">GR.</th>
    </tr>
    ' . $subjectRows . '
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:5px;">
    <tr>
        <td width="50%" style="vertical-align:top;padding-right:3px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #666;">
                <tr><td style="background:#f4f8fb;padding:3px 4px;font-size:7.8pt;font-weight:bold;">Class Teacher Remarks</td></tr>
                <tr><td style="padding:6px 4px;min-height:28px;font-size:8pt;">' . $remarksLeft . '</td></tr>
                <tr><td style="padding:4px 4px;font-size:8pt;">Signature:</td></tr>
            </table>
        </td>
        <td width="50%" style="vertical-align:top;padding-left:3px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #666;">
                <tr><td style="background:#f4f8fb;padding:3px 4px;font-size:7.8pt;font-weight:bold;">Principal Remarks</td></tr>
                <tr><td style="padding:6px 4px;min-height:28px;font-size:8pt;">' . $remarksRight . '</td></tr>
                <tr><td style="padding:4px 4px;font-size:8pt;">Signature:</td></tr>
            </table>
        </td>
    </tr>
</table>
<div style="margin-top:4px;">' . $graderHtml . '</div>
<div style="margin-top:3px;font-size:7.8pt;">Scan to access your interactive student profile on Zeraki Analytics.</div>
<div style="font-size:7.8pt;">Your username: ' . app_report_html($userName) . '</div>
';
}

function app_report_generic_html(PDO $conn, array $payload): string
{
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : null;
    $subjects = is_array($payload['exam_breakdown'] ?? null) ? $payload['exam_breakdown'] : [];

    $studentName = (string)($payload['student_name'] ?? '');
    $schoolId = (string)($payload['school_id'] ?? '');
    $className = (string)($payload['class_name'] ?? '');
    $termName = (string)($payload['term_name'] ?? '');
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $schoolAddress = defined('WBAddress') ? (string)WBAddress : '';
    $schoolPhone = defined('WBPhone') ? (string)WBPhone : (defined('WBContact') ? (string)WBContact : '');
    $schoolEmail = defined('WBEmail') ? (string)WBEmail : '';

    $photoHtml = app_report_student_photo_html($conn, (string)($payload['student_id'] ?? ''));
    if ($photoHtml === '') {
        $photoHtml = '<div style="width:76px;height:88px;border:1px solid #8ea0b2;text-align:center;line-height:88px;font-size:8pt;color:#555;">PHOTO</div>';
    }

    $gradingSystemId = report_exam_grading_system_id($conn, (int)($examSummary['exam_id'] ?? 0));
    $meanScore = (float)($card['mean'] ?? 0);
    [$meanGrade, $meanRemark] = report_grade_for_score($conn, $meanScore, $gradingSystemId);
    $totalMarks = (float)($card['total'] ?? 0);
    $position = (string)($card['position'] ?? '-') . '/' . (string)($card['total_students'] ?? 0);

    $subjectRows = '';
    foreach ($subjects as $subject) {
        $score = (float)($subject['score'] ?? 0);
        $classMean = (float)($subject['class_mean'] ?? 0);
        $deviation = round($score - $classMean, 1);
        $subjectRows .= '<tr>'
            . '<td style="border:1px solid #555;padding:3px 4px;font-size:7.8pt;">' . app_report_html((string)($subject['subject_name'] ?? '')) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . number_format($score, 1) . '%</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . number_format($classMean, 1) . '%</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . number_format($deviation, 1) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . app_report_html((string)($subject['grade'] ?? '')) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;text-align:center;font-size:7.8pt;">' . app_report_html((string)($subject['position'] ?? '-')) . '</td>'
            . '<td style="border:1px solid #555;padding:3px 4px;font-size:7.8pt;">' . app_report_html((string)($subject['teacher_name'] ?? '')) . '</td>'
            . '</tr>';
    }

    if ($subjectRows === '') {
        $subjectRows = '<tr><td colspan="7" style="border:1px solid #555;padding:6px;text-align:center;font-size:8pt;">No subject data available.</td></tr>';
    }

    $remarksLeft = app_report_html((string)($card['teacher_comment'] ?? $card['remark'] ?? ''));
    $remarksRight = app_report_html((string)($card['headteacher_comment'] ?? $card['remark'] ?? ''));
    $graderHtml = app_report_grade_descriptors_html($conn, $gradingSystemId);
    $verificationCode = app_report_html((string)($card['verification_code'] ?? ''));
    $userName = $schoolId !== ''
        ? $schoolId . '@' . strtolower(preg_replace('/[^a-z0-9]+/i', '', $schoolName))
        : (string)($payload['student_id'] ?? '');

    return '
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:3px;">
    <tr><td style="font-size:11pt;font-weight:bold;">' . app_report_html($schoolName) . '</td></tr>
    <tr><td style="font-size:7.8pt;">Address: ' . app_report_html($schoolAddress) . '</td></tr>
    <tr><td style="font-size:7.8pt;">Tel: ' . app_report_html($schoolPhone) . ' &nbsp; Email: ' . app_report_html($schoolEmail) . '</td></tr>
</table>
<div style="text-align:center;font-size:11pt;font-weight:bold;padding:4px 0;border-top:1px solid #666;border-bottom:1px solid #666;">ACADEMIC REPORT FORM - ' . app_report_html($className) . ' - ' . app_report_html($termName) . '</div>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:5px;margin-bottom:4px;border-collapse:collapse;">
    <tr>
        <td width="15%" style="border:1px solid #666;padding:4px;vertical-align:top;">' . $photoHtml . '</td>
        <td width="42%" style="border:1px solid #666;padding:4px;vertical-align:top;">
            <div style="font-size:10pt;font-weight:bold;">' . app_report_html($studentName) . '</div>
            <div style="font-size:8.2pt;">ADMNO: ' . app_report_html($schoolId !== '' ? $schoolId : (string)($payload['student_id'] ?? '')) . '</div>
            <div style="font-size:8.2pt;">FORM: ' . app_report_html($className) . '</div>
        </td>
        <td width="43%" style="border:1px solid #666;padding:4px;vertical-align:top;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                <tr>' . app_report_metric_box('Mean Grade', (string)$meanGrade) . app_report_metric_box('Total Marks', number_format($totalMarks, 0)) . '</tr>
                <tr>' . app_report_metric_box('Position', $position) . app_report_metric_box('Verification', $verificationCode) . '</tr>
            </table>
        </td>
    </tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
    <tr style="background:#e9edf1;">
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">SUBJECT</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">MARKS</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">CLASS AVG</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">DEV.</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">GR.</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">RANK</th>
        <th style="border:1px solid #555;padding:3px 4px;font-size:7.5pt;">TEACHER</th>
    </tr>
    ' . $subjectRows . '
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:5px;">
    <tr>
        <td width="50%" style="vertical-align:top;padding-right:3px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #666;">
                <tr><td style="background:#f4f8fb;padding:3px 4px;font-size:7.8pt;font-weight:bold;">Class Teacher Remarks</td></tr>
                <tr><td style="padding:6px 4px;min-height:28px;font-size:8pt;">' . $remarksLeft . '</td></tr>
                <tr><td style="padding:4px 4px;font-size:8pt;">Signature:</td></tr>
            </table>
        </td>
        <td width="50%" style="vertical-align:top;padding-left:3px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #666;">
                <tr><td style="background:#f4f8fb;padding:3px 4px;font-size:7.8pt;font-weight:bold;">Principal Remarks</td></tr>
                <tr><td style="padding:6px 4px;min-height:28px;font-size:8pt;">' . $remarksRight . '</td></tr>
                <tr><td style="padding:4px 4px;font-size:8pt;">Signature:</td></tr>
            </table>
        </td>
    </tr>
</table>
<div style="margin-top:4px;">' . $graderHtml . '</div>
<div style="margin-top:3px;font-size:7.8pt;">Scan to access your interactive student profile on Zeraki Analytics.</div>
<div style="font-size:7.8pt;">Your username: ' . app_report_html($userName) . '</div>
';
}

function app_output_single_page_report_pdf(PDO $conn, TCPDF $pdf, array $payload): void
{
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(true, 10);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetTitle('Academic Report Card');
    $pdf->AddPage('P', 'A4');
    $pdf->SetFont('helvetica', '', 9);

    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : null;
    $examMode = strtolower(trim((string)($examSummary['assessment_mode'] ?? 'normal')));
    $html = ($examMode === 'consolidated')
        ? app_report_combined_cycles_html($conn, $payload)
        : app_report_generic_html($conn, $payload);

    $pdf->writeHTML($html, true, false, true, false, '');

    $verifyUrl = app_report_verify_url((string)($payload['card']['verification_code'] ?? ''));
    if ($verifyUrl !== '') {
        $pdf->lastPage();
        $margins = $pdf->getMargins();
        $qrSize = 18;
        $x = $pdf->getPageWidth() - (float)$margins['right'] - $qrSize;
        $y = $pdf->getPageHeight() - (float)$margins['bottom'] - $qrSize;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', $x, $y, $qrSize, $qrSize);
    }
}
