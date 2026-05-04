<?php
chdir('../../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header('Location: ../../'); exit; }
app_require_permission('staff.manage', 'admin');

$staffId = (int)($_POST['staff_id'] ?? 0);
$portal = strtolower(trim((string)($_POST['portal'] ?? '')));
$moduleKeys = $_POST['module_keys'] ?? [];
$returnTo = (string)($_POST['return_to'] ?? '../../role_matrix');

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($staffId < 1 || $portal === '') {
        header('Location: ' . $returnTo);
        exit;
    }

    if (!app_table_exists($conn, 'tbl_staff')) {
        header('Location: ' . $returnTo);
        exit;
    }

    if (!app_ensure_staff_module_allocations_table($conn)) {
        header('Location: ' . $returnTo);
        exit;
    }

    // Normalize and filter module keys against portal catalog and skip core modules
    $catalog = app_portal_module_catalog($portal);
    $allowedKeys = [];
    foreach ($catalog as $m) {
        $k = strtolower(trim((string)($m['key'] ?? '')));
        if ($k === '') { continue; }
        if (!empty($m['core'])) { continue; }
        $allowedKeys[$k] = true;
    }

    $filtered = [];
    foreach ((array)$moduleKeys as $mk) {
        $k = strtolower(trim((string)$mk));
        if ($k === '') { continue; }
        if (!empty($allowedKeys[$k])) { $filtered[$k] = true; }
    }

    // Replace allocations in a transaction
    $conn->beginTransaction();
    $del = $conn->prepare('DELETE FROM tbl_staff_module_allocations WHERE staff_id = ? AND portal = ?');
    $del->execute([$staffId, $portal]);

    if (!empty($filtered)) {
        $isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
        if ($isPgsql) {
            $ins = $conn->prepare('INSERT INTO tbl_staff_module_allocations (staff_id, portal, module_key) VALUES (?, ?, ?) ON CONFLICT DO NOTHING');
        } else {
            $ins = $conn->prepare('INSERT IGNORE INTO tbl_staff_module_allocations (staff_id, portal, module_key) VALUES (?, ?, ?)');
        }
        foreach (array_keys($filtered) as $k) {
            $ins->execute([$staffId, $portal, $k]);
        }
    }

    $conn->commit();
} catch (Throwable $e) {
    if ($conn && $conn->inTransaction()) { $conn->rollBack(); }
}

header('Location: ' . $returnTo);
exit;
