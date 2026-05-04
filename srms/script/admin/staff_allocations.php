<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header('Location: ../'); exit; }
app_require_permission('staff.manage', 'admin');

$staffId = (int)($_GET['staff_id'] ?? 0);
$portal = strtolower(trim((string)($_GET['portal'] ?? 'teacher')));
$error = '';

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($staffId < 1) {
        throw new RuntimeException('Missing staff id');
    }

    $stmt = $conn->prepare('SELECT id, fname, lname, level FROM tbl_staff WHERE id = ? LIMIT 1');
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$staff) {
        throw new RuntimeException('Staff not found');
    }

    $allocatableModules = [];
    foreach (app_portal_module_catalog($portal) as $module) {
        $moduleKey = strtolower(trim((string)($module['key'] ?? '')));
        $modulePermissions = array_values(array_filter(array_map('strval', (array)($module['permissions'] ?? []))));
        $isCore = !empty($module['core']);
        if ($moduleKey === '' || empty($modulePermissions) || $isCore) {
            continue;
        }
        $allocatableModules[] = $module;
    }

    // Load current allocations (staff first falls back to role)
    $alloc = app_staff_module_allocations($conn, $portal, (string)$staffId);
    $allocatedKeys = array_keys($alloc['module_keys']);
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Manage Staff Module Allocations</title>
<base href="../">
<link rel="stylesheet" href="css/main.css">
</head><body class="app sidebar-mini">
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content"><div class="app-title"><h1>Manage Modules for <?php echo htmlspecialchars(trim((string)($staff['fname'] ?? '') . ' ' . (string)($staff['lname'] ?? ''))); ?></h1>
<a class="btn btn-outline-primary" href="admin/role_matrix">Back</a>
</div>
<?php if ($error !== ''): ?><div class="tile"><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div></div><?php else: ?>
<div class="tile">
<form method="POST" action="admin/core/save_staff_allocations" class="row g-3">
<input type="hidden" name="staff_id" value="<?php echo (int)$staffId; ?>">
<input type="hidden" name="portal" value="<?php echo htmlspecialchars($portal); ?>">
<input type="hidden" name="return_to" value="../admin/role_matrix">
<?php if (empty($allocatableModules)): ?>
<div class="alert alert-info">No allocatable modules for this portal.</div>
<?php else: ?>
<div class="col-12"><div class="mb-2">Select modules to allocate to this staff (non-core only):</div></div>
<?php foreach ($allocatableModules as $module): $k = strtolower(trim((string)$module['key'])); $checked = in_array($k, $allocatedKeys, true); ?>
<div class="col-6 col-md-4"><label class="form-check">
  <input class="form-check-input" type="checkbox" name="module_keys[]" value="<?php echo htmlspecialchars($k); ?>" <?php echo $checked ? 'checked' : ''; ?>>
  <span class="form-check-label"><?php echo htmlspecialchars((string)($module['label'] ?? $k)); ?> <div class="text-muted small"><?php echo htmlspecialchars($k); ?></div></span>
</label></div>
<?php endforeach; ?>
<div class="col-12 mt-3"><button class="btn btn-primary">Save allocations</button></div>
<?php endif; ?>
</form>
</div>
<?php endif; ?></main></body></html>
