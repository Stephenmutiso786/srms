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
        $stmt = $conn->prepare("SELECT gender, image FROM tbl_students WHERE id = ? LIMIT 1");
        $stmt->execute([$studentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return '';
        }

        $image = trim((string)($row['image'] ?? ''));
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

function app_report_one_page_html(PDO $conn, array $payload): string
{
    $card = $payload['card'];
    $subjects = is_array($card['subjects'] ?? null) ? $card['subjects'] : [];
    $subjectRows = '';

    $position = (int)($card['position'] ?? 0);
    $totalStudents = (int)($card['total_students'] ?? 0);

    foreach ($subjects as $subject) {
        $subjectRows .= '<tr>'
            . '<td style="border:1px solid #444;padding:4px;font-size:8.3pt;">' . htmlspecialchars((string)($subject['subject_name'] ?? '')) . '</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . number_format((float)($subject['score'] ?? 0), 1) . '%</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . htmlspecialchars((string)($subject['grade'] ?? '')) . '</td>'
            . '<td style="border:1px solid #444;padding:4px;text-align:center;font-size:8.3pt;">' . $position . '/' . $totalStudents . '</td>'
            . '<td style="border:1px solid #444;padding:4px;font-size:8.3pt;">' . htmlspecialchars((string)($subject['teacher_name'] ?? '')) . '</td>'
            . '</tr>';
    }

    if ($subjectRows === '') {
        $subjectRows = '<tr><td colspan="5" style="border:1px solid #444;padding:6px;text-align:center;font-size:8.3pt;">No subjects available.</td></tr>';
    }

    $photoHtml = app_report_student_photo_html($conn, (string)$payload['student_id']);
    if ($photoHtml === '') {
        $photoHtml = '<div style="width:78px;height:88px;border:1px solid #8ea0b2;text-align:center;line-height:88px;font-size:8pt;color:#555;">PHOTO</div>';
    }

    $logoHtml = app_pdf_image_html('images/logo/' . WBLogo, 56, 0, WBName);

    return '
<table width="100%" cellpadding="3" cellspacing="0" style="font-family:helvetica,sans-serif;">
<tr>
    <td width="12%">' . $logoHtml . '</td>
    <td width="88%" style="text-align:right;">
        <div style="font-size:14pt;font-weight:bold;">' . htmlspecialchars(WBName) . '</div>
        <div style="font-size:9pt;">' . htmlspecialchars(WBAddress) . '</div>
        <div style="font-size:9pt;">' . htmlspecialchars(WBEmail) . '</div>
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
    <td width="40%" style="border:1px solid #95a5b3;vertical-align:top;">
        <div style="font-size:9pt;"><b>TOTAL MARKS:</b> ' . number_format((float)($card['total'] ?? 0), 2) . '</div>
        <div style="font-size:9pt;"><b>POSITION:</b> ' . $position . '/' . $totalStudents . '</div>
        <div style="font-size:9pt;"><b>ATTENDANCE:</b> ' . (int)($payload['attendance']['present'] ?? 0) . '/' . (int)($payload['attendance']['days_open'] ?? 0) . '</div>
        <div style="font-size:9pt;"><b>TREND:</b> ' . htmlspecialchars((string)($card['trend'] ?? 'N/A')) . '</div>
        <div style="font-size:9pt;"><b>FEES BAL:</b> KES ' . number_format((float)($payload['fees_balance'] ?? 0), 0) . '</div>
    </td>
</tr>
</table>
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;border-collapse:collapse;">
<tr style="background:#eceff1;">
    <th style="border:1px solid #444;padding:4px;text-align:left;font-size:8pt;">SUBJECT</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">MARKS</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">GRADE</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">RANK</th>
    <th style="border:1px solid #444;padding:4px;font-size:8pt;">TEACHER</th>
</tr>
' . $subjectRows . '
</table>
<table width="100%" cellpadding="4" cellspacing="0" style="margin-top:6px;">
<tr>
    <td width="50%" style="border:1px solid #95a5b3;vertical-align:top;">
        <div style="font-size:8pt;"><b>Teacher Remark:</b></div>
        <div style="font-size:8.5pt;">' . htmlspecialchars((string)($card['teacher_comment'] ?? $card['remark'] ?? '')) . '</div>
        <div style="font-size:8pt;margin-top:4px;"><b>Headteacher Remark:</b></div>
        <div style="font-size:8.5pt;">' . htmlspecialchars((string)($card['headteacher_comment'] ?? $card['remark'] ?? '')) . '</div>
        <div style="font-size:8pt;margin-top:4px;"><b>AI Summary:</b> ' . htmlspecialchars((string)($card['ai_summary'] ?? '')) . '</div>
    </td>
    <td width="50%" style="border:1px solid #95a5b3;vertical-align:top;">
        <div style="font-size:8pt;"><b>Verification Code:</b> ' . htmlspecialchars((string)($card['verification_code'] ?? '')) . '</div>
        <div style="font-size:8pt;"><b>Document Hash:</b> ' . htmlspecialchars(substr((string)($card['report_hash'] ?? ''), 0, 24)) . '...</div>
        <div style="font-size:8pt;"><b>Generated:</b> ' . date('Y-m-d H:i') . '</div>
        <div style="font-size:8pt;"><b>Scan QR to verify originality.</b></div>
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
    $pdf->SetFont('helvetica', '', 7);
    $pdf->Text(38, 261, 'Verify: ' . $verifyUrl);
}
