<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}
$schoolId = '';
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (app_column_exists($conn, 'tbl_students', 'school_id')) {
		$stmt = $conn->prepare("SELECT school_id FROM tbl_students WHERE id = ? LIMIT 1");
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
<li><a class="dropdown-item" href="student/settings"><i class="bi bi-person me-2 fs-5"></i> Change Password</a></li>
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
<li><a class="app-menu__item" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item active" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/id_card"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">My ID Card</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>My Profile</h1>
</div>

</div>

<div class="row">
<div class="col-md-12">
<div class="tile">

<div class="d-flex justify-content-end mb-3">
<a href="student/id_card" class="btn btn-primary btn-sm"><i class="bi bi-credit-card-2-front me-2"></i>Open ID Card</a>
</div>

<div class="kv-detail-view"><table class="detail-view table table-hover" data-krajee-kvdetailview="kvDetailView_fd109e89"><tbody><tr class="default">
<th colspan="2">Profile Photo <span></span></th></tr>
<tr><th colspan="2"><div class="row">
<div class="col-xs-6 col-md-3">
<a href="javascript:void(0);" data-toggle="modal" class="img-rounded">
<?php
if ($img == "DEFAULT") {
?><img src="images/students/<?php echo $gender; ?>.png" class="avatar_img"><?php
}else{
?><img src="images/students/<?php echo $img; ?>" class="avatar_img"><?php
}
?>
</a>
</div>

</div></th></tr>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">Registration Number</th>
<td><div class="kv-attribute"><?php echo $account_id; ?></div>
</td>
</tr>
<?php if ($schoolId !== '') { ?>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">School ID</th>
<td><div class="kv-attribute"><?php echo htmlspecialchars($schoolId); ?></div>
</td>
</tr>
<?php } ?>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">First Name</th>
<td><div class="kv-attribute"><?php echo $fname; ?></div>
</td>
</tr>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">Middle Name</th>
<td><div class="kv-attribute"><?php echo $mname; ?></div>
</td>
</tr>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">Last Name</th>
<td><div class="kv-attribute"><?php echo $lname; ?></div>
</td>
</tr>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">Gender</th>
<td><div class="kv-attribute"><?php echo $gender; ?></div>
</td>
</tr>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">Email</th>
<td><div class="kv-attribute"><?php echo $email; ?> </div>
</td>
</tr>
<tr>
<th style="width: 20%; text-align: ; vertical-align: ;">Class</th>
<td><div class="kv-attribute"><?php echo $act_class; ?></div>
</td>
</tr>
</tbody></table></div>


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

</body>

</html>
