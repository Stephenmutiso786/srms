<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('transport.manage', 'admin');
app_require_unlocked('transport', 'admin');

$vehicles = [];
$routes = [];
$stops = [];
$students = [];
$drivers = [];
$assignments = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_vehicles')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_vehicles ORDER BY id DESC");
		$stmt->execute();
		$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_routes')) {
		$stmt = $conn->prepare("SELECT r.id, r.name, v.plate_number FROM tbl_routes r LEFT JOIN tbl_vehicles v ON v.id = r.vehicle_id ORDER BY r.id DESC");
		$stmt->execute();
		$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_route_stops')) {
		$stmt = $conn->prepare("SELECT s.id, s.route_id, s.stop_name, s.stop_order, r.name AS route_name
			FROM tbl_route_stops s
			LEFT JOIN tbl_routes r ON r.id = s.route_id
			ORDER BY s.route_id, s.stop_order");
		$stmt->execute();
		$stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_transport_assignments')) {
		$stmt = $conn->prepare("SELECT a.id, a.student_id, r.name AS route_name, s.stop_name
			FROM tbl_transport_assignments a
			LEFT JOIN tbl_routes r ON r.id = a.route_id
			LEFT JOIN tbl_route_stops s ON s.id = a.stop_id
			ORDER BY a.created_at DESC LIMIT 50");
		$stmt->execute();
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, mname, lname) AS name FROM tbl_students ORDER BY id");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, lname) AS name FROM tbl_staff ORDER BY id");
	$stmt->execute();
	$drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load transport data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Transport - Elimu Hub</title>
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
<li><a class="app-menu__item active" href="admin/transport"><i class="app-menu__icon feather icon-truck"></i><span class="app-menu__label">Transport</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Transport & Fleet</h1>
<p>Manage vehicles, routes, stops, and student assignments.</p>
</div>
</div>

<div class="row">
<div class="col-md-4">
<div class="tile">
<h3 class="tile-title">Add Vehicle</h3>
<form class="app_frm" action="admin/core/new_vehicle" method="POST">
<div class="mb-3">
<label class="form-label">Plate Number</label>
<input class="form-control" name="plate_number" required>
</div>
<div class="mb-3">
<label class="form-label">Model</label>
<input class="form-control" name="model">
</div>
<div class="mb-3">
<label class="form-label">Capacity</label>
<input type="number" class="form-control" name="capacity" min="0" value="0">
</div>
<div class="mb-3">
<label class="form-label">Driver</label>
<select class="form-control" name="driver_id">
<option value="">None</option>
<?php foreach ($drivers as $d): ?>
<option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="btn btn-primary">Save Vehicle</button>
</form>
</div>
</div>

<div class="col-md-4">
<div class="tile">
<h3 class="tile-title">Create Route</h3>
<form class="app_frm" action="admin/core/new_route" method="POST">
<div class="mb-3">
<label class="form-label">Route Name</label>
<input class="form-control" name="name" required>
</div>
<div class="mb-3">
<label class="form-label">Vehicle</label>
<select class="form-control" name="vehicle_id">
<option value="">None</option>
<?php foreach ($vehicles as $v): ?>
<option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['plate_number']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="btn btn-primary">Save Route</button>
</form>
</div>
</div>

<div class="col-md-4">
<div class="tile">
<h3 class="tile-title">Add Route Stop</h3>
<form class="app_frm" action="admin/core/new_route_stop" method="POST">
<div class="mb-3">
<label class="form-label">Route</label>
<select class="form-control" name="route_id" required>
<option value="">Select</option>
<?php foreach ($routes as $r): ?>
<option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Stop Name</label>
<input class="form-control" name="stop_name" required>
</div>
<div class="mb-3">
<label class="form-label">Stop Order</label>
<input type="number" class="form-control" name="stop_order" min="1" value="1">
</div>
<button class="btn btn-primary">Save Stop</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Assign Student</h3>
<form class="app_frm" action="admin/core/new_transport_assignment" method="POST">
<div class="mb-3">
<label class="form-label">Student</label>
<select class="form-control" name="student_id" required>
<option value="">Select</option>
<?php foreach ($students as $s): ?>
<option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name'].' ('.$s['id'].')'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Route</label>
<select class="form-control" name="route_id" required>
<option value="">Select</option>
<?php foreach ($routes as $r): ?>
<option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Stop (optional)</label>
<select class="form-control" name="stop_id">
<option value="">None</option>
<?php foreach ($stops as $s): ?>
<option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars(($s['route_name'] ?? '').' - '.$s['stop_name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="btn btn-primary">Assign</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Assignments</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Student</th><th>Route</th><th>Stop</th></tr></thead>
<tbody>
<?php foreach ($assignments as $a): ?>
<tr>
<td><?php echo htmlspecialchars($a['student_id']); ?></td>
<td><?php echo htmlspecialchars($a['route_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($a['stop_name'] ?? '-'); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Vehicles</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Plate</th><th>Model</th><th>Capacity</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($vehicles as $v): ?>
<tr>
<td><?php echo htmlspecialchars($v['plate_number']); ?></td>
<td><?php echo htmlspecialchars($v['model']); ?></td>
<td><?php echo (int)$v['capacity']; ?></td>
<td><?php echo htmlspecialchars($v['status']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Routes</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Name</th><th>Vehicle</th></tr></thead>
<tbody>
<?php foreach ($routes as $r): ?>
<tr>
<td><?php echo htmlspecialchars($r['name']); ?></td>
<td><?php echo htmlspecialchars($r['plate_number'] ?? '-'); ?></td>
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
