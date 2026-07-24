<?php
require_once(__DIR__ . '/../db/config.php');
require_once(__DIR__ . '/http_client.php');
require_once(__DIR__ . '/school.php');

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
		$preferredColumns = app_column_exists($conn, 'tbl_smtp', 'server')
			? "server, username, password, port, encryption, status"
			: "mail_server AS server, mail_username AS username, mail_password AS password, mail_port AS port, mail_security AS encryption, 1 AS status";
		$stmt = $conn->prepare("SELECT {$preferredColumns} FROM tbl_smtp ORDER BY id DESC LIMIT 1");
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

function app_mail_fallback_available(): bool {
	if (!function_exists('mail')) {
		return false;
	}
	$sendmailPath = trim((string)ini_get('sendmail_path'));
	if ($sendmailPath === '') {
		return true;
	}
	$binary = preg_split('/\s+/', $sendmailPath)[0] ?? '';
	if ($binary === '') {
		return true;
	}
	return is_file($binary) && is_executable($binary);
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

		if ($status !== 'sent' && strtolower((string)(getenv('APP_ALLOW_MAIL_FALLBACK') ?: '1')) !== '0' && app_mail_fallback_available()) {
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
		} elseif ($status !== 'sent' && strtolower((string)(getenv('APP_ALLOW_MAIL_FALLBACK') ?: '1')) !== '0') {
			$error = $error !== '' ? 'SMTP failed: ' . $error . ' | mail() fallback unavailable: sendmail binary not found.' : 'mail() fallback unavailable: sendmail binary not found.';
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

function app_normalize_phone_number(string $phone, string $defaultCountryCode = '254'): string {
	$value = trim($phone);
	if ($value === '') {
		return '';
	}

	$value = preg_replace('/[^0-9+]/', '', $value);
	if ($value === null || $value === '') {
		return '';
	}

	if (strpos($value, '+') === 0) {
		$value = substr($value, 1);
	}

	if (strpos($value, '00') === 0) {
		$value = substr($value, 2);
	}

	$defaultCountryCode = preg_replace('/\D+/', '', $defaultCountryCode);
	if ($defaultCountryCode === null || $defaultCountryCode === '') {
		$defaultCountryCode = '254';
	}

	if (strpos($value, $defaultCountryCode) === 0) {
		return $value;
	}

	if (strlen($value) === 9 && strpos($value, '7') === 0) {
		return $defaultCountryCode . $value;
	}

	if (strlen($value) === 10 && strpos($value, '0') === 0) {
		return $defaultCountryCode . substr($value, 1);
	}

	return $value;
}

function app_http_resolution_error(string $url): string
{
	$host = (string)(parse_url($url, PHP_URL_HOST) ?: '');
	if ($host === '') {
		return 'Invalid API URL';
	}
	$resolved = gethostbyname($host);
	if ($resolved === $host) {
		return 'Could not resolve host: ' . $host;
	}
	return '';
}

function app_ensure_whatsapp_tables(PDO $conn): void {
	try {
		if (!app_table_exists($conn, 'tbl_whatsapp_settings')) {
			if (defined('DBDriver') && DBDriver === 'pgsql') {
				$conn->exec("CREATE TABLE IF NOT EXISTS tbl_whatsapp_settings (
					id integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,
					provider varchar(80) NOT NULL DEFAULT 'wireweb',
					api_url varchar(255) NOT NULL DEFAULT '',
					api_key varchar(200) NOT NULL DEFAULT '',
					session_id varchar(120) NOT NULL DEFAULT '',
					status integer NOT NULL DEFAULT 0,
					created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id)
				)");
			} else {
				$conn->exec("CREATE TABLE IF NOT EXISTS tbl_whatsapp_settings (
					id int NOT NULL AUTO_INCREMENT,
					provider varchar(80) NOT NULL DEFAULT 'wireweb',
					api_url varchar(255) NOT NULL DEFAULT '',
					api_key varchar(200) NOT NULL DEFAULT '',
					session_id varchar(120) NOT NULL DEFAULT '',
					status tinyint(1) NOT NULL DEFAULT 0,
					created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			}
		}

		if (!app_table_exists($conn, 'tbl_whatsapp_logs')) {
			if (defined('DBDriver') && DBDriver === 'pgsql') {
				$conn->exec("CREATE TABLE IF NOT EXISTS tbl_whatsapp_logs (
					id integer GENERATED BY DEFAULT AS IDENTITY NOT NULL,
					recipient varchar(50) NOT NULL,
					message text NOT NULL,
					status varchar(30) NOT NULL DEFAULT 'queued',
					provider varchar(50) NOT NULL DEFAULT '',
					message_id varchar(120) NOT NULL DEFAULT '',
					delivery_status varchar(40) NOT NULL DEFAULT '',
					error_message text NULL,
					message_type varchar(30) NOT NULL DEFAULT 'text',
					attachment_name varchar(255) NOT NULL DEFAULT '',
					metadata_json text NULL,
					delivered_at timestamp NULL,
					updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id)
				)");
			} else {
				$conn->exec("CREATE TABLE IF NOT EXISTS tbl_whatsapp_logs (
					id int NOT NULL AUTO_INCREMENT,
					recipient varchar(50) NOT NULL,
					message text NOT NULL,
					status varchar(30) NOT NULL DEFAULT 'queued',
					provider varchar(50) NOT NULL DEFAULT '',
					message_id varchar(120) NOT NULL DEFAULT '',
					delivery_status varchar(40) NOT NULL DEFAULT '',
					error_message text NULL,
					message_type varchar(30) NOT NULL DEFAULT 'text',
					attachment_name varchar(255) NOT NULL DEFAULT '',
					metadata_json longtext NULL,
					delivered_at datetime NULL DEFAULT NULL,
					updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
					PRIMARY KEY (id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			}
		}

		$logColumns = [
			'message_id' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN message_id varchar(120) NOT NULL DEFAULT ''"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN message_id varchar(120) NOT NULL DEFAULT ''",
			'delivery_status' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN delivery_status varchar(40) NOT NULL DEFAULT ''"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN delivery_status varchar(40) NOT NULL DEFAULT ''",
			'error_message' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN error_message text NULL"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN error_message text NULL",
			'message_type' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN message_type varchar(30) NOT NULL DEFAULT 'text'"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN message_type varchar(30) NOT NULL DEFAULT 'text'",
			'attachment_name' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN attachment_name varchar(255) NOT NULL DEFAULT ''"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN attachment_name varchar(255) NOT NULL DEFAULT ''",
			'metadata_json' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN metadata_json text NULL"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN metadata_json longtext NULL",
			'delivered_at' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN delivered_at timestamp NULL"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN delivered_at datetime NULL DEFAULT NULL",
			'updated_at' => DBDriver === 'pgsql'
				? "ALTER TABLE tbl_whatsapp_logs ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP"
				: "ALTER TABLE tbl_whatsapp_logs ADD COLUMN updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
		];
		foreach ($logColumns as $columnName => $sql) {
			if (!app_column_exists($conn, 'tbl_whatsapp_logs', $columnName)) {
				try {
					$conn->exec($sql);
				} catch (Throwable $ignored) {
					// best-effort online schema adjustment
				}
			}
		}

		$stmt = $conn->prepare("SELECT id FROM tbl_whatsapp_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		if (!$stmt->fetchColumn()) {
			$stmt = $conn->prepare("INSERT INTO tbl_whatsapp_settings (provider, api_url, api_key, session_id, status) VALUES (?,?,?,?,?)");
			$stmt->execute(['wireweb', 'https://app.wireweb.co.in/api/v1/messages', '', '', 0]);
		}
	} catch (Throwable $e) {
		error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	}
}

function app_whatsapp_log_create(PDO $conn, array $data): int
{
	app_ensure_whatsapp_tables($conn);
	if (!app_table_exists($conn, 'tbl_whatsapp_logs')) {
		return 0;
	}

	$recipient = (string)($data['recipient'] ?? '');
	$message = (string)($data['message'] ?? '');
	$status = (string)($data['status'] ?? 'queued');
	$provider = (string)($data['provider'] ?? 'wireweb');
	$messageId = (string)($data['message_id'] ?? '');
	$deliveryStatus = (string)($data['delivery_status'] ?? '');
	$errorMessage = (string)($data['error_message'] ?? '');
	$messageType = (string)($data['message_type'] ?? 'text');
	$attachmentName = (string)($data['attachment_name'] ?? '');
	$metadataJson = isset($data['metadata_json']) ? (string)$data['metadata_json'] : null;

	$stmt = $conn->prepare("INSERT INTO tbl_whatsapp_logs
		(recipient, message, status, provider, message_id, delivery_status, error_message, message_type, attachment_name, metadata_json)
		VALUES (?,?,?,?,?,?,?,?,?,?)");
	$stmt->execute([
		$recipient,
		$message,
		$status,
		$provider,
		$messageId,
		$deliveryStatus,
		$errorMessage,
		$messageType,
		$attachmentName,
		$metadataJson,
	]);

	return (int)$conn->lastInsertId();
}

function app_whatsapp_log_update_by_message_id(PDO $conn, string $messageId, array $fields): void
{
	app_ensure_whatsapp_tables($conn);
	if ($messageId === '' || !app_table_exists($conn, 'tbl_whatsapp_logs')) {
		return;
	}

	$sets = [];
	$values = [];
	foreach ([
		'status' => 'status',
		'delivery_status' => 'delivery_status',
		'error_message' => 'error_message',
		'metadata_json' => 'metadata_json',
		'provider' => 'provider',
	] as $inputKey => $columnName) {
		if (array_key_exists($inputKey, $fields)) {
			$sets[] = $columnName . ' = ?';
			$values[] = (string)$fields[$inputKey];
		}
	}

	if (!empty($fields['mark_delivered'])) {
		$sets[] = 'delivered_at = ' . (DBDriver === 'pgsql' ? 'CURRENT_TIMESTAMP' : 'CURRENT_TIMESTAMP');
	}

	if (empty($sets)) {
		return;
	}

	if (app_column_exists($conn, 'tbl_whatsapp_logs', 'updated_at')) {
		$sets[] = 'updated_at = ' . (DBDriver === 'pgsql' ? 'CURRENT_TIMESTAMP' : 'CURRENT_TIMESTAMP');
	}

	$values[] = $messageId;
	$stmt = $conn->prepare('UPDATE tbl_whatsapp_logs SET ' . implode(', ', $sets) . ' WHERE message_id = ?');
	$stmt->execute($values);
}

function app_whatsapp_delivery_status_from_event(string $event, array $payload = []): array
{
	$eventLower = strtolower(trim($event));
	$status = 'received';
	$error = '';
	$markDelivered = false;

	if ($eventLower === '') {
		$eventLower = strtolower(trim((string)($payload['status'] ?? '')));
	}

	if (strpos($eventLower, 'deliver') !== false || strpos($eventLower, 'read') !== false) {
		$status = 'delivered';
		$markDelivered = true;
	} elseif (strpos($eventLower, 'sent') !== false || strpos($eventLower, 'accept') !== false || strpos($eventLower, 'queue') !== false) {
		$status = 'sent';
	} elseif (strpos($eventLower, 'fail') !== false || strpos($eventLower, 'error') !== false) {
		$status = 'failed';
		$error = trim((string)($payload['error'] ?? $payload['message'] ?? $payload['reason'] ?? 'Provider reported a delivery failure'));
	} elseif (strpos($eventLower, 'receive') !== false) {
		$status = 'received';
	}

	return [
		'status' => $status,
		'error' => $error,
		'mark_delivered' => $markDelivered,
	];
}

function app_get_whatsapp_settings(PDO $conn): ?array {
	try {
		app_ensure_whatsapp_tables($conn);
		if (!app_table_exists($conn, 'tbl_whatsapp_settings')) { return null; }
		$stmt = $conn->prepare("SELECT provider, api_url, api_key, session_id, status FROM tbl_whatsapp_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if (!$row) { return null; }
		return $row;
	} catch (Throwable $e) {
		return null;
	}
}

function app_whatsapp_media_endpoint(string $messagesUrl): string
{
	$url = trim($messagesUrl);
	if ($url === '') {
		return '';
	}
	if (strpos($url, '/messages/send') !== false) {
		return str_replace('/messages/send', '/media', $url);
	}
	if (strpos($url, '/messages') !== false) {
		return str_replace('/messages', '/media', $url);
	}
	return rtrim($url, '/') . '/media';
}

function app_send_whatsapp(PDO $conn, string $recipient, string $message, array $metadata = []): array {
	$status = 'failed';
	$error = '';
	$provider = 'wireweb';
	$recipient = app_normalize_phone_number($recipient, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254'));
	$messageId = '';
	$deliveryStatus = '';
	$responsePayload = null;

	$settings = app_get_whatsapp_settings($conn);
	if (!$settings || (int)($settings['status'] ?? 0) !== 1) {
		$error = 'WhatsApp gateway not configured';
	} elseif ($recipient === '') {
		$error = 'Recipient phone number missing or invalid';
	} else {
		$provider = strtolower(trim((string)($settings['provider'] ?? 'wireweb')));
		$apiUrl = trim((string)($settings['api_url'] ?? ''));
		$apiKey = trim((string)($settings['api_key'] ?? ''));
		$sessionId = trim((string)($settings['session_id'] ?? ''));

		if ($apiUrl === '' || $apiKey === '' || $sessionId === '') {
			$error = 'Wireweb API URL, API key, or session ID missing';
		} else {
			$payload = json_encode([
				'sessionId' => $sessionId,
				'to' => $recipient,
				'text' => $message,
			]);
			$headers = [
				'Content-Type: application/json',
				'Accept: application/json',
				'Authorization: Bearer ' . $apiKey,
			];

				$resp = app_http_request('POST', $apiUrl, $payload, $headers, 20);
				$response = (string)($resp['body'] ?? '');
				$httpCode = (int)($resp['http_code'] ?? 0);
				if (!empty($resp['error'])) {
					$error = (string)$resp['error'];
			} elseif ($httpCode === 0) {
				$error = app_http_resolution_error($apiUrl);
				if ($error === '') {
					$error = 'Unable to reach WhatsApp gateway';
				}
				} elseif ($httpCode >= 200 && $httpCode < 300) {
					$data = json_decode($response, true);
					$responsePayload = is_array($data) ? $data : null;
					$messageId = (string)($data['messageId'] ?? $data['message_id'] ?? '');
					if (!is_array($data) || !array_key_exists('success', $data) || !empty($data['success'])) {
						$status = 'sent';
						$deliveryStatus = 'sent';
					} else {
						$error = trim((string)($data['message'] ?? 'WhatsApp send failed'));
					}
				} else {
				$snippet = trim(substr($response, 0, 180));
				$error = 'HTTP ' . $httpCode . ($snippet !== '' ? ': ' . $snippet : '');
			}
		}
	}

	$metadata['provider_response'] = $responsePayload;
	app_whatsapp_log_create($conn, [
		'recipient' => $recipient,
		'message' => $message,
		'status' => $status,
		'provider' => $provider,
		'message_id' => $messageId,
		'delivery_status' => $deliveryStatus,
		'error_message' => $error,
		'message_type' => 'text',
		'attachment_name' => '',
		'metadata_json' => !empty($metadata) ? json_encode($metadata) : null,
	]);

	return ['ok' => $status === 'sent', 'status' => $status, 'error' => $error, 'provider' => $provider, 'message_id' => $messageId];
}

function app_send_whatsapp_document(PDO $conn, string $recipient, string $caption, string $filePath, string $fileName = '', array $metadata = []): array {
	$status = 'failed';
	$error = '';
	$provider = 'wireweb';
	$recipient = app_normalize_phone_number($recipient, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254'));
	$messageId = '';
	$deliveryStatus = '';
	$responsePayload = null;

	$settings = app_get_whatsapp_settings($conn);
	if (!$settings || (int)($settings['status'] ?? 0) !== 1) {
		$error = 'WhatsApp gateway not configured';
	} elseif ($recipient === '') {
		$error = 'Recipient phone number missing or invalid';
	} elseif ($filePath === '' || !is_file($filePath)) {
		$error = 'WhatsApp document file missing';
	} elseif (!function_exists('curl_init') || !class_exists('CURLFile')) {
		$error = 'cURL file upload support is not available';
	} else {
		$provider = strtolower(trim((string)($settings['provider'] ?? 'wireweb')));
		$apiKey = trim((string)($settings['api_key'] ?? ''));
		$sessionId = trim((string)($settings['session_id'] ?? ''));
		$apiUrl = app_whatsapp_media_endpoint((string)($settings['api_url'] ?? ''));
		$fileName = trim($fileName) !== '' ? trim($fileName) : basename($filePath);

		if ($apiUrl === '' || $apiKey === '' || $sessionId === '') {
			$error = 'Wireweb API URL, API key, or session ID missing';
		} else {
			$mimeType = 'application/pdf';
			if (function_exists('mime_content_type')) {
				$detected = (string)@mime_content_type($filePath);
				if ($detected !== '') {
					$mimeType = $detected;
				}
			}

			$payload = [
				'sessionId' => $sessionId,
				'to' => $recipient,
				'caption' => $caption,
				'file' => new CURLFile($filePath, $mimeType, $fileName),
			];
			$headers = [
				'Accept: application/json',
				'Authorization: Bearer ' . $apiKey,
			];

				$resp = app_http_request('POST', $apiUrl, $payload, $headers, 60);
				$response = (string)($resp['body'] ?? '');
				$httpCode = (int)($resp['http_code'] ?? 0);
				if (!empty($resp['error'])) {
					$error = (string)$resp['error'];
			} elseif ($httpCode === 0) {
				$error = app_http_resolution_error($apiUrl);
				if ($error === '') {
					$error = 'Unable to reach WhatsApp media gateway';
				}
				} elseif ($httpCode >= 200 && $httpCode < 300) {
					$data = json_decode($response, true);
					$responsePayload = is_array($data) ? $data : null;
					$messageId = (string)($data['messageId'] ?? $data['message_id'] ?? '');
					if (!is_array($data) || !array_key_exists('success', $data) || !empty($data['success'])) {
						$status = 'sent';
						$deliveryStatus = 'sent';
					} else {
						$error = trim((string)($data['message'] ?? 'WhatsApp document send failed'));
					}
				} else {
				$snippet = trim(substr($response, 0, 200));
				$error = 'HTTP ' . $httpCode . ($snippet !== '' ? ': ' . $snippet : '');
			}
		}
	}

	$loggedMessage = trim($caption) !== '' ? $caption : ('Document: ' . ($fileName !== '' ? $fileName : basename($filePath)));
	$metadata['provider_response'] = $responsePayload;
	app_whatsapp_log_create($conn, [
		'recipient' => $recipient,
		'message' => $loggedMessage,
		'status' => $status,
		'provider' => $provider,
		'message_id' => $messageId,
		'delivery_status' => $deliveryStatus,
		'error_message' => $error,
		'message_type' => 'document',
		'attachment_name' => $fileName !== '' ? $fileName : basename($filePath),
		'metadata_json' => !empty($metadata) ? json_encode($metadata) : null,
	]);

	return ['ok' => $status === 'sent', 'status' => $status, 'error' => $error, 'provider' => $provider, 'message_id' => $messageId];
}

function app_send_sms(PDO $conn, string $recipient, string $message): array {
	$status = 'failed';
	$error = '';
	$provider = 'custom';
	$recipient = app_normalize_phone_number($recipient, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254'));
	$walletId = 1;
	$tokensUsed = app_sms_token_segments($message);
	$deductedTokens = false;

	$settings = app_get_sms_settings($conn);
	if (!$settings || (int)$settings['status'] !== 1 || empty($settings['api_url']) || empty($settings['api_key'])) {
		$error = 'SMS gateway not configured';
	} elseif ($recipient === '') {
		$error = 'Recipient phone number missing or invalid';
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

			$resp = app_http_request('POST', $apiUrl, $payload, $headers, 20);
			$response = (string)($resp['body'] ?? '');
			$httpCode = (int)($resp['http_code'] ?? 0);
			if (!empty($resp['error'])) {
				$error = (string)$resp['error'];
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
