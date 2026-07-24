<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/network_access.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$isSuperAdmin = !empty($super_admin);
$isLeadershipRole = in_array((int)($level ?? -1), [0, 1], true);
if ($res !== "1" || (!$isLeadershipRole && !$isSuperAdmin)) {
	http_response_code(403);
	echo json_encode([
		'error' => 'forbidden',
		'message' => 'Admin access is required.',
	]);
	exit;
}

$data = app_network_access_data();
$data['qr_image_url'] = 'qr_image.php?size=180&data=' . rawurlencode((string)$data['url']) . '&v=' . rawurlencode((string)$data['generated_at']);

echo json_encode($data);
