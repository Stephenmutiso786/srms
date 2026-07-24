<?php
require_once('db/config.php');
require_once('const/notify.php');

header('Content-Type: application/json');

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
	http_response_code(400);
	echo json_encode(['ok' => false, 'error' => 'Invalid JSON payload']);
	exit;
}

$messageId = trim((string)($payload['messageId'] ?? $payload['message_id'] ?? ''));
$event = trim((string)($payload['event'] ?? $payload['status'] ?? ''));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$delivery = app_whatsapp_delivery_status_from_event($event, $payload);
	if ($messageId !== '') {
		$stmt = $conn->prepare("SELECT metadata_json FROM tbl_whatsapp_logs WHERE message_id = ? ORDER BY id DESC LIMIT 1");
		$stmt->execute([$messageId]);
		$existingMetadata = json_decode((string)($stmt->fetchColumn() ?: ''), true);
		if (!is_array($existingMetadata)) {
			$existingMetadata = [];
		}
		$existingMetadata['webhook_payload'] = $payload;
		$existingMetadata['last_webhook_event'] = $event;
		app_whatsapp_log_update_by_message_id($conn, $messageId, [
			'status' => $delivery['status'] === 'failed' ? 'failed' : 'sent',
			'delivery_status' => $delivery['status'],
			'error_message' => $delivery['error'],
			'metadata_json' => json_encode($existingMetadata),
			'mark_delivered' => !empty($delivery['mark_delivered']),
		]);
	}

	echo json_encode([
		'ok' => true,
		'message_id' => $messageId,
		'event' => $event,
		'delivery_status' => $delivery['status'],
	]);
	exit;
} catch (Throwable $e) {
	http_response_code(500);
	echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
	exit;
}
