<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

$studentOptions = [];
$termOptions = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students ORDER BY fname, lname, id");
	$stmt->execute();
	$studentOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($studentOptions as &$studentRow) {
		$studentId = (int)($studentRow['id'] ?? 0);
		$classStmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
		$classStmt->execute([$studentId]);
		$studentRow['class'] = (int)$classStmt->fetchColumn();
	}
	unset($studentRow);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE status = '1' ORDER BY id");
	$stmt->execute();
	$termOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$termOptions = app_sort_term_rows($termOptions);
} catch (PDOException $e) {
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Individual Results</title>
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
<h1>Individual Results</h1>
</div>
</div>


<div class="row">
<div class="col-md-4 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Individual Results</h3>
<form enctype="multipart/form-data" action="academic/core/start_edit.php" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Student</label>
<select class="form-control select2" name="student" id="studentSelect" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php foreach($studentOptions as $row) { ?>
<option value="<?php echo (int)($row['id'] ?? 0); ?>" data-class-id="<?php echo (int)($row['class'] ?? 0); ?>"><?php echo htmlspecialchars(trim((string)($row['fname'] ?? '').' '.(string)($row['mname'] ?? '').' '.(string)($row['lname'] ?? '')).' ('.(string)($row['id'] ?? '').')'); ?> </option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" required style="width: 100%;">
<option selected disabled value="">Select One</option>
<?php foreach($termOptions as $row) { ?>
<option value="<?php echo (int)($row['id'] ?? 0); ?>"><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?> </option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Exam</label>
<select class="form-control select2" name="exam" id="examSelect" required style="width: 100%;">
<option selected disabled value="">Select student and term first</option>
</select>
</div>

<input type="hidden" name="class" id="studentClassInput" value="">

<div class="">
<button class="btn btn-primary app_btn" type="submit">View Results</button>
</div>
</form>
</div>

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
$('.select2').select2();

function loadExamOptions() {
	const studentOption = $('#studentSelect option:selected');
	const classId = parseInt(studentOption.data('class-id'), 10) || 0;
	const termId = parseInt($('#termSelect').val(), 10) || 0;
	$('#studentClassInput').val(classId > 0 ? classId : '');
	if (classId < 1 || termId < 1) {
		$('#examSelect').html('<option selected disabled value="">Select student and term first</option>').trigger('change.select2');
		return;
	}
	$.post('app/ajax/fetch_exams.php', {id: classId, term_id: termId, include_unpublished: 1, submit: 1}, function(data){
		$('#examSelect').html(data).trigger('change.select2');
	});
}

$('#studentSelect').on('change', loadExamOptions);
$('#termSelect').on('change', loadExamOptions);
</script>
</body>

</html>
