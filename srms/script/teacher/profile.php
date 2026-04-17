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

<?php include("teacher/partials/sidebar.php"); ?>
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
