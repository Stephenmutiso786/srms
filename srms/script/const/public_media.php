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
	$data = app_public_login_background_data($conn);
	if (!empty($data['src'])) {
		return (string)$data['src'];
	}

	return '';
}

function app_public_login_background_data(PDO $conn): array
{
	$bgB64 = app_setting_get($conn, 'public_login_bg_b64', '');
	$bgExt = app_setting_get($conn, 'public_login_bg_ext', 'jpeg');
	$uri = app_public_media_data_uri($bgB64, $bgExt);
	if ($uri !== '') {
		return [
			'src' => $uri,
			'width' => (int)app_setting_get($conn, 'public_login_bg_width', 0),
			'height' => (int)app_setting_get($conn, 'public_login_bg_height', 0)
		];
	}

	$gallery = app_public_showcase_images($conn);
	if (!empty($gallery) && !empty($gallery[0]['src'])) {
		return [
			'src' => (string)$gallery[0]['src'],
			'width' => isset($gallery[0]['width']) ? (int)$gallery[0]['width'] : 0,
			'height' => isset($gallery[0]['height']) ? (int)$gallery[0]['height'] : 0
		];
	}

	return [
		'src' => '',
		'width' => 0,
		'height' => 0
	];
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
			'name' => isset($item['name']) ? trim((string)$item['name']) : '',
			'width' => isset($item['width']) ? (int)$item['width'] : 0,
			'height' => isset($item['height']) ? (int)$item['height'] : 0
		];
	}

	return $rows;
}
