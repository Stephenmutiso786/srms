<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

$subjectOptions = [];
$classOptions = [];
$teacherOptions = [];
$combinationRows = [];
$classNameMap = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjectOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	$classOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($classOptions as $classRow) {
		$classNameMap[(string)($classRow['id'] ?? '')] = (string)($classRow['name'] ?? '');
	}

	$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = '2' ORDER BY fname, lname");
	$stmt->execute();
	$teacherOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT
		sc.id,
		sc.class,
		sc.reg_date,
		sb.name AS subject_name,
		st.fname AS teacher_fname,
		st.lname AS teacher_lname
		FROM tbl_subject_combinations sc
		LEFT JOIN tbl_subjects sb ON sb.id = sc.subject
		LEFT JOIN tbl_staff st ON st.id = sc.teacher
		ORDER BY sc.reg_date DESC, sc.id DESC");
	$stmt->execute();
	$combinationRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Subject Combinations</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
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
<h1>Subject Combinations</h1>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add</button></li>
</ul>
</div>

<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content ">
<div class="modal-header">
<h5 class="modal-title" id="addModalLabel">Add Subject Combinations</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_comb.php">


<div class="mb-2">
<label class="form-label">Select Subject</label>
<select class="form-control select2" name="subject" required style="width: 100%;">
<option selected disabled value="">Select one</option>
<?php
?>
<?php foreach($subjectOptions as $row) { ?>
<option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?> </option>
<?php } ?>
?>
</select>
</div>


<div class="mb-2">
<label class="form-label">Select Class</label>
<select multiple="true" class="form-control select2" name="class[]" required style="width: 100%;">
<?php
?>
<?php foreach($classOptions as $row) { ?>
<option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?> </option>
<?php } ?>
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Teacher</label>
<select class="form-control select2" name="teacher" required style="width: 100%;">
<option selected disabled value="">Select one</option>
<?php
?>
<?php foreach($teacherOptions as $row) { ?>
<option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo htmlspecialchars(trim((string)($row['fname'] ?? '').' '.(string)($row['lname'] ?? ''))); ?> </option>
<?php } ?>
?>
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
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editModalLabel">Edit Subject Combination</h5>
</div>
<div class="modal-body" id="comb_feedback">

</div>

</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Subject Combinations</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>Subject</th>
<th>Teacher</th>
<th>Classes</th>
<th>Added On</th>
<th width="120" align="center"></th>
</tr>
</thead>
<tbody>
<?php foreach($combinationRows as $row) {
$classList = app_unserialize((string)($row['class'] ?? ''));
$classNames = [];
foreach ($classList as $classId) {
	$classId = (string)$classId;
	if (isset($classNameMap[$classId])) {
		$classNames[] = $classNameMap[$classId];
	}
}
?>

<tr>
<td><?php echo htmlspecialchars((string)($row['subject_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars(trim((string)($row['teacher_fname'] ?? '').' '.(string)($row['teacher_lname'] ?? ''))); ?></td>
<td><?php echo htmlspecialchars(implode(', ', $classNames)); ?></td>
<td><?php echo htmlspecialchars((string)($row['reg_date'] ?? '')); ?></td>
<td align="center">
<a onclick="set_combination('<?php echo (int)($row['id'] ?? 0); ?>');" class="btn btn-primary btn-sm" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal">Edit</a>
<a onclick="del('academic/core/drop_comb.php?id=<?php echo (int)($row['id'] ?? 0); ?>', 'Delete Subject Combination?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
</td>
</tr>
<?php } ?>

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
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.select2').select2({
dropdownParent: $("#addModal")
})
</script>
</body>

</html>
