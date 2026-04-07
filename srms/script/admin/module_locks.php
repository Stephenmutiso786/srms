<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('system.manage', 'admin');

$modules = [
	'exams' => 'Exams',
	'reports' => 'Reports',
	'finance' => 'Finance',
	'students' => 'Students',
	'staff' => 'Staff',
	'communication' => 'Communication',
	'transport' => 'Transport',
	'library' => 'Library',
	'inventory' => 'Inventory'
];

$locks = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_module_locks')) {
		$stmt = $conn->prepare("SELECT module, locked, reason, locked_at FROM tbl_module_locks");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$locks[$row['module']] = $row;
		}
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load module locks."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Module Locks - Elimu Hub</title>
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

<?php include('admin/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Module Locks</h1>
<p>Lock modules system-wide (Super Admin only).</p>
</div>
</div>

<div class="tile">
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Module</th><th>Status</th><th>Reason</th><th>Locked At</th><th></th></tr></thead>
<tbody>
<?php foreach ($modules as $key => $label): 
	$lock = $locks[$key] ?? ['locked' => 0, 'reason' => '', 'locked_at' => null];
	$isLocked = (int)$lock['locked'] === 1;
?>
<tr>
<td><?php echo htmlspecialchars($label); ?></td>
<td><?php echo $isLocked ? '<span class="badge bg-danger">LOCKED</span>' : '<span class="badge bg-success">OPEN</span>'; ?></td>
<td><?php echo htmlspecialchars((string)($lock['reason'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)($lock['locked_at'] ?? '')); ?></td>
<td>
  <form class="d-flex gap-2" action="admin/core/set_module_lock" method="POST">
	<input type="hidden" name="module" value="<?php echo htmlspecialchars($key); ?>">
	<input class="form-control form-control-sm" name="reason" placeholder="Reason (optional)" value="<?php echo htmlspecialchars((string)($lock['reason'] ?? '')); ?>">
	<?php if ($isLocked) { ?>
	  <button class="btn btn-sm btn-success" name="locked" value="0">Unlock</button>
	<?php } else { ?>
	  <button class="btn btn-sm btn-danger" name="locked" value="1">Lock</button>
	<?php } ?>
  </form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
