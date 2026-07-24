<?php

chdir(__DIR__);
if (ob_get_level() === 0) {
	ob_start();
}

require_once('tcpdf/tcpdf_barcodes_2d.php');

$value = trim((string)($_GET['data'] ?? ''));
$size = isset($_GET['size']) ? (int)$_GET['size'] : 92;
$size = max(48, min(320, $size));

if ($value === '') {
	http_response_code(400);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Missing QR data.';
	exit;
}

try {
	$moduleSize = max(2, min(8, (int)floor($size / 28)));
	$barcode = new TCPDF2DBarcode($value, 'QRCODE,H');
	$svg = $barcode->getBarcodeSVGcode($moduleSize, $moduleSize, '#0f172a');
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	header('Content-Type: image/svg+xml; charset=utf-8');
	header('Cache-Control: public, max-age=300');
	echo $svg;
	exit;
} catch (Throwable $e) {
	while (ob_get_level() > 0) {
		ob_end_clean();
	}
	http_response_code(500);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Unable to generate QR image.';
	exit;
}
