<?php
session_start();
require_once(__DIR__ . '/_common.php');

api_apply_cors();

if (($res ?? '0') !== '1') {
	api_json(['ok' => false, 'authenticated' => false], 200);
}

$user = api_session_user();
api_json([
	'ok' => true,
	'authenticated' => true,
	'user' => $user,
	'school' => [
		'name' => WBName,
		'logo' => WBLogo,
		'portal' => $user['portal'],
	],
]);

