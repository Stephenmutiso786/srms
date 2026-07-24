<?php

require_once(__DIR__ . '/report_engine.php');
require_once(__DIR__ . '/school.php');
require_once(__DIR__ . '/pdf_branding.php');
require_once(__DIR__ . '/report_card_layout.php');
require_once(__DIR__ . '/id_card_engine.php');

function app_report_verify_url(string $verificationCode): string
{
    return rtrim(app_base_url(), '/') . '/verify_report?code=' . urlencode($verificationCode);
}

function app_report_verify_pdf_url(string $verificationCode): string
{
    return rtrim(app_base_url(), '/') . '/verify_report_pdf?code=' . urlencode($verificationCode);
}

function app_report_student_photo_html(PDO $conn, string $studentId): array
{
    $photoPath = '';
    $photoExists = false;
    if (function_exists('idcard_student_payload')) {
        $payload = idcard_student_payload($conn, $studentId);
        if ($payload) {
            $photoPath = (string)($payload['photo_path'] ?? '');
            $photoExists = (bool)($payload['photo_exists'] ?? false);
        }
    }

    return [$photoPath, $photoExists];
}

function app_report_scale_html_font_sizes(string $html, float $scale): string
{
    $safeScale = max(0.45, min(1.30, $scale));
    return (string)preg_replace_callback(
        '/font-size\s*:\s*([0-9]+(?:\.[0-9]+)?)((?:rem|pt))/i',
        static function (array $matches) use ($safeScale): string {
            $base = (float)$matches[1];
            $unit = strtolower($matches[2]);
            $scaled = round($base * $safeScale, 3);
            if ($unit === 'pt') {
                $scaled = max(5.0, min(16.0, $scaled));
            }
            if ($unit === 'rem') {
                $scaled = max(0.5, min(1.6, $scaled));
            }
            return 'font-size:' . rtrim(rtrim(number_format($scaled, 3, '.', ''), '0'), '.') . $unit;
        },
        $html
    );
}

function app_report_pick_single_page_scale(TCPDF $pdf, string $html, float $topMargin, float $bottomMargin): float
{
    $pageHeight = (float)$pdf->getPageHeight();
    $usableHeight = max(1.0, $pageHeight - $topMargin - $bottomMargin);
    $chosenScale = 0.66;

    for ($scale = 1.0; $scale >= 0.45; $scale -= 0.02) {
        $trialHtml = app_report_scale_html_font_sizes($html, $scale);
        $pdf->startTransaction();
        $startPage = (int)$pdf->getPage();
        $pdf->SetY($topMargin);
        $pdf->writeHTML($trialHtml, true, false, true, false, '');
        $endPage = (int)$pdf->getPage();
        $endY = (float)$pdf->GetY();
        $pdf->rollbackTransaction(true);

        if ($endPage === $startPage) {
            $usedHeight = max(0.0, $endY - $topMargin);
            $chosenScale = $scale;
            if (($usedHeight / $usableHeight) >= 0.95) {
                break;
            }
        }
    }

    return $chosenScale;
}

