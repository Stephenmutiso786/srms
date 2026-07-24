<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/network_access.php');
require_once('tcpdf/tcpdf_barcodes_2d.php');

$isSuperAdmin = !empty($super_admin);
$isLeadershipRole = in_array((int)($level ?? -1), [0, 1], true);
if ($res !== "1" || (!$isLeadershipRole && !$isSuperAdmin)) {
	http_response_code(403);
	header('Content-Type: text/plain; charset=utf-8');
	echo 'Forbidden';
	exit;
}

$network = app_network_access_data();
$barcode = new TCPDF2DBarcode((string)$network['url'], 'QRCODE,H');
$svg = $barcode->getBarcodeSVGcode(5, 5, '#0f172a');

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
echo $svg;
