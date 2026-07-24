<?php

require_once(__DIR__ . '/school.php');

if (!function_exists('app_pdf_supports_alpha_images')) {
	function app_pdf_supports_alpha_images(): bool
	{
		return extension_loaded('gd') || extension_loaded('imagick');
	}
}

if (!function_exists('app_pdf_image_path_is_safe')) {
	function app_pdf_image_path_is_safe(string $path): bool
	{
		$path = trim($path);
		if ($path === '' || !is_file($path)) {
			return false;
		}

		$extension = strtolower((string)pathinfo($path, PATHINFO_EXTENSION));
		if (in_array($extension, ['png', 'webp', 'gif'], true) && !app_pdf_supports_alpha_images()) {
			return false;
		}

		return true;
	}
}

function app_pdf_branding_info(?PDO $conn = null): array
{
	$schoolName = defined('WBName') && trim((string)WBName) !== '' ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
	$schoolLogo = defined('WBLogo') && trim((string)WBLogo) !== '' ? (string)WBLogo : 'school_logo.png';
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

function app_pdf_brand_asset_path(string $storedValue, string $subDir): string
{
	$storedValue = trim($storedValue);
	if ($storedValue === '') {
		return '';
	}
	$path = $storedValue;
	if (strpos($path, '/') === false) {
		$path = 'images/' . trim($subDir, '/') . '/' . $path;
	}
	if (!app_pdf_image_path_is_safe($path) || !is_file($path)) {
		return '';
	}
	return $path;
}

function app_pdf_brand_headteacher_meta(): array
{
	$title = trim((string)(defined('WBHeadteacherTitle') ? WBHeadteacherTitle : 'Headteacher'));
	if ($title === '') {
		$title = 'Headteacher';
	}
	return [
		'name' => trim((string)(defined('WBHeadteacherName') ? WBHeadteacherName : '')),
		'title' => $title,
		'signature_path' => app_pdf_brand_asset_path((string)(defined('WBHeadteacherSignature') ? WBHeadteacherSignature : ''), 'signatures'),
		'stamp_path' => app_pdf_brand_asset_path((string)(defined('WBSchoolStamp') ? WBSchoolStamp : ''), 'stamps'),
	];
}

function app_pdf_draw_official_footer(TCPDF $pdf, array $options = []): void
{
	$meta = app_pdf_brand_headteacher_meta();
	$leftX = isset($options['left_x']) ? (float)$options['left_x'] : 12.0;
	$baseY = isset($options['base_y']) ? (float)$options['base_y'] : ((float)$pdf->getPageHeight() - 28.0);
	$lineWidth = isset($options['line_width']) ? (float)$options['line_width'] : 52.0;
	$signatureWidth = isset($options['signature_width']) ? (float)$options['signature_width'] : 28.0;
	$signatureHeight = isset($options['signature_height']) ? (float)$options['signature_height'] : 12.0;
	$stampX = isset($options['stamp_x']) ? (float)$options['stamp_x'] : ((float)$pdf->getPageWidth() - 36.0);
	$stampY = isset($options['stamp_y']) ? (float)$options['stamp_y'] : ($baseY - 9.0);
	$stampSize = isset($options['stamp_size']) ? (float)$options['stamp_size'] : 20.0;
	$dateLabel = trim((string)($options['date_label'] ?? 'Date'));
	$dateValue = trim((string)($options['date_value'] ?? ''));
	$titleOverride = trim((string)($options['title'] ?? ''));
	$drawDate = array_key_exists('show_date', $options) ? (bool)$options['show_date'] : true;
	$fontSize = isset($options['font_size']) ? (float)$options['font_size'] : 6.6;
	$label = $titleOverride !== '' ? $titleOverride : $meta['title'];

	if ($meta['signature_path'] !== '') {
		$pdf->Image($meta['signature_path'], $leftX, $baseY - 8.5, $signatureWidth, $signatureHeight, '', '', '', false, 300, '', false, false, 0, false, false, false);
	}

	$pdf->SetDrawColor(110, 120, 130);
	$pdf->Line($leftX, $baseY + 4.0, $leftX + $lineWidth, $baseY + 4.0);
	$pdf->SetFont('helvetica', 'B', $fontSize);
	$pdf->SetTextColor(70, 80, 90);
	$pdf->SetXY($leftX, $baseY + 4.8);
	$pdf->Cell($lineWidth, 3.0, $label, 0, 1, 'L');
	if ($meta['name'] !== '') {
		$pdf->SetFont('helvetica', '', max(5.8, $fontSize - 0.5));
		$pdf->SetX($leftX);
		$pdf->Cell($lineWidth, 2.8, $meta['name'], 0, 1, 'L');
	}

	if ($drawDate) {
		$dateX = isset($options['date_x']) ? (float)$options['date_x'] : ($leftX + $lineWidth + 10.0);
		$pdf->SetFont('helvetica', '', $fontSize);
		$pdf->SetXY($dateX, $baseY + 4.8);
		$pdf->Cell(36, 3.0, $dateLabel . ': ' . $dateValue, 0, 1, 'L');
	}

	if ($meta['stamp_path'] !== '') {
		$pdf->Image($meta['stamp_path'], $stampX, $stampY, $stampSize, $stampSize, '', '', '', false, 300, '', false, false, 0, false, false, false);
	} else {
		$pdf->SetDrawColor(180, 190, 200);
		$pdf->RoundedRect($stampX, $stampY, $stampSize, $stampSize, 2, '1111', 'D');
		$pdf->SetFont('helvetica', 'B', 5.8);
		$pdf->SetTextColor(110, 120, 130);
		$pdf->SetXY($stampX, $stampY + ($stampSize / 2) - 2.5);
		$pdf->Cell($stampSize, 3, 'STAMP', 0, 1, 'C');
	}
}
