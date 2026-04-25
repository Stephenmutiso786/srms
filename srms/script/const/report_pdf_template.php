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
        . '<tr><td style="padding:0;vertical-align:top;height:100%;">'
        . '<div style="background:#1db14b;color:#fff;padding:5px 8px;font-size:9pt;font-weight:bold;text-transform:uppercase;line-height:1.1;">School Dates</div>'
        . '<div style="border:1px solid #8fbc8f;border-top:0;padding:8px 10px 10px 10px;font-size:8.4pt;line-height:1.35;color:#1f2f3a;">'
        . '<div style="margin:2px 0;"><b>Closing Date:</b> ' . app_report_html($closingValue) . '</div>'
        . '<div style="margin:7px 0 2px 0;"><b>Opening Date:</b> ' . app_report_html($openingValue) . '</div>'
        . '<div style="margin-top:6px;font-size:7.8pt;color:#4d5d68;">' . app_report_html($termName !== '' ? $termName : 'Term details') . '</div>'
        . '</div>'
        . '</td></tr>'
        . '</table>';
}

function app_report_subject_history_data(PDO $conn, string $studentId, int $classId, int $limitTerms = 5): array
{
    $limitTerms = max(2, min(8, $limitTerms));
    if ($studentId === '' || $classId < 1 || !app_table_exists($conn, 'tbl_report_cards') || !app_table_exists($conn, 'tbl_report_card_subjects')) {
        return ['terms' => [], 'subjects' => []];
    }

    $stmt = $conn->prepare("SELECT id, term_id
        FROM tbl_report_cards
        WHERE student_id = ? AND class_id = ?
        ORDER BY term_id DESC
        LIMIT $limitTerms");
    $stmt->execute([$studentId, $classId]);
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($cards)) {
        return ['terms' => [], 'subjects' => []];
    }

    $cards = array_reverse($cards);
    $reportIds = array_map(static function ($row) { return (int)$row['id']; }, $cards);
    $termByReport = [];
    $termIds = [];
    foreach ($cards as $row) {
        $rid = (int)$row['id'];
        $tid = (int)$row['term_id'];
        $termByReport[$rid] = $tid;
        $termIds[$tid] = true;
    }

    $termNames = [];
    if (!empty($termIds) && app_table_exists($conn, 'tbl_terms')) {
        $ids = array_keys($termIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $termNames[(int)$row['id']] = (string)($row['name'] ?? '');
        }
    }

    $placeholders = implode(',', array_fill(0, count($reportIds), '?'));
    $stmt = $conn->prepare("SELECT rs.report_id, rs.subject_id, rs.score, s.name AS subject_name
        FROM tbl_report_card_subjects rs
        LEFT JOIN tbl_subjects s ON s.id = rs.subject_id
        WHERE rs.report_id IN ($placeholders)
        ORDER BY s.name, rs.report_id");
    $stmt->execute($reportIds);

    $subjects = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rid = (int)($row['report_id'] ?? 0);
        $sid = (int)($row['subject_id'] ?? 0);
        if ($rid < 1 || $sid < 1 || !isset($termByReport[$rid])) {
            continue;
        }
        $tid = (int)$termByReport[$rid];
        if (!isset($subjects[$sid])) {
            $subjects[$sid] = [
                'subject_id' => $sid,
                'subject_name' => (string)($row['subject_name'] ?? ('Subject ' . $sid)),
                'scores' => [],
            ];
        }
        $subjects[$sid]['scores'][$tid] = (float)($row['score'] ?? 0);
    }

    $terms = [];
    foreach ($cards as $row) {
        $tid = (int)($row['term_id'] ?? 0);
        $terms[] = [
            'term_id' => $tid,
            'label' => (string)($termNames[$tid] ?? ('T' . $tid)),
        ];
    }

    return ['terms' => $terms, 'subjects' => array_values($subjects)];
}

