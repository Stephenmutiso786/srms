<?php
require_once(__DIR__ . '/report_engine.php');
require_once(__DIR__ . '/school.php');

function app_report_verify_url(string $verificationCode): string
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (defined('APP_URL') && APP_URL !== '') {
        return rtrim((string)APP_URL, '/') . '/verify_report?code=' . urlencode($verificationCode);
    }
    return 'http://' . $host . '/verify_report?code=' . urlencode($verificationCode);
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
        $path = '';
        if ($image !== '' && strtoupper($image) !== 'DEFAULT') {
            $path = 'images/students/' . $image;
        } else {
            $path = 'images/students/' . $gender . '.png';
        }

        if (!is_file($path)) {
            return '';
        }

        $safePath = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        return '<img src="' . $safePath . '" style="width:78px;height:88px;object-fit:cover;border:1px solid #8ea0b2;" />';
    } catch (Throwable $e) {
        return '';
    }
}

function app_report_class_subject_means(PDO $conn, int $classId, int $termId): array
{
    if ($classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_report_cards') || !app_table_exists($conn, 'tbl_report_card_subjects')) {
        return [];
    }

    try {
        $stmt = $conn->prepare("SELECT rcs.subject_id, AVG(rcs.score) AS avg_score
            FROM tbl_report_card_subjects rcs
            INNER JOIN tbl_report_cards rc ON rc.id = rcs.report_id
            WHERE rc.class_id = ? AND rc.term_id = ?
            GROUP BY rcs.subject_id");
        $stmt->execute([$classId, $termId]);

        $means = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $means[(int)$row['subject_id']] = round((float)($row['avg_score'] ?? 0), 2);
        }
        return $means;
    } catch (Throwable $e) {
        return [];
    }
}

function app_report_subject_chart_html(array $subjects, array $classMeans): string
{
    if (empty($subjects)) {
        return '<div style="font-size:8pt;color:#555;">No subject chart data available.</div>';
    }

    $rows = '';
    foreach (array_slice($subjects, 0, 8) as $subject) {
        $subjectId = (int)($subject['subject_id'] ?? 0);
        $name = (string)($subject['subject_name'] ?? 'Subject');
        $studentScore = max(0, min(100, (float)($subject['score'] ?? 0)));
        $classScore = max(0, min(100, (float)($classMeans[$subjectId] ?? 0)));
        $studentWidth = (int)round($studentScore * 1.4);
        $classWidth = (int)round($classScore * 1.4);

        $rows .= '<tr>'
            . '<td style="font-size:7.5pt;padding:2px 4px;width:56px;">' . htmlspecialchars(substr($name, 0, 8)) . '</td>'
            . '<td style="padding:2px 4px;width:156px;">'
            . '<div style="height:6px;background:#d9edf7;margin-bottom:2px;"><div style="height:6px;background:#2f9ed6;width:' . $studentWidth . 'px;"></div></div>'
            . '<div style="height:6px;background:#ececec;"><div style="height:6px;background:#9aa6b2;width:' . $classWidth . 'px;"></div></div>'
            . '</td>'
            . '</tr>';
    }

    return '<table cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #d6dde3;">'
        . '<tr><td colspan="2" style="background:#f4f8fb;padding:3px 4px;font-size:7.5pt;"><b>Subject Performance - Student vs Class</b></td></tr>'
        . '<tr><td colspan="2" style="padding:3px 4px;font-size:7pt;">'
        . '<span style="display:inline-block;width:8px;height:8px;background:#2f9ed6;"></span> Student'
        . '&nbsp;&nbsp;<span style="display:inline-block;width:8px;height:8px;background:#9aa6b2;"></span> Class Mean'
        . '</td></tr>'
        . $rows
        . '</table>';
}

function app_report_trend_chart_html(array $history): string
{
    if (empty($history)) {
        return '<div style="font-size:8pt;color:#555;">No trend data available.</div>';
    }

    $bars = '';
    foreach (array_slice($history, -6) as $point) {
        $label = (string)($point['term_name'] ?? 'Term');
        $mean = max(0, min(100, (float)($point['mean'] ?? 0)));
        $height = max(6, (int)round($mean * 0.72));
        $bars .= '<td style="vertical-align:bottom;text-align:center;padding:0 3px;">'
            . '<div style="margin:0 auto;width:14px;height:' . $height . 'px;background:#6eaee0;"></div>'
            . '<div style="font-size:6.8pt;line-height:1.1;margin-top:2px;">' . htmlspecialchars(substr($label, 0, 10)) . '</div>'
            . '</td>';
    }

    return '<table cellpadding="0" cellspacing="0" style="width:100%;border:1px solid #d6dde3;">'
        . '<tr><td style="background:#f4f8fb;padding:3px 4px;font-size:7.5pt;"><b>Performance Over Time</b></td></tr>'
        . '<tr><td style="padding:6px 4px;">'
        . '<table cellpadding="0" cellspacing="0" style="width:100%;"><tr>' . $bars . '</tr></table>'
        . '</td></tr>'
        . '</table>';
}

