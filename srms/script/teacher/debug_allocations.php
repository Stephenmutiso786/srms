<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1") { 
	header("location:../"); 
	exit; 
}

$staffId = (string)$account_id;
$level = (string)$GLOBALS['level'];
$portal = 'teacher';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	
	echo '<pre style="font-family:monospace; background:#f5f5f5; padding:20px; margin:20px;">';
	echo "=== DIAGNOSTIC REPORT FOR STAFF #$staffId ===\n\n";
	
	// 1. Staff info
	$stmt = $conn->prepare('SELECT id, fname, lname, level FROM tbl_staff WHERE id = ? LIMIT 1');
	$stmt->execute([$staffId]);
	$staff = $stmt->fetch(PDO::FETCH_ASSOC);
	echo "STAFF INFO:\n";
	echo "  ID: " . ($staff['id'] ?? 'N/A') . "\n";
	echo "  Name: " . ($staff['fname'] ?? '') . " " . ($staff['lname'] ?? '') . "\n";
	echo "  Level: " . ($staff['level'] ?? 'N/A') . "\n\n";
	
	// 2. Assigned roles
	echo "ASSIGNED ROLES:\n";
	$stmt = $conn->prepare('SELECT r.id, r.name, r.level FROM tbl_user_roles ur JOIN tbl_roles r ON r.id = ur.role_id WHERE ur.staff_id = ?');
	$stmt->execute([$staffId]);
	$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (empty($roles)) {
		echo "  (none assigned)\n";
	} else {
		foreach ($roles as $role) {
			echo "  - " . $role['name'] . " (ID: " . $role['id'] . ", Level: " . $role['level'] . ")\n";
		}
	}
	echo "\n";
	
	// 3. Effective permissions
	echo "EFFECTIVE PERMISSION CODES:\n";
	$perms = app_get_permissions($conn, $staffId, $level);
	if (empty($perms)) {
		echo "  (none)\n";
	} else {
		foreach ($perms as $perm) {
			echo "  - " . htmlspecialchars($perm) . "\n";
		}
	}
	echo "\n";
	
	// 4. Module allocations for Teacher portal
	if (app_ensure_role_module_allocations_table($conn)) {
		echo "MODULE ALLOCATIONS FOR TEACHER PORTAL:\n";
		$allocation = app_staff_role_module_allocation($conn, $portal, $staffId);
		if (empty($allocation['active'])) {
			echo "  Status: NOT ACTIVE (system will allow all permission-based modules)\n";
		} else {
			echo "  Status: ACTIVE\n";
			echo "  Allocated modules: " . (empty($allocation['module_keys']) ? '(none)' : implode(', ', array_keys($allocation['module_keys']))) . "\n";
		}
	}
	echo "\n";
	
	// 5. Visible modules
	echo "VISIBLE MODULES IN TEACHER PORTAL:\n";
	$visible = app_portal_visible_modules($conn, $portal, $staffId, $level);
	if (empty($visible)) {
		echo "  (EMPTY - THIS IS THE PROBLEM!)\n";
	} else {
		foreach ($visible as $module) {
			$permsReq = implode(', ', (array)($module['permissions'] ?? []));
			$core = empty($module['core']) ? 'optional' : 'core';
			echo "  - " . ($module['label'] ?? '?') . " [$core] (requires: " . ($permsReq ?: 'none') . ")\n";
		}
	}
	echo "\n</pre>";
	
} catch (Throwable $e) {
	echo '<pre style="color:red;">';
	echo "ERROR: " . $e->getMessage() . "\n";
	echo $e->getTraceAsString();
	echo '</pre>';
}
?>
