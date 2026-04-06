<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
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
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Admin</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="admin/academic"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Academic Account</span></a></li>
<li><a class="app-menu__item" href="admin/teachers"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Teachers</span></a></li>

<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="admin/register_students"><i class="icon bi bi-circle-fill"></i> Register Students</a></li>
<li><a class="treeview-item" href="admin/import_students"><i class="icon bi bi-circle-fill"></i> Import Students</a></li>
<li><a class="treeview-item" href="admin/manage_students"><i class="icon bi bi-circle-fill"></i> Manage Students</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="admin/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item" href="admin/smtp"><i class="app-menu__icon feather icon-mail"></i><span class="app-menu__label">SMTP Settings</span></a></li>
<li><a class="app-menu__item" href="admin/system"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">System Settings</span></a></li>
</ul>
</aside>
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
<form enctype="multipart/form-data" action="admin/core/start_edit" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Student</label>
<select class="form-control select2" name="student" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_students");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1].' '.$row[2].' '.$row[3].' ('.$row[0].')'; ?> </option>
<?php
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
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
echo "Connection failed: " . $e->getMessage();
}
?>
</select>
</div>

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
$('.select2').select2()
</script>
</body>

</html>
