<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}

$nextAdmissionNumber = '';
$classRows = [];
$jssChoiceMap = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$nextAdmissionNumber = app_next_student_registration_number($conn);
	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	$classRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$jssChoiceMap = app_cbc_jss_choice_id_map($conn);
} catch (Throwable $e) {
	$nextAdmissionNumber = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Register Students</title>
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
<h1>Register Students</h1>
</div>
</div>


<div class="row">
<div class="col-md-6 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Register Students</h3>
<form enctype="multipart/form-data" action="admin/core/new_student" class="app_frm" method="POST" autocomplete="OFF">
<div class="mb-2">
<label class="form-label">Registration Number</label>
<input name="regno" required class="form-control" type="text" placeholder="Enter registration number" value="<?php echo htmlspecialchars($nextAdmissionNumber); ?>">
<div class="small text-muted mt-1">This becomes the student username. It starts from the value set in Admin Settings and increments automatically. You can still change it if needed.</div>
</div>
<div class="mb-2">
<label class="form-label">First Name</label>
<input name="fname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Middle Name</label>
<input name="mname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter middle name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input name="lname" required class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2 cbc-class-select" name="class" data-cbc-wrap="cbcJssChoices" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php foreach ($classRows as $row) { ?>
<option value="<?php echo htmlspecialchars((string)$row['id']); ?>" data-cbc-band="<?php echo htmlspecialchars(app_cbc_class_band((string)$row['name'])); ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option>
<?php } ?>
</select>
</div>

<div id="cbcJssChoices" class="border rounded p-3 mb-3" style="display:none;">
<div class="fw-semibold mb-2">Junior Secondary Subject Choices</div>
<div class="mb-2">
<label class="form-label">Language Choice</label>
<select class="form-control select2 cbc-jss-select" id="language_subject_id" name="language_subject_id" style="width: 100%;">
<option value="">Select language</option>
<?php foreach (($jssChoiceMap['language'] ?? []) as $subjectId => $subjectName) { ?>
<option value="<?php echo htmlspecialchars((string)$subjectId); ?>"><?php echo htmlspecialchars((string)$subjectName); ?></option>
<?php } ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Religion Choice</label>
<select class="form-control select2 cbc-jss-select" id="religion_subject_id" name="religion_subject_id" style="width: 100%;">
<option value="">Select religion</option>
<?php foreach (($jssChoiceMap['religion'] ?? []) as $subjectId => $subjectName) { ?>
<option value="<?php echo htmlspecialchars((string)$subjectId); ?>"><?php echo htmlspecialchars((string)$subjectName); ?></option>
<?php } ?>
</select>
</div>
<div class="mb-0">
<label class="form-label">Optional Subjects</label>
<select class="form-control select2 cbc-jss-select" id="optional_subject_ids" name="optional_subject_ids[]" multiple style="width: 100%;">
<?php foreach (($jssChoiceMap['optional'] ?? []) as $subjectId => $subjectName) { ?>
<option value="<?php echo htmlspecialchars((string)$subjectId); ?>"><?php echo htmlspecialchars((string)$subjectName); ?></option>
<?php } ?>
</select>
</div>
</div>

<div class="mb-2">
<label class="form-label">Email</label>
<input name="email" required class="form-control" type="text" placeholder="Enter email address">
</div>
<div class="mb-2">
<label class="form-label">Password</label>
<input type="password" class="form-control" id="npass" name="password" placeholder="***************" value="12345678">
<div class="small text-muted mt-1">Default password is 12345678 unless you change it.</div>
</div>
<div class="mb-2">
<label class="form-label">Confirm Password</label>
<input type="password" class="form-control" id="cnpass" placeholder="***************">
</div>

<div class="mb-3">
<label class="form-label">Display Image (Optional)</label>
<input name="image" class="form-control" type="file" accept=".png, .jpg, .jpeg">
</div>

<div class="">
<button id="sub_btnp2" class="btn btn-primary app_btn" type="submit">Register Student</button>
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
$('.select2').select2()
$('body').on('change', '.cbc-class-select', function() {
	toggleCbcStudentChoices(this, $(this).data('cbc-wrap'));
});
toggleCbcStudentChoices('.cbc-class-select', 'cbcJssChoices');
</script>
</body>

</html>
