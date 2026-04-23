<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('tcpdf/tcpdf.php');
require_once('const/calculations.php');
require_once('const/pdf_branding.php');

if ($res == '1' && $level == '1') {} else { header('location:../'); exit; }

if (!isset($_SESSION['bulk_result_2'])) {
	header('location:./');
	exit;
}

$class = trim((string)($_SESSION['bulk_result_2']['student'] ?? ''));
$term = (int)($_SESSION['bulk_result_2']['term'] ?? 0);
$examId = (int)($_SESSION['bulk_result_2']['exam'] ?? 0);
if ($class === '' || $term < 1 || $examId < 1) {
	$_SESSION['reply'] = array(array('danger', 'Please select class, term, and exam.'));
	header('location:report');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare('SELECT * FROM tbl_grade_system');
	$stmt->execute();
	$grading = $stmt->fetchAll();

	$_MATOKEO = [];
	foreach ($divisions as $value) {
		$_MATOKEO[$value[0]] = ['BOYS' => 0, 'GIRLS' => 0];
	}

	$stmt = $conn->prepare('SELECT * FROM tbl_students WHERE class = ?');
	$stmt->execute([$class]);
	$std_data = $stmt->fetchAll();
	if (!$std_data) {
		$_SESSION['reply'] = array(array('danger', 'No students found in the selected class.'));
		header('location:report');
		exit;
	}

	$stmt = $conn->prepare('SELECT * FROM tbl_terms WHERE id = ? LIMIT 1');
	$stmt->execute([$term]);
	$term_row = $stmt->fetch();
	if (!$term_row) {
		$_SESSION['reply'] = array(array('danger', 'Selected term was not found.'));
		header('location:report');
		exit;
	}

	$stmt = $conn->prepare('SELECT * FROM tbl_classes WHERE id = ? LIMIT 1');
	$stmt->execute([$class]);
	$class_row = $stmt->fetch();
	if (!$class_row) {
		$_SESSION['reply'] = array(array('danger', 'Selected class was not found.'));
		header('location:report');
		exit;
	}

	$examName = '';
	$examOptions = report_term_exam_options($conn, (int)$class, $term);
	foreach ($examOptions as $option) {
		if ((int)$option['id'] === $examId) {
			$examName = (string)$option['name'];
			break;
		}
	}
	if ($examName === '') {
		$_SESSION['reply'] = array(array('danger', 'Selected exam is not published for the selected class and term.'));
		header('location:report');
		exit;
	}

	$title = (string)$class_row[1] . ' (' . (string)$term_row[1] . ' - ' . $examName . ' Performance Report)';
	$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

	$stmt = $conn->prepare('SELECT * FROM tbl_subject_combinations LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id');
	$stmt->execute();
	$result = $stmt->fetchAll();

	foreach ($std_data as $row2) {
		$tscore = 0;
		$t_subjects = 0;
		$subssss = [];
		$gnd = (string)($row2[4] ?? '');

		foreach ($result as $row) {
			$class_list = app_unserialize($row[1]);
			if (in_array($class, $class_list, true)) {
				$t_subjects++;
				$score = 0;
				if ($useExamId) {
					$stmt = $conn->prepare('SELECT * FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? AND exam_id = ?');
					$stmt->execute([$class, $row[0], $term, $row2[0], $examId]);
				} else {
					$stmt = $conn->prepare('SELECT * FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ?');
					$stmt->execute([$class, $row[0], $term, $row2[0]]);
				}
				$ex_result = $stmt->fetchAll();
				if (!empty($ex_result[0][5])) {
					$score = (float)$ex_result[0][5];
					$tscore += $score;
				}
				$subssss[] = $score;
			}
		}

		$av = $t_subjects === 0 ? 0 : round($tscore / $t_subjects);
		foreach ($grading as $grade) {
			if ($av >= $grade[2] && $av <= $grade[3]) {
				break;
			}
		}

		$div = get_division($subssss);
		if (!isset($_MATOKEO[$div])) {
			$_MATOKEO[$div] = ['BOYS' => 0, 'GIRLS' => 0];
		}

		if ($gnd === 'Male') {
			$_MATOKEO[$div]['BOYS'] = $_MATOKEO[$div]['BOYS'] + 1;
		} else {
			$_MATOKEO[$div]['GIRLS'] = $_MATOKEO[$div]['GIRLS'] + 1;
		}
	}

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator(PDF_CREATOR);
	$pdf->SetAuthor(WBName);
	$pdf->SetTitle($title);
	$pdf->SetSubject($title);
	$pdf->SetKeywords(APP_NAME, WBName);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
	$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
	$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
	$pdf->setFontSubsetting(true);
	$pdf->SetFont('helvetica', '', 14, '', true);
	$pdf->AddPage();
	$pdf->setTextShadow(array('enabled' => true, 'depth_w' => 0.2, 'depth_h' => 0.2, 'color' => array(196, 196, 196), 'opacity' => 1, 'blend_mode' => 'Normal'));

	$brandingHeader = app_pdf_brand_header_html($conn, 'CLASS PERFORMANCE REPORT', 'Official class performance summary for term review and record keeping', 60);
	$html = $brandingHeader
		. '<table width="100%" cellpadding="0" cellspacing="0">'
		. '<tr><td style="text-align:center;"><h5><b style="font-size:18px;">' . WBName . '</b><br>Student Performance Report<br>'
		. htmlspecialchars((string)$class_row[1]) . '<br>'
		. htmlspecialchars((string)$term_row[1]) . '</h5></td></tr>'
		. '</table>';
	$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 1, 0, true, '', true);
	$pdf->SetFont('helvetica', '', 10, '', true);
	$pdf->Cell(0, 0, '', 0, 1, 'C');

	$htmls = '<table border="1" cellpadding="5"><tr><td>DIVISION</td><td>BOYS</td><td>GIRLS</td><td>Total</td></tr>';
	foreach ($divisions as $value) {
		$key = $value[0];
		$boys = (int)($_MATOKEO[$key]['BOYS'] ?? 0);
		$girls = (int)($_MATOKEO[$key]['GIRLS'] ?? 0);
		$htmls .= '<tr><td>' . htmlspecialchars((string)$key) . '</td><td>' . $boys . '</td><td>' . $girls . '</td><td>' . ($boys + $girls) . '</td></tr>';
	}
	$htmls .= '</table>';
	$pdf->writeHTMLCell(0, 0, '', '', $htmls, 0, 1, 0, true, '', true);

	$html2 = '<br><br><b>Date : ' . date('F d, Y G:i:s A') . '</b>';
	$pdf->writeHTMLCell(0, 0, '', '', $html2, 0, 1, 0, true, '', true);
	$pdf->IncludeJS('print(true);');

	if (ob_get_length()) {
		ob_end_clean();
	}
	$pdf->Output($title . '.pdf', 'I');
} catch (Throwable $e) {
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to generate class performance report.'));
	header('location:report');
	exit;
}
