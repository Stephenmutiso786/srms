<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('exams.manage', 'admin');
app_require_unlocked('exams', 'admin');

$types = [];
$exams = [];
$classes = [];
$terms = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_exam_types')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_exam_types ORDER BY name");
		$stmt->execute();
		$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_exams')) {
		$stmt = $conn->prepare("SELECT e.id, e.name, e.status, t.name AS term_name, c.name AS class_name, et.name AS type_name
			FROM tbl_exams e
			LEFT JOIN tbl_terms t ON t.id = e.term_id
			LEFT JOIN tbl_classes c ON c.id = e.class_id
			LEFT JOIN tbl_exam_types et ON et.id = e.exam_type_id
			ORDER BY e.created_at DESC");
		$stmt->execute();
		$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load exam data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Exams - Elimu Hub</title>
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
<li><a class="app-menu__item active" href="admin/exams"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span></a></li>
<li><a class="app-menu__item" href="admin/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Exam Management</h1>
<p>Create exams, manage types, and keep the schedule aligned.</p>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Create Exam Type</h3>
<form class="app_frm" action="admin/core/new_exam_type" method="POST">
<div class="mb-3">
<label class="form-label">Type Name</label>
<input class="form-control" name="name" required placeholder="CAT, Midterm, End Term">
</div>
<button class="btn btn-primary">Save Type</button>
</form>

<hr>
<h3 class="tile-title">Exam Types</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Name</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($types as $type): ?>
<tr>
<td><?php echo htmlspecialchars($type['name']); ?></td>
<td><?php echo ((int)$type['status'] === 1) ? 'Active' : 'Inactive'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Create Exam</h3>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-outline-primary btn-sm" href="admin/exam_timetable"><i class="bi bi-calendar-event me-1"></i>Manage Timetable</a>
	<a class="btn btn-outline-secondary btn-sm" href="admin/results_locks"><i class="bi bi-lock me-1"></i>Results Locks</a>
</div>
<form class="app_frm" action="admin/core/new_exam" method="POST">
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Exam Name</label>
<input class="form-control" name="name" required placeholder="Term 1 End Term">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Exam Type</label>
<select class="form-control" name="exam_type_id">
<option value="">Optional</option>
<?php foreach ($types as $type): ?>
<option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select</option>
<?php foreach ($classes as $class): ?>
<option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select</option>
<?php foreach ($terms as $term): ?>
<option value="<?php echo $term['id']; ?>"><?php echo htmlspecialchars($term['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<button class="btn btn-primary">Create Exam</button>
</form>

<hr>
<h3 class="tile-title">Recent Exams</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr><th>Name</th><th>Type</th><th>Class</th><th>Term</th><th>Status</th><th>Action</th></tr>
</thead>
<tbody>
<?php foreach ($exams as $exam): ?>
<tr>
<td><?php echo htmlspecialchars($exam['name']); ?></td>
<td><?php echo htmlspecialchars($exam['type_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($exam['class_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($exam['term_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($exam['status']); ?></td>
<td>
	<form class="d-inline" action="admin/core/update_exam_status" method="POST">
		<input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
		<?php if (($exam['status'] ?? '') === 'open') { ?>
			<button class="btn btn-sm btn-outline-danger" name="status" value="closed">Close</button>
		<?php } else { ?>
			<button class="btn btn-sm btn-outline-success" name="status" value="open">Reopen</button>
		<?php } ?>
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
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
