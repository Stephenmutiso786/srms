<?php

require_once(__DIR__ . '/school.php');

function app_pdf_branding_info(?PDO $conn = null): array
{
	$schoolName = defined('WBName') && trim((string)WBName) !== '' ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
	$schoolLogo = defined('WBLogo') && trim((string)WBLogo) !== '' ? (string)WBLogo : 'school_logo1711003619.png';
	$schoolMotto = defined('WBMotto') ? (string)WBMotto : '';
	$schoolAddress = defined('WBAddress') ? (string)WBAddress : '';
	$schoolEmail = defined('WBEmail') ? (string)WBEmail : '';
	$schoolPhone = defined('WBPhone') ? (string)WBPhone : '';

	if ($conn instanceof PDO) {
		try {
			$schoolMotto = trim((string)app_setting_get($conn, 'public_school_motto', $schoolMotto));
		} catch (Throwable $e) {
		}
		try {
			$schoolPhone = trim((string)app_setting_get($conn, 'public_school_phone', $schoolPhone));
		} catch (Throwable $e) {
		}
		try {
			$schoolAddress = trim((string)app_setting_get($conn, 'school_address', $schoolAddress));
		} catch (Throwable $e) {
		}
		try {
			$schoolEmail = trim((string)app_setting_get($conn, 'school_email', $schoolEmail));
		} catch (Throwable $e) {
		}
	}

	return [
		'name' => $schoolName,
		'logo' => $schoolLogo,
		'motto' => $schoolMotto,
		'address' => $schoolAddress,
		'email' => $schoolEmail,
		'phone' => $schoolPhone,
	];
}

function app_pdf_brand_header_html(?PDO $conn, string $documentTitle, string $documentPurpose, int $logoWidth = 56): string
{
	$brand = app_pdf_branding_info($conn);
	$logoPath = 'images/logo/' . $brand['logo'];
	$logoHtml = app_pdf_image_html($logoPath, $logoWidth, 0, $brand['name']);
	$contacts = array_filter([
		$brand['address'] !== '' ? $brand['address'] : '',
		$brand['phone'] !== '' ? 'Phone: ' . $brand['phone'] : '',
		$brand['email'] !== '' ? 'Email: ' . $brand['email'] : '',
	]);

	return '<table width="100%" cellpadding="4" cellspacing="0" style="margin-bottom:4px;">
		<tr>
			<td width="18%">' . $logoHtml . '</td>
			<td width="82%" style="text-align:right;">
				<div style="font-size:14pt;font-weight:bold;">' . htmlspecialchars($brand['name']) . '</div>
				<div style="font-size:9.5pt;font-weight:bold;">' . htmlspecialchars($documentTitle) . '</div>
				<div style="font-size:8.8pt;color:#445;">' . htmlspecialchars($documentPurpose) . '</div>
				' . ($brand['motto'] !== '' ? '<div style="font-size:8.6pt;font-style:italic;color:#667;">Motto: ' . htmlspecialchars($brand['motto']) . '</div>' : '') . '
				' . (!empty($contacts) ? '<div style="font-size:8.4pt;color:#667;">' . htmlspecialchars(implode(' | ', $contacts)) . '</div>' : '') . '
			</td>
		</tr>
	</table>';
}

function app_pdf_document_watermark_text(string $studentName, string $schoolName): string
{
	$parts = [];
	$schoolName = trim($schoolName);
	$studentName = trim($studentName);
	if ($schoolName !== '') {
		$parts[] = $schoolName;
	}
	if ($studentName !== '') {
		$parts[] = $studentName;
	}
	$parts[] = 'ORIGINAL DOCUMENT';
	return implode(' | ', $parts);
}

function app_pdf_draw_document_watermark(TCPDF $pdf, string $studentName, string $schoolName): void
{
	$text = app_pdf_document_watermark_text($studentName, $schoolName);
	if ($text === '') {
		return;
	}

	$pdf->StartTransform();
	$pdf->SetAlpha(0.08);
	$pdf->SetTextColor(120, 120, 120);
	$pdf->SetFont('helvetica', 'B', 28);
	$pdf->Rotate(32, 105, 155);
	$pdf->Text(18, 150, $text);
	$pdf->Rotate(-32, 105, 155);
	$pdf->SetFont('helvetica', 'B', 20);
	$pdf->Text(26, 215, $text);
	$pdf->SetAlpha(1);
	$pdf->SetTextColor(0, 0, 0);
	$pdf->StopTransform();
}
