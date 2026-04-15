<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('tcpdf/tcpdf.php');

if ($res !== "1" || $level !== "0") {
	header("location:../");
	exit;
}

$classId = 0;
$termId = 0;
if (isset($_SESSION['bulk_result_2'])) {
	$classId = (int)($_SESSION['bulk_result_2']['student'] ?? 0);
	$termId = (int)($_SESSION['bulk_result_2']['term'] ?? 0);
} else {
	$classId = (int)($_GET['class'] ?? 0);
	$termId = (int)($_GET['term'] ?? 0);
}

if ($classId < 1 || $termId < 1) {
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

	$merit = report_class_merit_list($conn, $classId, $termId, isset($account_id) ? (int)$account_id : null);
	if (empty($merit['rows'])) {
		throw new RuntimeException('No report-ready learners were found for this class and term.');
	}

	$gradeDistribution = [];
	$meanSum = 0.0;
	foreach ($merit['rows'] as $row) {
		$grade = strtoupper(trim((string)($row['grade'] ?? 'N/A')));
		$gradeDistribution[$grade] = (int)($gradeDistribution[$grade] ?? 0) + 1;
		$meanSum += (float)($row['mean'] ?? 0);
	}
	$classMean = round($meanSum / max(1, count($merit['rows'])), 2);

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator(PDF_CREATOR);
	$pdf->SetAuthor(WBName);
	$pdf->SetTitle($className . ' - ' . $termName . ' Performance Summary');
	$pdf->SetSubject('Class performance summary');
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(12, 12, 12);
	$pdf->SetAutoPageBreak(true, 15);
	$pdf->AddPage();

	$logoHtml = app_pdf_image_html('images/logo/' . WBLogo, 54, 0, WBName);
	$headerHtml = '
	<table width="100%" cellpadding="3">
		<tr>
			<td width="15%">' . $logoHtml . '</td>
			<td width="85%">
				<div style="font-size:18px;font-weight:bold;">' . htmlspecialchars(WBName) . '</div>
				<div style="font-size:13px;">Class Performance Summary</div>
				<div style="font-size:11px;">' . htmlspecialchars($className) . ' · ' . htmlspecialchars($termName) . '</div>
			</td>
		</tr>
	</table>';
	$pdf->writeHTML($headerHtml, true, false, true, false, '');

	$distHtml = '<table border="1" cellpadding="5" cellspacing="0">
		<tr style="background-color:#f3f7fb;">
			<td width="40%"><b>Total Learners</b></td>
			<td width="60%">' . (int)$merit['total_students'] . '</td>
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
	foreach ($merit['rows'] as $row) {
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

	$pdf->Output($className . '-' . $termName . '-performance-summary.pdf', 'I');
} catch (Throwable $e) {
	error_log('[admin/save_report] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Failed to generate summary report: ' . $e->getMessage()));
	header('location:report');
	exit;
}
