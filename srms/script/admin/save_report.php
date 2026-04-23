<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/pdf_branding.php');
require_once('tcpdf/tcpdf.php');

if ($res !== "1" || $level !== "0") {
	header("location:../");
	exit;
}

$classId = 0;
$termId = 0;
$examId = 0;
if (isset($_SESSION['bulk_result_2'])) {
	$classId = (int)($_SESSION['bulk_result_2']['student'] ?? 0);
	$termId = (int)($_SESSION['bulk_result_2']['term'] ?? 0);
	$examId = (int)($_SESSION['bulk_result_2']['exam'] ?? 0);
} else {
	$classId = (int)($_GET['class'] ?? 0);
	$termId = (int)($_GET['term'] ?? 0);
	$examId = (int)($_GET['exam'] ?? 0);
}

if ($classId < 1 || $termId < 1 || $examId < 1) {
	header("location:./");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$classId]);
	$className = (string)$stmt->fetchColumn();
	if ($className === '') {
		throw new RuntimeException('Class not found.');
	}

	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();
	if ($termName === '') {
		throw new RuntimeException('Term not found.');
	}

	$examName = '';
	if (app_table_exists($conn, 'tbl_exams')) {
		$stmt = $conn->prepare("SELECT name FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([$examId, $classId, $termId]);
		$examName = (string)$stmt->fetchColumn();
	}
	if ($examName === '') {
		throw new RuntimeException('Selected exam is not valid for the selected class and term.');
	}

	$summaryRows = [];
	$totalStudents = 0;
	if (app_column_exists($conn, 'tbl_exam_results', 'exam_id')) {
		$stmt = $conn->prepare("SELECT er.student, st.school_id, st.fname, st.mname, st.lname,
				AVG(er.score) AS mean_score, SUM(er.score) AS total_score
			FROM tbl_exam_results er
			JOIN tbl_students st ON st.id = er.student
			WHERE er.class = ? AND er.term = ? AND er.exam_id = ?
			GROUP BY er.student, st.school_id, st.fname, st.mname, st.lname
			ORDER BY mean_score DESC, total_score DESC, st.fname ASC, st.lname ASC");
		$stmt->execute([$classId, $termId, $examId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$totalStudents = count($rows);
		if ($totalStudents < 1) {
			throw new RuntimeException('No learners have saved scores for the selected exam.');
		}

		$stmt = $conn->prepare("SELECT name, min, max FROM tbl_grade_system ORDER BY min DESC");
		$stmt->execute();
		$grading = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$position = 1;
		foreach ($rows as $row) {
			$grade = 'N/A';
			$mean = (float)($row['mean_score'] ?? 0);
			foreach ($grading as $gradeRule) {
				if ($mean >= (float)$gradeRule['min'] && $mean <= (float)$gradeRule['max']) {
					$grade = (string)$gradeRule['name'];
					break;
				}
			}
			$summaryRows[] = [
				'school_id' => (string)($row['school_id'] ?? (string)$row['student']),
				'student_name' => trim((string)($row['fname'] ?? '') . ' ' . (string)($row['mname'] ?? '') . ' ' . (string)($row['lname'] ?? '')),
				'position' => $position,
				'total_students' => $totalStudents,
				'total' => (float)($row['total_score'] ?? 0),
				'mean' => $mean,
				'grade' => $grade,
				'trend' => '-',
			];
			$position++;
		}
	} else {
		$merit = report_class_merit_list($conn, $classId, $termId, isset($account_id) ? (int)$account_id : null);
		if (empty($merit['rows'])) {
			throw new RuntimeException('No report-ready learners were found for this class and term.');
		}
		$summaryRows = $merit['rows'];
		$totalStudents = (int)$merit['total_students'];
	}

	$gradeDistribution = [];
	$meanSum = 0.0;
	foreach ($summaryRows as $row) {
		$grade = strtoupper(trim((string)($row['grade'] ?? 'N/A')));
		$gradeDistribution[$grade] = (int)($gradeDistribution[$grade] ?? 0) + 1;
		$meanSum += (float)($row['mean'] ?? 0);
	}
	$classMean = round($meanSum / max(1, count($summaryRows)), 2);

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator(PDF_CREATOR);
	$pdf->SetAuthor(WBName);
	$pdf->SetTitle($className . ' - ' . $termName . ' - ' . $examName . ' Performance Summary');
	$pdf->SetSubject('Class performance summary');
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(12, 12, 12);
	$pdf->SetAutoPageBreak(true, 15);
	$pdf->AddPage();
	app_pdf_draw_document_watermark($pdf, $className . ' ' . $termName, defined('WBName') ? (string)WBName : 'School');

	$headerHtml = app_pdf_brand_header_html($conn, 'CLASS PERFORMANCE SUMMARY', 'Official class performance summary for school record and review', 54)
		. '<div style="font-size:11px;margin-top:4px;">' . htmlspecialchars($className) . ' · ' . htmlspecialchars($termName) . ' · ' . htmlspecialchars($examName) . '</div>';
	$pdf->writeHTML($headerHtml, true, false, true, false, '');

	$distHtml = '<table border="1" cellpadding="5" cellspacing="0">
		<tr style="background-color:#f3f7fb;">
			<td width="40%"><b>Total Learners</b></td>
			<td width="60%">' . (int)$totalStudents . '</td>
		</tr>
		<tr>
			<td><b>Class Mean</b></td>
			<td>' . number_format($classMean, 2) . '%</td>
		</tr>
	</table><br>';
	$pdf->writeHTML($distHtml, true, false, true, false, '');

	if (!empty($gradeDistribution)) {
		$rows = '';
		ksort($gradeDistribution);
		foreach ($gradeDistribution as $grade => $count) {
			$rows .= '<tr><td>' . htmlspecialchars($grade) . '</td><td>' . (int)$count . '</td></tr>';
		}
		$pdf->writeHTML('<h4>Grade Distribution</h4><table border="1" cellpadding="4"><tr style="background-color:#f3f7fb;"><td><b>Grade</b></td><td><b>Learners</b></td></tr>' . $rows . '</table><br>', true, false, true, false, '');
	}

	$tableRows = '';
	foreach ($summaryRows as $row) {
		$tableRows .= '<tr>
			<td>' . htmlspecialchars((string)$row['school_id']) . '</td>
			<td>' . htmlspecialchars((string)$row['student_name']) . '</td>
			<td>' . (int)($row['position'] ?? 0) . '/' . (int)($row['total_students'] ?? 0) . '</td>
			<td>' . number_format((float)($row['total'] ?? 0), 2) . '</td>
			<td>' . number_format((float)($row['mean'] ?? 0), 2) . '</td>
			<td>' . htmlspecialchars((string)($row['grade'] ?? '')) . '</td>
			<td>' . htmlspecialchars((string)($row['trend'] ?? '')) . '</td>
		</tr>';
	}

	$pdf->writeHTML('<h4>Learner Summary</h4><table border="1" cellpadding="4" cellspacing="0">
		<tr style="background-color:#f3f7fb;">
			<td width="14%"><b>Adm/ID</b></td>
			<td width="28%"><b>Learner</b></td>
			<td width="12%"><b>Position</b></td>
			<td width="14%"><b>Total</b></td>
			<td width="12%"><b>Mean</b></td>
			<td width="8%"><b>Grade</b></td>
			<td width="12%"><b>Trend</b></td>
		</tr>' . $tableRows . '</table>', true, false, true, false, '');

	$pdf->Output($className . '-' . $termName . '-' . $examName . '-performance-summary.pdf', 'I');
} catch (Throwable $e) {
	error_log('[admin/save_report] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to generate summary report: ' . $e->getMessage()));
	header('location:report');
	exit;
}
