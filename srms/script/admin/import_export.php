<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('students.manage', 'admin');

$classes = [];
$terms = [];
$subjects = [];
$imports = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_import_logs')) {
		$stmt = $conn->prepare("SELECT import_type, total, success, failed, created_at FROM tbl_import_logs ORDER BY created_at DESC LIMIT 10");
		$stmt->execute();
		$imports = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load import/export data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Import / Export - Elimu Hub</title>
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
<h1>Import / Export Center</h1>
<p>Bulk upload via CSV and export data in CSV/PDF.</p>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Import Students (CSV)</h3>
<p class="text-muted">Headers: student_id,fname,mname,lname,gender,email,class_id OR class_name</p>
<form class="app_frm" action="admin/core/import_students_csv" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<input class="form-control" type="file" name="file" accept=".csv" required>
</div>
<button class="btn btn-primary">Import Students</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Import Teachers (CSV)</h3>
<p class="text-muted">Headers: fname,lname,gender,email,phone (email required)</p>
<form class="app_frm" action="admin/core/import_staff_csv" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<input class="form-control" type="file" name="file" accept=".csv" required>
</div>
<button class="btn btn-primary">Import Teachers</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Import Marks (CSV)</h3>
<p class="text-muted">Headers: student_id,class_id,term_id,subject_id,score</p>
<form class="app_frm" action="admin/core/import_marks_csv" method="POST" enctype="multipart/form-data">
<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select</option>
<?php foreach ($terms as $t): ?>
<option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select</option>
<?php foreach ($classes as $c): ?>
<option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Subject</label>
<select class="form-control" name="subject_id" required>
<option value="">Select</option>
<?php foreach ($subjects as $s): ?>
<option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
</div>
<div class="mb-3">
<input class="form-control" type="file" name="file" accept=".csv" required>
</div>
<button class="btn btn-primary">Import Marks</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Import CBC Assessments (CSV)</h3>
<p class="text-muted">Headers: student_id,class_id,term_id,learning_area,strand,level(EE/ME/AE/BE)</p>
<form class="app_frm" action="admin/core/import_cbc_csv" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<input class="form-control" type="file" name="file" accept=".csv" required>
</div>
<button class="btn btn-primary">Import CBC</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Exports</h3>
<div class="d-flex flex-wrap gap-2">
<a class="btn btn-outline-primary" href="admin/core/export_students?format=csv">Export Students (CSV)</a>
<a class="btn btn-outline-secondary" href="admin/core/export_students?format=pdf">Export Students (PDF)</a>
</div>
<hr>
<form class="d-flex flex-wrap gap-2 align-items-end" action="admin/core/export_results" method="GET">
<div>
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select</option>
<?php foreach ($classes as $c): ?>
<option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select</option>
<?php foreach ($terms as $t): ?>
<option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Format</label>
<select class="form-control" name="format">
<option value="csv">CSV</option>
</select>
</div>
<button class="btn btn-outline-primary">Export Results</button>
</form>
<hr>
<form class="d-flex flex-wrap gap-2 align-items-end" action="admin/core/export_cbc" method="GET">
<div>
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select</option>
<?php foreach ($terms as $t): ?>
<option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Format</label>
<select class="form-control" name="format">
<option value="csv">CSV</option>
</select>
</div>
<button class="btn btn-outline-primary">Export CBC</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Recent Imports</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Type</th><th>Total</th><th>Success</th><th>Failed</th><th>Date</th></tr></thead>
<tbody>
<?php foreach ($imports as $imp): ?>
<tr>
<td><?php echo htmlspecialchars($imp['import_type']); ?></td>
<td><?php echo (int)$imp['total']; ?></td>
<td><?php echo (int)$imp['success']; ?></td>
<td><?php echo (int)$imp['failed']; ?></td>
<td><?php echo htmlspecialchars($imp['created_at']); ?></td>
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
