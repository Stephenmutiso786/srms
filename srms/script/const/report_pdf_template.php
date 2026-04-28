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

    $chosenScale = 0.55;
    $bestUtilization = 0.0;
    $foundOnePageScale = false;

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
            $foundOnePageScale = true;
            $chosenScale = $scale;
            $bestUtilization = $utilization;
            if ($utilization >= 0.98) {
                break;
            }
        }
    }

    if ($foundOnePageScale && $bestUtilization < 0.80 && $chosenScale < 1.28) {
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

function app_report_rank_label(array $scores, string $studentId): string
{
    if ($studentId === '' || empty($scores)) {
        return '-';
    }

    arsort($scores, SORT_NUMERIC);
    $rank = 0;
    $position = 0;
    $prev = null;
    $total = count($scores);

    foreach ($scores as $rowStudentId => $score) {
        $position++;
        if ($prev === null || (float)$score !== (float)$prev) {
            $rank = $position;
            $prev = (float)$score;
        }
        if ((string)$rowStudentId === $studentId) {
            return $rank . '/' . $total;
        }
    }

    return '-';
}

function app_report_position_metrics(PDO $conn, string $studentId, int $classId, int $termId, array $card): array
{
    $fallbackPosition = (int)($card['position'] ?? 0);
    $fallbackTotal = (int)($card['total_students'] ?? 0);
    $fallbackLabel = ($fallbackPosition > 0 && $fallbackTotal > 0) ? ($fallbackPosition . '/' . $fallbackTotal) : '-';

    if ($studentId === '' || $classId < 1 || $termId < 1 || !app_table_exists($conn, 'tbl_report_cards')) {
        return ['stream' => $fallbackLabel, 'overall' => $fallbackLabel];
    }

    $stmt = $conn->prepare('SELECT student_id, mean FROM tbl_report_cards WHERE class_id = ? AND term_id = ?');
    $stmt->execute([$classId, $termId]);
    $streamScores = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (string)($row['student_id'] ?? '');
        if ($sid !== '') {
            $streamScores[$sid] = (float)($row['mean'] ?? 0);
        }
    }

    $stmt = $conn->prepare('SELECT student_id, mean FROM tbl_report_cards WHERE term_id = ?');
    $stmt->execute([$termId]);
    $overallScores = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $sid = (string)($row['student_id'] ?? '');
        if ($sid !== '') {
            $overallScores[$sid] = (float)($row['mean'] ?? 0);
        }
    }

    $streamLabel = app_report_rank_label($streamScores, $studentId);
    $overallLabel = app_report_rank_label($overallScores, $studentId);

    return [
        'stream' => $streamLabel !== '-' ? $streamLabel : $fallbackLabel,
        'overall' => $overallLabel !== '-' ? $overallLabel : $fallbackLabel,
    ];
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
    $schoolMotto = defined('WBMotto') ? (string)WBMotto : '';

    $photoHtml = app_report_student_photo_html($conn, $studentId);
    if ($photoHtml === '') {
        $photoHtml = '<div style="width:78px;height:92px;border:1px solid #b9c8d6;text-align:center;line-height:92px;font-size:8pt;color:#5a6b78;background:#fff;">PHOTO</div>';
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
        foreach ($examRows as $r) {
            $totalMarks += (float)($r['score'] ?? 0);
        }
    }
    $maxMarks = max(100, $subjectCount * 100);

    $gradePointMap = [
        'A+' => 12, 'A' => 11, 'A-' => 10, 'B+' => 9, 'B' => 8, 'B-' => 7,
        'C+' => 6, 'C' => 5, 'C-' => 4, 'D+' => 3, 'D' => 2, 'D-' => 1, 'E' => 0,
        'EE' => 4, 'ME' => 3, 'AE' => 2, 'BE' => 1,
    ];
    $totalPoints = 0.0;
    $classMeanTotal = 0.0;
    foreach ($examRows as $r) {
        $classMeanTotal += (float)($r['class_mean'] ?? 0);
        if (isset($r['grade_points']) && $r['grade_points'] !== '') {
            $totalPoints += (float)$r['grade_points'];
        } else {
            $gradeKey = strtoupper(trim((string)($r['grade'] ?? '')));
            $totalPoints += (float)($gradePointMap[$gradeKey] ?? 0);
        }
    }
    $classMeanAvg = $subjectCount > 0 ? $classMeanTotal / $subjectCount : 0.0;
    $pointsMax = max(12, $subjectCount * 12);
    $classPointEstimate = ($classMeanAvg / 100) * $pointsMax;
    $meanScore = isset($examSummary['mean']) ? (float)$examSummary['mean'] : (float)($card['mean'] ?? 0);
    if ($meanScore <= 0 && $subjectCount > 0) {
        $meanScore = $subjectCount > 0 ? ($totalMarks / $subjectCount) : 0.0;
    }
    $meanDev = $meanScore - $classMeanAvg;
    $totalDev = $totalMarks - $classMeanTotal;
    $pointsDev = $totalPoints - $classPointEstimate;

    $positions = app_report_position_metrics($conn, $studentId, $classId, $termId, $card);
    $streamPosition = (string)($positions['stream'] ?? '-');
    if (isset($examSummary['position']) && trim((string)$examSummary['position']) !== '' && trim((string)$examSummary['position']) !== '-') {
        $streamPosition = (string)$examSummary['position'];
    }
    $overallPosition = (string)($positions['overall'] ?? '-');
    $meanGrade = (string)($examSummary['grade'] ?? ($card['grade'] ?? 'N/A'));
    $feesBalance = (float)($payload['fees_balance'] ?? 0);

    $verificationCode = (string)($card['verification_code'] ?? '');
    $verificationText = $schoolId !== '' ? $schoolId . '@school' : $studentId . '@school';
    $remarksLeft = app_report_html((string)($card['teacher_comment'] ?? $card['remark'] ?? ''));
    $remarksRight = app_report_html((string)($card['headteacher_comment'] ?? $card['remark'] ?? ''));

    $subjectRowsHtml = '';
    foreach ($examRows as $row) {
        $cat1 = $row['cat1'] ?? ($row['cat_1'] ?? '-');
        $cat2 = $row['cat2'] ?? ($row['cat_2'] ?? '-');
        $score = (float)($row['score'] ?? 0);
        $classMean = (float)($row['class_mean'] ?? 0);
        $dev = isset($row['deviation']) ? (float)$row['deviation'] : ($score - $classMean);
        $grade = trim((string)($row['grade'] ?? ''));
        $devColor = $dev > 0 ? '#1a8f4d' : ($dev < 0 ? '#d18b00' : '#6d7a86');
        $gradeBg = $grade !== '' ? '#eef6ff' : '#f3f4f6';
        $subjectRowsHtml .= '<tr>'
            . '<td style="text-align:left;font-weight:bold;">' . app_report_html((string)($row['subject_name'] ?? '')) . '</td>'
            . '<td style="text-align:center;">' . (is_numeric($cat1) ? number_format((float)$cat1, 1) . '%' : app_report_html((string)$cat1)) . '</td>'
            . '<td style="text-align:center;">' . (is_numeric($cat2) ? number_format((float)$cat2, 1) . '%' : app_report_html((string)$cat2)) . '</td>'
            . '<td style="text-align:center;font-weight:bold;">' . number_format($score, 1) . '%</td>'
            . '<td style="text-align:center;color:' . $devColor . ';font-weight:bold;">' . (($dev > 0 ? '+' : '') . number_format($dev, 1)) . '</td>'
            . '<td style="text-align:center;background:' . $gradeBg . ';font-weight:bold;">' . app_report_html($grade !== '' ? $grade : '-') . '</td>'
            . '<td style="text-align:center;">' . app_report_html((string)($row['rank'] ?? $row['position'] ?? '-')) . '</td>'
            . '</tr>';
    }
    if ($subjectRowsHtml === '') {
        $subjectRowsHtml = '<tr><td colspan="7" style="text-align:center;">No subject data available.</td></tr>';
    }

    $metricStyle = 'border:1px solid #d6dde6;border-top:3px solid #c79a2d;padding:6px 7px;background:#f8fafc;font-size:8.1pt;line-height:1.1;text-align:center;vertical-align:top;';
    $metricValueStyle = 'display:block;font-size:11pt;font-weight:bold;color:#1f2f3a;margin-top:3px;';
    $metricDev = static function (float $value): string {
        if ($value > 0) {
            return '#0f6a46';
        }
        if ($value < 0) {
            return '#d18b00';
        }
        return '#6d7a86';
    };

    $subjectSnapshotHtml = '';
    foreach (array_slice($examRows, 0, 4) as $row) {
        $studentWidth = max(0, min(100, (float)($row['score'] ?? 0)));
        $classWidth = max(0, min(100, (float)($row['class_mean'] ?? 0)));
        $subjectSnapshotHtml .= '<tr><td style="padding-bottom:6px;font-size:7.2pt;color:#38505e;">' . app_report_html(substr((string)($row['subject_name'] ?? ''), 0, 16)) . '</td><td style="padding-bottom:6px;"><div style="height:10px;background:#dfe8ef;border-radius:10px;position:relative;overflow:hidden;"><div style="position:absolute;left:0;top:0;height:10px;background:#1b4c73;width:' . number_format($studentWidth, 2, '.', '') . '%;"></div><div style="position:absolute;left:0;bottom:0;height:5px;background:#c79a2d;width:' . number_format($classWidth, 2, '.', '') . '%;"></div></div></td></tr>';
    }
    if ($subjectSnapshotHtml === '') {
        $subjectSnapshotHtml = '<tr><td colspan="2" style="font-size:7.5pt;color:#667680;">No performance data available.</td></tr>';
    }

    return '
