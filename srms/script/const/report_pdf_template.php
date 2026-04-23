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

function app_report_scale_html_font_sizes(string $html, float $scale): string
{
    $safeScale = max(0.55, min(1.30, $scale));
    return (string)preg_replace_callback(
        '/font-size\s*:\s*([0-9]+(?:\.[0-9]+)?)pt/i',
        static function (array $m) use ($safeScale): string {
            $base = (float)$m[1];
            $scaled = max(6.4, min(16.0, round($base * $safeScale, 2)));
            return 'font-size:' . rtrim(rtrim(number_format($scaled, 2, '.', ''), '0'), '.') . 'pt';
        },
        $html
    );
}

function app_report_pick_single_page_scale(TCPDF $pdf, string $html, float $topMargin, float $bottomMargin): float
{
    $pageHeight = (float)$pdf->getPageHeight();
    $usableHeight = max(1.0, $pageHeight - $topMargin - $bottomMargin);

    $chosenScale = 1.0;
    $bestUtilization = 0.0;

    for ($scale = 1.28; $scale >= 0.55; $scale -= 0.03) {
        $trialHtml = app_report_scale_html_font_sizes($html, $scale);
        $pdf->startTransaction();
        $startPage = (int)$pdf->getPage();
        $pdf->SetY($topMargin);
        $pdf->writeHTML($trialHtml, true, false, true, false, '');

        $endPage = (int)$pdf->getPage();
        $endY = (float)$pdf->GetY();
        $fitsOnePage = ($endPage === $startPage);
        $usedHeight = max(0.0, $endY - $topMargin);
        $utilization = min(1.0, $usedHeight / $usableHeight);

        $pdf->rollbackTransaction(true);

        if ($fitsOnePage) {
            $chosenScale = $scale;
            $bestUtilization = $utilization;
            if ($utilization >= 0.98) {
                break;
            }
        }
    }

    if ($bestUtilization < 0.80 && $chosenScale < 1.28) {
        $boostedScale = min(1.28, $chosenScale + 0.06);
        $trialHtml = app_report_scale_html_font_sizes($html, $boostedScale);
        $pdf->startTransaction();
        $startPage = (int)$pdf->getPage();
        $pdf->SetY($topMargin);
        $pdf->writeHTML($trialHtml, true, false, true, false, '');
        $fitsOnePage = ((int)$pdf->getPage() === $startPage);
        $pdf->rollbackTransaction(true);
        if ($fitsOnePage) {
            return $boostedScale;
        }
    }

    return $chosenScale;
}

function app_report_subject_table_density(int $subjectCount): array
{
    if ($subjectCount > 0 && $subjectCount <= 8) {
        return [
            'header_padding' => '3px 4px',
            'header_font' => '7.6pt',
            'cell_padding' => '3px 4px',
            'cell_font' => '7.9pt',
            'empty_padding' => '7px',
            'empty_font' => '8.2pt',
        ];
    }

    if ($subjectCount >= 16) {
        return [
            'header_padding' => '1px 2px',
            'header_font' => '6.8pt',
            'cell_padding' => '1px 2px',
            'cell_font' => '6.8pt',
            'empty_padding' => '4px',
            'empty_font' => '7.4pt',
        ];
    }

    if ($subjectCount >= 12) {
        return [
            'header_padding' => '1px 2px',
            'header_font' => '7pt',
            'cell_padding' => '1px 2px',
            'cell_font' => '7.1pt',
            'empty_padding' => '5px',
            'empty_font' => '7.8pt',
        ];
    }

    return [
        'header_padding' => '2px 3px',
        'header_font' => '7.3pt',
        'cell_padding' => '2px 3px',
        'cell_font' => '7.5pt',
        'empty_padding' => '6px',
        'empty_font' => '8pt',
    ];
}

function app_report_student_kcpe(PDO $conn, string $studentId): string
{
    try {
        if (!app_column_exists($conn, 'tbl_students', 'kcpe')) {
            return '';
        }
        $stmt = $conn->prepare('SELECT kcpe FROM tbl_students WHERE id = ? LIMIT 1');
        $stmt->execute([$studentId]);
        return trim((string)$stmt->fetchColumn());
    } catch (Throwable $e) {
        return '';
    }
}

