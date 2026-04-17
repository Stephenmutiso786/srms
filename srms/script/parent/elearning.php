<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "4") {}else{header("location:../"); exit;}

$children = [];
$assignments = [];
$liveClasses = [];
$progressByChild = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_parent_students')) {
		$stmt = $conn->prepare("SELECT s.id, concat_ws(' ', s.fname, s.mname, s.lname) AS name, s.class
			FROM tbl_parent_students ps
			JOIN tbl_students s ON s.id = ps.student_id
			WHERE ps.parent_id = ?");
		$stmt->execute([$account_id]);
		$children = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$childClasses = array_values(array_unique(array_map(function ($c) { return (int)($c['class'] ?? 0); }, $children)));

	if (app_table_exists($conn, 'tbl_assignments')) {
		if (!empty($childClasses)) {
			$placeholders = implode(',', array_fill(0, count($childClasses), '?'));
			$stmt = $conn->prepare("SELECT a.*, c.name AS course_name, c.class_id
				FROM tbl_assignments a
				JOIN tbl_courses c ON c.id = a.course_id
				WHERE c.class_id IN ($placeholders)
				ORDER BY a.created_at DESC");
			$stmt->execute($childClasses);
			$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}

	if (app_table_exists($conn, 'tbl_live_classes')) {
		if (!empty($childClasses)) {
			$placeholders = implode(',', array_fill(0, count($childClasses), '?'));
			$stmt = $conn->prepare("SELECT lc.*, c.name AS course_name, c.class_id
				FROM tbl_live_classes lc
				JOIN tbl_courses c ON c.id = lc.course_id
				WHERE c.class_id IN ($placeholders)
				ORDER BY lc.start_time DESC");
			$stmt->execute($childClasses);
			$liveClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}

	if (app_table_exists($conn, 'tbl_elearning_progress') && !empty($children)) {
		foreach ($children as $child) {
			$stmt = $conn->prepare("SELECT competency_level, completion_pct, updated_at FROM tbl_elearning_progress WHERE student_id = ? ORDER BY updated_at DESC LIMIT 5");
			$stmt->execute([(string)$child['id']]);
			$progressByChild[(string)$child['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load e-learning data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - E-Learning</title>
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
<li><a class="dropdown-item" href="parent/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("parent/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>E-Learning</h1>
<p>Track lessons, assignments and live classes for your children.</p>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Children</h3>
<ul>
<?php foreach ($children as $child): ?>
<li>
<?php echo htmlspecialchars($child['name']); ?>
<?php $childProgress = $progressByChild[(string)$child['id']] ?? []; ?>
<?php if (!empty($childProgress)): ?>
<div class="small text-muted">Recent progress:
<?php foreach ($childProgress as $p): ?>
<span class="badge bg-<?php echo ($p['competency_level'] === 'EE' || $p['competency_level'] === 'ME') ? 'success' : (($p['competency_level'] === 'AE') ? 'warning text-dark' : 'danger'); ?> ms-1"><?php echo htmlspecialchars((string)$p['competency_level']); ?> · <?php echo number_format((float)$p['completion_pct'], 0); ?>%</span>
<?php endforeach; ?>
</div>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</div>

<div class="tile">
<h3 class="tile-title">Assignments</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Assignment</th><th>Course</th><th>Due</th></tr></thead>
<tbody>
<?php foreach ($assignments as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['title']); ?></td>
<td><?php echo htmlspecialchars($row['course_name']); ?></td>
<td><?php echo htmlspecialchars($row['due_date']); ?></td>
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
<thead><tr><th>Title</th><th>Course</th><th>Start</th></tr></thead>
<tbody>
<?php foreach ($liveClasses as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['title']); ?></td>
<td><?php echo htmlspecialchars($row['course_name']); ?></td>
<td><?php echo htmlspecialchars($row['start_time']); ?></td>
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