function app_report_subject_trends_html(PDO $conn, string $studentId, int $classId, array $currentRows): string
{
    $history = app_report_subject_history_data($conn, $studentId, $classId, 4);
    $terms = is_array($history['terms'] ?? null) ? $history['terms'] : [];
    $subjects = is_array($history['subjects'] ?? null) ? $history['subjects'] : [];
    if (empty($terms) || empty($subjects)) {
        return '<div style="font-size:8pt;color:#666;">No multi-term subject history available yet.</div>';
    }

    $priority = [];
    foreach ($currentRows as $row) {
        $name = strtolower(trim((string)($row['subject_name'] ?? '')));
        if ($name !== '') {
            $priority[$name] = true;
        }
    }
    usort($subjects, static function (array $a, array $b) use ($priority): int {
        $ak = strtolower((string)($a['subject_name'] ?? ''));
        $bk = strtolower((string)($b['subject_name'] ?? ''));
        $ap = isset($priority[$ak]) ? 1 : 0;
        $bp = isset($priority[$bk]) ? 1 : 0;
        if ($ap !== $bp) {
            return $bp <=> $ap;
        }
        return strcmp((string)$a['subject_name'], (string)$b['subject_name']);
    });
    $subjects = array_slice($subjects, 0, 4);

    $rowsHtml = '';
    foreach ($subjects as $subject) {
        $name = app_report_html((string)($subject['subject_name'] ?? 'Subject'));
        $scores = is_array($subject['scores'] ?? null) ? $subject['scores'] : [];

        $bars = '';
        foreach ($terms as $term) {
            $tid = (int)($term['term_id'] ?? 0);
            $value = (float)($scores[$tid] ?? 0);
            $h = (int)round(max(3, min(18, ($value / 100) * 18)));
            $bars .= '<td style="text-align:center;vertical-align:bottom;padding:0 2px;">'
                . '<div style="height:18px;display:block;position:relative;">'
                . '<div style="position:absolute;bottom:0;left:50%;margin-left:-5px;width:10px;height:' . $h . 'px;background:#5ea1d8;border:1px solid #4f84b4;"></div>'
                . '</div>'
                . '<div style="font-size:6.2pt;color:#6a7680;line-height:1.0;">' . number_format($value, 0) . '</div>'
                . '</td>';
        }

        $rowsHtml .= '<tr>'
            . '<td style="font-size:7.2pt;padding:2px 3px;white-space:nowrap;">' . $name . '</td>'
            . '<td style="padding:0 0 2px 0;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;"><tr>' . $bars . '</tr></table>'
            . '</td>'
            . '</tr>';
    }

    $labels = '';
    foreach ($terms as $term) {
        $labels .= '<td style="font-size:6pt;color:#6a7680;text-align:center;padding-top:1px;">' . app_report_html((string)($term['label'] ?? '')) . '</td>';
    }

    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">'
        . '<tr><td colspan="2" style="font-size:8.2pt;font-weight:bold;color:#1f2f3a;padding-bottom:2px;">Subject Performance Over Time</td></tr>'
        . $rowsHtml
        . '<tr><td></td><td><table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;"><tr>' . $labels . '</tr></table></td></tr>'
        . '</table>';
}

