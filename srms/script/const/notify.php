<?php
require_once('db/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../mail/src/Exception.php');
require_once(__DIR__ . '/../mail/src/PHPMailer.php');
require_once(__DIR__ . '/../mail/src/SMTP.php');

function app_get_smtp(PDO $conn): ?array {
	try {
		$envServer = trim((string)(getenv('SMTP_HOST') ?: ''));
		$envUsername = trim((string)(getenv('SMTP_USERNAME') ?: ''));
		$envPassword = (string)(getenv('SMTP_PASSWORD') ?: '');
		$envPort = trim((string)(getenv('SMTP_PORT') ?: ''));
		$envEncryption = trim((string)(getenv('SMTP_ENCRYPTION') ?: ''));
		if (!app_table_exists($conn, 'tbl_smtp')) {
			if ($envServer === '' || $envUsername === '') {
				return null;
			}
			return [
				'server' => $envServer,
				'username' => $envUsername,
				'password' => $envPassword,
				'port' => $envPort !== '' ? $envPort : '587',
				'encryption' => $envEncryption !== '' ? $envEncryption : 'tls',
				'status' => 1,
			];
		}
		$stmt = $conn->prepare("SELECT server, username, password, port, encryption, status FROM tbl_smtp ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) {
			if ($envServer === '' || $envUsername === '') { return null; }
			return [
				'server' => $envServer,
				'username' => $envUsername,
				'password' => $envPassword,
				'port' => $envPort !== '' ? $envPort : '587',
				'encryption' => $envEncryption !== '' ? $envEncryption : 'tls',
				'status' => 1,
			];
		}
		if (isset($row['status']) && (int)$row['status'] !== 1) { return null; }
		if ($envServer !== '') { $row['server'] = $envServer; }
		if ($envUsername !== '') { $row['username'] = $envUsername; }
		if ($envPassword !== '') { $row['password'] = $envPassword; }
		if ($envPort !== '') { $row['port'] = $envPort; }
		if ($envEncryption !== '') { $row['encryption'] = $envEncryption; }
		return $row;
	} catch (Throwable $e) {
		return null;
	}
}

function app_configure_mailer_smtp(PHPMailer $mail, array $smtp): void {
	$mail->SMTPOptions = array(
		'ssl' => array(
			'verify_peer' => false,
			'verify_peer_name' => false,
			'allow_self_signed' => true
		)
	);
	$mail->isSMTP();
	$mail->Host = (string)($smtp['server'] ?? '');
	$mail->SMTPAuth = true;
	$mail->Username = (string)($smtp['username'] ?? '');
	$mail->Password = (string)($smtp['password'] ?? '');
	$encryption = strtolower(trim((string)($smtp['encryption'] ?? '')));
	if ($encryption === 'ssl') {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
	} elseif ($encryption === 'tls') {
		$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
	} else {
		$mail->SMTPSecure = '';
	}
	$mail->Port = (int)($smtp['port'] ?? 587);
}

function app_configure_mailer_common(PHPMailer $mail, string $fromAddress, string $fromName, string $recipient, string $subject, string $message, array $attachments = []): void {
	$mail->setFrom($fromAddress, $fromName);
	$mail->addAddress($recipient);
	$mail->isHTML(true);
	$mail->Subject = $subject;
	$mail->Body = $message;
	$mail->AltBody = strip_tags($message);
	foreach ($attachments as $attachment) {
		$path = trim((string)($attachment['path'] ?? ''));
		if ($path === '' || !is_file($path)) {
			continue;
		}
		$name = trim((string)($attachment['name'] ?? basename($path)));
		$mail->addAttachment($path, $name === '' ? basename($path) : $name);
	}
}

