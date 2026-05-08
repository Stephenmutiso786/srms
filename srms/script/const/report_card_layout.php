<?php

require_once(__DIR__ . '/report_engine.php');
require_once(__DIR__ . '/school.php');

function app_report_card_view_styles(): string
{
    return <<<'CSS'
<style>
:root {
	--report-blue: #00aeef;
	--report-gray: #f4f4f4;
	--report-border: #d7d7d7;
	--report-text: #1b2733;
}
.report-container {
	max-width: 1080px;
	margin: 0 auto;
	background: #fff;
	border-left: 15px solid var(--report-blue);
	padding: 18px 18px 22px;
	box-shadow: 0 14px 36px rgba(20, 40, 60, 0.08);
}
.report-container.report-compact {
	border-left-width: 9px;
	padding: 12px 12px 14px;
}
.report-container.report-pdf-one-page {
	border-left-width: 6px;
	padding: 8px 8px 9px;
	box-shadow: none;
}
.report-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	border-bottom: 1px solid #e9eef2;
	padding-bottom: 10px;
}
.logo-wrap {
	width: 88px;
	height: 88px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1px solid var(--report-border);
	background: #fff;
}
.logo {
	max-width: 82px;
	max-height: 82px;
	object-fit: contain;
}
.report-container.report-compact .logo-wrap {
	width: 64px;
	height: 64px;
}
.report-container.report-pdf-one-page .logo-wrap {
	width: 52px;
	height: 52px;
}
.report-container.report-compact .logo {
	max-width: 58px;
	max-height: 58px;
}
.report-container.report-pdf-one-page .logo {
	max-width: 46px;
	max-height: 46px;
}
.school-info {
	text-align: right;
	color: var(--report-text);
}
.school-info h1 {
	margin: 0;
	font-size: 1.5rem;
	font-weight: 800;
}
.report-container.report-pdf-one-page .school-info h1 {
	font-size: 1.12rem;
}
.school-info p {
	margin: 4px 0 0;
	font-size: 0.92rem;
	color: #4c5b68;
}
.report-container.report-pdf-one-page .school-info p {
	font-size: 0.75rem;
}
.report-title {
	background: var(--report-blue);
	color: #fff;
	text-align: center;
	padding: 10px;
	font-weight: 700;
	margin: 16px 0;
	letter-spacing: 0.01em;
}
.report-container.report-pdf-one-page .report-title {
	margin: 8px 0;
	padding: 6px;
	font-size: 0.74rem;
}
.report-container.report-compact .report-title {
	margin: 10px 0;
	padding: 7px;
	font-size: 0.85rem;
}
.student-profile {
	display: grid;
	grid-template-columns: 120px 1fr 250px;
	gap: 16px;
	border-bottom: 2px solid var(--report-border);
	padding-bottom: 16px;
}
.report-container.report-compact .student-profile {
	grid-template-columns: 110px 1fr;
	gap: 10px;
	padding-bottom: 10px;
}
.report-container.report-pdf-one-page .student-profile {
	grid-template-columns: 90px 1fr 190px;
	gap: 8px;
	padding-bottom: 8px;
}
.photo-box {
	width: 132px;
	height: 152px;
	border: 1px solid #c7d0d9;
	overflow: hidden;
	background: #f9fbfd;
	display: flex;
	align-items: center;
	justify-content: center;
}
.report-container.report-compact .photo-box {
	width: 98px;
	height: 112px;
}
.report-container.report-pdf-one-page .photo-box {
	width: 74px;
	height: 88px;
}
.photo-box img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.photo-fallback {
	font-size: 1.9rem;
	font-weight: 700;
	color: #1f4d75;
}
.report-container.report-pdf-one-page .photo-fallback {
	font-size: 1.3rem;
}
.details p {
	margin: 6px 0;
	font-size: 0.94rem;
	color: #2c3a46;
}
.report-container.report-compact .details p {
	margin: 4px 0;
	font-size: 0.82rem;
}
.report-container.report-pdf-one-page .details p {
	margin: 2px 0;
	font-size: 0.72rem;
}
.performance-chart {
	border: 1px solid var(--report-border);
	padding: 10px;
	background: #fcfeff;
}
.report-container.report-pdf-one-page .performance-chart {
	display: none;
}
.performance-chart p {
	margin: 0 0 8px;
	font-size: 0.82rem;
	font-weight: 700;
	color: #4c5b68;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}
.report-container.report-compact .performance-chart {
	display: none;
}
.chart-placeholder {
	display: grid;
	gap: 6px;
}
.chart-row {
	display: grid;
	grid-template-columns: 58px 1fr;
	gap: 8px;
	align-items: center;
}
.chart-row span {
	font-size: 0.76rem;
	color: #4f5d68;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.chart-bars {
	height: 12px;
	background: #e5ecf2;
	position: relative;
	overflow: hidden;
}
.chart-bars .student-bar {
	position: absolute;
	height: 12px;
	left: 0;
	top: 0;
	background: #1a8fd4;
	opacity: 0.9;
}
.chart-bars .class-bar {
	position: absolute;
	height: 6px;
	left: 0;
	bottom: 0;
	background: #38b56a;
	opacity: 0.75;
}
.stats-row {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
	margin: 16px 0;
}
.report-container.report-compact .stats-row {
	margin: 10px 0;
	gap: 6px;
}
.report-container.report-pdf-one-page .stats-row {
	margin: 6px 0;
	gap: 4px;
}
.stat-card {
	background: var(--report-gray);
	padding: 10px;
	text-align: center;
	border-top: 3px solid var(--report-blue);
	font-size: 0.88rem;
	color: #2f3f4c;
}
.report-container.report-compact .stat-card {
	padding: 7px;
	font-size: 0.78rem;
}
.report-container.report-pdf-one-page .stat-card {
	padding: 5px;
	font-size: 0.68rem;
	border-top-width: 2px;
}
.stat-card strong {
	display: inline-block;
	margin-left: 4px;
	color: #13222d;
}
.dev {
	font-size: 0.78em;
	margin-left: 5px;
	font-weight: 700;
}
.dev.down { color: #da8a00; }
.dev.up { color: #128a42; }
.dev.flat { color: #687886; }
.report-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 10px;
}
.report-table th,
.report-table td {
	border: 1px solid #999;
	padding: 7px;
	text-align: left;
	font-size: 12px;
	color: #1f2f3a;
}
.report-container.report-compact .report-table th,
.report-container.report-compact .report-table td {
	padding: 4px;
	font-size: 10px;
}
.report-container.report-pdf-one-page .report-table th,
.report-container.report-pdf-one-page .report-table td {
	padding: 2px 3px;
	font-size: 7px;
}
.report-table thead th {
	background: #fff;
	font-weight: 700;
	text-transform: uppercase;
	font-size: 11px;
}
.report-container.report-pdf-one-page .report-table thead th {
	font-size: 7px;
}
.report-table td.center,
.report-table th.center {
	text-align: center;
}
.remarks-section {
	display: flex;
	justify-content: space-between;
	gap: 14px;
	margin-top: 22px;
	border-top: 1px solid #dde5ec;
	padding-top: 14px;
}
.report-container.report-compact .remarks-section {
	margin-top: 10px;
	padding-top: 8px;
	gap: 8px;
}
.report-container.report-pdf-one-page .remarks-section {
	margin-top: 6px;
	padding-top: 4px;
	gap: 6px;
}
.remarks {
	flex: 1;
	background: #fafcfe;
	border: 1px solid #d8e2eb;
	padding: 12px;
}
.report-container.report-compact .remarks {
	padding: 8px;
}
.report-container.report-pdf-one-page .remarks {
	padding: 5px;
}
.remarks p {
	margin: 7px 0;
	font-size: 0.9rem;
	color: #293843;
}
.report-container.report-compact .remarks p {
	margin: 4px 0;
	font-size: 0.78rem;
}
.report-container.report-pdf-one-page .remarks p {
	margin: 2px 0;
	font-size: 0.68rem;
}
.qr-code {
	width: 112px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1px solid #d8e2eb;
	background: #fff;
	padding: 8px;
}
.report-container.report-compact .qr-code {
	width: 80px;
	padding: 4px;
}
.report-container.report-pdf-one-page .qr-code {
	width: 62px;
	padding: 3px;
}
.qr-code img {
	width: 92px;
	height: 92px;
	object-fit: contain;
}
.report-container.report-compact .qr-code img {
	width: 68px;
	height: 68px;
}
.report-container.report-pdf-one-page .qr-code img {
	width: 54px;
	height: 54px;
}
@media (max-width: 991px) {
	.student-profile {
		grid-template-columns: 1fr;
	}
	.stats-row {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
	.remarks-section {
		flex-direction: column;
	}
	.school-info {
		text-align: left;
	}
}
@media (max-width: 640px) {
	.report-header {
		flex-direction: column;
		align-items: flex-start;
	}
	.stats-row {
		grid-template-columns: 1fr;
	}
}
@media print{
	.app-header,.app-sidebar,.app-title,.report-actions,.app-nav,.tile:first-of-type,.report-toolbar{display:none!important}
	.app-content{margin-left:0;padding:0}
	.report-container{box-shadow:none;max-width:100%;margin:0;border-left-width:10px}
}
</style>
CSS;
}

function app_report_card_point_display(PDO $conn, $value): string
{
    if (!is_numeric($value)) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    list(, , $points) = report_cbe_grade_for_score($conn, (float)$value);
    $points = (float)$points;
    return number_format($points, $points === floor($points) ? 0 : 1);
}

function app_report_card_subject_points_value(array $subject): ?float
{
    if (isset($subject['grade_points']) && $subject['grade_points'] !== null && $subject['grade_points'] !== '') {
        return (float)$subject['grade_points'];
    }
    if (isset($subject['points']) && $subject['points'] !== null && $subject['points'] !== '') {
        return (float)$subject['points'];
    }
    $grade = trim((string)($subject['grade'] ?? ''));
    if ($grade === '' || strtoupper($grade) === 'N/A') {
        return null;
    }
    return (float)report_grade_points_from_label($grade);
}

function app_report_card_subject_points_display(array $subject): string
{
    $points = app_report_card_subject_points_value($subject);
    if ($points === null) {
        return '-';
    }
    return number_format($points, $points === floor($points) ? 0 : 1);
}

function app_report_card_qr_src(string $value): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=92x92&data=' . urlencode($value);
}

function app_report_card_build_rows(array $payload): array
{
    $rows = is_array($payload['rows'] ?? null) ? $payload['rows'] : [];
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];

    if (empty($rows) && !empty($card['subjects']) && is_array($card['subjects'])) {
        foreach ($card['subjects'] as $subject) {
            $rows[] = [
                'subject_name' => (string)($subject['subject_name'] ?? ''),
                'score' => (float)($subject['score'] ?? 0),
                'class_mean' => 0,
                'grade' => (string)($subject['grade'] ?? ''),
                'teacher_name' => (string)($subject['teacher_name'] ?? ''),
                'remark' => '',
                'rank' => '-',
            ];
        }
    }

    return $rows;
}

function app_report_card_render(PDO $conn, array $payload): string
{
    $rows = app_report_card_build_rows($payload);
    $card = is_array($payload['card'] ?? null) ? $payload['card'] : [];
    $studentName = (string)($payload['student_name'] ?? '');
    $studentId = (string)($payload['student_id'] ?? '');
    $schoolId = (string)($payload['school_id'] ?? '');
    $className = (string)($payload['class_name'] ?? '');
    $termName = (string)($payload['term_name'] ?? '');
    $examName = (string)($payload['exam_name'] ?? 'END TERM COMBINED');
    $kcpeScore = (string)($payload['kcpe_score'] ?? 'N/A');
    $schoolContact = (string)($payload['school_contact'] ?? '');
    $photoPath = (string)($payload['photo_path'] ?? '');
    $photoExists = !empty($payload['photo_exists']);
    $verificationCode = (string)($card['verification_code'] ?? ($payload['verification_code'] ?? ''));
    $teacherComment = (string)($card['teacher_comment'] ?? $card['remark'] ?? '');
    $headComment = (string)($card['headteacher_comment'] ?? $card['remark'] ?? '');
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $logoPath = (string)($payload['logo_path'] ?? '');
    $logoExists = !empty($payload['logo_exists']);
    $overallGrade = (string)($payload['overall_grade'] ?? ($card['grade'] ?? 'N/A'));
    $showQrImage = array_key_exists('show_qr_image', $payload) ? (bool)$payload['show_qr_image'] : true;
	$compact = !empty($payload['compact']);
	$pdfOnePage = !empty($payload['pdf_one_page']);
	$displayRows = $rows;
	$omittedRows = 0;
	if ($pdfOnePage && count($displayRows) > 6) {
		$omittedRows = count($displayRows) - 6;
		$displayRows = array_slice($displayRows, 0, 6);
	}

    $subjectCount = count($rows);
    $classMeanTotal = 0.0;
    $totalPoints = 0.0;
    foreach ($rows as $subjectRow) {
        $classMeanTotal += (float)($subjectRow['class_mean'] ?? 0);
        $subjectPoints = app_report_card_subject_points_value((array)$subjectRow);
        if ($subjectPoints !== null) {
            $totalPoints += $subjectPoints;
        }
    }
    $classMeanAvg = $subjectCount > 0 ? ($classMeanTotal / $subjectCount) : 0.0;
    $pointsMax = max(12, $subjectCount * 12);
    $classPointEstimate = ($classMeanAvg / 100) * $pointsMax;
    $meanPoints = $subjectCount > 0 ? ($totalPoints / $subjectCount) : 0.0;
    $displayTotalScore = isset($card['total_points']) ? (float)$card['total_points'] : $totalPoints;
    $displayMeanScore = isset($card['mean_points']) ? (float)$card['mean_points'] : $meanPoints;
    $classMeanPoints = $subjectCount > 0 ? ($classPointEstimate / $subjectCount) : 0.0;
    $meanDev = $displayMeanScore - $classMeanPoints;
    $pointsDev = $displayTotalScore - $classPointEstimate;

    $rowHtml = '';
	foreach ($displayRows as $subject) {
        $cat1 = $subject['cat1'] ?? ($subject['cat_1'] ?? '-');
        $cat2 = $subject['cat2'] ?? ($subject['cat_2'] ?? '-');
        $classMean = (float)($subject['class_mean'] ?? 0);
        $subjectPoints = app_report_card_subject_points_value((array)$subject);
        list(, , $classMeanPointsRow) = report_cbe_grade_for_score($conn, $classMean);
        $dev = ($subjectPoints ?? 0.0) - (float)$classMeanPointsRow;
		if ($pdfOnePage) {
			$rowHtml .= '<tr>'
				. '<td>' . htmlspecialchars((string)($subject['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
				. '<td class="center">' . app_report_card_subject_points_display((array)$subject) . '</td>'
				. '<td class="center">' . htmlspecialchars((string)($subject['grade'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
				. '</tr>';
			continue;
		}

        $rowHtml .= '<tr>'
            . '<td>' . htmlspecialchars((string)($subject['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td class="center">' . app_report_card_subject_points_display((array)$subject) . '</td>'
			. '<td class="center">' . htmlspecialchars((string)($subject['grade'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
			. '<td>' . htmlspecialchars((string)($subject['remark'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
			. '<td>' . htmlspecialchars((string)($subject['teacher_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
            . '</tr>';
    }

    if ($rowHtml === '') {
		$rowHtml = '<tr><td colspan="' . ($pdfOnePage ? 3 : 5) . '" class="center">No subject data available.</td></tr>';
	}
    $chartHtml = '';
    foreach (array_slice($rows, 0, 6) as $chartRow) {
		if ($pdfOnePage) {
			break;
		}
        $chartHtml .= '<div class="chart-row">'
            . '<span>' . htmlspecialchars((string)($chartRow['subject_name'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>'
            . '<div class="chart-bars">'
            . '<div class="student-bar" style="width:' . max(0, min(100, (float)($chartRow['score'] ?? 0))) . '%;"></div>'
            . '<div class="class-bar" style="width:' . max(0, min(100, (float)($chartRow['class_mean'] ?? 0))) . '%;"></div>'
            . '</div></div>';
    }

    if ($verificationCode === '') {
        $qrHtml = '<div style="font-size:0.8rem;color:#687886;">No QR</div>';
    } elseif ($showQrImage) {
        $qrHtml = '<img src="' . htmlspecialchars(app_report_card_qr_src($verificationCode), ENT_QUOTES, 'UTF-8') . '" alt="QR Code">';
    } else {
        $qrHtml = '<div style="width:92px;height:92px;"></div>';
    }

    $photoHtml = $photoExists && $photoPath !== ''
        ? '<img src="' . htmlspecialchars($photoPath, ENT_QUOTES, 'UTF-8') . '" alt="Student Photo">'
        : '<div class="photo-fallback">' . htmlspecialchars(strtoupper(substr($studentName !== '' ? $studentName : $studentId, 0, 1)), ENT_QUOTES, 'UTF-8') . '</div>';

    $logoHtml = $logoExists && $logoPath !== ''
        ? '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="School Logo" class="logo">'
        : '';

	return app_report_card_view_styles()
		. '<div class="report-container' . ($compact ? ' report-compact' : '') . ($pdfOnePage ? ' report-pdf-one-page' : '') . '">'
        . '<header class="report-header">'
        . '<div class="logo-wrap">' . $logoHtml . '</div>'
        . '<div class="school-info"><h1>' . htmlspecialchars($schoolName, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($schoolContact, ENT_QUOTES, 'UTF-8') . '</p></div>'
        . '</header>'
        . '<div class="report-title">ACADEMIC REPORT FORM - ' . strtoupper(htmlspecialchars($className, ENT_QUOTES, 'UTF-8')) . ' - ' . strtoupper(htmlspecialchars($examName, ENT_QUOTES, 'UTF-8')) . ' - (' . strtoupper(htmlspecialchars($termName, ENT_QUOTES, 'UTF-8')) . ')</div>'
		. '<section class="student-profile">'
        . '<div class="photo-box">' . $photoHtml . '</div>'
        . '<div class="details">'
        . '<p><strong>NAME:</strong> ' . htmlspecialchars($studentName, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>ADMNO:</strong> ' . htmlspecialchars($schoolId !== '' ? $schoolId : $studentId, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>FORM:</strong> ' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p><strong>KCPE:</strong> ' . htmlspecialchars($kcpeScore !== '' ? $kcpeScore : 'N/A', ENT_QUOTES, 'UTF-8') . '</p>'
        . '</div>'
		. '<div class="performance-chart"><p>Subject Performance - Student vs Class</p><div class="chart-placeholder">' . $chartHtml . '</div></div>'
        . '</section>'
        . '<div class="stats-row">'
        . '<div class="stat-card">Mean Score: <strong>' . number_format($displayMeanScore, 1) . '</strong><span class="dev ' . ($meanDev > 0 ? 'up' : ($meanDev < 0 ? 'down' : 'flat')) . '">' . ($meanDev > 0 ? '+' : '') . number_format($meanDev, 2) . ' pts</span></div>'
        . '<div class="stat-card">Total Score: <strong>' . number_format($displayTotalScore, 1) . '/' . number_format($pointsMax, 0) . '</strong><span class="dev ' . ($pointsDev > 0 ? 'up' : ($pointsDev < 0 ? 'down' : 'flat')) . '">' . ($pointsDev > 0 ? '+' : '') . number_format($pointsDev, 1) . '</span></div>'
        . '<div class="stat-card">QR Status: <strong>' . ($verificationCode !== '' ? 'Ready' : 'Pending') . '</strong><span class="dev flat">verification</span></div>'
        . '</div>'
		. '<table class="report-table"><thead>'
		. '<tr><th>Subject</th><th class="center">Score</th><th class="center">Grade</th>' . ($pdfOnePage ? '' : '<th>Comment</th><th>Teacher</th>') . '</tr>'
        . '</thead><tbody>' . $rowHtml . '</tbody></table>'
		. ($pdfOnePage ? '' : '<footer class="remarks-section">'
		. '<div class="remarks"><p><strong>Remarks</strong></p><p><strong>Class Teacher:</strong> ' . htmlspecialchars($teacherComment, ENT_QUOTES, 'UTF-8') . '</p><p><strong>Principal:</strong> ' . htmlspecialchars($headComment, ENT_QUOTES, 'UTF-8') . '</p></div>'
		. '<div class="qr-code">' . $qrHtml . '</div>'
		. '</footer>')
        . '</div>';
}
