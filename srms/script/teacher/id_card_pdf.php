<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/id_card_engine.php');
require_once('tcpdf/tcpdf.php');

if ($res !== "1" || $level !== "2") { header("location:../"); }

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$payload = idcard_staff_payload($conn, (string)$account_id);
	$school = idcard_school_meta($conn);
	if (!$payload) {
		header("location:id_card");
		exit;
	}

	$pdf = new TCPDF('L', 'mm', [54, 86], true, 'UTF-8', false);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(0, 0, 0);
	$pdf->AddPage();
	$pdf->SetAutoPageBreak(false, 0);

	$pdf->RoundedRect(1.5, 1.5, 83, 51, 4, '1111', 'FD', ['all' => ['width' => 0, 'color' => [215,225,234]]], [248,251,255]);
	$pdf->SetFillColor(6, 95, 70);
	$pdf->RoundedRect(1.5, 1.5, 83, 13, 4, '1100', 'F');

	$logoPath = 'images/logo/' . ($school['logo'] ?? '');
	if (!empty($school['logo']) && file_exists($logoPath)) {
		$pdf->Image($logoPath, 4, 3.2, 8.5, 8.5);
	}

	$pdf->SetTextColor(255, 255, 255);
	$pdf->SetFont('helvetica', 'B', 10);
	$pdf->Text(14, 4.2, strtoupper((string)$school['name']));
	$pdf->SetFont('helvetica', '', 7.5);
	$pdf->Text(14, 9.1, 'Official Staff Identification');
	$pdf->SetFont('helvetica', 'B', 12);
	$pdf->Text(64, 5.2, 'STAFF ID');

	$pdf->SetFillColor(6, 95, 70);
	$pdf->RoundedRect(5, 17, 21, 24, 2, '1111', 'F');
	$pdf->SetTextColor(255,255,255);
	$pdf->SetFont('helvetica', 'B', 16);
	$pdf->Text(10.5, 27, $payload['initials']);

	$pdf->SetTextColor(17, 48, 74);
	$pdf->SetFont('helvetica', 'B', 10);
	$pdf->Text(29, 18, $payload['name']);
	$pdf->SetFont('helvetica', '', 7.5);
	$pdf->Text(29, 23, $payload['subtitle']);
	$pdf->SetFont('helvetica', '', 7.2);
	$pdf->Text(29, 28.5, 'Staff ID');
	$pdf->SetFont('helvetica', 'B', 9);
	$pdf->Text(29, 32.5, $payload['school_id']);
	$pdf->SetFont('helvetica', '', 7.2);
	$pdf->Text(29, 37, 'Role');
	$pdf->SetFont('helvetica', 'B', 8.8);
	$pdf->Text(29, 40.8, $payload['class_name']);
	$pdf->SetFont('helvetica', '', 7.2);
	$pdf->Text(29, 45.2, 'Portal');
	$pdf->SetFont('helvetica', 'B', 8.3);
	$pdf->Text(29, 48.7, APP_NAME);

	$verifyUrl = idcard_verify_url($payload['school_id']);
	$pdf->write2DBarcode($verifyUrl, 'QRCODE,H', 68.5, 34.5, 12.5, 12.5);
	$pdf->SetFont('helvetica', 'B', 7.5);
	$pdf->Text(5, 45.2, 'Issued ' . date('Y'));
	$pdf->SetFont('helvetica', '', 6.8);
	$pdf->Text(5, 48.8, 'Verify via school portal');

	$pdf->Output('staff-id-card.pdf', 'I');
} catch (Throwable $e) {
	header("location:id_card");
}
