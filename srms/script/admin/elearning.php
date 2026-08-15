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
$elearningWarnings = [];
$progressStats = [
	'tracked_learners' => 0,
	'avg_completion' => 0,
	'ee' => 0,
	'me' => 0,
	'ae' => 0,
	'be' => 0,
];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_courses')) {
		$courseSql = "SELECT c.*, cl.name AS class_name, sb.name AS subject_name, st.fname, st.lname
			FROM tbl_courses c
			LEFT JOIN tbl_classes cl ON cl.id = c.class_id
			LEFT JOIN tbl_subjects sb ON sb.id = c.subject_id
			LEFT JOIN tbl_staff st ON st.id = c.teacher_id
			ORDER BY c.created_at DESC";
		$stmt = $conn->prepare($courseSql);
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

	if (app_table_exists($conn, 'tbl_elearning_progress')) {
		$stmt = $conn->prepare("SELECT competency_level, completion_pct, student_id FROM tbl_elearning_progress");
		$stmt->execute();
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (!empty($rows)) {
			$students = [];
			$totalPct = 0;
			foreach ($rows as $r) {
				$students[(string)$r['student_id']] = true;
				$totalPct += (float)$r['completion_pct'];
				$k = strtolower((string)$r['competency_level']);
				if (isset($progressStats[$k])) {
					$progressStats[$k]++;
				}
			}
			$progressStats['tracked_learners'] = count($students);
			$progressStats['avg_completion'] = round($totalPct / count($rows), 2);
		}
	}

	if (empty($courses) && empty($lessons) && empty($assignments) && empty($liveClasses)) {
		$elearningWarnings[] = 'No e-learning records have been created yet for this school.';
	}
} catch (Throwable $e) {
	$elearningWarnings[] = 'E-learning data could not be loaded. Check course setup, permissions, and database connectivity.';
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

<?php if (!empty($elearningWarnings)): ?>
<div class="tile">
	<div class="tile-body">
		<?php foreach ($elearningWarnings as $warning): ?>
			<div class="alert alert-warning mb-2"><?php echo htmlspecialchars($warning); ?></div>
		<?php endforeach; ?>
		<p class="mb-0">This view only shows real school content. If modules were not configured, create classes, subjects, teachers, and courses first.</p>
	</div>
</div>
<?php endif; ?>

<div class="row mb-3">
<div class="col-md-3"><div class="tile tile-colored bg-primary"><div class="tile-body"><h4><?php echo (int)$progressStats['tracked_learners']; ?></h4><p>Tracked Learners</p></div></div></div>
<div class="col-md-3"><div class="tile tile-colored bg-info"><div class="tile-body"><h4><?php echo number_format((float)$progressStats['avg_completion'], 1); ?>%</h4><p>Avg Completion</p></div></div></div>
<div class="col-md-3"><div class="tile tile-colored bg-success"><div class="tile-body"><h4><?php echo (int)$progressStats['ee']; ?></h4><p>EE Mastery Records</p></div></div></div>
<div class="col-md-3"><div class="tile tile-colored bg-warning"><div class="tile-body"><h4><?php echo (int)$progressStats['be']; ?></h4><p>BE Intervention Areas</p></div></div></div>
</div>

<div class="tile">
<h3 class="tile-title">Courses</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Name</th><th>Class</th><th>Subject</th><th>Teacher</th></tr></thead>
<tbody>
<?php if (empty($courses)): ?>
<tr><td colspan="4" class="text-muted">No courses found.</td></tr>
<?php else: foreach ($courses as $course): ?>
<tr>
<td><?php echo htmlspecialchars($course['name']); ?></td>
<td><?php echo htmlspecialchars($course['class_name']); ?></td>
<td><?php echo htmlspecialchars($course['subject_name']); ?></td>
<td><?php echo htmlspecialchars(trim(($course['fname'] ?? '').' '.($course['lname'] ?? ''))); ?></td>
</tr>
<?php endforeach; endif; ?>
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
<?php if (empty($lessons)): ?>
<tr><td colspan="3" class="text-muted">No lessons found.</td></tr>
<?php else: foreach ($lessons as $lesson): ?>
<tr>
<td><?php echo htmlspecialchars($lesson['course_name']); ?></td>
<td><?php echo htmlspecialchars($lesson['title']); ?></td>
<td><?php echo htmlspecialchars($lesson['strand']); ?></td>
</tr>
<?php endforeach; endif; ?>
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
<?php if (empty($assignments)): ?>
<tr><td colspan="3" class="text-muted">No assignments found.</td></tr>
<?php else: foreach ($assignments as $assignment): ?>
<tr>
<td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
<td><?php echo htmlspecialchars($assignment['title']); ?></td>
<td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
</tr>
<?php endforeach; endif; ?>
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
<?php if (empty($liveClasses)): ?>
<tr><td colspan="4" class="text-muted">No live classes found.</td></tr>
<?php else: foreach ($liveClasses as $live): ?>
<tr>
<td><?php echo htmlspecialchars($live['course_name']); ?></td>
<td><?php echo htmlspecialchars($live['title']); ?></td>
<td><?php echo htmlspecialchars($live['start_time']); ?></td>
<td><?php echo htmlspecialchars($live['platform']); ?></td>
</tr>
<?php endforeach; endif; ?>
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
