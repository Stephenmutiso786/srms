<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('communication.manage', 'admin');
app_require_unlocked('communication', 'admin');

$classes = [];
$terms = [];
$items = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT n.id, n.title, n.message, n.audience, n.created_at, c.name AS class_name, t.name AS term_name
			FROM tbl_notifications n
			LEFT JOIN tbl_classes c ON c.id = n.class_id
			LEFT JOIN tbl_terms t ON t.id = n.term_id
			ORDER BY n.created_at DESC LIMIT 50");
		$stmt->execute();
		$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load notifications."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Notifications - Elimu Hub</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">Elimu Hub</a>
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/notifications"><i class="app-menu__icon feather icon-bell"></i><span class="app-menu__label">Notifications</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Notifications</h1>
<p>Send announcements and results release alerts.</p>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Create Notification</h3>
<form class="app_frm" action="admin/core/new_notification" method="POST">
<div class="mb-3">
<label class="form-label">Title</label>
<input class="form-control" name="title" required>
</div>
<div class="mb-3">
<label class="form-label">Message</label>
<textarea class="form-control" name="message" rows="4" required></textarea>
</div>
<div class="mb-3">
<label class="form-label">Audience</label>
<select class="form-control" name="audience" required>
<option value="all">All Users</option>
<option value="students">Students</option>
<option value="parents">Parents</option>
<option value="staff">Staff</option>
<option value="class">Specific Class</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Class (optional)</label>
<select class="form-control" name="class_id">
<option value="">None</option>
<?php foreach ($classes as $class): ?>
<option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Term (optional)</label>
<select class="form-control" name="term_id">
<option value="">None</option>
<?php foreach ($terms as $term): ?>
<option value="<?php echo $term['id']; ?>"><?php echo htmlspecialchars($term['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Link (optional)</label>
<input class="form-control" name="link" placeholder="student/report_card?term=1">
</div>
<button class="btn btn-primary">Send Notification</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Recent Notifications</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr><th>Title</th><th>Audience</th><th>Class</th><th>Term</th><th>Date</th></tr>
</thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
<td><?php echo htmlspecialchars($item['title']); ?></td>
<td><?php echo htmlspecialchars($item['audience']); ?></td>
<td><?php echo htmlspecialchars($item['class_name'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($item['term_name'] ?? '-'); ?></td>
<td><?php echo htmlspecialchars($item['created_at']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
