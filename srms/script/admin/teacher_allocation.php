<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res != "1") { header("location:../"); exit; }
app_require_permission('teacher.allocate', '../admin');

$teachers = [];
$classes = [];
$subjects = [];
$terms = [];
$assignments = [];
$year = (int)date('Y');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = '2' ORDER BY fname, lname");
	$stmt->execute();
	$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$subjectClassMap = [];
	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("SELECT subject_id, class_id FROM tbl_subject_class_assignments");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$subjectClassMap[(int)$row['subject_id']][] = (int)$row['class_id'];
		}
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT ta.id, ta.teacher_id, ta.class_id, ta.subject_id, ta.term_id, ta.year,
			s.name AS subject_name, c.name AS class_name, t.name AS term_name,
			st.fname, st.lname
			FROM tbl_teacher_assignments ta
			JOIN tbl_staff st ON st.id = ta.teacher_id
			JOIN tbl_subjects s ON s.id = ta.subject_id
			JOIN tbl_classes c ON c.id = ta.class_id
			JOIN tbl_terms t ON t.id = ta.term_id
			ORDER BY ta.year DESC, t.id DESC, st.fname, s.name");
		$stmt->execute();
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", "Failed to load allocations."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Teacher Allocation</title>
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
<h1>Subject Teachers</h1>
<p>Assign teachers onto the class and stream structure that has already been set in <a href="admin/classes">Class Management</a>.</p>
</div>
</div>

<div class="tile mb-3">
<div class="tile-body">
<div class="alert alert-info mb-0">
<strong>How this fits together:</strong> first set up the class, stream, class teacher, and subjects in <a href="admin/classes">Class Management</a>. Then use this page to attach subject teachers to that structure. One teacher can still teach multiple subjects in one class and multiple classes across the school.
</div>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<div class="tile-body">
<h3 class="tile-title">Assign Subject Teacher</h3>
<form class="app_frm" method="POST" action="admin/core/teacher_assignment_save">
<input type="hidden" name="assignment_id" id="assignment_id" value="0">
<div class="mb-2">
<label class="form-label">Teacher</label>
<select class="form-control" name="teacher_id" id="teacher_id" required>
<option value="">Select teacher</option>
<?php foreach ($teachers as $row): ?>
<option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['fname'].' '.$row['lname']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" id="class_id" required>
<option value="">Select class</option>
<?php foreach ($classes as $row): ?>
<option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
<?php endforeach; ?>
</select>
<div class="form-text">Only subjects already linked to this class in Class Management will remain available below.</div>
</div>
<div class="mb-2">
<label class="form-label">Subject</label>
<select class="form-control" name="subject_id" id="subject_id" required>
<option value="">Select subject</option>
<?php foreach ($subjects as $row): $classesMap = $subjectClassMap[(int)$row['id']] ?? []; ?>
<option value="<?php echo $row['id']; ?>" data-classes="<?php echo htmlspecialchars(json_encode($classesMap)); ?>">
	<?php echo htmlspecialchars($row['name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Term</label>
<select class="form-control" name="term_id" id="term_id" required>
<option value="">Select term</option>
<?php foreach ($terms as $row): ?>
<option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Year</label>
<input class="form-control" type="number" name="year" id="year" value="<?php echo $year; ?>" required>
</div>
<div class="d-flex gap-2">
<button class="btn btn-primary">Save Allocation</button>
<button type="button" class="btn btn-light" id="resetForm">Reset</button>
</div>
</form>
</div>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<div class="tile-body">
<h3 class="tile-title">Current Subject Teacher Allocations</h3>
<p class="text-muted">Use this list to review which teachers are handling each class subject after class setup has been defined.</p>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Teacher</th>
<th>Class</th>
<th>Subject</th>
<th>Term</th>
<th>Year</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($assignments as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['fname'].' '.$row['lname']); ?></td>
<td><?php echo htmlspecialchars($row['class_name']); ?></td>
<td><?php echo htmlspecialchars($row['subject_name']); ?></td>
<td><?php echo htmlspecialchars($row['term_name']); ?></td>
<td><?php echo (int)$row['year']; ?></td>
<td>
<button class="btn btn-sm btn-outline-primary edit-assignment"
	data-id="<?php echo $row['id']; ?>"
	data-teacher="<?php echo $row['teacher_id']; ?>"
	data-class="<?php echo $row['class_id']; ?>"
	data-subject="<?php echo $row['subject_id']; ?>"
	data-term="<?php echo $row['term_id']; ?>"
	data-year="<?php echo $row['year']; ?>">
	Edit
</button>
<a onclick="del('admin/core/teacher_assignment_delete?id=<?php echo $row['id']; ?>', 'Delete allocation?');" href="javascript:void(0);" class="btn btn-sm btn-danger">Delete</a>
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
$('.edit-assignment').on('click', function () {
	$('#assignment_id').val($(this).data('id'));
	$('#teacher_id').val($(this).data('teacher'));
	$('#class_id').val($(this).data('class'));
	$('#subject_id').val($(this).data('subject'));
	$('#term_id').val($(this).data('term'));
	$('#year').val($(this).data('year'));
});
$('#resetForm').on('click', function () {
	$('#assignment_id').val('0');
	$('#teacher_id').val('');
	$('#class_id').val('');
	$('#subject_id').val('');
	$('#term_id').val('');
	$('#year').val('<?php echo $year; ?>');
});
$('#class_id').on('change', function () {
	const classId = parseInt($(this).val(), 10);
	$('#subject_id option').each(function () {
		const allowed = $(this).data('classes');
		if (!allowed) {
			$(this).show();
			return;
		}
		if (!classId) {
			$(this).show();
			return;
		}
		if (Array.isArray(allowed) && allowed.length > 0) {
			$(this).toggle(allowed.includes(classId));
		} else {
			$(this).show();
		}
	});
	$('#subject_id').val('');
});
</script>
</body>
</html>