function app_report_school_logo_html(): string
{
    $logoFile = defined('WBLogo') ? trim((string)WBLogo) : '';
    if ($logoFile === '') {
        return '';
    }
    $logoPath = 'images/logo/' . $logoFile;
    if (!is_file($logoPath)) {
        return '';
    }
    return '<img src="' . app_report_html($logoPath) . '" style="width:54px;height:54px;object-fit:contain;border:1px solid #d7d7d7;" />';
}

function app_report_setting_first_non_empty(PDO $conn, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        $value = trim((string)app_setting_get($conn, (string)$key, ''));
        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function app_report_school_dates_html(PDO $conn, string $termName): string
{
    $openingDate = app_report_setting_first_non_empty($conn, [
        'school_opening_date',
        'public_school_opening_date',
        'term_opening_date',
        'opening_date',
    ]);
    $closingDate = app_report_setting_first_non_empty($conn, [
        'school_closing_date',
        'public_school_closing_date',
        'term_closing_date',
        'closing_date',
    ]);

    $openingValue = $openingDate !== '' ? $openingDate : '________________';
    $closingValue = $closingDate !== '' ? $closingDate : '________________';

    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;height:100%;">'
        . '<tr><td style="border:1px solid #b0b0b0;padding:6px;vertical-align:top;height:100%;">'
        . '<div style="font-size:8.8pt;font-weight:bold;text-align:center;line-height:1.1;height:14px;">School Dates</div>'
        . '<div style="font-size:8pt;line-height:1.25;margin-top:8px;"><b>Opening Date:</b> ' . app_report_html($openingValue) . '</div>'
        . '<div style="font-size:8pt;line-height:1.25;margin-top:8px;"><b>Closing Date:</b> ' . app_report_html($closingValue) . '</div>'
        . '<div style="font-size:8pt;line-height:1.25;margin-top:8px;"><b>Term:</b> ' . app_report_html($termName) . '</div>'
        . '<div style="font-size:7.7pt;line-height:1.15;margin-top:10px;color:#555;">Dates shown here mirror the school calendar used for this report cycle.</div>'
        . '</td></tr>'
        . '</table>';
}

function app_report_performance_over_time_html(float $meanScore, float $classMeanAvg, float $totalMarks, float $maxMarks, float $totalPoints, float $pointsMax): string
{
    $bars = [
        ['label' => 'Current Mean', 'value' => $meanScore, 'max' => 100, 'color' => '#00aeef'],
        ['label' => 'Class Mean', 'value' => $classMeanAvg, 'max' => 100, 'color' => '#7f8c8d'],
        ['label' => 'Total Marks', 'value' => $totalMarks, 'max' => $maxMarks, 'color' => '#3e8e7e'],
        ['label' => 'Total Points', 'value' => $totalPoints, 'max' => $pointsMax, 'color' => '#f39c12'],
    ];

    $rows = '';
    foreach ($bars as $bar) {
        $percent = $bar['max'] > 0 ? max(0, min(100, ($bar['value'] / $bar['max']) * 100)) : 0;
        $rows .= '<tr style="height:16px;">'
            . '<td style="width:34%;font-size:7.8pt;padding:1px 3px 1px 0;white-space:nowrap;">' . app_report_html($bar['label']) . '</td>'
            . '<td style="width:56%;padding:2px 0 2px 0;">'
            . '<div style="background:#f1f1f1;height:7px;border:1px solid #d4d4d4;">'
            . '<div style="width:' . number_format($percent, 1, '.', '') . '%;height:5px;margin:1px;background:' . $bar['color'] . ';"></div>'
            . '</div>'
            . '</td>'
            . '<td style="width:10%;font-size:7.6pt;text-align:right;padding-left:3px;">' . number_format($bar['value'], 0) . '</td>'
            . '</tr>';
    }

    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;height:100%;">'
        . '<tr><td style="border:1px solid #b0b0b0;padding:6px;vertical-align:top;height:100%;">'
        . '<div style="font-size:8.8pt;font-weight:bold;text-align:center;line-height:1.1;height:14px;">Performance Over Time</div>'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:7px;table-layout:fixed;">' . $rows . '</table>'
        . '</td></tr>'
        . '</table>';
}

function app_report_render_layout(PDO $conn, array $payload, array $rows, string $examTitle): string
{
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : null;

    $studentName = (string)($payload['student_name'] ?? '');
    $schoolId = (string)($payload['school_id'] ?? '');
    $className = (string)($payload['class_name'] ?? '');
    $termName = (string)($payload['term_name'] ?? '');
    $studentId = (string)($payload['student_id'] ?? '');
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $schoolAddress = defined('WBAddress') ? (string)WBAddress : '';
    $schoolPhone = defined('WBPhone') ? (string)WBPhone : '';
    $schoolEmail = defined('WBEmail') ? (string)WBEmail : '';

    $photoHtml = app_report_student_photo_html($conn, $studentId);
    if ($photoHtml === '') {
        $photoHtml = '<div style="width:76px;height:88px;border:1px solid #8ea0b2;text-align:center;line-height:88px;font-size:8pt;color:#555;">PHOTO</div>';
    }
    $logoHtml = app_report_school_logo_html();
    $kcpe = app_report_student_kcpe($conn, $studentId);

    $subjectCount = count($rows);
    $totalMarks = isset($examSummary['total']) ? (float)$examSummary['total'] : (float)($card['total'] ?? 0);
    if ($totalMarks <= 0 && $subjectCount > 0) {
        $totalMarks = 0.0;
        foreach ($rows as $r) {
            $totalMarks += (float)($r['score'] ?? 0);
        }
    }
    $maxMarks = max(100, $subjectCount * 100);

    $gradePointMap = [
        'A+' => 12, 'A' => 11, 'A-' => 10, 'B+' => 9, 'B' => 8, 'B-' => 7,
        'C+' => 6, 'C' => 5, 'C-' => 4, 'D+' => 3, 'D' => 2, 'D-' => 1, 'E' => 0,
    ];
    $totalPoints = 0.0;
    $classMeanTotal = 0.0;
    foreach ($rows as $r) {
        $classMeanTotal += (float)($r['class_mean'] ?? 0);
        $gradeKey = strtoupper(trim((string)($r['grade'] ?? '')));
        $totalPoints += (float)($gradePointMap[$gradeKey] ?? 0);
    }
    $classMeanAvg = $subjectCount > 0 ? $classMeanTotal / $subjectCount : 0.0;
    $pointsMax = max(12, $subjectCount * 12);
    $classPointEstimate = ($classMeanAvg / 100) * $pointsMax;
    $meanScore = isset($examSummary['mean']) ? (float)$examSummary['mean'] : (float)($card['mean'] ?? 0);
    if ($meanScore <= 0 && $subjectCount > 0) {
        $meanScore = $totalMarks / $subjectCount;
    }
    $meanDev = $meanScore - $classMeanAvg;
    $totalDev = $totalMarks - $classMeanTotal;
    $pointsDev = $totalPoints - $classPointEstimate;
    $overallPosition = (string)($card['position'] ?? '-') . '/' . (string)($card['total_students'] ?? 0);
    $meanGrade = (string)($examSummary['grade'] ?? ($card['grade'] ?? 'N/A'));
    $gradingSystemId = report_exam_grading_system_id($conn, (int)($examSummary['exam_id'] ?? 0));

    $density = app_report_subject_table_density($subjectCount);
    $headerStyle = 'border:1px solid #aaa;padding:' . $density['header_padding'] . ';font-size:' . $density['header_font'] . ';font-weight:bold;text-transform:uppercase;text-align:center;line-height:1.1;height:20px;';
    $cellStyle = 'border:1px solid #aaa;padding:' . $density['cell_padding'] . ';font-size:' . $density['cell_font'] . ';line-height:1.15;height:19px;';
    $cellCenterStyle = $cellStyle . 'text-align:center;';

    $chartRows = '';
    foreach (array_slice($rows, 0, 6) as $row) {
        $studentWidth = max(0, min(100, (float)($row['score'] ?? 0)));
        $classWidth = max(0, min(100, (float)($row['class_mean'] ?? 0)));
        $chartRows .= '<tr>'
            . '<td style="font-size:7pt;padding:1px 0;width:22%;line-height:1.1;height:12px;">' . app_report_html(substr((string)($row['subject_name'] ?? ''), 0, 6)) . '</td>'
            . '<td style="padding:1px 0 1px 4px;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">'
            . '<tr><td style="background:#f0f0f0;height:7px;"><div style="width:' . number_format($studentWidth, 2, '.', '') . '%;height:7px;background:#7cb5ec;"></div></td></tr>'
            . '<tr><td style="background:#f7f7f7;height:4px;"><div style="width:' . number_format($classWidth, 2, '.', '') . '%;height:4px;background:#b0b0b0;"></div></td></tr>'
            . '</table>'
            . '</td>'
            . '</tr>';
    }
    if ($chartRows === '') {
        $chartRows = '<tr><td style="font-size:7pt;color:#666;" colspan="2">[Chart Placeholder]</td></tr>';
    }

    $subjectRows = '';
    foreach ($rows as $row) {
        $score = (float)($row['score'] ?? 0);
        $classMean = (float)($row['class_mean'] ?? 0);
        $dev = $score - $classMean;
        $devClass = $dev < 0 ? '#f39c12' : '#27ae60';
        $devArrow = $dev < 0 ? '&#9660;' : '&#9650;';
        $cat1 = $row['cat1'] ?? ($row['cat_1'] ?? '-');
        $cat2 = $row['cat2'] ?? ($row['cat_2'] ?? '-');
        $subjectRows .= '<tr style="height:20px;">'
            . '<td style="' . $cellStyle . 'text-align:left;font-weight:bold;">' . app_report_html((string)($row['subject_name'] ?? '')) . '</td>'
            . '<td style="' . $cellCenterStyle . '">' . (is_numeric($cat1) ? number_format((float)$cat1, 0) . '%' : app_report_html((string)$cat1)) . '</td>'
            . '<td style="' . $cellCenterStyle . '">' . (is_numeric($cat2) ? number_format((float)$cat2, 0) . '%' : app_report_html((string)$cat2)) . '</td>'
            . '<td style="' . $cellCenterStyle . '"><b>' . number_format($score, 0) . '%</b></td>'
            . '<td style="' . $cellCenterStyle . 'color:' . $devClass . ';font-weight:bold;">' . ($dev > 0 ? '+' : '') . number_format($dev, 0) . ' ' . $devArrow . '</td>'
            . '<td style="' . $cellCenterStyle . '"><b>' . app_report_html((string)($row['grade'] ?? '')) . '</b></td>'
            . '<td style="' . $cellCenterStyle . '">' . app_report_html((string)($row['position'] ?? ($row['rank'] ?? '-'))) . '</td>'
            . '<td style="' . $cellStyle . 'text-align:left;font-style:italic;">' . app_report_html((string)($row['remark'] ?? '')) . '</td>'
            . '<td style="' . $cellStyle . '">' . app_report_html((string)($row['teacher_name'] ?? '')) . '</td>'
            . '</tr>';
    }
    if ($subjectRows === '') {
        $subjectRows = '<tr><td colspan="9" style="border:1px solid #aaa;padding:6px;text-align:center;font-size:8pt;">No subject data available.</td></tr>';
    }

    $remarksLeft = app_report_html((string)($card['teacher_comment'] ?? $card['remark'] ?? ''));
    $remarksRight = app_report_html((string)($card['headteacher_comment'] ?? $card['remark'] ?? ''));
    $verificationCode = app_report_html((string)($card['verification_code'] ?? ''));
    $contactLine = implode(' | ', array_filter([trim($schoolAddress), trim($schoolPhone), trim($schoolEmail)]));
    $subjectRowsHtml = '';
    foreach ($rows as $row) {
        $cat1 = $row['cat1'] ?? ($row['cat_1'] ?? '-');
        $cat2 = $row['cat2'] ?? ($row['cat_2'] ?? '-');
        $score = (float)($row['score'] ?? 0);
        $classMean = (float)($row['class_mean'] ?? 0);
        $dev = $score - $classMean;
        $devColor = $dev > 0 ? '#128a42' : ($dev < 0 ? '#da8a00' : '#687886');
        $subjectRowsHtml .= '<tr>'
            . '<td style="text-align:left;">' . app_report_html((string)($row['subject_name'] ?? '')) . '</td>'
            . '<td style="text-align:center;">' . (is_numeric($cat1) ? number_format((float)$cat1, 1) . '%' : app_report_html((string)$cat1)) . '</td>'
            . '<td style="text-align:center;">' . (is_numeric($cat2) ? number_format((float)$cat2, 1) . '%' : app_report_html((string)$cat2)) . '</td>'
            . '<td style="text-align:center;">' . number_format($score, 1) . '%</td>'
            . '<td style="text-align:center;color:' . $devColor . ';font-weight:bold;">' . (($dev > 0 ? '+' : '') . number_format($dev, 1)) . '</td>'
            . '<td style="text-align:center;">' . app_report_html((string)($row['rank'] ?? $row['position'] ?? '-')) . '</td>'
            . '<td style="text-align:left;">' . app_report_html((string)($row['remark'] ?? '')) . '</td>'
            . '<td style="text-align:left;">' . app_report_html((string)($row['teacher_name'] ?? '')) . '</td>'
            . '</tr>';
    }
    if ($subjectRowsHtml === '') {
        $subjectRowsHtml = '<tr><td colspan="8" class="center">No subject data available.</td></tr>';
    }

    return '
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:Segoe UI,Arial,sans-serif;table-layout:fixed;">
    <tr>
        <td width="3.2%" style="background:#00aeef;"></td>
        <td width="96.8%" style="padding-left:6px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;height:88px;">
                <tr>
                    <td width="88" style="width:88px;height:88px;border:1px solid #d7d7d7;background:#fff;text-align:center;vertical-align:middle;">' . $logoHtml . '</td>
                    <td style="text-align:right;padding-left:16px;vertical-align:middle;">
                        <div style="font-size:15pt;font-weight:800;color:#1b2733;line-height:1.1;">' . app_report_html($schoolName) . '</div>
                        <div style="font-size:9pt;color:#4c5b68;line-height:1.2;margin-top:4px;">' . app_report_html($contactLine) . '</div>
                    </td>
                </tr>
            </table>

            <div style="background:#00aeef;color:#fff;text-align:center;padding:10px;font-size:9pt;font-weight:bold;margin:16px 0;text-transform:uppercase;line-height:1.1;">Academic Report Form - ' . app_report_html($className) . ' - ' . app_report_html($examTitle) . ' - (' . app_report_html($termName) . ')</div>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:16px;table-layout:fixed;">
                <tr>
                    <td width="145" style="width:145px;padding-right:16px;vertical-align:top;">
                        <div style="width:132px;height:152px;border:1px solid #c7d0d9;background:#f9fbfd;text-align:center;vertical-align:middle;overflow:hidden;">' . $photoHtml . '</div>
                    </td>
                    <td style="vertical-align:top;padding-right:16px;">
                        <div style="font-size:9.4pt;line-height:1.7;color:#2c3a46;"><strong>NAME:</strong> ' . app_report_html($studentName) . '</div>
                        <div style="font-size:9.4pt;line-height:1.7;color:#2c3a46;"><strong>ADMNO:</strong> ' . app_report_html($schoolId !== '' ? $schoolId : $studentId) . '</div>
                        <div style="font-size:9.4pt;line-height:1.7;color:#2c3a46;"><strong>FORM:</strong> ' . app_report_html($className) . '</div>
                        <div style="font-size:9.4pt;line-height:1.7;color:#2c3a46;"><strong>KCPE:</strong> ' . app_report_html($kcpe !== '' ? $kcpe : 'N/A') . '</div>
                    </td>
                    <td style="vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #d7d7d7;padding:10px;background:#fcfeff;">
                            <tr><td style="font-size:8pt;font-weight:bold;text-transform:uppercase;letter-spacing:0.03em;color:#4c5b68;padding-bottom:8px;">Subject Performance - Student vs Class</td></tr>
                            <tr><td>' . $chartRows . '</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:8px 0;margin-bottom:16px;table-layout:fixed;">
                <tr>
                    <td style="background:#f4f4f4;border-top:3px solid #00aeef;padding:10px;text-align:center;font-size:8.8pt;line-height:1.05;">Mean<br><strong style="font-size:10pt;">' . app_report_html($meanGrade) . '</strong> <span style="font-size:0.78em;margin-left:5px;font-weight:700;color:' . ($meanDev > 0 ? '#128a42' : ($meanDev < 0 ? '#da8a00' : '#687886')) . ';">' . ($meanDev > 0 ? '+' : '') . number_format($meanDev, 1) . '</span></td>
                    <td style="background:#f4f4f4;border-top:3px solid #00aeef;padding:10px;text-align:center;font-size:8.8pt;line-height:1.05;">Total Marks<br><strong style="font-size:10pt;">' . number_format($totalMarks, 0) . '/' . number_format($maxMarks, 0) . '</strong> <span style="font-size:0.78em;margin-left:5px;font-weight:700;color:' . ($totalDev > 0 ? '#128a42' : ($totalDev < 0 ? '#da8a00' : '#687886')) . ';">' . ($totalDev > 0 ? '+' : '') . number_format($totalDev, 0) . '</span></td>
                    <td style="background:#f4f4f4;border-top:3px solid #00aeef;padding:10px;text-align:center;font-size:8.8pt;line-height:1.05;">Total Points<br><strong style="font-size:10pt;">' . number_format($totalPoints, 1) . '/' . number_format($pointsMax, 0) . '</strong> <span style="font-size:0.78em;margin-left:5px;font-weight:700;color:' . ($pointsDev > 0 ? '#128a42' : ($pointsDev < 0 ? '#da8a00' : '#687886')) . ';">' . ($pointsDev > 0 ? '+' : '') . number_format($pointsDev, 1) . '</span></td>
                    <td style="background:#f4f4f4;border-top:3px solid #00aeef;padding:10px;text-align:center;font-size:8.8pt;line-height:1.05;">Stream Position<br><strong style="font-size:10pt;">' . app_report_html($overallPosition) . '</strong> <span class="dev flat">0</span></td>
                    <td style="background:#f4f4f4;border-top:3px solid #00aeef;padding:10px;text-align:center;font-size:8.8pt;line-height:1.05;">Overall Position<br><strong style="font-size:10pt;">' . app_report_html($overallPosition) . '</strong> <span class="dev flat">0</span></td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;margin-top:10px;">
                <thead>
                    <tr>
                        <th rowspan="2" style="' . $headerStyle . '">SUBJECT</th>
                        <th rowspan="2" style="' . $headerStyle . '">CAT 1</th>
                        <th rowspan="2" style="' . $headerStyle . '">CAT 2</th>
                        <th colspan="3" style="' . $headerStyle . '">' . app_report_html(strtoupper((string)($examTitle !== '' ? $examTitle : 'END TERM COMBINED'))) . '</th>
                        <th rowspan="2" style="' . $headerStyle . '">RANK</th>
                        <th rowspan="2" style="' . $headerStyle . '">COMMENT</th>
                        <th rowspan="2" style="' . $headerStyle . '">TEACHER</th>
                    </tr>
                    <tr>
                        <th style="' . $headerStyle . '">MARKS</th>
                        <th style="' . $headerStyle . '">DEV.</th>
                        <th style="' . $headerStyle . '">GR.</th>
                    </tr>
                </thead>
                <tbody>' . $subjectRowsHtml . '</tbody>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:14px 0;margin-top:18px;table-layout:fixed;">
                <tr>
                    <td style="vertical-align:top;">
                        <div style="border:1px solid #d8e2eb;background:#fafcfe;padding:12px;min-height:112px;">
                            <p style="margin:0 0 7px 0;font-size:9pt;font-weight:bold;color:#293843;">Remarks</p>
                            <p style="margin:7px 0;font-size:8.8pt;color:#293843;"><strong>Class Teacher:</strong> ' . $remarksLeft . '</p>
                            <p style="margin:7px 0;font-size:8.8pt;color:#293843;"><strong>Principal:</strong> ' . $remarksRight . '</p>
                            <p style="margin:12px 0 0 0;font-size:8.8pt;color:#293843;"><strong>Parent\'s Signature:</strong> ______________________</p>
                        </div>
                    </td>
                    <td width="112" style="width:112px;vertical-align:top;">
                        <div style="border:1px solid #d8e2eb;background:#fff;padding:8px;text-align:center;min-height:112px;">
                            <div style="width:92px;height:92px;margin:0 auto;"> </div>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>';
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
    if (empty($rows)) {
        return app_report_generic_html($conn, $payload);
    }
    $cycleLabels = array_values(array_filter(array_map('strval', $breakdownData['cycle_labels'] ?? [])));
    $cycleTitle = !empty($cycleLabels) ? implode(' / ', $cycleLabels) : 'COMBINED CYCLES';

    $normalizedRows = [];
    foreach ($rows as $row) {
        $cat1 = '-';
        $cat2 = '-';
        if (!empty($cycleLabels)) {
            $first = (string)$cycleLabels[0];
            $cat1 = $row['cycle_scores'][$first] ?? '-';
        }
        if (count($cycleLabels) > 1) {
            $second = (string)$cycleLabels[1];
            $cat2 = $row['cycle_scores'][$second] ?? '-';
        }
        $normalizedRows[] = [
            'subject_name' => (string)($row['subject_name'] ?? ''),
            'cat1' => $cat1,
            'cat2' => $cat2,
            'score' => (float)($row['combined_score'] ?? 0),
            'class_mean' => (float)($row['class_mean'] ?? 0),
            'grade' => (string)($row['grade'] ?? ''),
            'position' => (string)($row['position'] ?? '-'),
            'remark' => (string)($row['remark'] ?? ''),
            'teacher_name' => (string)($row['teacher_name'] ?? ''),
        ];
    }

    return app_report_render_layout($conn, $payload, $normalizedRows, $cycleTitle);
}

function app_report_generic_html(PDO $conn, array $payload): string
{
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : null;
    $subjects = is_array($payload['exam_breakdown'] ?? null) ? $payload['exam_breakdown'] : [];

    if (empty($subjects) && !empty($card['class_id']) && !empty($card['term_id']) && !empty($payload['student_id'])) {
        $subjects = report_subject_breakdown(
            $conn,
            (string)$payload['student_id'],
            (int)$card['class_id'],
            (int)$card['term_id']
        );
    }

    $examTitle = (string)($examSummary['exam_name'] ?? 'End Term Combined');
    return app_report_render_layout($conn, $payload, $subjects, $examTitle);
}

function app_output_single_page_report_pdf(PDO $conn, TCPDF $pdf, array $payload): void
{
    $topMargin = 6.5;
    $bottomMargin = 6.5;

    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetAutoPageBreak(true, $bottomMargin);
    $pdf->SetMargins(7, $topMargin, 7);
    $pdf->SetTitle('Academic Report Card');
    $pdf->AddPage('P', 'A4');
    $pdf->SetFont('helvetica', '', 9);

    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : null;
    $examMode = strtolower(trim((string)($examSummary['assessment_mode'] ?? 'normal')));
    $html = ($examMode === 'consolidated')
        ? app_report_combined_cycles_html($conn, $payload)
        : app_report_generic_html($conn, $payload);

    // Keep deterministic output for spec fidelity by disabling adaptive scaling.
    $scaledHtml = app_report_scale_html_font_sizes($html, 1.0);

    $pdf->SetY($topMargin);
    $pdf->writeHTML($scaledHtml, true, false, true, false, '');

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
