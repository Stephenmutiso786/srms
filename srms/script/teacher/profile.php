<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}
$schoolId = '';
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (app_column_exists($conn, 'tbl_staff', 'school_id')) {
		$stmt = $conn->prepare("SELECT school_id FROM tbl_staff WHERE id = ? LIMIT 1");
		$stmt->execute([$account_id]);
		$schoolId = (string)$stmt->fetchColumn();
	}
} catch (Throwable $e) {
	$schoolId = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - My Profile</title>
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
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
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
<p class="app-sidebar__user-designation">Teacher</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="teacher/id_card"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">My ID Card</span></a></li>
<li><a class="app-menu__item" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
<li><a class="app-menu__item" href="teacher/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/list_students"><i class="icon bi bi-circle-fill"></i> List Students</a></li>
<li><a class="treeview-item" href="teacher/export_students"><i class="icon bi bi-circle-fill"></i> Export Students</a></li>
</ul>
</li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/exam_marks_entry"><i class="icon bi bi-circle-fill"></i> Exam Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/import_results"><i class="icon bi bi-circle-fill"></i> Import Results</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="teacher/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="teacher/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>My Profile</h1>
</div>

</div>
<div class="row">

<div class="tile">
<h3 class="tile-title">Profile Information</h3>
<div class="tile-body">
<div class="d-flex justify-content-end mb-3">
<a href="teacher/id_card" class="btn btn-primary btn-sm"><i class="bi bi-credit-card-2-front me-2"></i>Open Staff ID</a>
</div>

<div class="mb-2">
<label class="form-label">First Name</label>
<input value="<?php echo $fname; ?>" required disabled  name="fname" class="form-control" type="text" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input value="<?php echo $lname; ?>" required disabled  name="lname" class="form-control" type="text" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input value="<?php echo $email; ?>" required disabled  name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<?php if ($schoolId !== '') { ?>
<div class="mb-2">
<label class="form-label">Staff ID</label>
<input value="<?php echo htmlspecialchars($schoolId); ?>" required disabled name="school_id" class="form-control" type="text">
</div>
<?php } ?>

<div class="mb-2">
<label class="form-label">Gender</label>
<select disabled  class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option <?php if ($gender == "Male") { print ' selected '; } ?> value="Male">Male</option>
<option <?php if ($gender == "Female") { print ' selected '; } ?> value="Female">Female</option>
</select>
</div>


</div>
</div>
</div>

<div class="row">

<div class="tile">
<h3 class="tile-title">Change Password</h3>
<div class="tile-body">

<form class="app_frm" action="teacher/core/update_password" method="POST" autocomplete="off">

<div class="mb-2">
<label class="form-label">Current Password</label>
<input type="password" class="form-control" id="cpass" name="cpassword" placeholder="Enter your current password">
</div>
<div class="mb-2">
<label class="form-label">New Password</label>
<input type="password" class="form-control" id="npass" name="npassword" placeholder="Enter your new password">
</div>
<div class="mb-3">
<label class="form-label">Confirm New Password</label>
<input type="password" class="form-control" id="cnpass" placeholder="Repeat your new password">
</div>

<button type="submit" id="sub_btnp" name="submit" value="1" class="btn btn-primary">Change Password</button>

</form>
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
