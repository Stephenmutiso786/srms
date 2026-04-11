<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}
app_require_permission('report.generate', 'admin');
app_require_unlocked('reports', 'admin');
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Report Tool</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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
<h1>Report Tool</h1>
</div>

</div>
<div class="row">
<div class="col-md-5 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Generate Report Cards</h3>
<p class="text-muted mb-3">Lock results first, then generate the full class report set. The system computes every learner's report card, stores the ranked merit list, and prepares the published documents for student, parent, and teacher access.</p>
<form enctype="multipart/form-data" action="admin/core/process_results" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="class_id" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term_id" required style="width: 100%;">
<option selected disabled value="">Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="">
<button class="btn btn-primary app_btn" type="submit">Generate Report Cards</button>
</div>
</form>
</div>

</div>
</div>
</div>

<div class="col-md-5 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Performance Summary</h3>
<p class="text-muted mb-3">Generate a class-level performance summary PDF.</p>
<form enctype="multipart/form-data" action="admin/core/start_report" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="student" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" required style="width: 100%;">
<option selected disabled value="">Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="">
<button class="btn btn-outline-primary app_btn" type="submit">Generate Summary Report</button>
</div>
</form>
</div>

</div>
</div>
</div>

<div class="col-12 mt-3">
<div class="tile">
<div class="tile-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<div>
<h3 class="tile-title mb-1">Merit List</h3>
<p class="text-muted mb-0">Generate a ranked class merit list and export it as a printable PDF.</p>
</div>
<a class="btn btn-primary" href="admin/merit_list"><i class="bi bi-trophy me-2"></i>Open Merit List</a>
</div>
</div>
</div>
</div>


</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.select2').select2()
</script>
</body>

</html>
