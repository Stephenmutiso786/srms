<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res !== '1' || $level !== '3') { header('location:../'); exit; }

$cases = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_cases_table($conn);

	$stmt = $conn->prepare("SELECT d.id, d.incident_type, d.description, d.severity, d.status, d.created_at,
		c.name AS class_name
		FROM tbl_discipline_cases d
		LEFT JOIN tbl_classes c ON c.id = d.class_id
		WHERE d.student_id = ?
		ORDER BY d.id DESC
		LIMIT 300");
	$stmt->execute([(string)$account_id]);
	$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$error = 'Failed to load discipline cases.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Student Discipline</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<style>
.sev-low{color:#1f8f52;font-weight:700}
.sev-medium{color:#b88900;font-weight:700}
.sev-high{color:#d33c2d;font-weight:700}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Student</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">My Attendance</span></a></li>
<li><a class="app-menu__item" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="student/discipline"><i class="app-menu__icon feather icon-alert-triangle"></i><span class="app-menu__label">Discipline</span></a></li>
<li><a class="app-menu__item" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="student/leadership"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Leadership</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item" href="student/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title"><div><h1>My Discipline Cases</h1><p>Live view of teacher-assigned incidents.</p></div></div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="tile">
<div class="small text-muted mb-2">Auto refresh every 5 seconds for live updates.</div>
<div class="table-responsive">
<table class="table table-hover table-striped">
<thead><tr><th>Date</th><th>Class</th><th>Type</th><th>Severity</th><th>Status</th><th>Description</th></tr></thead>
<tbody>
<?php foreach ($cases as $case): ?>
<tr>
<td><?php echo htmlspecialchars((string)$case['created_at']); ?></td>
<td><?php echo htmlspecialchars((string)($case['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$case['incident_type']); ?></td>
<td class="sev-<?php echo htmlspecialchars((string)$case['severity']); ?>"><?php echo ucfirst(htmlspecialchars((string)$case['severity'])); ?></td>
<td><?php echo htmlspecialchars((string)$case['status']); ?></td>
<td><?php echo htmlspecialchars((string)$case['description']); ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$cases): ?><tr><td colspan="6" class="text-center text-muted">No discipline cases found yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
setInterval(function() { window.location.reload(); }, 5000);
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
