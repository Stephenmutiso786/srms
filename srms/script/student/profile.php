<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/id_card_engine.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

$schoolId = '';
$photoPath = '';
$photoExists = false;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_column_exists($conn, 'tbl_students', 'school_id')) {
		$stmt = $conn->prepare("SELECT school_id FROM tbl_students WHERE id = ? LIMIT 1");
		$stmt->execute([$account_id]);
		$schoolId = (string)$stmt->fetchColumn();
	}

	$payload = idcard_student_payload($conn, (string)$account_id);
	if ($payload) {
		$photoPath = (string)$payload['photo_path'];
		$photoExists = (bool)$payload['photo_exists'];
	}
} catch (Throwable $e) {
	$schoolId = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Edit Profile</title>
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
<li><a class="dropdown-item" href="student/view"><i class="bi bi-person me-2 fs-5"></i> View Profile</a></li>
<li><a class="dropdown-item" href="student/settings"><i class="bi bi-shield-lock me-2 fs-5"></i> Change Password</a></li>
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
<p class="app-sidebar__user-designation">Student</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/settings"><i class="app-menu__icon feather icon-lock"></i><span class="app-menu__label">Change Password</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>Edit Profile</h1>
</div>
</div>

<div class="row">
<div class="col-lg-4 mb-3">
<div class="tile h-100">
<h3 class="tile-title">Current Profile</h3>
<div class="tile-body text-center">
<?php if ($photoExists) { ?>
<img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Student photo" class="avatar_img" style="max-width:160px;max-height:180px;object-fit:cover;">
<?php } else { ?>
<div class="d-inline-flex align-items-center justify-content-center bg-light rounded-3" style="width:160px;height:180px;font-size:3rem;font-weight:800;color:#0d64b0;">
<?php echo htmlspecialchars(strtoupper(substr($fname,0,1).substr($lname,0,1))); ?>
</div>
<?php } ?>
<div class="mt-3 fw-semibold"><?php echo htmlspecialchars($fname . ' ' . $lname); ?></div>
<div class="text-muted"><?php echo htmlspecialchars($schoolId !== '' ? $schoolId : $account_id); ?></div>
<div class="text-muted mt-1"><?php echo htmlspecialchars($act_class ?? ''); ?></div>
</div>
</div>
</div>

<div class="col-lg-8 mb-3">
<div class="tile h-100">
<h3 class="tile-title">Profile Information</h3>
<div class="tile-body">
<form class="app_frm" action="student/core/update_profile" method="POST" enctype="multipart/form-data" autocomplete="off">
<input type="hidden" name="old_photo" value="<?php echo htmlspecialchars((string)($img ?? 'DEFAULT')); ?>">
<div class="row g-2">
<div class="col-md-4 mb-2">
<label class="form-label">First Name</label>
<input value="<?php echo htmlspecialchars($fname); ?>" required name="fname" onkeypress="return lettersOnly(event)" class="form-control" type="text" placeholder="Enter first name">
</div>
<div class="col-md-4 mb-2">
<label class="form-label">Middle Name</label>
<input value="<?php echo htmlspecialchars($mname); ?>" name="mname" onkeypress="return lettersOnly(event)" class="form-control" type="text" placeholder="Enter middle name">
</div>
<div class="col-md-4 mb-2">
<label class="form-label">Last Name</label>
<input value="<?php echo htmlspecialchars($lname); ?>" required name="lname" onkeypress="return lettersOnly(event)" class="form-control" type="text" placeholder="Enter last name">
</div>
</div>

<div class="row g-2">
<div class="col-md-6 mb-2">
<label class="form-label">Email Address</label>
<input value="<?php echo htmlspecialchars($email); ?>" required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<div class="col-md-6 mb-2">
<label class="form-label">Gender</label>
<select class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option <?php if ($gender == "Male") { print ' selected '; } ?> value="Male">Male</option>
<option <?php if ($gender == "Female") { print ' selected '; } ?> value="Female">Female</option>
</select>
</div>
</div>

<div class="row g-2">
<div class="col-md-6 mb-2">
<label class="form-label">Class</label>
<input value="<?php echo htmlspecialchars($act_class ?? ''); ?>" class="form-control" type="text" readonly>
</div>
<div class="col-md-6 mb-2">
<label class="form-label">Profile Photo</label>
<input type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png">
</div>
</div>

<button type="submit" name="submit" value="1" class="btn btn-primary">Update Profile</button>
<a class="btn btn-outline-secondary ms-2" href="student/view">Back to Profile</a>
</form>
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
</body>

</html>