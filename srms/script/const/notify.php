<?php
require_once('db/config.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once(__DIR__ . '/../mail/src/Exception.php');
require_once(__DIR__ . '/../mail/src/PHPMailer.php');
require_once(__DIR__ . '/../mail/src/SMTP.php');

function app_get_smtp(PDO $conn): ?array {
	try {
		if (!app_table_exists($conn, 'tbl_smtp')) { return null; }
		$stmt = $conn->prepare("SELECT server, username, password, port, encryption, status FROM tbl_smtp LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) { return null; }
		return $row;
	} catch (Throwable $e) {
		return null;
	}
}

function app_send_email(PDO $conn, string $recipient, string $subject, string $message, array $attachments = []): array {
	$status = 'failed';
	$error = '';
	$provider = 'smtp';

	$smtp = app_get_smtp($conn);
	if (!$smtp || empty($smtp['server']) || empty($smtp['username'])) {
		$error = 'SMTP not configured';
	} else {
		try {
			$mail = new PHPMailer(true);
			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			);
			$mail->isSMTP();
			$mail->Host = $smtp['server'];
			$mail->SMTPAuth = true;
			$mail->Username = $smtp['username'];
			$mail->Password = $smtp['password'];
			$mail->SMTPSecure = $smtp['encryption'] ?: PHPMailer::ENCRYPTION_STARTTLS;
			$mail->Port = (int)($smtp['port'] ?: 587);

			$fromName = defined('WBName') ? WBName : (defined('APP_NAME') ? APP_NAME : 'School');
			$mail->setFrom($smtp['username'], $fromName);
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

			if ($mail->send()) {
				$status = 'sent';
			} else {
				$error = $mail->ErrorInfo;
			}
		} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
		}
	}

	if (app_table_exists($conn, 'tbl_email_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_email_logs (recipient, subject, message, status, provider) VALUES (?,?,?,?,?)");
		$stmt->execute([$recipient, $subject, $message, $status, $provider]);
	}

	return ['ok' => $status === 'sent', 'status' => $status, 'error' => $error];
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
		$payload = json_encode([
			'to' => $recipient,
			'message' => $message,
			'sender' => $settings['sender_id'],
			'api_key' => $settings['api_key']
		]);

		$ch = curl_init($settings['api_url']);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer '.$settings['api_key']
		]);
		$response = curl_exec($ch);
		$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($response === false) {
			$error = curl_error($ch);
		} else if ($httpCode >= 200 && $httpCode < 300) {
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
			$error = 'HTTP '.$httpCode;
		}
		curl_close($ch);
	}

	if (app_table_exists($conn, 'tbl_sms_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_sms_logs (recipient, message, status, provider) VALUES (?,?,?,?)");
		$stmt->execute([$recipient, $message, $status, $provider]);
	}

	return ['ok' => $status === 'sent', 'status' => $status, 'error' => $error];
}
