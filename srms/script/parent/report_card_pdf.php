<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('tcpdf/tcpdf.php');

if ($res !== "1" || $level !== "4") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
if ($termId < 1 || $studentId === '') { header("location:report_card"); exit; }

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT 1 FROM tbl_parent_students WHERE parent_id = ? AND student_id = ? LIMIT 1");
	$stmt->execute([$account_id, $studentId]);
	if (!$stmt->fetchColumn()) {
		header("location:report_card");
		exit;
	}

	$stmt = $conn->prepare("SELECT class, fname, lname FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$studentId]);
	$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$studentRow) {
		header("location:report_card");
		exit;
	}
	$classId = (int)$studentRow['class'];
	$studentName = trim(($studentRow['fname'] ?? '') . ' ' . ($studentRow['lname'] ?? ''));

	if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, $classId, $termId)) {
		header("location:report_card?term=" . $termId . "&student=" . $studentId);
		exit;
	}

	$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
	$stmt->execute([$studentId, $termId]);
	$reportId = (int)$stmt->fetchColumn();
	if ($reportId < 1) {
		header("location:report_card?term=" . $termId . "&student=" . $studentId);
		exit;
	}

	$card = report_load_card($conn, $reportId);
	$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
	$feesBalance = report_fees_balance($conn, $studentId, $termId);
	$settings = report_get_settings($conn);
	if ((int)$settings['require_fees_clear'] === 1 && $feesBalance > 0) {
		header("location:report_card?term=" . $termId . "&student=" . $studentId);
		exit;
	}

	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();

	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$classId]);
	$className = (string)$stmt->fetchColumn();

	$verifyUrl = APP_URL !== '' ? APP_URL . '/verify_report?code=' . $card['verification_code'] : ("http://" . ($_SERVER['HTTP_HOST'] ?? '') . "/verify_report?code=" . $card['verification_code']);

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetTitle("Report Card");
	$pdf->AddPage();
	$pdf->SetFont('helvetica', '', 10);

	$pdf->SetAlpha(0.08);
	$pdf->SetFont('helvetica', 'B', 50);
	$pdf->Rotate(20, 60, 190);
	$pdf->Text(10, 120, 'OFFICIAL');
	$pdf->Rotate(0);
	$pdf->SetAlpha(1);
	$pdf->SetFont('helvetica', '', 10);

	$logoPath = 'images/logo/' . WBLogo;
	$logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="60" />' : '';

	$html = '
	<table width="100%" cellpadding="4">
	<tr>
		<td width="20%">' . $logoHtml . '</td>
		<td width="80%">
			<h2 style="margin:0;">' . WBName . '</h2>
			<span>' . WBAddress . '</span><br>
			<span>' . WBEmail . '</span>
		</td>
	</tr>
	</table>
	<hr>
	<table width="100%" cellpadding="4">
	<tr>
		<td><strong>Student:</strong> ' . $studentName . '</td>
		<td><strong>Admission No:</strong> ' . $studentId . '</td>
	</tr>
	<tr>
		<td><strong>Class:</strong> ' . $className . '</td>
		<td><strong>Term:</strong> ' . $termName . '</td>
	</tr>
	</table>
	<br>
	<table width="100%" border="1" cellpadding="4">
	<tr style="background-color:#f2f7f6;">
		<th width="40%">Subject</th>
		<th width="15%">Score</th>
		<th width="15%">Grade</th>
		<th width="30%">Teacher</th>
	</tr>';

	foreach ($card['subjects'] as $subject) {
		$html .= '<tr>
			<td>' . $subject['subject_name'] . '</td>
			<td>' . $subject['score'] . '</td>
			<td>' . $subject['grade'] . '</td>
			<td>' . $subject['teacher_name'] . '</td>
		</tr>';
	}

	$html .= '</table>
	<br>
	<table width="100%" cellpadding="4">
	<tr>
		<td><strong>Total Marks:</strong> ' . $card['total'] . '</td>
		<td><strong>Mean Score:</strong> ' . $card['mean'] . '%</td>
		<td><strong>Grade:</strong> ' . $card['grade'] . '</td>
	</tr>
	<tr>
		<td><strong>Position:</strong> ' . $card['position'] . ' / ' . $card['total_students'] . '</td>
		<td><strong>Trend:</strong> ' . $card['trend'] . '</td>
		<td><strong>Fees Balance:</strong> KES ' . number_format($feesBalance, 0) . '</td>
	</tr>
	</table>
	<br>
	<table width="100%" cellpadding="4">
	<tr>
		<td><strong>Attendance:</strong> ' . $attendance['present'] . ' / ' . $attendance['days_open'] . '</td>
		<td><strong>Remarks:</strong> ' . $card['remark'] . '</td>
	</tr>
	</table>
	<br>
	<table width="100%" cellpadding="4">
	<tr>
		<td><strong>Verification Code:</strong> ' . $card['verification_code'] . '</td>
		<td><strong>Generated:</strong> ' . date('F d, Y H:i') . '</td>
	</tr>
	</table>
	';

	$pdf->writeHTML($html, true, false, true, false, '');
	$pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 160, 235, 35, 35);
	$pdf->SetFont('helvetica', '', 8);
	$pdf->Text(20, 270, 'Verify at: ' . $verifyUrl);

	$stmt = $conn->prepare("UPDATE tbl_report_cards SET downloads = downloads + 1 WHERE id = ?");
	$stmt->execute([$reportId]);

	$pdf->Output('report-card.pdf', 'I');
} catch (Throwable $e) {
	header("location:report_card?term=" . $termId . "&student=" . $studentId);
}
