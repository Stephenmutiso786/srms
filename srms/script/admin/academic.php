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
<title><?php echo APP_NAME; ?> - Academic Account</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
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
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/academic"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Academic Account</span></a></li>
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
<h1>Academic Account</h1>
</div>

</div>
<div class="row">


<div class="tile">
<div class="tile-body">

<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE level = '1'");
$stmt->execute();
$result = $stmt->fetchAll();

if (count($result) < 1) {
?>
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/new_user">
<div class="mb-2">
<label class="form-label">First Name</label>
<input required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>

<div class="mb-2">
<label class="form-label">Password</label>
<input type="password" class="form-control" id="npass" name="password" placeholder="***************">
</div>
<div class="mb-2">
<label class="form-label">Confirm Password</label>
<input type="password" class="form-control" id="cnpass" placeholder="***************">
</div>

<div class="mb-3">
<label class="form-label">Gender</label>
<select class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>

<button id="sub_btnp2" type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add</button>
</form>
<?php
}else{

foreach($result as $row) {
?>
<div class="mb-2">
<label class="form-label">First Name</label>
<input value="<?php echo $row[1]; ?>" disabled name="fname" class="form-control" type="text" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input value="<?php echo $row[2]; ?>" disabled required name="lname" class="form-control" type="text" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input value="<?php echo $row[4]; ?>" disabled required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>

<div class="mb-3">
<label class="form-label">Gender</label>
<select disabled class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option <?php if ($row[4] == "Male") { print ' selected '; } ?> value="Male">Male</option>
<option <?php if ($row[4] == "Female") { print ' selected '; } ?> value="Female">Female</option>
</select>
</div>

<div class="box-footer">
<a onclick="del('admin/core/drop_user?id=<?php echo $row[0]; ?>', 'Delete Academic?');" href="javascript:void(0);" class="btn btn-danger">Delete</a>
</div>
<?php
}

}


}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}
?>


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
</body>

</html>
