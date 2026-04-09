<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('students.manage', '../admin');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Class Management</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Class Management</h1>
<p>Create classes/streams and keep them ready for subject assignment, teacher allocation, exams, and report cards.</p>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add Class</button></li>
</ul>
</div>

<div class="tile mb-3">
<div class="tile-body">
<div class="alert alert-info mb-0">
<strong>Flexible setup:</strong> a teacher can be allocated to multiple subjects in the same class and also to multiple classes. Manage the classes here, then use <a href="admin/teacher_allocation">Teacher Allocation</a> to map teachers to class-subject pairs.
</div>
</div>
</div>

<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Class</h5></div><div class="modal-body">
<form class="app_frm" method="POST" autocomplete="off" action="admin/core/new_class">
<div class="mb-3"><label class="form-label">Grade / Class</label><input required name="grade_name" class="form-control" type="text" placeholder="e.g. Grade 8"></div>
<div class="mb-3"><label class="form-label">Stream / Section</label><input name="stream_name" class="form-control" type="text" placeholder="e.g. A"></div>
<div class="form-text mb-3">The system will save this as one class name, for example <strong>Grade 8 A</strong>.</div>
<input type="hidden" name="name" value="">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div></div></div>
</div>

<div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Class</h5></div><div class="modal-body">
<form class="app_frm" method="POST" autocomplete="off" action="admin/core/update_class">
<div class="mb-3"><label class="form-label">Grade / Class</label><input id="grade_name" required name="grade_name" class="form-control" type="text" placeholder="e.g. Grade 8"></div>
<div class="mb-3"><label class="form-label">Stream / Section</label><input id="stream_name" name="stream_name" class="form-control" type="text" placeholder="e.g. A"></div>
<div class="form-text mb-3">Edit grade and stream separately; the system combines them into one class name.</div>
<input id="name" name="name" type="hidden">
<input type="hidden" name="id" id="id">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Save</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div></div></div>
</div>

<div class="row"><div class="col-md-12"><div class="tile"><div class="tile-body"><div class="table-responsive">
<h3 class="tile-title">Classes</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead><tr><th>Grade</th><th>Stream</th><th>Saved Class Name</th><th>Added On</th><th width="140"></th></tr></thead>
<tbody>
<?php
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT * FROM tbl_classes ORDER BY name");
	$stmt->execute();
	foreach($stmt->fetchAll() as $row) {
		$parts = app_class_name_parts((string)$row[1]);
?>
<tr>
<td><?php echo htmlspecialchars($parts['grade']); ?></td>
<td><?php echo htmlspecialchars($parts['stream'] !== '' ? $parts['stream'] : '—'); ?></td>
<td><?php echo htmlspecialchars($row[1]); ?></td>
<td><?php echo htmlspecialchars($row[2] ?? ''); ?></td>
<td align="center">
<button
	type="button"
	class="btn btn-primary btn-sm edit-class"
	data-id="<?php echo $row[0]; ?>"
	data-name="<?php echo htmlspecialchars($row[1]); ?>"
	data-grade="<?php echo htmlspecialchars($parts['grade']); ?>"
	data-stream="<?php echo htmlspecialchars($parts['stream']); ?>"
	data-bs-toggle="modal"
	data-bs-target="#editModal">Edit</button>
<a onclick="del('admin/core/drop_class?id=<?php echo $row[0]; ?>', 'Delete Class?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
</td>
</tr>
<?php
	}
} catch (Throwable $e) {
	echo '<tr><td colspan="3">Failed to load classes.</td></tr>';
}
?>
</tbody>
</table>
</div></div></div></div></div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script type="text/javascript">$('#srmsTable').DataTable({"sort":false});</script>
<script>
$('.edit-class').on('click', function () {
	$('#id').val($(this).data('id'));
	$('#name').val($(this).data('name'));
	$('#grade_name').val($(this).data('grade'));
	$('#stream_name').val($(this).data('stream'));
});
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
