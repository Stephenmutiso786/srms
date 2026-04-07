<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('staff.manage', 'admin');

$roles = [];
$staff = [];
$assignments = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_roles') || !app_table_exists($conn, 'tbl_user_roles')) {
		throw new RuntimeException("RBAC tables missing. Run migration 012.");
	}

	$stmt = $conn->prepare("SELECT id, name, description FROM tbl_roles ORDER BY level DESC, name");
	$stmt->execute();
	$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, lname) AS name, level FROM tbl_staff ORDER BY id");
	$stmt->execute();
	$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT ur.staff_id, ur.role_id, r.name AS role_name, s.fname, s.lname
		FROM tbl_user_roles ur
		JOIN tbl_roles r ON r.id = ur.role_id
		JOIN tbl_staff s ON s.id = ur.staff_id
		ORDER BY s.id");
	$stmt->execute();
	$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Roles & Permissions - Elimu Hub</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/roles"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Roles & Permissions</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Roles & Permissions</h1>
<p>Assign enterprise roles to staff.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Assign Role</h3>
<form class="app_frm" action="admin/core/assign_role" method="POST">
<div class="mb-3">
<label class="form-label">Staff</label>
<select class="form-control" name="staff_id" required>
<option value="">Select</option>
<?php foreach ($staff as $s): ?>
<option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'].' (#'.$s['id'].')'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Role</label>
<select class="form-control" name="role_id" required>
<option value="">Select</option>
<?php foreach ($roles as $r): ?>
<option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="btn btn-primary">Assign Role</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Current Assignments</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Staff</th><th>Role</th><th></th></tr></thead>
<tbody>
<?php foreach ($assignments as $a): ?>
<tr>
<td><?php echo htmlspecialchars(trim($a['fname'].' '.$a['lname']).' (#'.$a['staff_id'].')'); ?></td>
<td><?php echo htmlspecialchars($a['role_name']); ?></td>
<td>
  <form class="d-inline" action="admin/core/remove_role" method="POST">
	<input type="hidden" name="staff_id" value="<?php echo (int)$a['staff_id']; ?>">
	<input type="hidden" name="role_id" value="<?php echo (int)$a['role_id']; ?>">
	<button class="btn btn-sm btn-outline-danger">Remove</button>
  </form>
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
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
