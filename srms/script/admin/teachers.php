<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/online_presence.php');
require_once('const/rbac.php');
$teacherControlAllowed = $res === "1" && ((int)$level === 1 || app_current_user_has_any_permission(['staff.manage', 'academic.manage']));
if (!$teacherControlAllowed) { header("location:../"); exit; }

$isSuperAdminController = false;
$isHeadteacherController = false;
$teacherStats = [
	'total' => 0,
	'active' => 0,
	'blocked' => 0,
	'online' => 0,
];
$teacherRows = [];
$adminRows = [];
$onlineStaff = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$isSuperAdminController = app_is_super_admin_controller($conn, (string)($account_id ?? ''), (string)($level ?? ''));
	$isHeadteacherController = app_staff_designation_key($conn, (int)($account_id ?? 0), (string)($level ?? '')) === 'headteacher';
	$onlineMaps = app_online_fetch_maps($conn, 180);
	$onlineStaff = isset($onlineMaps['staff']) && is_array($onlineMaps['staff']) ? $onlineMaps['staff'] : [];
	$stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE level IN (0,1,2,5,9) ORDER BY status DESC, fname ASC, lname ASC");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $teacherRow) {
		$rowLevel = (string)($teacherRow['level'] ?? '');
		$staffId = (int)($teacherRow['id'] ?? 0);
		if ($rowLevel === '9') {
			continue;
		}
		$designationKey = app_staff_designation_key($conn, $staffId, $rowLevel);
		if (in_array($designationKey, ['headteacher', 'deputy_headteacher', 'senior_teacher', 'accountant'], true)) {
			$adminRows[] = $teacherRow;
			continue;
		}
		if ($rowLevel !== '2') {
			continue;
		}
		$teacherRows[] = $teacherRow;
		$teacherStats['total']++;
		if ((string)($teacherRow['status'] ?? '0') === "1") {
			$teacherStats['active']++;
		} else {
			$teacherStats['blocked']++;
		}
		if (isset($onlineStaff[(string)$teacherRow['id']])) {
			$teacherStats['online']++;
		}
	}
} catch (Throwable $e) {
	// Keep the page usable even if the stats summary cannot load.
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Staff</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<style>
.online-pill { display:inline-flex; align-items:center; gap:6px; font-weight:700; }
.online-dot { width:9px; height:9px; border-radius:999px; background:#20b65d; }
</style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
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
<h1>Teachers</h1>
<p class="text-muted mb-0">Add teachers, import them in bulk, and jump to teacher control pages.</p>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add</button></li>
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#importModal">Import</button></li>
<li class="breadcrumb-item"><a class="btn btn-outline-primary btn-sm" href="admin/teacher_allocation">Subject Control</a></li>
<li class="breadcrumb-item"><a class="btn btn-outline-secondary btn-sm" href="admin/role_matrix">Role Control</a></li>
</ul>
</div>

<div class="tile mb-3">
<div class="tile-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
<div>
<strong>Teacher control panel</strong>
<div class="text-muted">Use this module to create, edit, block, delete, and impersonate staff accounts. Leadership and admin accounts, including the accountant, are reserved for the super admin or headteacher.</div>
</div>
<div class="d-flex flex-wrap gap-2">
<?php if ($isSuperAdminController || $isHeadteacherController) { ?>
<button class="btn btn-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addAdminModal">Create Admin Account</button>
<?php } ?>
<button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add Teacher</button>
<a class="btn btn-outline-primary btn-sm" href="admin/teacher_allocation">Control Subjects</a>
<a class="btn btn-outline-secondary btn-sm" href="admin/role_matrix">Control Roles</a>
</div>
</div>
</div>

<div class="row mb-3">
<div class="col-md-3 col-sm-6">
<div class="tile mb-0">
<div class="tile-body">
<div class="text-muted text-uppercase small">Teachers</div>
<div class="h3 mb-0"><?php echo number_format($teacherStats['total']); ?></div>
</div>
</div>
</div>
<div class="col-md-3 col-sm-6">
<div class="tile mb-0">
<div class="tile-body">
<div class="text-muted text-uppercase small">Active</div>
<div class="h3 mb-0 text-success"><?php echo number_format($teacherStats['active']); ?></div>
</div>
</div>
</div>
<div class="col-md-3 col-sm-6">
<div class="tile mb-0">
<div class="tile-body">
<div class="text-muted text-uppercase small">Blocked</div>
<div class="h3 mb-0 text-danger"><?php echo number_format($teacherStats['blocked']); ?></div>
</div>
</div>
</div>
<div class="col-md-3 col-sm-6">
<div class="tile mb-0">
<div class="tile-body">
<div class="text-muted text-uppercase small">Online</div>
<div class="h3 mb-0 text-primary"><?php echo number_format($teacherStats['online']); ?></div>
</div>
</div>
</div>
</div>
<?php if ($isSuperAdminController) { ?>
<div class="modal fade" id="addAdminModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addAdminModalLabel">Create Admin Account</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/new_user2">
<div class="alert alert-warning mb-3">
Only the super admin or headteacher can create and manage these leadership and admin accounts, including Headteacher, Deputy Headteacher, Senior Teacher, and Accountant.
</div>
<div class="mb-2">
<label class="form-label">First Name</label>
<input required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<div class="mb-2">
<label class="form-label">Password</label>
<input type="password" class="form-control" name="password" placeholder="***************">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Admin Designation</label>
<select class="form-control" name="designation" id="adminDesignation" required onchange="syncAdminRole(this.value)">
<option value="headteacher" selected>Headteacher</option>
<option value="deputy_headteacher">Deputy Headteacher</option>
<option value="senior_teacher">Senior Teacher</option>
<option value="accountant">Accountant</option>
</select>
</div>
<input type="hidden" name="role" id="adminRoleField" value="0">
<div class="mb-3">
<label class="form-label">Status</label>
<select class="form-control" name="status" required>
<option value="1" selected>Active</option>
<option value="0">Blocked</option>
</select>
</div>
<button type="submit" name="submit" value="1" class="btn btn-danger app_btn">Create Admin</button>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>
<?php } ?>
<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addModalLabel">Add Teacher Account</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/new_user2">
<div class="alert alert-primary mb-3">
This form creates teacher accounts only. Use <strong>Create Admin Account</strong> for admin users.
</div>
<div class="mb-2">
<label class="form-label">First Name</label>
<input required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input required name="lname" class="form-control" type="text"  onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<div class="mb-2">
<label class="form-label">Password</label>
<input type="password" class="form-control" id="npass" name="password" placeholder="***************">
</div>
<div class="mb-2">
<label class="form-label">Confirm Password</label>
<input type="password" class="form-control" id="cnpass" placeholder="***************">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>
<input type="hidden" name="role" value="2">
<input type="hidden" name="designation" value="teacher">

<div class="mb-3">
<label class="form-label">Status</label>
<select class="form-control" name="status" required>
<option selected disabled value="">Select status</option>
<option value="1">Active</option>
<option value="0">Blocked</option>
</select>
</div>

<button id="sub_btnp2" type="submit" name="submit" value="1" class="btn btn-primary app_btn">Create Teacher</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editModalLabel">Edit Staff</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/update_user2">
<div class="mb-2">
<label class="form-label">First Name</label>
<input id="fname" required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input id="lname" required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input id="email" required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select id="gender" class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>

<div class="mb-2">
<label class="form-label">Role</label>
<select id="role" class="form-control" name="role" required>
<option value="2">Teacher</option>
</select>
</div>
<input type="hidden" name="designation" value="teacher">


<div class="mb-3">
<label class="form-label">Status</label>
<select id="status" class="form-control" name="status" required>
<option selected disabled value="">Select status</option>
<option value="1">Active</option>
<option value="0">Blocked</option>
</select>
</div>
<input type="hidden" name="id" id="id">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Save</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<?php if ($isSuperAdminController) { ?>
<div class="modal fade" id="editAdminModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editAdminModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editAdminModalLabel">Edit Admin Account</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/update_user2">
<div class="mb-2">
<label class="form-label">First Name</label>
<input id="admin_fname" required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input id="admin_lname" required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input id="admin_email" required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select id="admin_gender" class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Admin Designation</label>
<select id="admin_designation_edit" class="form-control" name="designation" required onchange="syncAdminEditRole(this.value)">
<option value="headteacher">Headteacher</option>
<option value="deputy_headteacher">Deputy Headteacher</option>
<option value="senior_teacher">Senior Teacher</option>
<option value="accountant">Accountant</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Status</label>
<select id="admin_status" class="form-control" name="status" required>
<option value="1">Active</option>
<option value="0">Blocked</option>
</select>
</div>
<input type="hidden" name="role" id="admin_role_edit" value="0">
<input type="hidden" name="id" id="admin_id">
<button type="submit" name="submit" value="1" class="btn btn-danger app_btn">Save Admin Account</button>
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>
<?php } ?>

<div class="modal fade" id="importModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="importModalLabel">Import Teachers</h5>
</div>
<div class="modal-body">
<form enctype="multipart/form-data" class="app_frm" method="POST" autocomplete="OFF" action="admin/core/import_users">
<div class="mb-3">
<label class="form-label">Excel File</label>
<input required accept=".xlsx" type="file" name="file" class="form-control" accept="application/msexcel">
</div>


<div class="alert alert-info">
Download excel template from <a download href="templates/import_teachers.xlsx" class="alert-link">here</a>
</div>
<button type="submit" name="submit" value="1" class="btn btn-primary">Import</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<?php if ($isSuperAdminController) { ?>
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Leadership / Admin Accounts</h3>
<p class="text-muted">Only the super admin can view and edit these accounts.</p>
<table class="table table-hover table-bordered" id="adminStaffTable">
<thead>
<tr>
<th>First Name</th>
<th>Last Name</th>
<th>School ID</th>
<th>Email</th>
<th>Gender</th>
<th>Designation</th>
<th>Presence</th>
<th width="120" align="center">Status</th>
<th width="120" align="center">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($adminRows as $row) { ?>
<?php
$adminStatus = ((string)($row['status'] ?? '0') === "1")
	? '<span class="me-1 badge badge-pill bg-success">Active</span>'
	: '<span class="me-1 badge badge-pill bg-danger">Blocked</span>';
$adminDesignation = ucwords(str_replace('_', ' ', app_staff_designation_key($conn, (int)$row['id'], (string)$row['level'])));
?>
<tr>
<td><?php echo htmlspecialchars((string)$row['fname']); ?></td>
<td><?php echo htmlspecialchars((string)$row['lname']); ?></td>
<td><?php echo htmlspecialchars((string)($row['school_id'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$row['email']); ?></td>
<td><?php echo htmlspecialchars((string)$row['gender']); ?></td>
<td><?php echo htmlspecialchars($adminDesignation); ?></td>
<td>
<?php if (isset($onlineStaff[(string)$row['id']])) { ?>
<span class="online-pill"><span class="online-dot"></span>Online</span>
<?php } else { ?>
<span class="text-muted">Offline</span>
<?php } ?>
</td>
<td width="100" align="center"><?php echo $adminStatus; ?></td>
<td width="120" align="center">
<textarea style="display:none;" id="admin_fname_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['fname']); ?></textarea>
<textarea style="display:none;" id="admin_lname_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['lname']); ?></textarea>
<textarea style="display:none;" id="admin_email_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['email']); ?></textarea>
<textarea style="display:none;" id="admin_designation_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)app_staff_designation_key($conn, (int)$row['id'], (string)$row['level'])); ?></textarea>
<button onclick="set_admin_user('<?php echo (int)$row['id']; ?>', '<?php echo htmlspecialchars((string)$row['gender'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8'); ?>');" data-bs-toggle="modal" data-bs-target="#editAdminModal" class="btn btn-danger btn-sm" type="button">Edit</button>
</td>
</tr>
<?php } ?>
</tbody>
</table>
</div>
</div>
</div>
<?php } ?>

<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Teacher Accounts</h3>
<form id="bulkStaffForm" method="POST" action="admin/core/bulk_delete_staff" onsubmit="return confirmBulkDelete('staff');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
<select class="form-control form-control-sm" name="bulk_action" style="max-width:200px;">
<option value="delete" selected>Delete selected</option>
<option value="set_active">Set status: Active</option>
<option value="set_blocked">Set status: Blocked</option>
</select>
<button type="submit" class="btn btn-primary btn-sm">Apply</button>
<div class="form-check ms-2">
<input class="form-check-input" type="checkbox" id="selectAllStaff">
<label class="form-check-label" for="selectAllStaff">Select all</label>
</div>
</div>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th width="40"><input class="form-check-input" type="checkbox" id="selectAllStaffHead"></th>
<th>First Name</th>
<th>Last Name</th>
<th>School ID</th>
<th>Email</th>
<th>Gender</th>
<th>Role</th>
<th>Presence</th>
<th width="120" align="center">Status</th>
<th width="220" align="center">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach($teacherRows as $row) { ?>
<?php
$st = ((string)($row['status'] ?? '0') === "1")
	? '<span class="me-1 badge badge-pill bg-success">Active</span>'
	: '<span class="me-1 badge badge-pill bg-danger">Blocked</span>';
?>
<tr>
<td>
<input class="form-check-input staff-checkbox" type="checkbox" name="staff_ids[]" value="<?php echo $row['id']; ?>">
</td>
<td><?php echo htmlspecialchars($row['fname']);?></td>
<td><?php echo htmlspecialchars($row['lname']);?></td>
<td><?php echo htmlspecialchars($row['school_id'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['email']);?></td>
<td><?php echo htmlspecialchars($row['gender']);?></td>
<td>Teacher</td>
<td>
<?php if (isset($onlineStaff[(string)$row['id']])) { ?>
<span class="online-pill"><span class="online-dot"></span>Online</span>
<?php } else { ?>
<span class="text-muted">Offline</span>
<?php } ?>
</td>
<td width="100" align="center"><?php echo $st;?></td>
<td width="120" align="center">
<textarea style="display:none;" id="fname_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['fname']); ?></textarea>
<textarea style="display:none;" id="lname_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['lname']); ?></textarea>
<textarea style="display:none;" id="email_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['email']); ?></textarea>
<textarea style="display:none;" id="role_<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['level']); ?></textarea>
<button onclick="set_user('<?php echo (int)$row['id']; ?>', '<?php echo htmlspecialchars((string)$row['gender'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)$row['status'], ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars((string)$row['level'], ENT_QUOTES, 'UTF-8'); ?>');" data-bs-toggle="modal" data-bs-target="#editModal" class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<form method="POST" action="admin/core/start_impersonation" style="display:inline-block;">
<input type="hidden" name="target_type" value="staff">
<input type="hidden" name="target_id" value="<?php echo (int)$row['id']; ?>">
<button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Impersonate this staff account now?');">Impersonate</button>
</form>
<a onclick="del('admin/core/drop_user2?id=<?php echo (int)$row['id']; ?>', 'Delete this staff account?');" href="javascript:void(0);" class="btn btn-danger btn-sm">Delete</a>
</td>
</tr>
<?php } ?>

</tbody>
</table>
</form>
</div>
</div>
</div>
</div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script type="text/javascript">
$('#srmsTable').DataTable({"sort" : false});
if ($('#adminStaffTable').length) {
	$('#adminStaffTable').DataTable({"sort" : false});
}
</script>
<script type="text/javascript">
function designationRoleValue(designation){
	switch (designation) {
		case 'headteacher': return '0';
		case 'deputy_headteacher': return '1';
		case 'senior_teacher': return '2';
		case 'accountant': return '5';
		default: return '2';
	}
}
function syncAdminRole(designation){
	var role = document.getElementById('adminRoleField');
	if (role) { role.value = designationRoleValue(designation); }
}
function syncAdminEditRole(designation){
	var role = document.getElementById('admin_role_edit');
	if (role) { role.value = designationRoleValue(designation); }
}
function set_user(id, gender, status, role){
	document.getElementById("id").value = id;
	document.getElementById("fname").value = document.getElementById("fname_"+id).value;
	document.getElementById("lname").value = document.getElementById("lname_"+id).value;
	document.getElementById("email").value = document.getElementById("email_"+id).value;
	document.getElementById("gender").value = gender;
	document.getElementById("status").value = status;
	document.getElementById("role").value = role;
}
function set_admin_user(id, gender, status){
	document.getElementById("admin_id").value = id;
	document.getElementById("admin_fname").value = document.getElementById("admin_fname_"+id).value;
	document.getElementById("admin_lname").value = document.getElementById("admin_lname_"+id).value;
	document.getElementById("admin_email").value = document.getElementById("admin_email_"+id).value;
	document.getElementById("admin_gender").value = gender;
	document.getElementById("admin_status").value = status;
	var designation = document.getElementById("admin_designation_"+id).value || 'headteacher';
	document.getElementById("admin_designation_edit").value = designation;
	syncAdminEditRole(designation);
}
function resetAddStaffModal(){
	var role = document.querySelector('#addModal select[name="role"]');
	var status = document.querySelector('#addModal select[name="status"]');
	if (role) { role.value = '2'; }
	if (status) { status.value = '1'; }
}
function resetAddAdminModal(){
	var role = document.querySelector('#addAdminModal input[name="role"]');
	var designation = document.getElementById('adminDesignation');
	var status = document.querySelector('#addAdminModal select[name="status"]');
	if (designation) { designation.value = 'headteacher'; }
	if (role) { role.value = '0'; }
	if (status) { status.value = '1'; }
}
document.getElementById('addModal')?.addEventListener('show.bs.modal', resetAddStaffModal);
document.getElementById('addAdminModal')?.addEventListener('show.bs.modal', resetAddAdminModal);
function confirmBulkDelete(label){
  var checked = document.querySelectorAll('.staff-checkbox:checked');
  if (!checked.length) {
    alert('Please select at least one staff member.');
    return false;
  }
  var action = document.querySelector('select[name="bulk_action"]');
  var val = action ? action.value : 'delete';
  if (val === 'delete') {
    return confirm('Delete selected ' + label + '? This action cannot be undone.');
  }
  return confirm('Update status for selected ' + label + '?');
}
function bindSelectAll(sourceId, targetClass) {
  var source = document.getElementById(sourceId);
  if (!source) return;
  source.addEventListener('change', function(){
    document.querySelectorAll(targetClass).forEach(function(cb){
      cb.checked = source.checked;
    });
  });
}
bindSelectAll('selectAllStaff', '.staff-checkbox');
bindSelectAll('selectAllStaffHead', '.staff-checkbox');
</script>
<?php require_once('const/check-reply.php'); ?>
</body>

</html>
