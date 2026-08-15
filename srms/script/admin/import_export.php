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
	$terms = app_sort_term_rows($terms);

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
<title>Import / Export - <?php echo APP_NAME; ?></title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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
<h1>Import / Export Center</h1>
<p>Bulk upload via CSV and export data in CSV/PDF.</p>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Import Students (CSV)</h3>
<p class="text-muted">Minimum headers: first_name,last_name. Admission number and email are generated automatically.</p>
<form class="app_frm" action="admin/core/import_students_csv" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select class</option>
<?php foreach ($classes as $c): ?>
<option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars((string)$c['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<input class="form-control" type="file" name="file" accept=".csv">
</div>
<button class="btn btn-primary">Import Students</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Import Teachers (CSV)</h3>
<p class="text-muted">Minimum headers: first_name,last_name. Email is optional; password defaults to Password123 and teachers must change it on first login.</p>
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
<p class="text-muted">You can paste marks in class list order or upload CSV. Pasted lines may be either <code>student name,score</code> or just <code>score</code> per line.</p>
<div class="alert alert-info">Paste mode follows the class list order shown in the system. If you paste names, the system will match by student name. Existing scores are not overwritten.</div>
<form class="app_frm" action="admin/core/import_marks_csv" method="POST" enctype="multipart/form-data">
<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label">Term</label>
<select class="form-control" name="term_id" id="marksTermSelect" required onchange="loadMarksExamOptions();">
<option value="">Select</option>
<?php foreach ($terms as $t): ?>
<option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" id="marksClassSelect" required onchange="loadMarksExamOptions();">
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
<div class="col-md-12 mb-3">
<label class="form-label">Exam</label>
<select class="form-control" name="exam_id" id="marksExamSelect" required>
<option value="">Select class and term first</option>
</select>
</div>
</div>
<div class="mb-3">
<label class="form-label">Paste Marks</label>
<textarea class="form-control" name="paste_marks" rows="10" placeholder="Examples:
John Smith, 84
Mary Ann 76
71
68
66"></textarea>
<div class="form-text">Use one row per student. If you paste only scores, they will be applied top-to-bottom in the current class list order. If you paste names, the system will match them to the class list.</div>
</div>
<div class="mb-2">
<div class="text-center fw-semibold text-uppercase small text-muted my-2">or</div>
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
<h3 class="tile-title">Import CBE Assessments (CSV)</h3>
<p class="text-muted">Headers: student_id,class_id,term_id,learning_area,strand,level(EE/ME/AE/BE)</p>
<form class="app_frm" action="admin/core/import_cbe_csv" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<input class="form-control" type="file" name="file" accept=".csv" required>
</div>
<button class="btn btn-primary">Import CBE</button>
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
<a class="btn btn-outline-primary" href="admin/core/export_teachers?format=csv">Export Teachers (CSV)</a>
<a class="btn btn-outline-secondary" href="admin/core/export_teachers?format=pdf">Export Teachers (PDF)</a>
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
<form class="d-flex flex-wrap gap-2 align-items-end" action="admin/core/export_cbe" method="GET">
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
<button class="btn btn-outline-primary">Export CBE</button>
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
<script>
function loadMarksExamOptions() {
	const classId = parseInt(document.getElementById('marksClassSelect').value || '0', 10);
	const termId = parseInt(document.getElementById('marksTermSelect').value || '0', 10);
	const examSelect = document.getElementById('marksExamSelect');
	if (classId < 1 || termId < 1) {
		examSelect.innerHTML = '<option value="">Select class and term first</option>';
		return;
	}
	$.post('app/ajax/fetch_exams.php', {id: classId, term_id: termId, include_unpublished: 1, submit: 1}, function(data){
		examSelect.innerHTML = data;
	});
}
</script>
</body>
</html>
