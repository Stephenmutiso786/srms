<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/online_presence.php');

header('Content-Type: application/json');

if ($res !== '1') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sessionKey = (string)($_COOKIE['__SRMS__key'] ?? '');
    app_online_touch($conn, $sessionKey);

    $online = app_online_fetch_users($conn, (string)$level, (string)$account_id, 150, 180);

    echo json_encode([
        'ok' => true,
        'is_admin' => (bool)$online['is_admin'],
        'count' => (int)$online['count'],
        'users' => $online['users']
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Failed to load online users']);
}
