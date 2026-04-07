<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('system.manage', 'admin');

$courses = [];
$lessons = [];
$assignments = [];
$liveClasses = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_courses')) {
		$stmt = $conn->prepare("SELECT c.*, cl.name AS class_name, sb.name AS subject_name, st.fname, st.lname
			FROM tbl_courses c
			LEFT JOIN tbl_classes cl ON cl.id = c.class_id
			LEFT JOIN tbl_subjects sb ON sb.id = c.subject_id
			LEFT JOIN tbl_staff st ON st.id = c.teacher_id
			ORDER BY c.created_at DESC");
		$stmt->execute();
		$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_lessons')) {
		$stmt = $conn->prepare("SELECT l.*, c.name AS course_name
			FROM tbl_lessons l
			LEFT JOIN tbl_courses c ON c.id = l.course_id
			ORDER BY l.created_at DESC");
		$stmt->execute();
		$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_assignments')) {
		$stmt = $conn->prepare("SELECT a.*, c.name AS course_name
			FROM tbl_assignments a
			LEFT JOIN tbl_courses c ON c.id = a.course_id
			ORDER BY a.created_at DESC");
		$stmt->execute();
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_live_classes')) {
		$stmt = $conn->prepare("SELECT lc.*, c.name AS course_name
			FROM tbl_live_classes lc
			LEFT JOIN tbl_courses c ON c.id = lc.course_id
			ORDER BY lc.start_time DESC");
		$stmt->execute();
		$liveClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load e-learning data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - E-Learning Monitor</title>
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
<h1>E-Learning Monitor</h1>
<p>Track courses, lessons, assignments, and live classes.</p>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Courses</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Name</th><th>Class</th><th>Subject</th><th>Teacher</th></tr></thead>
<tbody>
<?php foreach ($courses as $course): ?>
<tr>
<td><?php echo htmlspecialchars($course['name']); ?></td>
<td><?php echo htmlspecialchars($course['class_name']); ?></td>
<td><?php echo htmlspecialchars($course['subject_name']); ?></td>
<td><?php echo htmlspecialchars(trim(($course['fname'] ?? '').' '.($course['lname'] ?? ''))); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Lessons</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Course</th><th>Title</th><th>Strand</th></tr></thead>
<tbody>
<?php foreach ($lessons as $lesson): ?>
<tr>
<td><?php echo htmlspecialchars($lesson['course_name']); ?></td>
<td><?php echo htmlspecialchars($lesson['title']); ?></td>
<td><?php echo htmlspecialchars($lesson['strand']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Assignments</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Course</th><th>Title</th><th>Due</th></tr></thead>
<tbody>
<?php foreach ($assignments as $assignment): ?>
<tr>
<td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
<td><?php echo htmlspecialchars($assignment['title']); ?></td>
<td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Live Classes</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Course</th><th>Title</th><th>Start</th><th>Platform</th></tr></thead>
<tbody>
<?php foreach ($liveClasses as $live): ?>
<tr>
<td><?php echo htmlspecialchars($live['course_name']); ?></td>
<td><?php echo htmlspecialchars($live['title']); ?></td>
<td><?php echo htmlspecialchars($live['start_time']); ?></td>
<td><?php echo htmlspecialchars($live['platform']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