function app_report_history_chart_html(PDO $conn, string $studentId, int $classId, float $fallbackMean, string $studentName): string
{
    $history = [];
    try {
        if ($studentId !== '' && $classId > 0) {
            $history = report_student_term_history($conn, $studentId, $classId, 5);
        }
    } catch (Throwable $e) {
        $history = [];
    }

    $series = [];
    foreach ($history as $item) {
        $series[] = [
            'label' => (string)($item['term_name'] ?? ''),
            'value' => (float)($item['mean'] ?? 0),
        ];
    }
    if (empty($series)) {
        $series[] = ['label' => 'Current', 'value' => $fallbackMean];
    }

    $maxValue = 100.0;
    foreach ($series as $item) {
        $maxValue = max($maxValue, (float)$item['value']);
    }

    $barCells = '';
    foreach ($series as $item) {
        $value = max(0, (float)$item['value']);
        $height = (int)round(max(10, min(72, ($value / max(1.0, $maxValue)) * 72)));
        $safeLabel = app_report_html((string)$item['label']);
        $barCells .= '<td style="width:' . number_format(100 / max(1, count($series)), 2, '.', '') . '%;vertical-align:bottom;text-align:center;padding:0 2px;">'
            . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">'
            . '<tr><td style="height:72px;vertical-align:bottom;text-align:center;">'
            . '<div style="width:20px;height:' . $height . 'px;background:#5ea1d8;border:1px solid #4f84b4;margin:0 auto;"></div>'
            . '</td></tr>'
            . '<tr><td style="font-size:6.8pt;line-height:1.1;margin-top:3px;color:#6a7680;text-align:center;">' . $safeLabel . '</td></tr>'
            . '</table>'
            . '</td>';
    }

    return '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;height:100%;">'
        . '<tr><td style="padding:0;vertical-align:top;height:100%;">'
        . '<div style="font-size:8.8pt;font-weight:bold;color:#1f2f3a;margin-bottom:6px;">' . app_report_html($studentName) . '&#8217;s Performance over Time</div>'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;height:86px;border-bottom:1px solid #d6dce2;">'
        . '<tr><td width="18" style="font-size:7pt;color:#76838f;text-align:right;vertical-align:top;padding-right:4px;">90</td><td style="border-bottom:1px solid #eef2f5;"></td></tr>'
        . '<tr><td width="18" style="font-size:7pt;color:#76838f;text-align:right;vertical-align:middle;padding-right:4px;">70</td><td style="vertical-align:bottom;padding-top:10px;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">'
        . '<tr>' . $barCells . '</tr>'
        . '</table>'
        . '</td></tr>'
        . '</table>'
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

    $examRows = $rows;
    $classId = (int)($card['class_id'] ?? 0);
    $termId = (int)($card['term_id'] ?? 0);
    if (empty($examRows) && $studentId !== '' && $classId > 0 && $termId > 0) {
        $examRows = report_subject_breakdown($conn, $studentId, $classId, $termId);
    }

    $subjectCount = count($examRows);
    $totalMarks = isset($examSummary['total']) ? (float)$examSummary['total'] : (float)($card['total'] ?? 0);
    if ($totalMarks <= 0 && $subjectCount > 0) {
        $totalMarks = 0.0;
        foreach ($examRows as $r) {
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
    foreach ($examRows as $r) {
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
    $density = app_report_subject_table_density($subjectCount);
    $headerStyle = 'border:1px solid #aaa;padding:' . $density['header_padding'] . ';font-size:' . $density['header_font'] . ';font-weight:bold;text-transform:uppercase;text-align:center;line-height:1.1;height:20px;';
    $cellStyle = 'border:1px solid #aaa;padding:' . $density['cell_padding'] . ';font-size:' . $density['cell_font'] . ';line-height:1.15;height:19px;';
    $cellCenterStyle = $cellStyle . 'text-align:center;';

    $remarksLeft = app_report_html((string)($card['teacher_comment'] ?? $card['remark'] ?? ''));
    $remarksRight = app_report_html((string)($card['headteacher_comment'] ?? $card['remark'] ?? ''));
    $verificationCode = (string)($card['verification_code'] ?? '');

    $chartRows = '';
    foreach (array_slice($examRows, 0, 6) as $row) {
        $studentWidth = max(0, min(100, (float)($row['score'] ?? 0)));
        $classWidth = max(0, min(100, (float)($row['class_mean'] ?? 0)));
        $chartRows .= '<div style="display:table;width:100%;margin-bottom:6px;">'
            . '<div style="display:table-cell;width:55px;font-size:7.6pt;color:#4f5d68;vertical-align:middle;">' . app_report_html(substr((string)($row['subject_name'] ?? ''), 0, 8)) . '</div>'
            . '<div style="display:table-cell;vertical-align:middle;">'
            . '<div style="height:12px;background:#e5ecf2;position:relative;">'
            . '<div style="position:absolute;left:0;top:0;height:12px;background:#1a8fd4;opacity:0.9;width:' . number_format($studentWidth, 2, '.', '') . '%;"></div>'
            . '<div style="position:absolute;left:0;bottom:0;height:6px;background:#38b56a;opacity:0.75;width:' . number_format($classWidth, 2, '.', '') . '%;"></div>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
    if ($chartRows === '') {
        $chartRows = '<div style="font-size:7pt;color:#666;">No performance data available.</div>';
    }

    $subjectRowsHtml = '';
    foreach ($examRows as $row) {
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
        $subjectRowsHtml = '<tr><td colspan="8" style="text-align:center;">No subject data available.</td></tr>';
    }

    $schoolDatesHtml = app_report_school_dates_html($conn, $termName);
    $historyChartHtml = app_report_subject_trends_html($conn, $studentId, $classId, $examRows);
    $verificationText = $schoolId !== '' ? $schoolId . '@fsk' : $studentId . '@fsk';

    return '
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;table-layout:fixed;">
    <tr>
        <td width="12" style="width:12px;background:#36b44a;"></td>
        <td style="padding-left:8px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;height:88px;">
                <tr>
                    <td width="84" style="width:84px;vertical-align:top;">
                        <div style="width:72px;height:72px;border:1px solid #d7d7d7;background:#fff;text-align:center;line-height:72px;">' . $logoHtml . '</div>
                    </td>
                    <td style="vertical-align:top;text-align:right;padding-top:2px;">
                        <div style="font-size:16pt;font-weight:bold;color:#111;line-height:1.1;">' . app_report_html($schoolName) . '</div>
                        <div style="font-size:9.5pt;font-weight:bold;color:#333;line-height:1.2;margin-top:2px;">' . app_report_html(defined('WBAddress') ? (string)WBAddress : '') . '</div>
                        <div style="font-size:9.3pt;font-weight:bold;color:#333;line-height:1.2;">' . app_report_html(trim((string)WBPhone !== '' ? (string)WBPhone : $schoolPhone)) . '</div>
                        <div style="font-size:9.3pt;font-weight:bold;color:#333;line-height:1.2;">' . app_report_html(trim((string)WBEmail !== '' ? (string)WBEmail : $schoolEmail)) . '</div>
                    </td>
                </tr>
            </table>

            <div style="background:#37aee3;color:#fff;text-align:center;padding:7px 10px;font-size:9.2pt;font-weight:bold;margin:9px 0 12px 0;text-transform:uppercase;line-height:1.1;">ACADEMIC REPORT FORM - ' . app_report_html($className) . ' - ' . app_report_html($examTitle) . ' - (' . app_report_html($termName) . ')</div>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-bottom:12px;table-layout:fixed;">
                <tr>
                    <td width="145" style="width:145px;padding-right:14px;vertical-align:top;">
                        <div style="width:132px;height:152px;border:1px solid #c7d0d9;background:#f9fbfd;text-align:center;overflow:hidden;">' . $photoHtml . '</div>
                    </td>
                    <td style="vertical-align:top;padding-right:12px;">
                        <div style="font-size:9.4pt;font-weight:bold;line-height:1.5;color:#1f2f3a;"><strong>NAME:</strong> ' . app_report_html($studentName) . '</div>
                        <div style="font-size:9.4pt;font-weight:bold;line-height:1.5;color:#1f2f3a;"><strong>ADMNO:</strong> ' . app_report_html($schoolId !== '' ? $schoolId : $studentId) . '</div>
                        <div style="font-size:9.4pt;font-weight:bold;line-height:1.5;color:#1f2f3a;"><strong>FORM:</strong> ' . app_report_html($className) . '</div>
                        <div style="font-size:9.4pt;font-weight:bold;line-height:1.5;color:#1f2f3a;"><strong>KCPE:</strong> ' . app_report_html($kcpe !== '' ? $kcpe : 'N/A') . '</div>
                        <div style="font-size:9.4pt;font-weight:bold;line-height:1.5;color:#1f2f3a;"><strong>VAP:</strong> ' . (($meanDev > 0 ? '+' : '') . number_format($meanDev, 2)) . '</div>
                    </td>
                    <td style="vertical-align:top;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;border:1px solid #d7d7d7;background:#fcfeff;">
                            <tr><td style="padding:10px 10px 8px 10px;font-size:8.2pt;font-weight:bold;text-align:center;color:#4c5b68;">Subject Performance - Student vs Class</td></tr>
                            <tr><td style="padding:0 10px 10px 10px;">' . $chartRows . '</td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:7px 0;margin-bottom:11px;table-layout:fixed;">
                <tr>
                    <td style="background:#dff1fb;border-top:3px solid #37aee3;padding:9px 6px;text-align:center;font-size:8.7pt;line-height:1.05;">Mean<br><strong style="font-size:10pt;">' . app_report_html($meanGrade) . '</strong> <span style="font-size:0.78em;margin-left:5px;font-weight:700;color:' . ($meanDev > 0 ? '#128a42' : ($meanDev < 0 ? '#da8a00' : '#687886')) . ';">' . ($meanDev > 0 ? '+' : '') . number_format($meanDev, 1) . '</span></td>
                    <td style="background:#dff1fb;border-top:3px solid #37aee3;padding:9px 6px;text-align:center;font-size:8.7pt;line-height:1.05;">Total Marks<br><strong style="font-size:10pt;">' . number_format($totalMarks, 0) . '/' . number_format($maxMarks, 0) . '</strong> <span style="font-size:0.78em;margin-left:5px;font-weight:700;color:' . ($totalDev > 0 ? '#128a42' : ($totalDev < 0 ? '#da8a00' : '#687886')) . ';">' . ($totalDev > 0 ? '+' : '') . number_format($totalDev, 0) . '</span></td>
                    <td style="background:#dff1fb;border-top:3px solid #37aee3;padding:9px 6px;text-align:center;font-size:8.7pt;line-height:1.05;">Total Points<br><strong style="font-size:10pt;">' . number_format($totalPoints, 1) . '/' . number_format($pointsMax, 0) . '</strong> <span style="font-size:0.78em;margin-left:5px;font-weight:700;color:' . ($pointsDev > 0 ? '#128a42' : ($pointsDev < 0 ? '#da8a00' : '#687886')) . ';">' . ($pointsDev > 0 ? '+' : '') . number_format($pointsDev, 1) . '</span></td>
                    <td style="background:#dff1fb;border-top:3px solid #37aee3;padding:9px 6px;text-align:center;font-size:8.7pt;line-height:1.05;">Stream Position<br><strong style="font-size:10pt;">' . app_report_html($overallPosition) . '</strong></td>
                    <td style="background:#dff1fb;border-top:3px solid #37aee3;padding:9px 6px;text-align:center;font-size:8.7pt;line-height:1.05;">Overall Position<br><strong style="font-size:10pt;">' . app_report_html($overallPosition) . '</strong></td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;margin-top:8px;">
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

            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:14px 0;margin-top:12px;table-layout:fixed;">
                <tr>
                    <td width="58%" style="vertical-align:top;">
                        ' . $historyChartHtml . '
                        <div style="margin-top:10px;">' . $schoolDatesHtml . '</div>
                    </td>
                    <td width="42%" style="vertical-align:top;">
                        <div style="border-left:1px solid #1f2f3a;padding-left:12px;min-height:182px;">
                            <div style="font-size:9pt;font-weight:bold;color:#1f2f3a;margin-bottom:6px;">Remarks</div>
                            <div style="font-size:8.8pt;line-height:1.35;color:#1f2f3a;margin-bottom:7px;"><strong>Class Teacher</strong><br>' . $remarksLeft . '</div>
                            <div style="font-size:8.8pt;line-height:1.35;color:#1f2f3a;margin-bottom:10px;"><strong>Principal</strong><br>' . $remarksRight . '</div>
                            <div style="font-size:8.8pt;line-height:1.3;color:#1f2f3a;">Parent\'s Signature:</div>
                            <div style="border-bottom:1px solid #222;height:16px;margin:0 0 8px 0;width:80%;"></div>
                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                                <tr>
                                    <td width="70" style="width:70px;vertical-align:top;">
                                        <div style="width:58px;height:58px;border:1px solid #1db14b;padding:2px;background:#fff;">
                                            <div style="width:52px;height:52px;background:linear-gradient(180deg,#dff6df,#b6ebb6);border:1px solid #7fc77f;"></div>
                                        </div>
                                    </td>
                                    <td style="vertical-align:top;padding-left:8px;font-size:8.6pt;line-height:1.25;color:#1f2f3a;">
                                        Scan to access your interactive student profile on Zeraki Analytics. Your username: ' . app_report_html($verificationText) . '
                                    </td>
                                </tr>
                            </table>
                            <div style="font-size:8.4pt;font-weight:bold;color:#1f2f3a;margin-top:8px;">Verification Code: ' . app_report_html($verificationCode) . '</div>
                        </div>
                    </td>
                </tr>
            </table>

            <div style="margin-top:8px;text-align:right;">
                <span style="display:inline-block;background:#39b54a;color:#fff;font-size:8.4pt;font-weight:bold;padding:4px 10px;">School Motto: ' . app_report_html((string)WBMotto) . '</span>
            </div>
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
    $topMargin = 5.5;
    $bottomMargin = 5.5;

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

    // Fit to a single page while preserving the same layout geometry.
    $scale = app_report_pick_single_page_scale($pdf, $html, $topMargin, $bottomMargin);
    if ($scale <= 0) {
        $scale = 0.72;
    }
    $scaledHtml = app_report_scale_html_font_sizes($html, $scale);

    $pdf->SetY($topMargin);
    $pdf->writeHTML($scaledHtml, true, false, true, false, '');

    $verifyUrl = app_report_verify_url((string)($payload['card']['verification_code'] ?? ''));
    if ($verifyUrl !== '') {
        $pdf->lastPage();
        $margins = $pdf->getMargins();
        $qrSize = 18;
        $x = (float)$margins['left'] + 112;
        $y = $pdf->getPageHeight() - (float)$margins['bottom'] - 74;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', $x, $y, $qrSize, $qrSize);
    }
}
