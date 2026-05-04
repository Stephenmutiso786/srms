<?php
chdir(__DIR__ . '/..');
require_once('db/config.php');
require_once('const/rbac.php');

$term = $argv[1] ?? 'Jamal';
$term = trim((string)$term);
if ($term === '') { echo "Usage: php cli_diagnose.php <name>\n"; exit(1); }

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $like = '%' . $term . '%';
    $stmt = $conn->prepare('SELECT id, fname, lname, level FROM tbl_staff WHERE fname LIKE ? OR lname LIKE ? OR CONCAT(fname, " ", lname) LIKE ? LIMIT 50');
    $stmt->execute([$like, $like, $like]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo "No staff found matching '{$term}'.\n";
        exit(0);
    }

    foreach ($rows as $staff) {
        $id = (int)$staff['id'];
        $fname = $staff['fname'] ?? '';
        $lname = $staff['lname'] ?? '';
        $level = (string)$staff['level'];

        echo "=====================\n";
        echo "STAFF: {$fname} {$lname} (ID: {$id}, level: {$level})\n";
        echo "---------------------\n";

        // Roles
        $stmt = $conn->prepare('SELECT r.id, r.name, r.level FROM tbl_user_roles ur JOIN tbl_roles r ON r.id = ur.role_id WHERE ur.staff_id = ?');
        $stmt->execute([$id]);
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Roles:\n";
        if (empty($roles)) {
            echo "  (none)\n";
        } else {
            foreach ($roles as $r) {
                echo "  - {$r['name']} (ID: {$r['id']}, level: {$r['level']})\n";
            }
        }

        // Permissions
        $perms = app_get_permissions($conn, (string)$id, $level);
        echo "Effective permission codes:\n";
        if (empty($perms)) { echo "  (none)\n"; } else { foreach ($perms as $p) { echo "  - {$p}\n"; } }

        // Allocation
        if (app_ensure_role_module_allocations_table($conn)) {
            $alloc = app_staff_role_module_allocation($conn, 'teacher', (string)$id);
            echo "Module allocation status for teacher portal:\n";
            echo "  Active: " . ($alloc['active'] ? 'YES' : 'NO') . "\n";
            echo "  Allocated module keys: " . (empty($alloc['module_keys']) ? '(none)' : implode(', ', array_keys($alloc['module_keys']))) . "\n";
        } else {
            echo "Role module allocations table missing.\n";
        }

        // Visible modules
        $visible = app_portal_visible_modules($conn, 'teacher', (string)$id, $level);
        echo "Visible modules (teacher portal):\n";
        if (empty($visible)) {
            echo "  (EMPTY)\n";
        } else {
            foreach ($visible as $m) {
                $core = !empty($m['core']) ? 'core' : 'optional';
                $permsReq = implode(', ', (array)($m['permissions'] ?? []));
                echo "  - {$m['label']} [{$core}] (key: {$m['key']}) (requires: " . ($permsReq ?: 'none') . ")\n";
            }
        }

        echo "\n";
    }

} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
    exit(1);
}
