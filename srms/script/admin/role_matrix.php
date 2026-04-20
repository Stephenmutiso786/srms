<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('staff.manage', 'admin');

$roles = [];
$permissions = [];
$rolePermissionMap = [];
$staffRows = [];
$permissionGroups = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_roles($conn);

	if (!app_table_exists($conn, 'tbl_roles') || !app_table_exists($conn, 'tbl_permissions') || !app_table_exists($conn, 'tbl_role_permissions') || !app_table_exists($conn, 'tbl_user_roles')) {
		throw new RuntimeException('RBAC tables missing. Run migration 012.');
	}

	$stmt = $conn->prepare("SELECT id, name, level, description FROM tbl_roles ORDER BY level DESC, name ASC");
	$stmt->execute();
	$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, code, description FROM tbl_permissions ORDER BY code ASC");
	$stmt->execute();
	$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($permissions as &$permission) {
		$code = strtolower(trim((string)($permission['code'] ?? '')));
		$group = 'other';
		if ($code !== '') {
			$parts = explode('.', $code, 2);
			$candidate = trim((string)($parts[0] ?? ''));
			$group = $candidate !== '' ? $candidate : 'other';
		}
		$permission['group'] = $group;
		$permissionGroups[$group] = true;
	}
	unset($permission);
	$permissionGroups = array_keys($permissionGroups);
	sort($permissionGroups);

	$stmt = $conn->prepare("SELECT role_id, permission_id FROM tbl_role_permissions");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$roleId = (int)($row['role_id'] ?? 0);
		$permId = (int)($row['permission_id'] ?? 0);
		if ($roleId > 0 && $permId > 0) {
			$rolePermissionMap[$roleId][$permId] = true;
		}
	}

	$roleById = [];
	foreach ($roles as $role) {
		$roleById[(int)$role['id']] = $role;
	}

	$permissionById = [];
	foreach ($permissions as $permission) {
		$permissionById[(int)$permission['id']] = $permission;
	}

	$staffAssignments = [];
	$stmt = $conn->prepare("SELECT staff_id, role_id FROM tbl_user_roles");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$staffId = (int)($row['staff_id'] ?? 0);
		$roleId = (int)($row['role_id'] ?? 0);
		if ($staffId > 0 && $roleId > 0 && isset($roleById[$roleId])) {
			$staffAssignments[$staffId][$roleId] = true;
		}
	}

	$stmt = $conn->prepare("SELECT id, fname, lname, level FROM tbl_staff ORDER BY id ASC");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $staff) {
		$staffId = (int)($staff['id'] ?? 0);
		$assignedRoles = array_keys($staffAssignments[$staffId] ?? []);
		$assignedRoleNames = [];
		$effectivePermissionIds = [];

		foreach ($assignedRoles as $roleId) {
			$assignedRoleNames[] = (string)($roleById[$roleId]['name'] ?? ('Role #' . $roleId));
			foreach (array_keys($rolePermissionMap[$roleId] ?? []) as $permissionId) {
				$effectivePermissionIds[(int)$permissionId] = true;
			}
		}

		sort($assignedRoleNames);
		$effectivePermissionCodes = [];
		foreach (array_keys($effectivePermissionIds) as $permissionId) {
			if (isset($permissionById[$permissionId])) {
				$effectivePermissionCodes[] = (string)$permissionById[$permissionId]['code'];
			}
		}
		sort($effectivePermissionCodes);

		$staffRows[] = [
			'id' => $staffId,
			'name' => trim((string)($staff['fname'] ?? '') . ' ' . (string)($staff['lname'] ?? '')),
			'level' => (string)($staff['level'] ?? ''),
			'primary_title' => app_staff_primary_title($conn, $staffId, (string)($staff['level'] ?? '')),
			'roles' => $assignedRoleNames,
			'permission_count' => count($effectivePermissionCodes),
			'permission_codes' => $effectivePermissionCodes,
		];
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = 'An internal error occurred.';
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Role Permission Matrix - Elimu Hub</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.matrix-wrap { overflow-x: auto; }
.matrix-table { min-width: 980px; }
.matrix-table th,
.matrix-table td { white-space: nowrap; }
.matrix-table thead th { position: sticky; top: 0; background: #f8fafc; z-index: 2; }
.matrix-role-col { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 240px; }
.matrix-table thead .matrix-role-col { z-index: 3; background: #f8fafc; }
.badge-role { font-size: 0.72rem; }
.badge-perm { font-size: 0.72rem; }
.staff-table td { vertical-align: top; }
.filter-bar { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; margin-bottom: 12px; }
.filter-item { min-width: 220px; }
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">Elimu Hub</a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('admin/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Role Permission Matrix</h1>
<p>Audit role-level module access and staff effective permissions in one place.</p>
</div>
<div>
<a class="btn btn-outline-primary" href="admin/roles"><i class="bi bi-arrow-left me-1"></i>Back to Roles</a>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Role x Permission Grid</h3>
<div class="filter-bar">
<div class="filter-item">
<label class="form-label mb-1" for="permGroupFilter">Permission Group</label>
<select class="form-control" id="permGroupFilter">
<option value="all">All permission groups</option>
<?php foreach ($permissionGroups as $group): ?>
<option value="<?php echo htmlspecialchars((string)$group); ?>"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string)$group))); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="filter-item">
<label class="form-label mb-1" for="permCodeSearch">Permission Search</label>
<input class="form-control" type="text" id="permCodeSearch" placeholder="Type code, e.g. finance or results">
</div>
<div class="small text-muted" id="matrixVisibleCounter">Showing all permissions</div>
</div>
<div class="matrix-wrap">
<table class="table table-bordered table-sm matrix-table">
<thead>
<tr>
<th class="matrix-role-col">Role</th>
<?php foreach ($permissions as $permission): ?>
<th class="perm-col" data-group="<?php echo htmlspecialchars((string)$permission['group']); ?>" data-code="<?php echo htmlspecialchars((string)$permission['code']); ?>" title="<?php echo htmlspecialchars((string)($permission['description'] ?? '')); ?>"><?php echo htmlspecialchars((string)$permission['code']); ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ($roles as $role): ?>
<tr class="matrix-row">
<td class="matrix-role-col">
<div class="fw-semibold"><?php echo htmlspecialchars((string)$role['name']); ?></div>
<div class="text-muted small">Level <?php echo (int)$role['level']; ?></div>
</td>
<?php foreach ($permissions as $permission):
  $hasPermission = !empty($rolePermissionMap[(int)$role['id']][(int)$permission['id']]);
