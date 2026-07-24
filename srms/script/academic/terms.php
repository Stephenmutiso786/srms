<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

$terms = [];
$defaultYearTerms = app_default_academic_year_terms();
$currentAcademicYear = date('Y');
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_terms_academic_year_schema($conn);
	$currentAcademicYear = trim(app_setting_get($conn, 'current_academic_year', date('Y')));
	$stmt = $conn->prepare("SELECT * FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
	app_sort_term_rows($terms);
} catch (Throwable $e) {
	$terms = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Academic Terms</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="academic/profile.php"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('academic/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Academic Terms</h1>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#yearModal">Add Academic Year</button></li>
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add</button></li>
</ul>
</div>

<div class="modal fade" id="yearModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="yearModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="yearModalLabel">Create Academic Year</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_academic_year.php">
<div class="mb-2">
<label class="form-label">Academic Year</label>
<input required name="academic_year" class="form-control" type="text" value="<?php echo htmlspecialchars($currentAcademicYear); ?>" placeholder="Enter Academic Year e.g. 2026 or 2026/2027">
</div>
<div class="mb-2">
<label class="form-label">Linked Terms</label>
<?php foreach ($defaultYearTerms as $index => $termLabel): ?>
<div class="form-check">
<input class="form-check-input" type="checkbox" name="term_names[]" id="academic_year_term_<?php echo $index; ?>" value="<?php echo htmlspecialchars($termLabel); ?>" checked>
<label class="form-check-label" for="academic_year_term_<?php echo $index; ?>"><?php echo htmlspecialchars($termLabel); ?></label>
</div>
<?php endforeach; ?>
<div class="form-text">Create the full year structure at once so later term pickers stay tied to the same academic year.</div>
</div>
<div class="mb-2">
<label class="form-label">Default Status</label>
<select class="form-control" name="status" required>
<option value="1">Active</option>
<option value="0">Inactive</option>
</select>
</div>
<div class="mb-3 form-check">
<input class="form-check-input" type="checkbox" name="set_current_year" id="set_current_year_academic" value="1" checked>
<label class="form-check-label" for="set_current_year_academic">Set as current academic year</label>
</div>

<button type="submit" class="btn btn-primary app_btn">Create Academic Year</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addModalLabel">Add Academic Term</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_term.php">
<div class="mb-2">
<label class="form-label">Term Name</label>
<input required name="name" class="form-control" type="text" placeholder="Enter Term Name e.g. Term One">
</div>
<div class="mb-2">
<label class="form-label">Academic Year</label>
<input required name="academic_year" class="form-control" type="text" placeholder="Enter Academic Year e.g. 2026 or 2026/2027">
</div>
<div class="mb-3">
<label class="form-label">Status</label>
<select class="form-control" name="status" required>
<option selected disabled value="">Select status</option>
<option value="1">Active</option>
<option value="0">Inactive</option>
</select>
</div>

<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add</button>
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
<h5 class="modal-title" id="editModalLabel">Edit Academic Term</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/update_term.php">
<div class="mb-2">
<label class="form-label">Term Name</label>
<input id="term" required name="name" class="form-control" type="text" placeholder="Enter Term Name e.g. Term One">
</div>
<div class="mb-2">
<label class="form-label">Academic Year</label>
<input id="academic_year" required name="academic_year" class="form-control" type="text" placeholder="Enter Academic Year e.g. 2026 or 2026/2027">
</div>
<div class="mb-3">
<label class="form-label">Status</label>
<select id="status" class="form-control" name="status" required>
<option selected disabled value="">Select status</option>
<option value="1">Active</option>
<option value="0">Inactive</option>
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

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Academic Terms</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>Term</th>
<th>Academic Year</th>
<th>Stored Name</th>
<th width="120" align="center">Status</th>
<th width="120" align="center"></th>
</tr>
</thead>
<tbody>
<?php foreach ($terms as $row): ?>
<?php
$termId = (int)($row['id'] ?? 0);
$storedName = (string)($row['name'] ?? '');
$baseName = app_term_base_name($storedName);
$academicYear = trim((string)($row['academic_year'] ?? app_extract_academic_year($storedName)));
?>
<textarea style="display:none;" id="term_<?php echo $termId; ?>"><?php echo htmlspecialchars($baseName); ?></textarea>
<textarea style="display:none;" id="term_year_<?php echo $termId; ?>"><?php echo htmlspecialchars($academicYear); ?></textarea>
<tr>
<td><?php echo htmlspecialchars($baseName !== '' ? $baseName : $storedName); ?></td>
<td><?php echo htmlspecialchars($academicYear); ?></td>
<td><small><?php echo htmlspecialchars($storedName); ?></small></td>
<td align="center"><?php if (($row['status'] ?? '') == "1") { print '<span class="me-1 badge badge-pill bg-success">ACTIVE</span>'; }else{ print '<span class="me-1 badge badge-pill bg-danger">INACTIVE</span>'; } ?></td>
<td align="center">
<a onclick="set_term('<?php echo $termId; ?>', '<?php echo htmlspecialchars((string)($row['status'] ?? '0')); ?>');" class="btn btn-primary btn-sm" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal">Edit</a>
<a onclick="del('academic/core/drop_term.php?id=<?php echo $termId; ?>', 'Delete Academic Term?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$terms): ?>
<tr><td colspan="5">Unable to load academic terms right now.</td></tr>
<?php endif; ?>

</tbody>
</table>
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
<script type="text/javascript">$('#srmsTable').DataTable({"sort" : false});</script>
<?php require_once('const/check-reply.php'); ?>
<script>
function set_term(id, status) {
	document.getElementById('id').value = id;
	document.getElementById('term').value = document.getElementById('term_' + id).value;
	document.getElementById('academic_year').value = document.getElementById('term_year_' + id).value;
	document.getElementById('status').value = status;
}
</script>
</body>

</html>
