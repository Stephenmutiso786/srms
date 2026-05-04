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
    $school = ['id' => null, 'name' => $appName, 'logo' => ''];
    if (app_table_exists($conn, 'tbl_school')) {
        try {
            $s = $conn->prepare("SELECT id, name, logo FROM tbl_school ORDER BY id ASC LIMIT 1");
            $s->execute();
            $sr = $s->fetch(PDO::FETCH_ASSOC);
            if ($sr) {
                $school['id'] = isset($sr['id']) ? (int)$sr['id'] : null;
                $school['name'] = trim((string)($sr['name'] ?? $appName));
                $school['logo'] = trim((string)($sr['logo'] ?? ''));
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

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
