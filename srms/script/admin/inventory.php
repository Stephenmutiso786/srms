<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('inventory.manage', 'admin');
app_require_unlocked('inventory', 'admin');

$assets = [];
$logs = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_assets')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_assets ORDER BY id DESC LIMIT 50");
		$stmt->execute();
		$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_asset_logs')) {
		$stmt = $conn->prepare("SELECT l.id, a.name, l.action, l.quantity, l.note, l.created_at
			FROM tbl_asset_logs l
			LEFT JOIN tbl_assets a ON a.id = l.asset_id
			ORDER BY l.created_at DESC LIMIT 50");
		$stmt->execute();
		$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load inventory data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Inventory - Elimu Hub</title>
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
<li><a class="app-menu__item active" href="admin/inventory"><i class="app-menu__icon feather icon-box"></i><span class="app-menu__label">Inventory</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Inventory & Assets</h1>
<p>Track assets, stock, and adjustments.</p>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Add Asset</h3>
<form class="app_frm" action="admin/core/new_asset" method="POST">
<div class="mb-3">
<label class="form-label">Name</label>
<input class="form-control" name="name" required>
</div>
<div class="mb-3">
<label class="form-label">Category</label>
<input class="form-control" name="category">
</div>
<div class="mb-3">
<label class="form-label">Quantity</label>
<input type="number" class="form-control" name="quantity" min="0" value="0" required>
</div>
<div class="mb-3">
<label class="form-label">Location</label>
<input class="form-control" name="location">
</div>
<button class="btn btn-primary">Save Asset</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Adjust Stock</h3>
<form class="app_frm" action="admin/core/adjust_asset" method="POST">
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Asset</label>
<select class="form-control" name="asset_id" required>
<option value="">Select</option>
<?php foreach ($assets as $a): ?>
<option value="<?php echo $a['id']; ?>"><?php echo htmlspecialchars($a['name']); ?> (<?php echo (int)$a['quantity']; ?>)</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Action</label>
<select class="form-control" name="action" required>
<option value="add">Add</option>
<option value="issue">Issue</option>
<option value="return">Return</option>
<option value="dispose">Dispose</option>
<option value="update">Update</option>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Quantity</label>
<input type="number" class="form-control" name="quantity" min="0" value="1" required>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Note</label>
<input class="form-control" name="note" placeholder="Optional note">
</div>
</div>
<button class="btn btn-primary">Apply</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Assets</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Name</th><th>Category</th><th>Qty</th><th>Location</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($assets as $a): ?>
<tr>
<td><?php echo htmlspecialchars($a['name']); ?></td>
<td><?php echo htmlspecialchars($a['category']); ?></td>
<td><?php echo (int)$a['quantity']; ?></td>
<td><?php echo htmlspecialchars($a['location']); ?></td>
<td><?php echo htmlspecialchars($a['status']); ?></td>
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
<h3 class="tile-title">Recent Adjustments</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Asset</th><th>Action</th><th>Qty</th><th>Note</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($logs as $l): ?>
<tr>
<td><?php echo htmlspecialchars($l['name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($l['action']); ?></td>
<td><?php echo (int)$l['quantity']; ?></td>
<td><?php echo htmlspecialchars($l['note']); ?></td>
<td><?php echo htmlspecialchars($l['created_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
