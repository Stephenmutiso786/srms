<?php
chdir('../');
session_start();
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

	$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(8, 8, 8);
	$pdf->SetAutoPageBreak(true, 10);
	$pdf->AddPage();

	$headerHtml = app_pdf_brand_header_html(
		$conn,
		'CLASS MERIT LIST',
		'Ranked learner performance summary for class review and school records',
		42
	);
	$headerHtml .= '<table cellpadding="4" cellspacing="0" style="font-size:9pt;">'
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
	$compactMode = $subjectCount >= 14;
	$wPos = 10;
	$wAdm = $compactMode ? 16 : 20;
	$wName = $compactMode ? 34 : 42;
	$wGender = $compactMode ? 8 : 10;
	$wTotal = $compactMode ? 13 : 15;
	$wAvg = $compactMode ? 13 : 15;
	$wGrade = $compactMode ? 10 : 12;
	$wTrend = $compactMode ? 11 : 13;
	$minRemark = $compactMode ? 14 : 18;
	$minPerSubject = $compactMode ? 6 : 8;
	$headerFont = $compactMode ? 5.4 : 6.8;
	$rowFont = $compactMode ? 5.2 : 6.2;
	$headerHeight = $compactMode ? 6 : 8;
	$rowHeight = $compactMode ? 5 : 7;

	$fixedWithoutSubjects = $wPos + $wAdm + $wName + $wGender + $wTotal + $wAvg + $wGrade + $wTrend;
	$availableForSubjectsAndRemark = $printableWidth - $fixedWithoutSubjects;
	$wPerSubject = $subjectCount > 0 ? floor(($availableForSubjectsAndRemark - $minRemark) / $subjectCount) : 0;
	$wPerSubject = max($subjectCount > 0 ? 4 : 0, min($minPerSubject, $wPerSubject));
	$wRemark = max(8, $printableWidth - ($fixedWithoutSubjects + ($subjectCount * $wPerSubject)));

	$drawHeader = function () use ($pdf, $subjects, $wPos, $wAdm, $wName, $wGender, $wPerSubject, $wTotal, $wAvg, $wGrade, $wTrend, $wRemark, $headerFont, $headerHeight): void {
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
		$pdf->Cell($wTrend, $headerHeight, 'Trend', 1, 0, 'C', true);
		$pdf->Cell($wRemark, $headerHeight, 'Remark', 1, 1, 'L', true);
	};

	$drawHeader();
	$pdf->SetFont('helvetica', '', $rowFont);

	foreach ($rows as $row) {
		if ($pdf->GetY() > 185) {
			$pdf->AddPage();
			$drawHeader();
			$pdf->SetFont('helvetica', '', $rowFont);
		}

		$pdf->Cell($wPos, $rowHeight, (string)($row['position_text'] ?? $row['position'] ?? '-'), 1, 0, 'C');
		$pdf->Cell($wAdm, $rowHeight, merit_pdf_fit_text((string)($row['school_id'] !== '' ? $row['school_id'] : $row['student_id']), 10), 1, 0, 'C');
		$pdf->Cell($wName, $rowHeight, merit_pdf_fit_text((string)($row['student_name'] ?? ''), $compactMode ? 18 : 26), 1, 0, 'L');
		$pdf->Cell($wGender, $rowHeight, substr((string)($row['gender'] ?? ''), 0, 1), 1, 0, 'C');
		foreach ($subjects as $subject) {
			$subjectId = (int)($subject['subject'] ?? 0);
			$value = $row['subject_scores'][$subjectId] ?? null;
			$pdf->Cell($wPerSubject, $rowHeight, merit_pdf_score_text($value), 1, 0, 'C');
		}
		$pdf->Cell($wTotal, $rowHeight, number_format((float)($row['total_points'] ?? 0), 1), 1, 0, 'C');
		$pdf->Cell($wAvg, $rowHeight, number_format((float)($row['mean_points'] ?? 0), 1), 1, 0, 'C');
		$pdf->Cell($wGrade, $rowHeight, merit_pdf_fit_text((string)($row['grade'] ?? ''), 6), 1, 0, 'C');
		$pdf->Cell($wTrend, $rowHeight, merit_pdf_fit_text((string)($row['trend'] ?? ''), 8), 1, 0, 'C');
		$pdf->Cell($wRemark, $rowHeight, merit_pdf_fit_text((string)($row['remark'] ?? ''), $compactMode ? 18 : 28), 1, 1, 'L');
	}

	$pdf->Ln(4);
	$pdf->SetFont('helvetica', '', 8);
	$pdf->Cell(90, 7, 'Class Teacher: ____________________', 0, 0, 'L');
	$pdf->Cell(90, 7, 'Deputy Headteacher: ____________________', 0, 0, 'L');
	$pdf->Cell(90, 7, 'Headteacher: ____________________', 0, 1, 'L');

	$fileName = 'merit-list-' . preg_replace('/[^A-Za-z0-9\-]+/', '-', strtolower($className . '-' . $termName)) . '.pdf';
	$pdf->Output($fileName, 'I');
} catch (Throwable $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . " merit_list_pdf] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Unable to generate the merit list PDF right now."));
		header("location:merit_list?class_id=" . $classId . "&term_id=" . $termId . "&exam_id=" . $examId);
}
