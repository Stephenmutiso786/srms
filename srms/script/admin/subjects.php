<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('exams.manage', '../admin');

$subjects = [];
$classes = [];
$assignments = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("SELECT s.id AS subject_id, s.name AS subject_name, c.id AS class_id, c.name AS class_name
			FROM tbl_subject_class_assignments sc
			JOIN tbl_subjects s ON s.id = sc.subject_id
			JOIN tbl_classes c ON c.id = sc.class_id
			ORDER BY s.name, c.name");
		$stmt->execute();
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", "Failed to load subjects."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Subject Catalog</title>
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
<h1>Subject Catalog</h1>
<p>Keep the school subject list here, then use <a href="admin/classes">Class Management</a> to decide which class or stream studies each subject.</p>
</div>
</div>

<div class="tile mb-3">
<div class="tile-body">
<div class="alert alert-info mb-0">
<strong>Source of truth:</strong> Class Management controls class streams, class teachers, and the subjects each class studies. This page only maintains the reusable school subject catalog and offers an optional shortcut for bulk class links.
</div>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<div class="tile-body">
<h3 class="tile-title">Create / Update Subject</h3>
<form class="app_frm" method="POST" action="admin/core/subject_save">
<input type="hidden" name="subject_id" id="subject_id" value="0">
<div class="mb-2">
<label class="form-label">Subject Name</label>
<input class="form-control" name="name" id="subject_name" required>
</div>
<div class="mb-3">
<label class="form-label">Assign to Classes</label>
<select class="form-control" name="class_ids[]" id="class_ids" multiple>
<?php foreach ($classes as $class): ?>
<option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
<?php endforeach; ?>
</select>
<div class="form-text">Optional shortcut only. The preferred setup flow is still through <a href="admin/classes">Class Management</a>.</div>
</div>
<div class="d-flex gap-2">
<button class="btn btn-primary">Save Subject</button>
<button type="button" class="btn btn-light" id="resetSubjectForm">Reset</button>
</div>
</form>
</div>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<div class="tile-body">
<h3 class="tile-title">Subjects & Current Class Links</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Subject</th>
<th>Classes</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php
$subjectClasses = [];
foreach ($assignments as $row) {
	$subjectClasses[$row['subject_id']]['name'] = $row['subject_name'];
	$subjectClasses[$row['subject_id']]['classes'][] = $row['class_name'];
	$subjectClasses[$row['subject_id']]['class_ids'][] = (int)$row['class_id'];
}
foreach ($subjects as $subject):
	$classesList = $subjectClasses[$subject['id']]['classes'] ?? [];
?>
<tr>
<td><?php echo htmlspecialchars($subject['name']); ?></td>
<td><?php echo htmlspecialchars(implode(', ', $classesList)); ?></td>
<td>
<button class="btn btn-sm btn-outline-primary edit-subject"
	data-id="<?php echo $subject['id']; ?>"
	data-name="<?php echo htmlspecialchars($subject['name']); ?>"
	data-class-ids="<?php echo htmlspecialchars(json_encode($subjectClasses[$subject['id']]['class_ids'] ?? [])); ?>">
	Edit
</button>
<a onclick="del('admin/core/subject_delete?id=<?php echo $subject['id']; ?>', 'Delete subject?');" href="javascript:void(0);" class="btn btn-sm btn-danger">Delete</a>
</td>
</tr>
<?php endforeach; ?>
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
<script src="js/sweetalert2@11.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.edit-subject').on('click', function () {
	const subjectId = $(this).data('id');
	const name = $(this).data('name');
	const classIds = $(this).data('class-ids');
	$('#subject_id').val(subjectId);
	$('#subject_name').val(name);
	$('#class_ids option').prop('selected', false);
	if (Array.isArray(classIds)) {
		$('#class_ids option').each(function () {
			if (classIds.includes(parseInt($(this).val(), 10))) {
				$(this).prop('selected', true);
			}
		});
	}
});
$('#resetSubjectForm').on('click', function () {
	$('#subject_id').val('0');
	$('#subject_name').val('');
	$('#class_ids option').prop('selected', false);
});
</script>
</body>
</html>
