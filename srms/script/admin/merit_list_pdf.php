<?php
chdir('../');
session_start();
if (ob_get_level() === 0) {
	ob_start();
}
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/pdf_branding.php');
require_once('tcpdf/tcpdf.php');

if ($res !== "1" || $level !== "0") { header("location:../"); exit; }

function merit_pdf_subject_label(string $name): string
{
	$name = trim($name);
	if ($name === '') {
		return 'SUBJ';
	}
	$words = preg_split('/\s+/', strtoupper($name));
	if (count($words) === 1) {
		return strlen($words[0]) <= 6 ? $words[0] : substr($words[0], 0, 6);
	}
	$abbr = '';
	foreach ($words as $word) {
		if ($word === '') {
			continue;
		}
		$abbr .= substr($word, 0, 1);
	}
	return substr($abbr, 0, 6);
}

function merit_pdf_score_text($value): string
{
	if ($value === null || $value === '') {
		return '-';
	}
	$number = (float)$value;
	return number_format($number, $number === floor($number) ? 0 : 1);
}

function merit_pdf_fit_text(string $value, int $limit): string
{
	$value = trim($value);
	if ($limit < 1 || strlen($value) <= $limit) {
		return $value;
	}
	return rtrim(substr($value, 0, max(1, $limit - 1))) . '.';
}

function merit_pdf_grade_distribution(array $rows): array
{
	$distribution = [];
	foreach ($rows as $row) {
		$grade = strtoupper(trim((string)($row['grade'] ?? 'N/A')));
		if ($grade === '') {
			$grade = 'N/A';
		}
		if (!isset($distribution[$grade])) {
			$distribution[$grade] = [
				'grade' => $grade,
				'count' => 0,
				'best' => null,
				'lowest' => null,
			];
		}
		$distribution[$grade]['count']++;
		$mean = (float)($row['mean_points'] ?? 0);
		$distribution[$grade]['best'] = $distribution[$grade]['best'] === null ? $mean : max((float)$distribution[$grade]['best'], $mean);
		$distribution[$grade]['lowest'] = $distribution[$grade]['lowest'] === null ? $mean : min((float)$distribution[$grade]['lowest'], $mean);
	}

	$ordered = array_values($distribution);
	usort($ordered, static function ($a, $b) {
		if ((int)$a['count'] === (int)$b['count']) {
			return strcmp((string)$a['grade'], (string)$b['grade']);
		}
		return (int)$b['count'] <=> (int)$a['count'];
	});

	return $ordered;
}

function merit_pdf_subject_analysis(array $rows, array $subjects): array
{
	$analysis = [];
	foreach ($subjects as $subject) {
		$subjectId = (int)($subject['subject'] ?? 0);
		if ($subjectId < 1) {
			continue;
		}

		$scores = [];
		$gradeMix = [];
		foreach ($rows as $row) {
			$value = $row['subject_scores'][$subjectId] ?? null;
			if ($value !== null && $value !== '') {
				$scores[] = (float)$value;
			}
			$grade = strtoupper(trim((string)($row['subject_grades'][$subjectId] ?? '')));
			if ($grade !== '' && $grade !== 'N/A') {
				$gradeMix[$grade] = ($gradeMix[$grade] ?? 0) + 1;
			}
		}

		$bestGrade = '-';
		if (!empty($gradeMix)) {
			arsort($gradeMix);
			$bestGrade = key($gradeMix) . ' (' . current($gradeMix) . ')';
		}

		$analysis[] = [
			'subject_name' => (string)($subject['subject_name'] ?? 'Subject'),
			'entries' => count($scores),
			'highest' => !empty($scores) ? max($scores) : null,
			'lowest' => !empty($scores) ? min($scores) : null,
			'average' => !empty($scores) ? round(array_sum($scores) / count($scores), 2) : null,
			'top_grade' => $bestGrade,
		];
	}

	usort($analysis, static function ($a, $b) {
		return (float)($b['average'] ?? -1) <=> (float)($a['average'] ?? -1);
	});

	return $analysis;
}

