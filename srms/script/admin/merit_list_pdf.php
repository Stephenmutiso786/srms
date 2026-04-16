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

$classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$termId = isset($_GET['term_id']) ? (int)$_GET['term_id'] : 0;
if ($classId < 1 || $termId < 1) {
	header("location:merit_list");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$list = report_class_merit_list($conn, $classId, $termId, (int)$account_id);
	$rows = $list['rows'];

	$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$classId]);
	$className = (string)$stmt->fetchColumn();
	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();

	$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(10, 10, 10);
	$pdf->AddPage();
	$pdf->SetFont('helvetica', '', 10);

	$brandingHeader = app_pdf_brand_header_html($conn, 'MERIT LIST', 'Official ranked merit report for class and term review', 40);
	$html = $brandingHeader . '<div style="font-size:10pt;margin:6px 0 8px 0;"><strong>Merit List:</strong> ' . htmlspecialchars($className) . ' - ' . htmlspecialchars($termName) . '</div>';
	$html .= '<table width="100%" border="1" cellpadding="4"><thead><tr style="background-color:#f3f8f7;"><th width="10%">Rank</th><th width="18%">School ID</th><th width="32%">Student</th><th width="12%">Total</th><th width="12%">Mean</th><th width="8%">Grade</th><th width="8%">Trend</th></tr></thead><tbody>';
	foreach ($rows as $row) {
		$html .= '<tr><td>' . (int)$row['position'] . '</td><td>' . htmlspecialchars((string)($row['school_id'] !== '' ? $row['school_id'] : $row['student_id'])) . '</td><td>' . htmlspecialchars((string)$row['student_name']) . '</td><td>' . number_format((float)$row['total'], 2) . '</td><td>' . number_format((float)$row['mean'], 2) . '%</td><td>' . htmlspecialchars((string)$row['grade']) . '</td><td>' . htmlspecialchars((string)$row['trend']) . '</td></tr>';
	}
	$html .= '</tbody></table>';

	$pdf->writeHTML($html, true, false, true, false, '');
	$pdf->Output('merit-list.pdf', 'I');
} catch (Throwable $e) {
	header("location:merit_list?class_id=" . $classId . "&term_id=" . $termId);
}