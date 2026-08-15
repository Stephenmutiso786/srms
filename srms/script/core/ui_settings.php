<?php
require_once('../db/config.php');

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $bannerEnabled = app_setting_get($conn, 'top_banner_enabled', '0') === '1';
    $bannerType = strtolower(trim(app_setting_get($conn, 'top_banner_type', 'info')));
    if ($bannerType !== 'warning') {
        $bannerType = 'info';
    }

    $bannerText = trim(app_setting_get($conn, 'top_banner_text', ''));
    $maintenanceEnabled = app_setting_get($conn, 'maintenance_mode_enabled', '0') === '1';

    // Try to include app and school branding
    $appName = defined('APP_NAME') ? APP_NAME : '';
    $schoolRow = app_school_row($conn);
    $school = [
        'id' => $schoolRow['id'] > 0 ? (int)$schoolRow['id'] : null,
        'name' => trim((string)($schoolRow['name'] ?? $appName)),
        'logo' => trim((string)($schoolRow['logo'] ?? '')),
    ];

    echo json_encode([
        'ok' => true,
        'banner' => [
            'enabled' => $bannerEnabled,
            'type' => $bannerType,
            'text' => $bannerText,
        ],
        'app' => ['name' => $appName],
        'school' => $school,
        'maintenance' => [
            'enabled' => $maintenanceEnabled,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'banner' => [
            'enabled' => false,
            'type' => 'info',
            'text' => '',
        ],
        'maintenance' => [
            'enabled' => false,
        ],
    ]);
}