function app_report_card_pdf_html(PDO $conn, array $payload): string
{
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $schoolMotto = defined('WBMotto') ? trim((string)WBMotto) : '';
    $schoolContact = trim(implode(' | ', array_filter([trim((string)WBAddress), trim((string)WBPhone), trim((string)WBEmail)])));
    $headteacherTitle = defined('WBHeadteacherTitle') ? trim((string)WBHeadteacherTitle) : 'Headteacher';
    $headteacherName = defined('WBHeadteacherName') ? trim((string)WBHeadteacherName) : '';
    $logoPath = 'images/logo/' . trim((string)WBLogo);
    $logoExists = trim((string)WBLogo) !== '' && app_pdf_image_path_is_safe($logoPath);
    list($photoPath, $photoExists) = app_report_student_photo_html($conn, (string)($payload['student_id'] ?? ''));
    $photoExists = $photoExists && app_pdf_image_path_is_safe($photoPath);

    $studentName = (string)($payload['student_name'] ?? '');
    $studentId = (string)($payload['student_id'] ?? '');
    $schoolId = (string)($payload['school_id'] ?? '');
    $className = (string)($payload['class_name'] ?? '');
    $termName = (string)($payload['term_name'] ?? '');
    $examName = (string)($payload['exam_summary']['exam_name'] ?? 'END TERM COMBINED');
    $overallGrade = (string)($payload['exam_summary']['grade'] ?? $payload['card']['grade'] ?? 'N/A');
    $attendance = $payload['attendance'] ?? [];
    $feesBalance = $payload['fees_balance'] ?? null;
    $kcpeScore = (string)($payload['kcpe_score'] ?? 'N/A');
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $rows = is_array($payload['exam_breakdown'] ?? null) && !empty($payload['exam_breakdown']) ? $payload['exam_breakdown'] : (is_array($card['subjects'] ?? null) ? $card['subjects'] : []);
    // Show all subjects in the PDF; don't hide any rows.
    $displayRows = $rows;
    $hiddenRows = 0;

    $totalPoints = 0.0;
    $subjectCount = count($rows);
    foreach ($rows as $subjectRow) {
        $subjectPoints = app_report_card_subject_points_value((array)$subjectRow);
        if ($subjectPoints !== null) {
            $totalPoints += $subjectPoints;
        }
    }
    $meanPoints = $subjectCount > 0 ? ($totalPoints / $subjectCount) : 0.0;
    $displayTotalScore = isset($card['total_points']) ? (float)$card['total_points'] : $totalPoints;
    $displayMeanScore = isset($card['mean_points']) ? (float)$card['mean_points'] : $meanPoints;
    $attendanceText = '';
    if (is_array($attendance)) {
        $attendanceText = trim((string)($attendance['rate_text'] ?? $attendance['attendance_rate_text'] ?? $attendance['percentage'] ?? ''));
        if ($attendanceText === '') {
            $present = (string)($attendance['present'] ?? '');
            $total = (string)($attendance['total'] ?? '');
            if ($present !== '' && $total !== '') {
                $attendanceText = $present . '/' . $total;
            }
        }
    }
    if ($attendanceText === '') {
        $attendanceText = 'N/A';
    }
    $feesText = 'N/A';
    if (is_numeric($feesBalance)) {
        $feesText = number_format((float)$feesBalance, 2);
    } elseif (is_string($feesBalance) && trim($feesBalance) !== '') {
        $feesText = trim($feesBalance);
    }

    $photoHtml = $photoExists && $photoPath !== ''
        ? '<img src="' . htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') . '" alt="Student Photo">'
        : '<div style="font-size:18px;font-weight:700;color:#1f4d75;">' . htmlspecialchars(strtoupper(substr($studentName !== '' ? $studentName : $studentId, 0, 1)), ENT_QUOTES, 'UTF-8') . '</div>';

    $logoHtml = $logoExists && $logoPath !== ''
        ? '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="School Logo" style="max-width:100%;max-height:100%;object-fit:contain;">'
        : '';

    $subjectRows = '';
    foreach ($displayRows as $subject) {
        $subjectName = (string)($subject['subject_name'] ?? '');
        $score = app_report_card_subject_points_display((array)$subject);
        $grade = (string)($subject['grade'] ?? '');
        $subjectRows .= '<tr>'
            . '<td style="padding:4px 5px;border:1px solid #88939c;">' . htmlspecialchars($subjectName, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:4px 5px;border:1px solid #88939c;text-align:center;">' . htmlspecialchars($score, ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td style="padding:4px 5px;border:1px solid #88939c;text-align:center;">' . htmlspecialchars($grade, ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }
    if ($subjectRows === '') {
        $subjectRows = '<tr><td colspan="3" style="padding:4px 5px;border:1px solid #88939c;text-align:center;">No subject data available.</td></tr>';
    }
    // no hidden row indicator when showing all subjects

    return '<!doctype html><html><head><meta charset="utf-8"><title>Report Card</title>'
        . '<style>'
        . 'body{margin:0;padding:0;font-family:helvetica,arial,sans-serif;color:#1b2733;background:#fff;} '
        . '.card{width:100%;box-sizing:border-box;padding:4px 6px 0 6px;} '
        . '.header{width:100%;border-collapse:collapse;} '
        . '.header td{vertical-align:middle;} '
        . '.logo{width:42px;height:42px;border:1px solid #d7d7d7;text-align:center;overflow:hidden;} '
        . '.title{font-size:10pt;font-weight:700;text-align:center;color:#fff;background:#00aeef;padding:4px 6px;margin-top:3px;} '
        . '.info{width:100%;border-collapse:collapse;margin-top:4px;} '
        . '.info td{border:1px solid #88939c;padding:3px 4px;font-size:7.2pt;vertical-align:top;} '
        . '.info .label{font-weight:700;width:18%;background:#f3f7fb;} '
        . '.stats{width:100%;border-collapse:collapse;margin-top:4px;} '
        . '.stats td{border:1px solid #88939c;padding:3px 4px;font-size:7.2pt;} '
        . '.stats .label{font-weight:700;background:#f3f7fb;width:16%;} '
        . '.subjects{width:100%;border-collapse:collapse;margin-top:4px;} '
        . '.subjects th{border:1px solid #88939c;background:#f3f7fb;padding:4px 5px;font-size:7pt;text-align:left;} '
        . '.subjects td{font-size:7pt;} '
        . '.photo{width:42px;height:48px;border:1px solid #d7d7d7;overflow:hidden;text-align:center;vertical-align:middle;} '
        . '.photo img{width:100%;height:100%;object-fit:cover;} '
        . '.footer-note{margin-top:4px;font-size:6.8pt;color:#495966;} '
        . '</style></head><body>'
        . '<div class="card">'
        . '<table class="header"><tr>'
        . '<td style="width:48px;"><div class="logo">' . $logoHtml . '</div></td>'
        . '<td style="text-align:center;">'
        . '<div style="font-size:11pt;font-weight:800;line-height:1.05;">' . htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') . '</div>'
        . '<div style="font-size:7pt;line-height:1.1;margin-top:2px;">' . htmlspecialchars($schoolContact, ENT_QUOTES, 'UTF-8') . '</div>'
        . '</td>'
        . '<td style="width:48px;"><div class="photo">' . $photoHtml . '</div></td>'
        . '</tr></table>'
        . '<div class="title">ACADEMIC REPORT CARD - ' . strtoupper(htmlspecialchars($className, ENT_QUOTES, 'UTF-8')) . ' - ' . strtoupper(htmlspecialchars($examName, ENT_QUOTES, 'UTF-8')) . ' - ' . strtoupper(htmlspecialchars($termName, ENT_QUOTES, 'UTF-8')) . '</div>'
        . '<table class="info">'
        . '<tr><td class="label">Name</td><td>' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</td><td class="label">ADM No</td><td>' . htmlspecialchars($schoolId !== '' ? $schoolId : $studentId, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td class="label">Class</td><td>' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '</td><td class="label">Term</td><td>' . htmlspecialchars($termName, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '<tr><td class="label">Exam</td><td>' . htmlspecialchars($examName, ENT_QUOTES, 'UTF-8') . '</td><td class="label">KCPE</td><td>' . htmlspecialchars($kcpeScore, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '</table>'
        . '<table class="stats">'
        . '<tr><td class="label">Overall Grade</td><td>' . htmlspecialchars($overallGrade, ENT_QUOTES, 'UTF-8') . '</td><td class="label">CBE Mean</td><td>' . number_format($displayMeanScore, 2) . '</td><td class="label">Attendance</td><td>' . htmlspecialchars($attendanceText, ENT_QUOTES, 'UTF-8') . '</td><td class="label">Subjects</td><td>' . (int)$subjectCount . '</td></tr>'
        . '<tr><td class="label">Fees Balance</td><td>' . htmlspecialchars($feesText, ENT_QUOTES, 'UTF-8') . '</td><td class="label">Shown</td><td>' . count($displayRows) . '</td><td class="label">Status</td><td>One-page model</td><td class="label">Exam</td><td>' . htmlspecialchars($examName, ENT_QUOTES, 'UTF-8') . '</td></tr>'
        . '</table>'
        . '<table class="subjects">'
        . '<thead><tr><th style="width:58%;">Subject</th><th style="width:21%;text-align:center;">Score</th><th style="width:21%;text-align:center;">Grade</th></tr></thead>'
        . '<tbody>' . $subjectRows . '</tbody>'
        . '</table>'
        . '<div class="footer-note">' . htmlspecialchars($headteacherTitle . ' - ' . $schoolName, ENT_QUOTES, 'UTF-8') . ' | Verification: ' . htmlspecialchars(app_report_verify_url((string)($card['verification_code'] ?? '')), ENT_QUOTES, 'UTF-8') . '</div>'
        . '</div></body></html>';
}

function app_output_single_page_report_pdf(PDO $conn, TCPDF $pdf, array $payload): void
{
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    // Force a single-page layout; content is positioned manually.
    $pdf->SetAutoPageBreak(false, 0);
    // set conservative top margin so content fits evenly on the page
    $pdf->SetMargins(10, 12, 10);
    $pdf->SetTitle('Academic Report Card');
    $pdf->AddPage('P', 'A4');

    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $schoolMotto = defined('WBMotto') ? trim((string)WBMotto) : '';
    $schoolContact = trim(implode(' | ', array_filter([trim((string)WBAddress), trim((string)WBPhone), trim((string)WBEmail)])));
    $headteacherTitle = defined('WBHeadteacherTitle') ? trim((string)WBHeadteacherTitle) : 'Headteacher';
    $headteacherName = defined('WBHeadteacherName') ? trim((string)WBHeadteacherName) : '';
    $logoPath = 'images/logo/' . trim((string)WBLogo);
    $logoExists = trim((string)WBLogo) !== '' && app_pdf_image_path_is_safe($logoPath) && is_file($logoPath);
    $signaturePath = trim((string)(defined('WBHeadteacherSignature') ? WBHeadteacherSignature : ''));
    if ($signaturePath !== '' && strpos($signaturePath, '/') === false) {
        $signaturePath = 'images/signatures/' . $signaturePath;
    }
    $signatureExists = $signaturePath !== '' && app_pdf_image_path_is_safe($signaturePath) && is_file($signaturePath);
    $stampPath = trim((string)(defined('WBSchoolStamp') ? WBSchoolStamp : ''));
    if ($stampPath !== '' && strpos($stampPath, '/') === false) {
        $stampPath = 'images/stamps/' . $stampPath;
    }
    $stampExists = $stampPath !== '' && app_pdf_image_path_is_safe($stampPath) && is_file($stampPath);
    list($photoPath, $photoExists) = app_report_student_photo_html($conn, (string)($payload['student_id'] ?? ''));
    $photoExists = $photoExists && app_pdf_image_path_is_safe($photoPath) && is_file($photoPath);

    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $examSummary = is_array($payload['exam_summary'] ?? null) ? $payload['exam_summary'] : [];
    $studentName = (string)($payload['student_name'] ?? '');
    $studentId = (string)($payload['student_id'] ?? '');
    $schoolId = (string)($payload['school_id'] ?? '');
    $className = (string)($payload['class_name'] ?? '');
    $termName = (string)($payload['term_name'] ?? '');
    $examName = (string)($examSummary['exam_name'] ?? 'END TERM COMBINED');
    $assessmentMode = strtolower(trim((string)($card['assessment_mode'] ?? ($examSummary['assessment_mode'] ?? 'normal'))));
    $overallGrade = (string)($examSummary['grade'] ?? ($card['grade'] ?? 'N/A'));
    $teacherComment = trim((string)($card['teacher_comment'] ?? $card['remark'] ?? ''));
    $headteacherComment = trim((string)($card['headteacher_comment'] ?? $card['remark'] ?? ''));
    $currentMean = (float)($card['mean_points'] ?? 0);
    if ($currentMean <= 0 && isset($examSummary['mean_points'])) {
        $currentMean = (float)$examSummary['mean_points'];
    }
    $totalMarks = (float)($card['total_points'] ?? 0);
    if ($totalMarks <= 0 && isset($examSummary['total_points'])) {
        $totalMarks = (float)$examSummary['total_points'];
    }
    $generatedDate = gmdate('d M Y');
    $academicYear = gmdate('Y');
    $trend = trim((string)($card['trend'] ?? ''));
    $previousMean = null;
    if ((string)($card['term_id'] ?? '') !== '' && $studentId !== '') {
        $previousMean = report_previous_mean($conn, $studentId, (int)$card['term_id']);
    }
    if ($trend === '') {
        $trend = $studentId !== '' && (int)($card['term_id'] ?? 0) > 0 ? report_trend($conn, $studentId, (int)$card['term_id'], $currentMean) : 'New';
    }

    $attendance = is_array($payload['attendance'] ?? null) ? $payload['attendance'] : (is_array($card['attendance'] ?? null) ? $card['attendance'] : []);
    $feesBalance = $payload['fees_balance'] ?? ($card['fees_balance'] ?? null);
    $kcpeScore = (string)($payload['kcpe_score'] ?? 'N/A');
    $rows = is_array($card['subjects'] ?? null) ? $card['subjects'] : [];
    if (empty($rows) && is_array($payload['exam_breakdown'] ?? null)) {
        $rows = $payload['exam_breakdown'];
    }
    // Show all subject rows and avoid hiding rows to keep layout consistent.
    $displayRows = $rows;
    $hiddenRows = 0;
    $allMeanSum = 0.0;
    $allMeanCount = 0;
    foreach ($rows as $subjectRow) {
        if (isset($subjectRow['class_mean']) && $subjectRow['class_mean'] !== '') {
            $allMeanSum += (float)$subjectRow['class_mean'];
            $allMeanCount++;
        }
    }
    $classAverage = $allMeanCount > 0 ? round($allMeanSum / $allMeanCount, 2) : 0.0;
    $meanDelta = $currentMean - $classAverage;
    $previousDelta = $previousMean !== null ? $currentMean - $previousMean : null;
    $trendText = $trend !== '' ? $trend : ($previousDelta === null ? 'New' : (($previousDelta > 0) ? 'Improved' : (($previousDelta < 0) ? 'Dropped' : 'Steady')));
    if (empty($card['ai_summary']) && !empty($rows) && $studentId !== '' && (int)($card['term_id'] ?? 0) > 0) {
        $bundle = report_ai_comment_bundle($rows, $currentMean, $previousMean, $overallGrade, $trendText);
        $card['ai_summary'] = $bundle['ai_summary'];
        $card['strengths'] = $bundle['strengths'];
        $card['weaknesses'] = $bundle['weaknesses'];
    }
    $aiSummary = trim((string)($card['ai_summary'] ?? ''));
    $strengths = array_values(array_filter(array_map('trim', (array)($card['strengths'] ?? []))));
    $weaknesses = array_values(array_filter(array_map('trim', (array)($card['weaknesses'] ?? []))));
    $attendanceText = 'N/A';
    if (!empty($attendance)) {
        if (!empty($attendance['rate_text'])) {
            $attendanceText = (string)$attendance['rate_text'];
        } elseif (!empty($attendance['attendance_rate_text'])) {
            $attendanceText = (string)$attendance['attendance_rate_text'];
        } elseif (isset($attendance['present'], $attendance['days_open']) && (int)$attendance['days_open'] > 0) {
            $attendanceText = (int)$attendance['present'] . '/' . (int)$attendance['days_open'];
        }
    }
    if (is_numeric($feesBalance)) {
        $feesText = number_format((float)$feesBalance, 2);
    } elseif (is_string($feesBalance) && trim($feesBalance) !== '') {
        $feesText = trim($feesBalance);
    } else {
        $feesText = 'N/A';
    }

    $schoolInfo = $schoolContact !== '' ? $schoolContact : 'Published exam report';
    $verificationUrl = app_report_verify_url((string)($card['verification_code'] ?? ''));
    $pageW = (float)$pdf->getPageWidth();
    $pageH = (float)$pdf->getPageHeight();
    // Match the TCPDF margins above for consistent positioning
    $margin = 12.0;
    $innerW = $pageW - ($margin * 2);
    // make left column narrower so main table gets more horizontal space
    $leftW = 72.0;
    $rightW = $innerW - $leftW - 2.0;

    $hex = static function (string $hexColor): array {
        $hexColor = ltrim($hexColor, '#');
        return [hexdec(substr($hexColor, 0, 2)), hexdec(substr($hexColor, 2, 2)), hexdec(substr($hexColor, 4, 2))];
    };
    $drawSection = static function (TCPDF $pdf, float $x, float $y, float $w, float $h, string $title, string $subtitle = '', string $accent = '00AEEF') use ($hex): void {
        [$r, $g, $b] = $hex($accent);
        $pdf->SetDrawColor(215, 226, 235);
        $pdf->SetFillColor(250, 252, 253);
        $pdf->Rect($x, $y, $w, $h, 'DF');
        $pdf->SetFillColor($r, $g, $b);
        $pdf->Rect($x, $y, $w, 6, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('helvetica', 'B', 8.2);
        $pdf->SetXY($x + 1, $y + 0.7);
        $pdf->Cell($w - 2, 4, $title, 0, 0, 'L', false);
        if ($subtitle !== '') {
            $pdf->SetTextColor(82, 93, 103);
            $pdf->SetFont('helvetica', '', 6.3);
            $pdf->SetXY($x + 1, $y + 6.6);
            $pdf->Cell($w - 2, 3, $subtitle, 0, 0, 'L', false);
        }
        $pdf->SetTextColor(27, 39, 51);
    };
    $drawMetric = static function (TCPDF $pdf, float $x, float $y, float $w, string $label, string $value, string $note = ''): void {
        $pdf->SetDrawColor(215, 215, 215);
        $pdf->SetFillColor(244, 244, 244);
        $pdf->Rect($x, $y, $w, 13.5, 'DF');
        $pdf->SetFont('helvetica', 'B', 6.9);
        $pdf->SetXY($x + 1.3, $y + 1.2);
        $pdf->Cell($w - 2.6, 2.8, $label, 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 9.2);
        $pdf->SetX($x + 1.3);
        $pdf->Cell($w - 2.6, 4.0, $value, 0, 1, 'L');
        if ($note !== '') {
            $pdf->SetFont('helvetica', '', 6.0);
            $pdf->SetX($x + 1.3);
            $pdf->Cell($w - 2.6, 2.3, $note, 0, 1, 'L');
        }
    };
    $drawBar = static function (TCPDF $pdf, float $x, float $y, float $w, string $label, ?float $value, string $color, string $suffix = '') use ($hex): void {
        [$r, $g, $b] = $hex($color);
        $pdf->SetFont('helvetica', '', 6.5);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w, 3.2, $label, 0, 1, 'L');
        $pdf->SetDrawColor(224, 232, 238);
        $pdf->Rect($x, $y + 3.3, $w, 3.6, 'D');
        $pdf->SetFillColor($r, $g, $b);
        $fill = $value === null ? 0 : max(0, min($w, $w * min(1.0, $value / 100.0)));
        if ($fill > 0) {
            $pdf->Rect($x, $y + 3.3, $fill, 3.6, 'F');
        }
        $pdf->SetFont('helvetica', 'B', 6.2);
        $pdf->SetXY($x, $y + 7.0);
        $pdf->Cell($w, 2.6, ($value === null ? 'N/A' : number_format($value, 2)) . ($suffix !== '' ? ' ' . $suffix : ''), 0, 0, 'R');
    };
    $drawMiniComparison = static function (TCPDF $pdf, float $x, float $y, float $w, array $row): void {
        $student = isset($row['has_score']) && !$row['has_score'] ? null : (isset($row['score']) && $row['score'] !== null ? (float)$row['score'] : null);
        $classMean = isset($row['class_mean']) && $row['class_mean'] !== '' ? (float)$row['class_mean'] : null;
        $subject = (string)($row['subject_name'] ?? 'Subject');
        $dev = isset($row['deviation']) && $row['deviation'] !== null ? (float)$row['deviation'] : null;
        $pdf->SetFont('helvetica', '', 6.0);
        $pdf->SetXY($x, $y);
        $pdf->Cell($w * 0.36, 3.3, $subject, 0, 0, 'L');
        $pdf->SetDrawColor(224, 232, 238);
        $pdf->Rect($x + $w * 0.36, $y + 0.25, $w * 0.40, 2.5, 'D');
        if ($student !== null) {
            $pdf->SetFillColor(26, 143, 212);
            $pdf->Rect($x + $w * 0.36, $y + 0.25, ($w * 0.40) * max(0.0, min(1.0, $student / 100.0)), 2.5, 'F');
        }
        if ($classMean !== null) {
            $pdf->SetFillColor(56, 181, 106);
            $pdf->Rect($x + $w * 0.36, $y + 1.35, ($w * 0.40) * max(0.0, min(1.0, $classMean / 100.0)), 1.0, 'F');
        }
        $pdf->SetXY($x + $w * 0.77, $y);
        $pdf->Cell($w * 0.23, 3.3, $dev === null ? 'N/A' : (($dev > 0 ? '+' : '') . number_format($dev, 1)), 0, 0, 'R');
    };

    // ======================
    // CLEAN LAYOUT MATCHING HTML REFERENCE - SPACIOUS & PROFESSIONAL
    // NO OVERLAP HEADER (FLEX-LIKE LAYOUT)
    // ======================
    
    // ==== HEADER: Logo (LEFT) | School Name (CENTER-LEFT) | Photo (RIGHT) ====
    $headerY = $margin + 2;
    $logoSize = 20;  // 20mm logo
    $photoSize = 20;  // 20mm photo
    $gapSize = 2;    // gap between sections
    
    // LEFT SECTION: Logo
    $logoX = $margin;
    if ($logoExists) {
        $pdf->Image($logoPath, $logoX, $headerY, $logoSize, $logoSize, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }
    
    // CENTER-LEFT SECTION: School name + contact (between logo and photo)
    $textX = $logoX + $logoSize + $gapSize;
    $textMaxWidth = $innerW - $logoSize - $photoSize - ($gapSize * 3);
    
    $pdf->SetFont('helvetica', 'B', 12.5);
    $pdf->SetTextColor(31, 77, 117);
    $pdf->SetXY($textX, $headerY + 1);
    $pdf->Cell($textMaxWidth, 6.0, $schoolName, 0, 1, 'L');

    if ($schoolMotto !== '') {
        $pdf->SetFont('helvetica', 'I', 7.0);
        $pdf->SetTextColor(62, 98, 132);
        $pdf->SetX($textX);
        $pdf->Cell($textMaxWidth, 3.5, $schoolMotto, 0, 1, 'L');
    }
    
    $pdf->SetFont('helvetica', '', 8.0);
    $pdf->SetTextColor(27, 39, 51);
    $pdf->SetX($textX);
    $pdf->Cell($textMaxWidth, 4.0, $schoolContact, 0, 1, 'L');
    
    // RIGHT SECTION: Student Photo (far right, safe from collision)
    $photoX = $margin + $innerW - $photoSize - $gapSize;
    if ($photoExists && $photoPath !== '') {
        $pdf->Image($photoPath, $photoX, $headerY, $photoSize, $photoSize, '', '', '', false, 300, '', false, false, 0, false, false, false);
    } else {
        // Placeholder box with initial
        $pdf->SetDrawColor(26, 163, 207);
        $pdf->SetLineWidth(0.7);
        $pdf->Rect($photoX, $headerY, $photoSize, $photoSize, 'D');
        $pdf->SetFont('helvetica', 'B', 11.0);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->SetXY($photoX, $headerY + 6);
        $pdf->Cell($photoSize, 8, strtoupper(substr($studentName !== '' ? $studentName : $studentId, 0, 1)), 0, 0, 'C');
    }
    
    // Reset position after header
    $pdf->SetY($headerY + $logoSize + 2);
    
    // ==== BLUE BANNER with exam/term info (FULL WIDTH, NO COLLISION) ====
    $bannerY = $pdf->GetY() + 2;
    $pdf->SetY($bannerY);
    $pdf->SetFillColor(26, 163, 207);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('helvetica', 'B', 8.5);
    $pdf->SetX($margin);
    $pdf->Cell($innerW, 5.6, 'ACADEMIC REPORT FORM - ' . strtoupper($className) . ' - ' . strtoupper($examName) . ' - ' . strtoupper($termName), 0, 1, 'C', true);
    $pdf->SetFillColor(236, 246, 251);
    $pdf->SetTextColor(31, 77, 117);
    $pdf->SetFont('helvetica', '', 6.8);
    $pdf->SetX($margin);
    $pdf->Cell($innerW, 4.0, 'Generated: ' . $generatedDate . ' | Academic Year: ' . $academicYear, 0, 1, 'C', true);
    
    $pdf->SetTextColor(27, 39, 51);
    
    // ==== STUDENT DETAILS GRID (moved above the subject table) ====
    $pdf->SetFont('helvetica', '', 7.4);
    $pdf->SetFillColor(238, 246, 251);
    // small gap after banner
    $pdf->SetY($pdf->GetY() + 4);

    // Calculate cell width (4 equal columns with padding)
    $gridCellW = ($innerW - 6) / 4;

    // Row 1: Name, Class, ADM No, Attendance
    $pdf->SetX($margin + 2);
    $pdf->Cell($gridCellW, 5.0, 'Name: ' . $studentName, 0, 0, 'L', true);
    $pdf->Cell($gridCellW, 5.0, 'Class: ' . $className, 0, 0, 'L', true);
    $pdf->Cell($gridCellW, 5.0, 'ADM No: ' . ($schoolId !== '' ? $schoolId : $studentId), 0, 0, 'L', true);
    $pdf->Cell($gridCellW, 5.0, 'Attendance: ' . $attendanceText, 0, 1, 'L', true);

    // Row 2: CBE Mean, Grade, Term, Fees Balance
    $pdf->SetX($margin + 2);
    $pdf->SetFont('helvetica', 'B', 7.4);
    $pdf->Cell($gridCellW, 5.0, 'CBE Mean: ' . number_format($currentMean, 2), 0, 0, 'L', true);
    $pdf->Cell($gridCellW, 5.0, 'Grade: ' . $overallGrade, 0, 0, 'L', true);
    $pdf->Cell($gridCellW, 5.0, 'Term: ' . $termName, 0, 0, 'L', true);
    $pdf->Cell($gridCellW, 5.0, 'Fees Balance: ' . $feesText, 0, 1, 'L', true);

    $pdf->SetTextColor(27, 39, 51);

    // ==== SUBJECT TABLE (adaptive to always fit one page) ====
    $rowCount = max(1, count($displayRows));
    $contentStartY = $pdf->GetY() + 4;
    $footerTopY = $pageH - 33;
    $availableContentHeight = max(78.0, $footerTopY - $contentStartY);
    $remarksH = $rowCount >= 16 ? 18.0 : ($rowCount >= 12 ? 20.0 : 24.0);
    $sectionGap = $rowCount >= 16 ? 2.0 : 3.0;
    $tableHeaderH = $rowCount >= 16 ? 4.0 : 4.8;
    $chartTitlePad = $rowCount >= 16 ? 7.5 : 8.5;
    $usableSectionHeight = max(48.0, $availableContentHeight - $remarksH - ($sectionGap * 2) - $tableHeaderH - $chartTitlePad);
    $baseUnit = $usableSectionHeight / max(1.0, $rowCount * 1.62);
    $rowH = max(2.15, min(4.2, $baseUnit * 0.88));
    $groupH = max(2.3, min(5.4, $baseUnit * 0.82));
    $chartH = $chartTitlePad + ($groupH * $rowCount);
    $tableFont = max(4.7, min(5.8, $rowH + 0.95));
    $tableHeaderFont = max(5.3, min(6.6, $tableFont + 0.55));
    $remarksFont = max(5.0, min(6.2, $tableFont));
    $remarksLineH = max(4.0, min(5.5, $remarksFont * 0.82));

    $pdf->SetFont('helvetica', 'B', $tableHeaderFont);
    $pdf->SetFillColor(243, 247, 251);
    $pdf->SetDrawColor(136, 147, 156);
    $tableX = $margin + 2;
    $tableW = $innerW - 4;
    $pdf->SetY($contentStartY);

    if (!empty($rows)) {
        // Subject | Score | Grade | Comment | Teacher
        $pdf->SetX($tableX);
        $subjectW = $tableW * 0.34;
        $scoreW = $tableW * 0.12;
        $gradeW = $tableW * 0.11;
        $commentW = $tableW * 0.23;
        $teacherW = $tableW - $subjectW - $scoreW - $gradeW - $commentW;

        $pdf->Cell($subjectW, $tableHeaderH, 'Subject', 1, 0, 'L', true);
        $pdf->Cell($scoreW, $tableHeaderH, 'Score', 1, 0, 'C', true);
        $pdf->Cell($gradeW, $tableHeaderH, 'Grade', 1, 0, 'C', true);
        $pdf->Cell($commentW, $tableHeaderH, 'Comment', 1, 0, 'L', true);
        $pdf->Cell($teacherW, $tableHeaderH, 'Teacher', 1, 1, 'L', true);

        $pdf->SetFont('helvetica', '', $tableFont);
        $pdf->SetDrawColor(136, 147, 156);
        foreach ($displayRows as $subjectRow) {
            $pdf->SetX($tableX);
            $gradeText = (string)($subjectRow['grade'] ?? 'N/A');
            $subjectComment = trim((string)($subjectRow['remark'] ?? ''));
            $teacherName = trim((string)($subjectRow['teacher_name'] ?? ''));
            // Highlight low performance: score < 50 OR grade indicating low (BE)
            $isLow = false;
            if (isset($subjectRow['score']) && $subjectRow['score'] !== null && is_numeric($subjectRow['score'])) {
                $isLow = ((float)$subjectRow['score'] < 50.0);
            }
            if (!$isLow && strtoupper(trim((string)$subjectRow['grade'] ?? '')) === 'BE') {
                $isLow = true;
            }
            if ($isLow) {
                $pdf->SetTextColor(180, 30, 30);
            } else {
                $pdf->SetTextColor(27, 39, 51);
            }
            $subjectLabel = (string)($subjectRow['subject_name'] ?? '');

            $pdf->Cell($subjectW, $rowH, $subjectLabel, 1, 0, 'L');
            $scoreVal = app_report_card_subject_points_display((array)$subjectRow);
            $pdf->Cell($scoreW, $rowH, $scoreVal, 1, 0, 'C');
            $pdf->Cell($gradeW, $rowH, $gradeText, 1, 0, 'C');
            $pdf->Cell($commentW, $rowH, $subjectComment !== '' ? $subjectComment : 'No marks entered', 1, 0, 'L');
            $pdf->Cell($teacherW, $rowH, $teacherName, 1, 1, 'L');

            // reset text color after row
            $pdf->SetTextColor(27, 39, 51);
        }
    }

    // ==== SUBJECT PERFORMANCE CHART ====
    $chartY = $pdf->GetY() + $sectionGap;
    $chartX = $margin + 2;
    $chartW = $innerW - 4;
    $pdf->SetDrawColor(216, 226, 235);
    $pdf->SetFillColor(249, 251, 253);
    $pdf->Rect($chartX, $chartY, $chartW, $chartH, 'DF');
    $pdf->SetFont('helvetica', 'B', max(6.8, $tableHeaderFont));
    $pdf->SetTextColor(31, 77, 117);
    $pdf->SetXY($chartX + 3, $chartY + 2);
    $pdf->Cell($chartW - 6, 3.8, 'Subject Performance', 0, 1, 'L');

    $subjectsList = $displayRows;
    usort($subjectsList, static function (array $left, array $right): int {
        $leftScore = isset($left['score']) && $left['score'] !== null && is_numeric($left['score']) ? (float)$left['score'] : -1.0;
        $rightScore = isset($right['score']) && $right['score'] !== null && is_numeric($right['score']) ? (float)$right['score'] : -1.0;
        if ($leftScore === $rightScore) {
            return strcmp((string)($left['subject_name'] ?? ''), (string)($right['subject_name'] ?? ''));
        }
        return $leftScore < $rightScore ? 1 : -1;
    });
    $plotX = $chartX + 8;
    $plotY = $chartY + 8.5;
    $plotW = $chartW - 14;
    $labelReserveH = $rowCount >= 16 ? 13.0 : ($rowCount >= 12 ? 15.0 : 17.0);
    $plotH = max(18.0, $chartH - ($plotY - $chartY) - $labelReserveH - 3.5);
    $axisX = $plotX + 8.5;
    $axisY = $plotY + $plotH;
    $barsW = $plotW - 10.5;
    $slotW = $barsW / max(1, $rowCount);
    $barW = max(2.4, min(8.5, $slotW * ($rowCount >= 16 ? 0.58 : 0.64)));
    $scoreFont = $rowCount >= 16 ? 4.2 : 4.8;
    $labelFont = $rowCount >= 16 ? 3.7 : ($rowCount >= 12 ? 4.0 : 4.4);

    $pdf->SetDrawColor(222, 229, 236);
    $pdf->SetTextColor(120, 130, 140);
    $pdf->SetFont('helvetica', '', 4.5);
    foreach ([0, 25, 50, 75, 100] as $tick) {
        $lineY = $plotY + $plotH - (($tick / 100) * $plotH);
        $pdf->Line($axisX, $lineY, $axisX + $barsW, $lineY);
        $pdf->SetXY($plotX, $lineY - 1.6);
        $pdf->Cell(7, 3.2, (string)$tick, 0, 0, 'R');
    }
    $pdf->SetDrawColor(180, 190, 200);
    $pdf->Line($axisX, $plotY, $axisX, $axisY);
    $pdf->Line($axisX, $axisY, $axisX + $barsW, $axisY);

    foreach ($subjectsList as $index => $chartRow) {
        $studentScore = isset($chartRow['score']) && $chartRow['score'] !== null ? (float)$chartRow['score'] : 0.0;
        $studentScore = max(0.0, min(100.0, $studentScore));
        $barH = $plotH * ($studentScore / 100.0);
        $slotX = $axisX + ($index * $slotW);
        $barX = $slotX + max(0.2, ($slotW - $barW) / 2);
        $barY = $axisY - $barH;

        if ($barH > 0) {
            $pdf->SetFillColor(26, 143, 212);
            $pdf->SetDrawColor(20, 118, 176);
            $pdf->Rect($barX, $barY, $barW, $barH, 'DF');
        }

        $pdf->SetFont('helvetica', 'B', $scoreFont);
        $pdf->SetTextColor(31, 77, 117);
        $pdf->SetXY($slotX, max($plotY - 0.5, $barY - 3.2));
        $pdf->Cell($slotW, 2.8, number_format($studentScore, 0), 0, 0, 'C');

        $rawLabel = trim((string)($chartRow['subject_name'] ?? ''));
        $label = preg_replace('/\s+/', "\n", $rawLabel, 1);
        if (strlen($label) > ($rowCount >= 16 ? 16 : 22)) {
            $label = substr($rawLabel, 0, $rowCount >= 16 ? 14 : 18);
        }
        $pdf->SetFont('helvetica', '', $labelFont);
        $pdf->SetTextColor(27, 39, 51);
        $pdf->SetXY($slotX - 0.3, $axisY + 1.4);
        $pdf->MultiCell($slotW + 0.6, 2.1, $label, 0, 'C', false, 0);
    }

    // ==== REMARKS SECTION BELOW GRAPH ====
    $remarksY = $chartY + $chartH + $sectionGap;
    $remarksX = $margin + 2;
    $remarksW = $innerW - 30;
    $pdf->SetDrawColor(216, 226, 235);
    $pdf->SetFillColor(249, 251, 253);
    $pdf->Rect($remarksX, $remarksY, $remarksW, $remarksH, 'DF');
    $pdf->SetFont('helvetica', 'B', max(6.2, $tableHeaderFont - 0.5));
    $pdf->SetTextColor(31, 77, 117);
    $pdf->SetXY($remarksX + 3, $remarksY + 2);
    $pdf->Cell($remarksW - 6, 4, 'Remarks', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', $remarksFont);
    $pdf->SetTextColor(27, 39, 51);
    $pdf->SetXY($remarksX + 3, $remarksY + 6.5);
    $pdf->MultiCell($remarksW - 6, $remarksLineH, 'Class Teacher: ' . ($teacherComment !== '' ? $teacherComment : 'No class teacher remark available.'), 0, 'L', false, 1);
    $pdf->SetX($remarksX + 3);
    $pdf->MultiCell($remarksW - 6, $remarksLineH, $headteacherTitle . ': ' . ($headteacherComment !== '' ? $headteacherComment : 'No ' . strtolower($headteacherTitle) . ' remark available.'), 0, 'L', false, 1);

    // ==== SIGNATURE + STAMP + QR AT THE BOTTOM ====
    $signY = $pageH - 30;
    $leftBlockX = $margin + 4;
    $leftBlockW = 48;
    if ($signatureExists) {
        $pdf->Image($signaturePath, $leftBlockX, $signY - 7, 28, 10, '', '', '', false, 300, '', false, false, 0, false, false, false);
    }
    $pdf->SetDrawColor(120, 130, 140);
    $pdf->Line($leftBlockX, $signY + 4, $leftBlockX + $leftBlockW, $signY + 4);
    $pdf->SetFont('helvetica', 'B', 6.4);
    $pdf->SetTextColor(60, 70, 80);
    $pdf->SetXY($leftBlockX, $signY + 4.6);
    $pdf->Cell($leftBlockW, 3.2, $headteacherTitle, 0, 1, 'L');
    if ($headteacherName !== '') {
        $pdf->SetFont('helvetica', '', 5.9);
        $pdf->SetX($leftBlockX);
        $pdf->Cell($leftBlockW, 2.8, $headteacherName, 0, 1, 'L');
    }

    $qrSize = 22;
    $qrX = ($pageW / 2) - ($qrSize / 2);
    $qrY = $pageH - 28;
    $pdf->write2DBarcode($verificationUrl, 'QRCODE,H', $qrX, $qrY, $qrSize, $qrSize);

    $stampX = $pageW - $margin - 28;
    $stampY = $pageH - 31;
    if ($stampExists) {
        $pdf->Image($stampPath, $stampX, $stampY, 22, 22, '', '', '', false, 300, '', false, false, 0, false, false, false);
    } else {
        $pdf->SetDrawColor(180, 190, 200);
        $pdf->RoundedRect($stampX, $stampY, 22, 22, 2, '1111', 'D');
        $pdf->SetFont('helvetica', 'B', 6.0);
        $pdf->SetTextColor(100, 110, 120);
        $pdf->SetXY($stampX, $stampY + 8);
        $pdf->Cell(22, 4, 'SCHOOL', 0, 1, 'C');
        $pdf->SetX($stampX);
        $pdf->Cell(22, 4, 'STAMP', 0, 1, 'C');
    }

    app_pdf_draw_document_watermark(
        $pdf,
        $studentName,
        (string)(defined('WBName') ? WBName : (defined('APP_NAME') ? APP_NAME : 'School'))
    );
}