<table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-family:Arial,Helvetica,sans-serif;table-layout:fixed;background:#f4f8fb;">
    <tr>
        <td style="padding:0;">
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;border:1px solid #d6dde6;background:#fff;">
                <tr>
                    <td style="background:linear-gradient(90deg,#091c2d 0%,#0f2f4a 64%,#153f61 100%);padding:11px 13px;color:#fff;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                            <tr>
                                <td width="66" style="width:66px;vertical-align:top;">' . $logoHtml . '</td>
                                <td style="vertical-align:top;padding-left:10px;">
                                    <div style="font-size:17pt;font-weight:bold;line-height:1.05;">' . app_report_html($schoolName) . '</div>
                                    <div style="font-size:8.4pt;line-height:1.3;opacity:0.96;margin-top:2px;">' . app_report_html($schoolAddress) . '</div>
                                    <div style="font-size:8.4pt;line-height:1.3;opacity:0.96;">' . app_report_html($schoolPhone) . ($schoolEmail !== '' ? ' | ' . app_report_html($schoolEmail) : '') . '</div>
                                </td>
                                <td width="176" style="width:176px;vertical-align:top;text-align:right;">
                                    <div style="font-size:8.2pt;font-weight:bold;letter-spacing:0.06em;text-transform:uppercase;opacity:0.88;">Official Academic Report</div>
                                    <div style="font-size:12.2pt;font-weight:bold;line-height:1.04;margin-top:4px;">' . app_report_html($className) . '</div>
                                    <div style="font-size:8.5pt;line-height:1.25;margin-top:4px;opacity:0.95;">' . app_report_html($examTitle) . '</div>
                                    <div style="font-size:8.2pt;opacity:0.90;">' . app_report_html($termName) . '</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:10px 12px 8px 12px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                            <tr>
                                <td width="96" style="width:96px;vertical-align:top;">
                                    <div style="width:84px;height:98px;border:1px solid #c6d1db;border-radius:8px;overflow:hidden;background:#f7fafc;">' . $photoHtml . '</div>
                                </td>
                                <td style="vertical-align:top;padding-right:10px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                                        <tr>
                                            <td style="border:1px solid #d6dde6;background:#f7fbff;padding:7px 8px;vertical-align:top;">
                                                <div style="font-size:7.8pt;text-transform:uppercase;color:#5c6c78;font-weight:bold;">Student Profile</div>
                                                <div style="font-size:12pt;font-weight:bold;color:#13222d;line-height:1.15;margin-top:2px;">' . app_report_html($studentName) . '</div>
                                                <div style="font-size:8.3pt;color:#33414c;margin-top:4px;"><b>Adm No:</b> ' . app_report_html($schoolId !== '' ? $schoolId : $studentId) . '</div>
                                                <div style="font-size:8.3pt;color:#33414c;margin-top:2px;"><b>KCPE:</b> ' . app_report_html($kcpe !== '' ? $kcpe : 'N/A') . '</div>
                                            </td>
                                        </tr>
                                        <tr><td style="height:6px;"></td></tr>
                                        <tr>
                                            <td>
                                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:6px 0;table-layout:fixed;">
                                                    <tr>
                                                        <td style="' . $metricStyle . 'padding:7px 7px;">Mean<span style="' . $metricValueStyle . '">' . app_report_html($meanGrade) . '</span><span style="color:' . $metricDev($meanDev) . ';font-size:7.6pt;font-weight:bold;">' . ($meanDev > 0 ? '+' : '') . number_format($meanDev, 1) . '</span></td>
                                                        <td style="' . $metricStyle . 'padding:7px 7px;">Total Marks<span style="' . $metricValueStyle . '">' . number_format($totalMarks, 0) . '/' . number_format($maxMarks, 0) . '</span><span style="color:' . $metricDev($totalDev) . ';font-size:7.6pt;font-weight:bold;">' . ($totalDev > 0 ? '+' : '') . number_format($totalDev, 0) . '</span></td>
                                                        <td style="' . $metricStyle . 'padding:7px 7px;">Points<span style="' . $metricValueStyle . '">' . number_format($totalPoints, 1) . '/' . number_format($pointsMax, 0) . '</span><span style="color:' . $metricDev($pointsDev) . ';font-size:7.6pt;font-weight:bold;">' . ($pointsDev > 0 ? '+' : '') . number_format($pointsDev, 1) . '</span></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                        <tr><td style="height:6px;"></td></tr>
                                        <tr>
                                            <td>
                                                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:6px 0;table-layout:fixed;">
                                                    <tr>
                                                        <td style="' . $metricStyle . 'padding:7px 7px;">Stream Position<span style="' . $metricValueStyle . '">' . app_report_html($streamPosition) . '</span></td>
                                                        <td style="' . $metricStyle . 'padding:7px 7px;">Overall Position<span style="' . $metricValueStyle . '">' . app_report_html($overallPosition) . '</span></td>
                                                        <td style="' . $metricStyle . 'padding:7px 7px;">Fees<span style="' . $metricValueStyle . '">' . ($feesBalance > 0 ? 'Balance ' . number_format($feesBalance, 0) : 'Cleared') . '</span></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                                <td width="152" style="width:152px;vertical-align:top;">
                                    <div style="border:1px solid #d6dde6;border-radius:8px;overflow:hidden;background:#fbfdff;">
                                        <div style="background:#f3f6f9;color:#0f2f4a;padding:7px 8px;font-size:8.2pt;font-weight:bold;text-transform:uppercase;">Subject Snapshot</div>
                                        <div style="padding:8px;">
                                            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                                                ' . $subjectSnapshotHtml . '
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 12px 10px 12px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                            <tr>
                                <td style="background:linear-gradient(90deg,#f5f7fa 0%,#edf1f5 100%);padding:8px 10px;border:1px solid #d6dde6;border-right:0;font-size:8.4pt;font-weight:bold;">ACADEMIC PERFORMANCE</td>
                                <td style="background:linear-gradient(90deg,#f5f7fa 0%,#edf1f5 100%);padding:8px 10px;border:1px solid #d6dde6;text-align:right;font-size:8.2pt;color:#49606e;">Official school record</td>
                            </tr>
                        </table>
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                            <thead>
                                <tr>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">Subject</th>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">CAT 1</th>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">CAT 2</th>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">Score</th>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">Dev.</th>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">Grade</th>
                                    <th style="border:1px solid #8fb8d2;padding:4px 5px;font-size:7.5pt;text-transform:uppercase;background:#0f2f4a;color:#fff;">Rank</th>
                                </tr>
                            </thead>
                            <tbody>' . $subjectRowsHtml . '</tbody>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:0 12px 12px 12px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                            <tr>
                                <td width="40%" style="width:40%;vertical-align:top;padding-right:8px;">
                                    <div style="border:1px solid #d6dde6;border-radius:8px;padding:8px 10px;background:#f7fbff;min-height:96px;">
                                        <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;table-layout:fixed;">
                                            <tr>
                                                <td width="72" style="width:72px;vertical-align:top;">
                                                    <div style="width:66px;height:66px;border:1px solid #b9c8d6;border-radius:8px;background:linear-gradient(180deg,#f4f7fa,#e7edf3);text-align:center;"></div>
                                                </td>
                                                <td style="vertical-align:top;font-size:8pt;line-height:1.3;color:#253745;">
                                                    Scan to verify this report and access the student portal.<br>
                                                    <strong>Code:</strong> ' . app_report_html($verificationCode) . '<br>
                                                    <strong>User:</strong> ' . app_report_html($verificationText) . '
                                                </td>
                                            </tr>
                                        </table>
                                        <div style="margin-top:6px;">' . app_report_school_dates_html($conn, $termName) . '</div>
                                        <div style="margin-top:6px;font-size:7.8pt;color:#49606e;">' . app_report_html($schoolMotto) . '</div>
                                    </div>
                                </td>
                                <td width="60%" style="width:60%;vertical-align:top;padding-left:8px;">
                                    <div style="border:1px solid #d6dde6;border-radius:8px;padding:8px 10px;background:#fcfdff;min-height:96px;">
                                        <div style="font-size:8.6pt;font-weight:bold;color:#1f2f3a;margin-bottom:5px;">Remarks</div>
                                        <div style="font-size:8pt;line-height:1.35;color:#253745;margin-bottom:6px;"><strong>Class Teacher:</strong> ' . $remarksLeft . '</div>
                                        <div style="font-size:8pt;line-height:1.35;color:#253745;"><strong>Principal:</strong> ' . $remarksRight . '</div>
                                    </div>
                                </td>
                            </tr>
                        </table>
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
        $qrSize = 22;
        $x = (float)$margins['left'] + 4;
        $y = $pdf->getPageHeight() - (float)$margins['bottom'] - 26;
        $pdf->write2DBarcode($verifyUrl, 'QRCODE,H', $x, $y, $qrSize, $qrSize);
    }
}