?>
<td class="text-center perm-col" data-group="<?php echo htmlspecialchars((string)$permission['group']); ?>" data-code="<?php echo htmlspecialchars((string)$permission['code']); ?>"><?php echo $hasPermission ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-dash text-muted"></i>'; ?></td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Staff Effective Access Snapshot</h3>
<div class="filter-bar">
<div class="filter-item">
<label class="form-label mb-1" for="staffNameFilter">Staff Name Filter</label>
<input class="form-control" type="text" id="staffNameFilter" placeholder="Search by staff name or title">
</div>
<div class="small text-muted" id="staffVisibleCounter">Showing all staff</div>
</div>
<div class="table-responsive">
<table class="table table-hover staff-table">
<thead>
<tr>
<th>Staff</th>
<th>Primary Title</th>
<th>Assigned Roles</th>
<th>Effective Permissions</th>
</tr>
</thead>
<tbody>
<?php foreach ($staffRows as $staff): ?>
<tr class="staff-row" data-staff-search="<?php echo htmlspecialchars(strtolower((string)$staff['name'] . ' ' . (string)$staff['primary_title'] . ' ' . implode(' ', $staff['permission_codes']))); ?>">
<td>
<div class="fw-semibold"><?php echo htmlspecialchars((string)$staff['name']); ?></div>
<div class="text-muted small">#<?php echo (int)$staff['id']; ?></div>
</td>
<td><?php echo htmlspecialchars((string)$staff['primary_title']); ?></td>
<td>
<?php if (!empty($staff['roles'])): ?>
<?php foreach ($staff['roles'] as $roleName): ?>
<span class="badge bg-primary badge-role"><?php echo htmlspecialchars((string)$roleName); ?></span>
<?php endforeach; ?>
<?php else: ?>
<span class="text-muted">No role assigned</span>
<?php endif; ?>
</td>
<td>
<span class="fw-semibold"><?php echo (int)$staff['permission_count']; ?></span>
<span class="text-muted">permissions</span>
<?php if (!empty($staff['permission_codes'])): ?>
<div class="mt-1">
<?php foreach ($staff['permission_codes'] as $permissionCode): ?>
<span class="badge bg-secondary badge-perm"><?php echo htmlspecialchars((string)$permissionCode); ?></span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	var groupFilter = document.getElementById('permGroupFilter');
	var codeSearch = document.getElementById('permCodeSearch');
	var matrixCounter = document.getElementById('matrixVisibleCounter');
	var matrixColumns = Array.prototype.slice.call(document.querySelectorAll('.perm-col'));
	var staffNameFilter = document.getElementById('staffNameFilter');
	var staffCounter = document.getElementById('staffVisibleCounter');
	var staffRows = Array.prototype.slice.call(document.querySelectorAll('.staff-row'));
	var matrixRows = Array.prototype.slice.call(document.querySelectorAll('.matrix-row'));

	function updateMatrixFilter() {
		var groupValue = (groupFilter && groupFilter.value) ? groupFilter.value.toLowerCase() : 'all';
		var codeValue = (codeSearch && codeSearch.value) ? codeSearch.value.trim().toLowerCase() : '';
		var visiblePermissionCount = 0;

		matrixColumns.forEach(function (cell) {
			var cellGroup = (cell.getAttribute('data-group') || '').toLowerCase();
			var cellCode = (cell.getAttribute('data-code') || '').toLowerCase();
			var groupMatch = (groupValue === 'all') || (cellGroup === groupValue);
			var codeMatch = (codeValue === '') || (cellCode.indexOf(codeValue) !== -1);
			var visible = groupMatch && codeMatch;
			cell.style.display = visible ? '' : 'none';
			if (visible && cell.parentElement && cell.parentElement.tagName === 'TR' && cell.parentElement.parentElement && cell.parentElement.parentElement.tagName === 'THEAD') {
				visiblePermissionCount += 1;
			}
		});

		matrixRows.forEach(function (row) {
			var hasVisibleCell = !!row.querySelector('td.perm-col:not([style*="display: none"])');
			row.style.display = hasVisibleCell ? '' : 'none';
		});

		if (matrixCounter) {
			matrixCounter.textContent = 'Showing ' + visiblePermissionCount + ' permission columns';
		}
	}

	function updateStaffFilter() {
		var q = (staffNameFilter && staffNameFilter.value) ? staffNameFilter.value.trim().toLowerCase() : '';
		var visible = 0;
		staffRows.forEach(function (row) {
			var haystack = (row.getAttribute('data-staff-search') || '').toLowerCase();
			var match = (q === '') || (haystack.indexOf(q) !== -1);
			row.style.display = match ? '' : 'none';
			if (match) {
				visible += 1;
			}
		});
		if (staffCounter) {
			staffCounter.textContent = 'Showing ' + visible + ' staff records';
		}
	}

	if (groupFilter) {
		groupFilter.addEventListener('change', updateMatrixFilter);
	}
	if (codeSearch) {
		codeSearch.addEventListener('input', updateMatrixFilter);
	}
	if (staffNameFilter) {
		staffNameFilter.addEventListener('input', updateStaffFilter);
	}

	updateMatrixFilter();
	updateStaffFilter();
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