function app_send_email(PDO $conn, string $recipient, string $subject, string $message, array $attachments = []): array {
	$status = 'failed';
	$error = '';
	$provider = 'smtp';
	$attemptedFallback = false;

	$smtp = app_get_smtp($conn);
	if (!$smtp || empty($smtp['server']) || empty($smtp['username'])) {
		$error = 'SMTP not configured';
	} else {
		try {
			$mail = new PHPMailer(true);
			app_configure_mailer_smtp($mail, $smtp);

			$fromName = defined('WBName') ? WBName : (defined('APP_NAME') ? APP_NAME : 'School');
			app_configure_mailer_common($mail, (string)$smtp['username'], $fromName, $recipient, $subject, $message, $attachments);

			if ($mail->send()) {
				$status = 'sent';
			} else {
				$error = $mail->ErrorInfo;
			}
		} catch (Throwable $e) {
			error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
			$error = $e->getMessage();
		}

		if ($status !== 'sent' && strtolower((string)(getenv('APP_ALLOW_MAIL_FALLBACK') ?: '1')) !== '0' && function_exists('mail')) {
			try {
				$attemptedFallback = true;
				$mail = new PHPMailer(true);
				$fromName = defined('WBName') ? WBName : (defined('APP_NAME') ? APP_NAME : 'School');
				$mail->isMail();
				app_configure_mailer_common($mail, (string)$smtp['username'], $fromName, $recipient, $subject, $message, $attachments);
				if ($mail->send()) {
					$status = 'sent';
					$provider = 'mail';
					$error = $error !== '' ? 'SMTP failed: ' . $error . ' | Sent via mail() fallback.' : 'Sent via mail() fallback.';
				} else {
					$provider = 'mail';
					$fallbackError = $mail->ErrorInfo;
					$error = $error !== '' ? 'SMTP failed: ' . $error . ' | mail() fallback failed: ' . $fallbackError : 'mail() fallback failed: ' . $fallbackError;
				}
			} catch (Throwable $e) {
				error_log("[".__FILE__.":".__LINE__." Throwable fallback] " . $e->getMessage());
				$error = $error !== '' ? 'SMTP failed: ' . $error . ' | mail() fallback exception: ' . $e->getMessage() : $e->getMessage();
			}
		}
	}

	if ($status !== 'sent' && $error === '' && $smtp) {
		$host = (string)($smtp['server'] ?? '');
		$port = (string)($smtp['port'] ?? '');
		$encryption = (string)($smtp['encryption'] ?? '');
		$error = 'SMTP send failed. Check host=' . $host . ', port=' . $port . ', encryption=' . $encryption . ', and outbound network access.';
	}

	if (app_table_exists($conn, 'tbl_email_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_email_logs (recipient, subject, message, status, provider) VALUES (?,?,?,?,?)");
		$stmt->execute([$recipient, $subject, $message, $status, $provider]);
	}

	return ['ok' => $status === 'sent', 'status' => $status, 'error' => $error, 'provider' => $provider, 'fallback_attempted' => $attemptedFallback];
}

function app_get_sms_settings(PDO $conn): ?array {
	try {
		if (!app_table_exists($conn, 'tbl_sms_settings')) { return null; }
		$stmt = $conn->prepare("SELECT provider, api_url, api_key, sender_id, status FROM tbl_sms_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) { return null; }
		return $row;
	} catch (Throwable $e) {
		return null;
	}
}

function app_send_sms(PDO $conn, string $recipient, string $message): array {
	$status = 'failed';
	$error = '';
	$provider = 'custom';
	$walletId = 1;
	$tokensUsed = app_sms_token_segments($message);
	$deductedTokens = false;

	$settings = app_get_sms_settings($conn);
	if (!$settings || (int)$settings['status'] !== 1 || empty($settings['api_url']) || empty($settings['api_key'])) {
		$error = 'SMS gateway not configured';
	} else {
		if (app_table_exists($conn, 'tbl_sms_wallets')) {
			app_ensure_sms_wallet_tables($conn);
			if ($tokensUsed > 0 && app_sms_wallet_balance($conn, $walletId) < $tokensUsed) {
				$error = 'Insufficient SMS tokens';
				if (app_table_exists($conn, 'tbl_sms_logs')) {
					$stmt = $conn->prepare("INSERT INTO tbl_sms_logs (recipient, message, status, provider) VALUES (?,?,?,?)");
					$stmt->execute([$recipient, $message, $status, $provider]);
				}
				return ['ok' => false, 'status' => $status, 'error' => $error];
			}
		}

		$provider = $settings['provider'] ?: 'custom';
		$providerNormalized = strtolower(trim((string)$provider));
		$apiUrl = (string)$settings['api_url'];
		$apiKey = (string)$settings['api_key'];
		$senderId = trim((string)($settings['sender_id'] ?? ''));
		$payload = '';
		$headers = [];

		if ($providerNormalized === 'africastalking') {
			$username = trim((string)(getenv('AFRICASTALKING_USERNAME') ?: getenv('AT_USERNAME') ?: ''));
			if ($username === '' && stripos($apiUrl, 'sandbox') !== false) {
				$username = 'sandbox';
			}
			if ($username === '') {
				$error = 'Africa\'s Talking username missing. Set AFRICASTALKING_USERNAME or AT_USERNAME.';
			} else {
				$form = [
					'username' => $username,
					'to' => $recipient,
					'message' => $message,
				];
				if ($senderId !== '') {
					$form['from'] = $senderId;
				}
				$payload = http_build_query($form);
				$headers = [
					'Content-Type: application/x-www-form-urlencoded',
					'Accept: application/json',
					'apiKey: ' . $apiKey,
				];
			}
		} else {
			$payload = json_encode([
				'to' => $recipient,
				'message' => $message,
				'sender' => $senderId,
				'api_key' => $apiKey
			]);
			$headers = [
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer ' . $apiKey,
			];
		}

		if ($error === '') {
			$response = '';
			$httpCode = 0;

			if (function_exists('curl_init')) {
				$ch = curl_init($apiUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				$response = (string)curl_exec($ch);
				$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
				if ($response === '' && curl_errno($ch)) {
					$error = curl_error($ch);
				}
				curl_close($ch);
			} else {
				$headerText = '';
				foreach ($headers as $headerLine) {
					$headerText .= $headerLine . "\r\n";
				}
				$context = stream_context_create([
					'http' => [
						'method' => 'POST',
						'header' => $headerText,
						'content' => $payload,
						'ignore_errors' => true,
						'timeout' => 20,
					],
				]);
				$raw = @file_get_contents($apiUrl, false, $context);
				$response = $raw === false ? '' : (string)$raw;
				if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', (string)$http_response_header[0], $m)) {
					$httpCode = (int)$m[1];
				}
				if ($raw === false && $httpCode === 0) {
					$error = 'SMS request failed; HTTP client unavailable';
				}
			}

			if ($error === '') {
				if ($httpCode >= 200 && $httpCode < 300) {
					$status = 'sent';
					if (app_table_exists($conn, 'tbl_sms_wallets') && $tokensUsed > 0) {
						try {
							app_sms_wallet_adjust($conn, $walletId, -$tokensUsed, 'SMS-' . date('YmdHis'), 'Outbound SMS to ' . $recipient, 'usage');
							$deductedTokens = true;
						} catch (Throwable $e) {
							$status = 'failed';
							$error = 'SMS sent but token deduction failed';
						}
					}
				} else {
					$snippet = trim(substr($response, 0, 180));
					$error = 'HTTP ' . $httpCode . ($snippet !== '' ? ': ' . $snippet : '');
				}
			}
		}
	}

	if (app_table_exists($conn, 'tbl_sms_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_sms_logs (recipient, message, status, provider) VALUES (?,?,?,?)");
		$stmt->execute([$recipient, $message, $status, $provider]);
	}

	return ['ok' => $status === 'sent', 'status' => $status, 'error' => $error];
}
