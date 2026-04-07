<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../"); exit;}

$rows = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		throw new RuntimeException("Attendance module is not installed on the server yet.");
	}

	$stmt = $conn->prepare("SELECT s.id, s.session_date, c.name AS class_name,
		COALESCE(r.status, 'not_marked') AS status
		FROM tbl_attendance_sessions s
		LEFT JOIN tbl_classes c ON c.id = s.class_id
		LEFT JOIN tbl_attendance_records r ON r.session_id = s.id AND r.student_id = ?
		WHERE s.class_id = ?
		ORDER BY s.session_date DESC, s.id DESC
		LIMIT 60");
	$stmt->execute([(string)$account_id, (int)$class]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - My Attendance</title>
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
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item active" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">My Attendance</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>My Attendance</h1>
<p><?php echo htmlspecialchars($act_class ?? ''); ?></p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="tile">
  <h3 class="tile-title">Recent Sessions</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Date</th>
		  <th>Status</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php foreach ($rows as $r) {
		$status = (string)$r['status'];
		$badge = 'secondary';
		if ($status === 'present') $badge = 'success';
		if ($status === 'absent') $badge = 'danger';
		if ($status === 'late') $badge = 'warning';
		if ($status === 'excused') $badge = 'info';
	  ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$r['session_date']); ?></td>
		  <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $status))); ?></span></td>
		</tr>
	  <?php } ?>
	  </tbody>
	</table>
  </div>
</div>
<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>

