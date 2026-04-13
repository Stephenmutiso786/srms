<?php

function app_public_media_data_uri(string $b64, string $ext): string
{
	$b64 = trim($b64);
	$ext = strtolower(trim($ext));
	if ($b64 === '') {
		return '';
	}
	if (!preg_match('/^[a-z0-9+\/\r\n=]+$/i', $b64)) {
		return '';
	}
	if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
		$ext = 'jpeg';
	}
	$mime = $ext === 'jpg' ? 'jpeg' : $ext;
	return 'data:image/' . $mime . ';base64,' . preg_replace('/\s+/', '', $b64);
}

function app_public_login_background(PDO $conn): string
{
	$bgB64 = app_setting_get($conn, 'public_login_bg_b64', '');
	$bgExt = app_setting_get($conn, 'public_login_bg_ext', 'jpeg');
	$uri = app_public_media_data_uri($bgB64, $bgExt);
	if ($uri !== '') {
		return $uri;
	}

	$gallery = app_public_showcase_images($conn);
	if (!empty($gallery) && !empty($gallery[0]['src'])) {
		return (string)$gallery[0]['src'];
	}

	return '';
}

function app_public_showcase_images(PDO $conn): array
{
	$raw = app_setting_get($conn, 'public_showcase_gallery_json', '[]');
	if ($raw === '') {
		return [];
	}

	$decoded = json_decode($raw, true);
	if (!is_array($decoded)) {
		return [];
	}

	$rows = [];
	foreach ($decoded as $item) {
		if (!is_array($item)) {
			continue;
		}
		$b64 = isset($item['b64']) ? (string)$item['b64'] : '';
		$ext = isset($item['ext']) ? (string)$item['ext'] : 'jpeg';
		$src = app_public_media_data_uri($b64, $ext);
		if ($src === '') {
			continue;
		}
		$rows[] = [
			'src' => $src,
			'caption' => isset($item['caption']) ? trim((string)$item['caption']) : '',
			'name' => isset($item['name']) ? trim((string)$item['name']) : ''
		];
	}

	return $rows;
}