function merit_pdf_ensure_space(TCPDF $pdf, float $requiredHeight, callable $onNewPage = null): void
{
	$bottomLimit = $pdf->getPageHeight() - $pdf->getBreakMargin();
	if (($pdf->GetY() + $requiredHeight) <= $bottomLimit) {
		return;
	}

	$pdf->AddPage();
	if ($onNewPage !== null) {
		$onNewPage();
	}
}

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$termId = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
$examId = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
if ($classId < 1 || $termId < 1) {
	header("location:merit_list");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$list = report_class_merit_list($conn, $classId, $termId, (int)$account_id, $examId);
	$rows = $list['rows'];
	$subjects = is_array($list['subjects'] ?? null) ? $list['subjects'] : [];

	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$classId]);
	$className = (string)$stmt->fetchColumn();
	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();
	$examName = '';
	if ($examId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_exams WHERE id = ? LIMIT 1");
		$stmt->execute([$examId]);
		$examName = (string)$stmt->fetchColumn();
	}

	if (!$rows) {
		$_SESSION['reply'] = array(array("warning", "No merit rows found for the selected class and term."));
		header("location:merit_list?class_id=" . $classId . "&term_id=" . $termId);
		exit;
	}

	$studentCount = count($rows);
	$bestMean = (float)($rows[0]['mean_points'] ?? 0);
	$lowestMean = (float)($rows[$studentCount - 1]['mean_points'] ?? 0);
	$averageMean = 0.0;
	foreach ($rows as $row) {
		$averageMean += (float)($row['mean_points'] ?? 0);
	}
	$averageMean = $studentCount > 0 ? round($averageMean / $studentCount, 2) : 0.0;
	$gradeSummary = merit_pdf_grade_distribution($rows);
	$subjectAnalysis = merit_pdf_subject_analysis($rows, $subjects);

	$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(5, 5, 5);
	$pdf->SetAutoPageBreak(true, 8);
	$pdf->setCellPadding(0.6);
	$pdf->AddPage();

	$headerHtml = app_pdf_brand_header_html(
		$conn,
		'CLASS MERIT LIST',
		'Ranked learner performance summary for class review and school records',
		28
	);
	$headerHtml .= '<table cellpadding="2" cellspacing="0" style="font-size:7.5pt;">'
		. '<tr>'
		. '<td width="25%"><strong>Class:</strong> ' . htmlspecialchars($className, ENT_QUOTES, 'UTF-8') . '</td>'
		. '<td width="25%"><strong>Term:</strong> ' . htmlspecialchars($termName, ENT_QUOTES, 'UTF-8') . '</td>'
		. '<td width="25%"><strong>Exam:</strong> ' . htmlspecialchars($examName !== '' ? $examName : 'All Published Exams', ENT_QUOTES, 'UTF-8') . '</td>'
		. '<td width="25%"><strong>Learners:</strong> ' . $studentCount . '</td>'
		. '</tr>'
		. '<tr>'
		. '<td width="25%"><strong>Average:</strong> ' . number_format($averageMean, 2) . '</td>'
		. '<td width="25%"><strong>Best Mean:</strong> ' . number_format($bestMean, 2) . '</td>'
		. '<td width="25%"><strong>Lowest Mean:</strong> ' . number_format($lowestMean, 2) . '</td>'
		. '<td width="25%"><strong>Printed:</strong> ' . date('d M Y') . '</td>'
		. '</tr>'
		. '</table>';
	$pdf->writeHTML($headerHtml, true, false, true, false, '');

	$subjectCount = count($subjects);
	$printableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
	$compactMode = $subjectCount >= 8;
	$wPos = $compactMode ? 9 : 10;
	$wAdm = $compactMode ? 15 : 17;
	$wName = $compactMode ? 26 : 30;
	$wGender = 7;
	$wTotal = 10;
	$wAvg = 10;
	$wGrade = 9;
	$wTrend = $subjectCount >= 12 ? 0 : 10;
	$wRemark = 0;
	$minPerSubject = $compactMode ? 4.5 : 5.5;
	$headerFont = $compactMode ? 4.8 : 5.4;
	$rowFont = $compactMode ? 4.7 : 5.1;
	$headerHeight = $compactMode ? 4.8 : 5.5;
	$rowHeight = $compactMode ? 4.4 : 5;

	$fixedWithoutSubjects = $wPos + $wAdm + $wName + $wGender + $wTotal + $wAvg + $wGrade + $wTrend;
	$availableForSubjects = $printableWidth - $fixedWithoutSubjects;
	$wPerSubject = $subjectCount > 0 ? floor(($availableForSubjects) / $subjectCount) : 0;
	$wPerSubject = max($subjectCount > 0 ? 3 : 0, min($minPerSubject, $wPerSubject));
	$totalTableWidth = $fixedWithoutSubjects + ($subjectCount * $wPerSubject) + $wRemark;
	$widthScale = 1.0;
	if ($totalTableWidth > $printableWidth && $totalTableWidth > 0) {
		$widthScale = $printableWidth / $totalTableWidth;
		$wPos *= $widthScale;
		$wAdm *= $widthScale;
		$wName *= $widthScale;
		$wGender *= $widthScale;
		$wPerSubject *= $widthScale;
		$wTotal *= $widthScale;
		$wAvg *= $widthScale;
		$wGrade *= $widthScale;
		$wTrend *= $widthScale;
		$wRemark *= $widthScale;
		$headerFont *= max(0.78, $widthScale);
		$rowFont *= max(0.78, $widthScale);
		$headerHeight *= max(0.82, $widthScale);
		$rowHeight *= max(0.82, $widthScale);
	}
	$remainingWidth = $printableWidth - ($fixedWithoutSubjects + ($subjectCount * $wPerSubject));
	if ($remainingWidth > 0 && $subjectCount > 0) {
		$wPerSubject += $remainingWidth / $subjectCount;
	}
	$nameLimit = $subjectCount >= 12 ? 10 : ($subjectCount >= 8 ? 12 : 18);
	$admLimit = $subjectCount >= 12 ? 6 : 8;

	$drawHeader = function () use ($pdf, $subjects, $wPos, $wAdm, $wName, $wGender, $wPerSubject, $wTotal, $wAvg, $wGrade, $wTrend, $headerFont, $headerHeight, $subjectCount): void {
		$pdf->SetFont('helvetica', 'B', $headerFont);
		$pdf->SetFillColor(233, 241, 247);
		$pdf->Cell($wPos, $headerHeight, 'Pos', 1, 0, 'C', true);
		$pdf->Cell($wAdm, $headerHeight, 'Adm', 1, 0, 'C', true);
		$pdf->Cell($wName, $headerHeight, 'Name', 1, 0, 'L', true);
		$pdf->Cell($wGender, $headerHeight, 'Sex', 1, 0, 'C', true);
		foreach ($subjects as $subject) {
			$pdf->Cell($wPerSubject, $headerHeight, merit_pdf_subject_label((string)($subject['subject_name'] ?? 'Subject')), 1, 0, 'C', true);
		}
		$pdf->Cell($wTotal, $headerHeight, 'Total', 1, 0, 'C', true);
		$pdf->Cell($wAvg, $headerHeight, 'Avg', 1, 0, 'C', true);
		$pdf->Cell($wGrade, $headerHeight, 'Grade', 1, 0, 'C', true);
		if ($subjectCount < 12) {
			$pdf->Cell($wTrend, $headerHeight, 'Trend', 1, 0, 'C', true);
			$pdf->Ln();
			return;
		}
		$pdf->Ln();
	};

	$drawHeader();
	$pdf->SetFont('helvetica', '', $rowFont);

	foreach ($rows as $row) {
		if ($pdf->GetY() > 272) {
			$pdf->AddPage();
			$drawHeader();
			$pdf->SetFont('helvetica', '', $rowFont);
		}

		$pdf->Cell($wPos, $rowHeight, (string)($row['position_text'] ?? $row['position'] ?? '-'), 1, 0, 'C');
		$pdf->Cell($wAdm, $rowHeight, merit_pdf_fit_text((string)($row['school_id'] !== '' ? $row['school_id'] : $row['student_id']), $admLimit), 1, 0, 'C');
		$pdf->Cell($wName, $rowHeight, merit_pdf_fit_text((string)($row['student_name'] ?? ''), $nameLimit), 1, 0, 'L');
		$pdf->Cell($wGender, $rowHeight, substr((string)($row['gender'] ?? ''), 0, 1), 1, 0, 'C');
		foreach ($subjects as $subject) {
			$subjectId = (int)($subject['subject'] ?? 0);
			$value = $row['subject_scores'][$subjectId] ?? null;
			$pdf->Cell($wPerSubject, $rowHeight, merit_pdf_score_text($value), 1, 0, 'C');
		}
		$pdf->Cell($wTotal, $rowHeight, number_format((float)($row['total_points'] ?? 0), 1), 1, 0, 'C');
		$pdf->Cell($wAvg, $rowHeight, number_format((float)($row['mean_points'] ?? 0), 1), 1, 0, 'C');
		$pdf->Cell($wGrade, $rowHeight, merit_pdf_fit_text((string)($row['grade'] ?? ''), 6), 1, 0, 'C');
		if ($subjectCount < 12) {
			$pdf->Cell($wTrend, $rowHeight, merit_pdf_fit_text((string)($row['trend'] ?? ''), 8), 1, 0, 'C');
			$pdf->Ln();
			continue;
		}
		$pdf->Ln();
	}

	$summaryHeaderWriter = function () use ($pdf, $conn): void {
		$summaryHeader = app_pdf_brand_header_html(
			$conn,
			'MERIT LIST SUMMARY',
			'Grade distribution, class highlights, and subject analysis for the selected assessment',
			24
		);
		$pdf->writeHTML($summaryHeader, true, false, true, false, '');
	};

	$pdf->AddPage();
	$summaryHeaderWriter();

	$pdf->SetFont('helvetica', 'B', 10);
	$pdf->Cell(0, 5.5, 'Grade Distribution Summary', 0, 1, 'L');
	$pdf->SetFont('helvetica', '', 7);
	$pdf->SetFillColor(233, 241, 247);
	$pdf->Cell(28, 6, 'Grade', 1, 0, 'C', true);
	$pdf->Cell(28, 6, 'Learners', 1, 0, 'C', true);
	$pdf->Cell(34, 6, 'Best Mean', 1, 0, 'C', true);
	$pdf->Cell(34, 6, 'Lowest Mean', 1, 1, 'C', true);
	foreach ($gradeSummary as $item) {
		$pdf->Cell(28, 5.5, (string)$item['grade'], 1, 0, 'C');
		$pdf->Cell(28, 5.5, (string)((int)$item['count']), 1, 0, 'C');
		$pdf->Cell(34, 5.5, $item['best'] === null ? '-' : number_format((float)$item['best'], 2), 1, 0, 'C');
		$pdf->Cell(34, 5.5, $item['lowest'] === null ? '-' : number_format((float)$item['lowest'], 2), 1, 1, 'C');
	}

	$pdf->Ln(2);
	$pdf->SetFont('helvetica', '', 7);
	$pdf->SetFillColor(233, 241, 247);
	$pdf->Cell(32, 5.5, 'Class', 1, 0, 'L', true);
	$pdf->Cell(54, 5.5, $className, 1, 0, 'L');
	$pdf->Cell(32, 5.5, 'Exam', 1, 0, 'L', true);
	$pdf->Cell(62, 5.5, $examName !== '' ? $examName : 'All Published Exams', 1, 1, 'L');
	$pdf->Cell(32, 5.5, 'Term', 1, 0, 'L', true);
	$pdf->Cell(54, 5.5, $termName, 1, 0, 'L');
	$pdf->Cell(32, 5.5, 'Printed Date', 1, 0, 'L', true);
	$pdf->Cell(62, 5.5, date('Y-m-d'), 1, 1, 'L');

	merit_pdf_ensure_space($pdf, 78, $summaryHeaderWriter);

	$pdf->Ln(2);
	$pdf->SetFont('helvetica', 'B', 10);
	$pdf->Cell(0, 5.5, 'Subject Analysis', 0, 1, 'L');
	$pdf->SetFont('helvetica', '', 6.5);
	$pdf->Cell(44, 6, 'Subject', 1, 0, 'L', true);
	$pdf->Cell(16, 6, 'Entries', 1, 0, 'C', true);
	$pdf->Cell(20, 6, 'Highest', 1, 0, 'C', true);
	$pdf->Cell(20, 6, 'Lowest', 1, 0, 'C', true);
	$pdf->Cell(22, 6, 'Average', 1, 0, 'C', true);
	$pdf->Cell(30, 6, 'Top Grade', 1, 1, 'C', true);
	foreach ($subjectAnalysis as $item) {
		merit_pdf_ensure_space($pdf, 6.4, $summaryHeaderWriter);
		$pdf->Cell(44, 5.2, merit_pdf_fit_text((string)$item['subject_name'], 20), 1, 0, 'L');
		$pdf->Cell(16, 5.2, (string)((int)$item['entries']), 1, 0, 'C');
		$pdf->Cell(20, 5.2, $item['highest'] === null ? '-' : merit_pdf_score_text($item['highest']), 1, 0, 'C');
		$pdf->Cell(20, 5.2, $item['lowest'] === null ? '-' : merit_pdf_score_text($item['lowest']), 1, 0, 'C');
		$pdf->Cell(22, 5.2, $item['average'] === null ? '-' : number_format((float)$item['average'], 2), 1, 0, 'C');
		$pdf->Cell(30, 5.2, merit_pdf_fit_text((string)$item['top_grade'], 14), 1, 1, 'C');
	}

	merit_pdf_ensure_space($pdf, 42, $summaryHeaderWriter);

	$pdf->Ln(2);
	$pdf->SetFont('helvetica', 'B', 10);
	$pdf->Cell(0, 5.5, 'Class Highlights', 0, 1, 'L');
	$pdf->SetFont('helvetica', '', 7);
	$pdf->MultiCell(0, 4.8, 'Top performer: ' . (string)($rows[0]['student_name'] ?? '-') . ' (' . number_format((float)($rows[0]['mean_points'] ?? 0), 2) . ' mean points)' . "\n"
		. 'Lowest recorded mean: ' . number_format($lowestMean, 2) . "\n"
		. 'Class average mean: ' . number_format($averageMean, 2) . "\n"
		. 'Exam scope: ' . ($examName !== '' ? $examName : 'All Published Exams'), 1, 'L', false, 1);

	$pdf->Ln(2);
	$pdf->SetFont('helvetica', 'B', 8);
	$pdf->Cell(0, 5, 'Approval / Sign-off', 0, 1, 'L');
	$pdf->SetFont('helvetica', '', 7);
	$pdf->SetFillColor(233, 241, 247);
	$pdf->Cell(60, 5.5, 'Role', 1, 0, 'L', true);
	$pdf->Cell(76, 5.5, 'Name / Signature', 1, 0, 'L', true);
	$pdf->Cell(44, 5.5, 'Date', 1, 1, 'L', true);

	$headteacherMeta = app_pdf_brand_headteacher_meta();
	$headteacherName = trim((string)($headteacherMeta['name'] ?? ''));
	$headteacherTitle = trim((string)($headteacherMeta['title'] ?? 'Headteacher'));
	$approvalRows = [
		['role' => 'Class Teacher', 'name' => '____________________', 'date' => date('Y-m-d')],
		['role' => 'Deputy Headteacher', 'name' => '____________________', 'date' => date('Y-m-d')],
		['role' => $headteacherTitle !== '' ? $headteacherTitle : 'Headteacher', 'name' => ($headteacherName !== '' ? $headteacherName : '____________________'), 'date' => date('Y-m-d')],
	];
	foreach ($approvalRows as $approvalRow) {
		$pdf->Cell(60, 5.5, $approvalRow['role'], 1, 0, 'L');
		$pdf->Cell(76, 5.5, $approvalRow['name'], 1, 0, 'L');
		$pdf->Cell(44, 5.5, $approvalRow['date'], 1, 1, 'L');
	}

	$fileName = 'merit-list-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower($className . '-' . $termName)) . '.pdf';
	$pdfBinary = $pdf->Output($fileName, 'S');
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	if (!headers_sent()) {
		header('Content-Type: application/pdf');
		header('Content-Disposition: inline; filename="' . $fileName . '"');
		header('Content-Length: ' . strlen($pdfBinary));
	}
	echo $pdfBinary;
	exit;
} catch (Throwable $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . " merit_list_pdf] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Unable to generate the merit list PDF right now."));
		header("location:merit_list?class_id=" . $classId . "&term_id=" . $termId . "&exam_id=" . $examId);
}