function app_report_one_page_html(PDO $conn, array $payload): string
{
    $card = $payload['card'];
    $subjects = is_array($card['subjects'] ?? null) ? $card['subjects'] : [];
    $subjectRows = '';

    $position = (int)($card['position'] ?? 0);
    $totalStudents = (int)($card['total_students'] ?? 0);

    $classId = (int)($card['class_id'] ?? 0);
    $termId = (int)($card['term_id'] ?? 0);
    $classMeans = app_report_class_subject_means($conn, $classId, $termId);
    $history = ($classId > 0 && $termId > 0)
        ? report_student_term_history($conn, (string)$payload['student_id'], $classId, 6)
        : [];

    foreach ($subjects as $subject) {
        $subjectId = (int)($subject['subject_id'] ?? 0);
        $classMean = (float)($classMeans[$subjectId] ?? 0);
        $deviation = round(((float)($subject['score'] ?? 0)) - $classMean, 1);
        $deviationLabel = ($deviation > 0 ? '+' : '') . number_format($deviation, 1);

        $subjectRows .= '<tr>'
            . '<td style="border:1px solid #444;padding:4px;font-size:8.3pt;">' . htmlspecialchars((string)($subject['subject_name'] ?? '')) . '</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . number_format((float)($subject['score'] ?? 0), 1) . '%</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . number_format($classMean, 1) . '%</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . $deviationLabel . '</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . htmlspecialchars((string)($subject['grade'] ?? '')) . '</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . $position . '/' . $totalStudents . '</td>'
            . '<td style="border:1px solid #444;padding:4px;font-size:8.3pt;">' . htmlspecialchars((string)($subject['teacher_name'] ?? '')) . '</td>'
            . '</tr>';
    }

    if ($subjectRows === '') {
        $subjectRows = '<tr><td colspan="7" style="border:1px solid #444;padding:6px;text-align:center;font-size:8.3pt;">No subjects available.</td></tr>';
    }

    $photoHtml = app_report_student_photo_html($conn, (string)$payload['student_id']);
    if ($photoHtml === '') {
        $photoHtml = '<div style="width:78px;height:88px;border:1px solid #8ea0b2;text-align:center;line-height:88px;font-size:8pt;color:#555;">PHOTO</div>';
    }

    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $schoolLogo = defined('WBLogo') ? (string)WBLogo : '';
    $schoolAddress = defined('WBAddress') ? (string)WBAddress : '';
    $schoolEmail = defined('WBEmail') ? (string)WBEmail : '';

    $logoHtml = app_pdf_image_html('images/logo/' . $schoolLogo, 56, 0, $schoolName);

    $subjectChartHtml = app_report_subject_chart_html($subjects, $classMeans);
    $trendChartHtml = app_report_trend_chart_html($history);

    return '
<table width="100%" cellpadding="3" cellspacing="0" style="font-family:helvetica,sans-serif;">
<tr>
    <td width="12%">' . $logoHtml . '</td>
    <td width="88%" style="text-align:right;">
        <div style="font-size:14pt;font-weight:bold;">' . htmlspecialchars($schoolName) . '</div>
        <div style="font-size:9pt;">' . htmlspecialchars($schoolAddress) . '</div>
        <div style="font-size:9pt;">' . htmlspecialchars($schoolEmail) . '</div>
    </td>
</tr>
</table>
<div style="background:#2f9ed6;color:#fff;text-align:center;padding:5px 6px;font-size:10pt;font-weight:bold;margin-top:4px;">
ACADEMIC REPORT FORM - ' . htmlspecialchars((string)$payload['class_name']) . ' - ' . htmlspecialchars((string)$payload['term_name']) . '
</div>
<table width="100%" cellpadding="4" cellspacing="0" style="margin-top:6px;">
<tr>
    <td width="20%" style="border:1px solid #95a5b3;vertical-align:top;">' . $photoHtml . '</td>
    <td width="40%" style="border:1px solid #95a5b3;vertical-align:top;">
        <div style="font-size:9pt;"><b>NAME:</b> ' . htmlspecialchars((string)$payload['student_name']) . '</div>
        <div style="font-size:9pt;"><b>ADMNO:</b> ' . htmlspecialchars((string)$payload['school_id']) . '</div>
        <div style="font-size:9pt;"><b>CLASS:</b> ' . htmlspecialchars((string)$payload['class_name']) . '</div>
        <div style="font-size:9pt;"><b>AVERAGE:</b> ' . number_format((float)($card['mean'] ?? 0), 2) . '%</div>
        <div style="font-size:9pt;"><b>MEAN POINTS:</b> ' . number_format((float)($card['mean_points'] ?? 0), 2) . '</div>
        <div style="font-size:9pt;"><b>GRADE:</b> ' . htmlspecialchars((string)($card['grade'] ?? 'N/A')) . '</div>
    </td>
    <td width="40%" style="border:1px solid #95a5b3;vertical-align:top;">' . $subjectChartHtml . '</td>
</tr>
</table>
<table width="100%" cellpadding="3" cellspacing="0" style="margin-top:6px;border-collapse:collapse;">
<tr>
    <td width="24%" style="border:1px solid #95a5b3;background:#eef5fa;">
        <div style="font-size:7.2pt;text-transform:uppercase;">Mean</div>
        <div style="font-size:10.5pt;"><b>' . htmlspecialchars((string)($card['grade'] ?? 'N/A')) . '</b> &nbsp; ' . number_format((float)($card['mean'] ?? 0), 2) . '%</div>
    </td>
    <td width="24%" style="border:1px solid #95a5b3;background:#eef5fa;">
        <div style="font-size:7.2pt;text-transform:uppercase;">Total Marks</div>
        <div style="font-size:10.5pt;"><b>' . number_format((float)($card['total'] ?? 0), 2) . '</b></div>
    </td>
    <td width="24%" style="border:1px solid #95a5b3;background:#eef5fa;">
        <div style="font-size:7.2pt;text-transform:uppercase;">Total Points</div>
        <div style="font-size:10.5pt;"><b>' . number_format((float)($card['mean_points'] ?? 0), 2) . '</b></div>
    </td>
    <td width="28%" style="border:1px solid #95a5b3;background:#eef5fa;">
        <div style="font-size:7.2pt;text-transform:uppercase;">Overall Position</div>
        <div style="font-size:10.5pt;"><b>' . $position . '/' . $totalStudents . '</b></div>
    </td>
</tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;border-collapse:collapse;">
<tr style="background:#eceff1;">
    <th style="border:1px solid #444;padding:4px;text-align:left;font-size:8pt;">SUBJECT</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">MARKS</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">CLASS AVG</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">DEV</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">GRADE</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">RANK</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">TEACHER</th>
</tr>
' . $subjectRows . '
</table>
<table width="100%" cellpadding="4" cellspacing="0" style="margin-top:6px;">
<tr>
    <td width="50%" style="border:1px solid #95a5b3;vertical-align:top;">
        ' . $trendChartHtml . '
        <div style="font-size:8pt;margin-top:5px;"><b>Attendance:</b> ' . (int)($payload['attendance']['present'] ?? 0) . '/' . (int)($payload['attendance']['days_open'] ?? 0) . '</div>
        <div style="font-size:8pt;"><b>Fees Balance:</b> KES ' . number_format((float)($payload['fees_balance'] ?? 0), 0) . '</div>
    </td>
    <td width="50%" style="border:1px solid #95a5b3;vertical-align:top;">
        <div style="font-size:8pt;"><b>Teacher Remark:</b></div>
        <div style="font-size:8.5pt;">' . htmlspecialchars((string)($card['teacher_comment'] ?? $card['remark'] ?? '')) . '</div>
        <div style="font-size:8pt;margin-top:4px;"><b>Headteacher Remark:</b></div>
        <div style="font-size:8.5pt;">' . htmlspecialchars((string)($card['headteacher_comment'] ?? $card['remark'] ?? '')) . '</div>
        <div style="font-size:8pt;margin-top:4px;"><b>AI Summary:</b> ' . htmlspecialchars((string)($card['ai_summary'] ?? '')) . '</div>
        <div style="font-size:8pt;"><b>Verification Code:</b> ' . htmlspecialchars((string)($card['verification_code'] ?? '')) . '</div>
        <div style="font-size:8pt;"><b>Document Hash:</b> ' . htmlspecialchars(substr((string)($card['report_hash'] ?? ''), 0, 24)) . '...</div>
        <div style="font-size:8pt;"><b>Generated:</b> ' . date('Y-m-d H:i') . '</div>
        <div style="font-size:8pt;margin-top:8px;"><b>Signature:</b> ________________________</div>
    </td>
</tr>
</table>
';
}

function app_output_single_page_report_pdf(PDO $conn, TCPDF $pdf, array $payload): void
{
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(false, 0);
    $pdf->SetMargins(8, 8, 8);
    $pdf->SetTitle('Academic Report Card');
    $pdf->AddPage('P', 'A4');
    $pdf->SetFont('helvetica', '', 9);

    $html = app_report_one_page_html($conn, $payload);
    $pdf->writeHTML($html, true, false, true, false, '');

    $verifyUrl = app_report_verify_url((string)($payload['card']['verification_code'] ?? ''));
    $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 12, 252, 24, 24);
}
