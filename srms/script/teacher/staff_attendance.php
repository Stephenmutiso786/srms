<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../"); exit;}

$today = date('Y-m-d');
$record = null;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_staff_attendance')) {
		throw new RuntimeException("Staff attendance is not installed on the server (run migration 001_rbac_attendance.sql).");
	}

	$stmt = $conn->prepare("SELECT status, clock_in, clock_out FROM tbl_staff_attendance WHERE staff_id = ? AND attendance_date = ? LIMIT 1");
	$stmt->execute([(int)$account_id, $today]);
	$record = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Staff Attendance</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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
<li><a class="app-menu__item" href="teacher/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Student Attendance</span></a></li>
<li><a class="app-menu__item active" href="teacher/staff_attendance"><i class="app-menu__icon feather icon-clock"></i><span class="app-menu__label">Staff Attendance</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Staff Attendance</h1>
<p><?php echo htmlspecialchars($today); ?></p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row">
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Today</h3>
	  <div class="mb-2"><b>Status:</b> <?php echo htmlspecialchars(strtoupper((string)($record['status'] ?? 'not marked'))); ?></div>
	  <div class="mb-2"><b>Clock In:</b> <?php echo htmlspecialchars((string)($record['clock_in'] ?? '-')); ?></div>
	  <div class="mb-2"><b>Clock Out:</b> <?php echo htmlspecialchars((string)($record['clock_out'] ?? '-')); ?></div>
	  <div class="d-flex gap-2 mt-3">
		<form method="POST" action="teacher/core/staff_clock_in" style="margin:0;">
		  <button class="btn btn-success" type="submit"><i class="bi bi-box-arrow-in-right me-1"></i>Clock In</button>
		</form>
		<form method="POST" action="teacher/core/staff_clock_out" style="margin:0;">
		  <button class="btn btn-primary" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Clock Out</button>
		</form>
	  </div>
	  <p class="text-muted mt-3 mb-0">Tip: Clock out works only after clock in.</p>
	</div>
  </div>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

