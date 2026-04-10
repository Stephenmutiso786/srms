<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('tcpdf/tcpdf.php');

if ($res !== "1" || $level !== "2") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
if ($termId < 1 || $studentId === '') { header("location:manage_results"); exit; }

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$student = report_get_student_identity($conn, $studentId);
	if (!$student || !report_teacher_has_class_access($conn, (int)$account_id, (int)$student['class_id'], $termId)) {
		header("location:manage_results");
		exit;
	}
	if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, (int)$student['class_id'], $termId)) {
		header("location:report_card?term=" . $termId . "&student=" . urlencode($studentId));
		exit;
	}

	$card = report_ensure_card_generated($conn, $studentId, (int)$student['class_id'], $termId, (int)$account_id);
	if (!$card) {
		header("location:report_card?term=" . $termId . "&student=" . urlencode($studentId));
		exit;
	}

	$attendance = report_attendance_summary($conn, $studentId, (int)$student['class_id'], $termId);
	$feesBalance = report_fees_balance($conn, $studentId, $termId);

	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();

	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetTitle("Teacher Report Card");
	$pdf->AddPage();
	$pdf->SetFont('helvetica', '', 10);

	$logoPath = 'images/logo/' . WBLogo;
	$logoHtml = file_exists($logoPath) ? '<img src="' . $logoPath . '" width="60" />' : '';
	$html = '
	<table width="100%" cellpadding="4">
	<tr>
		<td width="20%">' . $logoHtml . '</td>
		<td width="80%">
			<h2 style="margin:0;">' . WBName . '</h2>
			<span>Teacher report access</span>
		</td>
	</tr>
	</table><hr>
	<table width="100%" cellpadding="4">
	<tr><td><strong>Student:</strong> ' . htmlspecialchars($student['name']) . '</td><td><strong>School ID:</strong> ' . htmlspecialchars($student['school_id'] ?: $student['id']) . '</td></tr>
	<tr><td><strong>Class:</strong> ' . htmlspecialchars($student['class_name']) . '</td><td><strong>Term:</strong> ' . htmlspecialchars($termName) . '</td></tr>
	</table><br>
	<table width="100%" border="1" cellpadding="4">
	<tr style="background-color:#f2f7f6;"><th width="40%">Subject</th><th width="15%">Score</th><th width="15%">Grade</th><th width="30%">Teacher</th></tr>';
	foreach ($card['subjects'] as $subject) {
		$html .= '<tr><td>' . htmlspecialchars($subject['subject_name']) . '</td><td>' . $subject['score'] . '</td><td>' . htmlspecialchars($subject['grade']) . '</td><td>' . htmlspecialchars($subject['teacher_name']) . '</td></tr>';
	}
	$html .= '</table><br>
	<table width="100%" cellpadding="4">
	<tr><td><strong>Total:</strong> ' . $card['total'] . '</td><td><strong>Mean:</strong> ' . $card['mean'] . '%</td><td><strong>Grade:</strong> ' . htmlspecialchars($card['grade']) . '</td></tr>
	<tr><td><strong>Position:</strong> ' . $card['position'] . ' / ' . $card['total_students'] . '</td><td><strong>Attendance:</strong> ' . $attendance['present'] . ' / ' . $attendance['days_open'] . '</td><td><strong>Fees Balance:</strong> KES ' . number_format($feesBalance, 0) . '</td></tr>
	</table><br>
	<p><strong>Remarks:</strong> ' . htmlspecialchars($card['remark']) . '</p>
	<p><strong>Verification Code:</strong> ' . htmlspecialchars($card['verification_code']) . '</p>';

	$pdf->writeHTML($html, true, false, true, false, '');
	$pdf->Output('teacher-student-report.pdf', 'I');
} catch (Throwable $e) {
	header("location:manage_results");
}
