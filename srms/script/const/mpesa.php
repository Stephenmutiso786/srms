<?php
require_once(__DIR__ . '/../db/config.php');

function mpesa_config(PDO $conn): array
{
	// Prefer environment variables (Render), fallback to DB table.
	$cfg = [
		'enabled' => (int)(getenv('MPESA_ENABLED') ?: 0),
		'environment' => getenv('MPESA_ENV') ?: 'sandbox', // sandbox | live
		'shortcode' => getenv('MPESA_SHORTCODE') ?: '',
		'passkey' => getenv('MPESA_PASSKEY') ?: '',
		'consumer_key' => getenv('MPESA_CONSUMER_KEY') ?: '',
		'consumer_secret' => getenv('MPESA_CONSUMER_SECRET') ?: '',
		'callback_url' => getenv('MPESA_CALLBACK_URL') ?: '',
		'callback_token' => getenv('MPESA_CALLBACK_TOKEN') ?: '',
	];

	if (app_table_exists($conn, 'tbl_payment_settings')) {
		try {
			$stmt = $conn->prepare("SELECT environment, shortcode, passkey, consumer_key, consumer_secret, callback_url, enabled FROM tbl_payment_settings WHERE id = 1 LIMIT 1");
			$stmt->execute();
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($row) {
				// Only fill missing env vars from DB (env always wins)
				if ($cfg['environment'] === 'sandbox' && getenv('MPESA_ENV') === false) $cfg['environment'] = (string)$row['environment'];
				if ($cfg['shortcode'] === '') $cfg['shortcode'] = (string)$row['shortcode'];
				if ($cfg['passkey'] === '') $cfg['passkey'] = (string)$row['passkey'];
				if ($cfg['consumer_key'] === '') $cfg['consumer_key'] = (string)$row['consumer_key'];
				if ($cfg['consumer_secret'] === '') $cfg['consumer_secret'] = (string)$row['consumer_secret'];
				if ($cfg['callback_url'] === '') $cfg['callback_url'] = (string)$row['callback_url'];
				if ((int)$cfg['enabled'] === 0) $cfg['enabled'] = (int)$row['enabled'];
			}
		} catch (Throwable $e) {
			// ignore
		}
	}

	// If callback token is set, append as query param for verification.
	if ($cfg['callback_url'] !== '' && $cfg['callback_token'] !== '') {
		$sep = (str_contains($cfg['callback_url'], '?')) ? '&' : '?';
		$cfg['callback_url'] = $cfg['callback_url'] . $sep . 'token=' . urlencode($cfg['callback_token']);
	}

	return $cfg;
}

function mpesa_base_url(string $env): string
{
	return strtolower($env) === 'live'
		? 'https://api.safaricom.co.ke'
		: 'https://sandbox.safaricom.co.ke';
}

function mpesa_http_json(string $url, array $headers, array $payload): array
{
	if (!function_exists('curl_init')) {
		throw new RuntimeException("PHP cURL is required for M-Pesa requests.");
	}

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
		CURLOPT_POST => true,
		CURLOPT_POSTFIELDS => json_encode($payload),
		CURLOPT_TIMEOUT => 30,
	]);

	$body = curl_exec($ch);
	$err = curl_error($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($body === false) {
		throw new RuntimeException("M-Pesa request failed: " . $err);
	}

	$data = json_decode($body, true);
	if (!is_array($data)) {
		throw new RuntimeException("M-Pesa response is not JSON (HTTP $code).");
	}

	return [$code, $data];
}

function mpesa_get_access_token(array $cfg): string
{
	if (!function_exists('curl_init')) {
		throw new RuntimeException("PHP cURL is required for M-Pesa requests.");
	}

	$base = mpesa_base_url($cfg['environment']);
	$url = $base . '/oauth/v1/generate?grant_type=client_credentials';

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => ['Accept: application/json'],
		CURLOPT_USERPWD => $cfg['consumer_key'] . ':' . $cfg['consumer_secret'],
		CURLOPT_TIMEOUT => 30,
	]);
	$body = curl_exec($ch);
	$err = curl_error($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($body === false) {
		throw new RuntimeException("M-Pesa auth failed: " . $err);
	}

	$data = json_decode($body, true);
	if (!is_array($data) || !isset($data['access_token'])) {
		throw new RuntimeException("M-Pesa auth response invalid (HTTP $code).");
	}
	return (string)$data['access_token'];
}

function mpesa_stk_push(PDO $conn, array $cfg, string $phone, float $amount, string $accountReference, string $desc): array
{
	if ((int)$cfg['enabled'] !== 1) {
		throw new RuntimeException("M-Pesa is disabled. Enable it in settings/environment.");
	}
	if ($cfg['shortcode'] === '' || $cfg['passkey'] === '' || $cfg['consumer_key'] === '' || $cfg['consumer_secret'] === '' || $cfg['callback_url'] === '') {
		throw new RuntimeException("M-Pesa settings incomplete (shortcode/passkey/consumer keys/callback URL).");
	}

	// Normalize phone (expects 2547XXXXXXXX)
	$phone = preg_replace('/\s+/', '', $phone);
	$phone = preg_replace('/^\+/', '', $phone);
	if (str_starts_with($phone, '0') && strlen($phone) >= 10) {
		$phone = '254' . substr($phone, 1);
	}
	if (!preg_match('/^2547\d{8}$/', $phone)) {
		throw new RuntimeException("Phone must be like 2547XXXXXXXX.");
	}

	$amountInt = (int)round($amount);
	if ($amountInt < 1) {
		throw new RuntimeException("Amount must be >= 1.");
	}

	$timestamp = gmdate('YmdHis');
	$password = base64_encode($cfg['shortcode'] . $cfg['passkey'] . $timestamp);
	$token = mpesa_get_access_token($cfg);

	$base = mpesa_base_url($cfg['environment']);
	$url = $base . '/mpesa/stkpush/v1/processrequest';

	$payload = [
		'BusinessShortCode' => $cfg['shortcode'],
		'Password' => $password,
		'Timestamp' => $timestamp,
		'TransactionType' => 'CustomerPayBillOnline',
		'Amount' => $amountInt,
		'PartyA' => $phone,
		'PartyB' => $cfg['shortcode'],
		'PhoneNumber' => $phone,
		'CallBackURL' => $cfg['callback_url'],
		'AccountReference' => $accountReference,
		'TransactionDesc' => $desc,
	];

	[$code, $data] = mpesa_http_json($url, ['Authorization: Bearer ' . $token], $payload);
	if ($code < 200 || $code >= 300) {
		$msg = $data['errorMessage'] ?? ($data['ResponseDescription'] ?? 'Unknown error');
		throw new RuntimeException("M-Pesa STK request failed: " . $msg);
	}

	return $data;
}

