<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}
app_require_permission('results.approve', 'admin');

$classes = [];
$terms = [];
$alerts = [];
$issues = [];
$summary = ['alerts' => 0, 'issues' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_insights_alerts')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_insights_alerts ORDER BY created_at DESC LIMIT 20");
		$stmt->execute();
		$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$summary['alerts'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_insights_alerts WHERE status = 'new'")->fetchColumn();
	}

	if (app_table_exists($conn, 'tbl_validation_issues')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_validation_issues ORDER BY created_at DESC LIMIT 20");
		$stmt->execute();
		$issues = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$summary['issues'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_validation_issues")->fetchColumn();
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Analytics Engine</title>
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
<h1>Analytics Engine</h1>
<p>Validate data, generate insights, and send alerts.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row mb-3">
  <div class="col-md-4">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-alert-triangle fs-1"></i>
	  <div class="info">
		<h4>New Alerts</h4>
		<p><b><?php echo number_format($summary['alerts']); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-4">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-check-circle fs-1"></i>
	  <div class="info">
		<h4>Validation Issues</h4>
		<p><b><?php echo number_format($summary['issues']); ?></b></p>
	  </div>
	</div>
  </div>
</div>

<div class="tile mb-3">
  <h3 class="tile-title">Run Analytics</h3>
  <form class="row g-3" method="POST" action="admin/core/run_analytics">
	<div class="col-md-4">
	  <label class="form-label">Term</label>
	  <select class="form-control" name="term_id">
		<option value="">Latest active term</option>
		<?php foreach ($terms as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars((string)$t['name']); ?></option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-4">
	  <label class="form-label">Class (optional)</label>
	  <select class="form-control" name="class_id">
		<option value="">All classes</option>
		<?php foreach ($classes as $c) { ?>
		  <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars((string)$c['name']); ?></option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-4 d-grid align-items-end">
	  <button class="btn btn-primary">Generate Analytics</button>
	</div>
  </form>
  <form method="POST" action="admin/core/publish_alerts" class="mt-3">
	<button class="btn btn-outline-primary"><i class="bi bi-megaphone me-2"></i>Publish Alerts to Notifications</button>
  </form>
</div>

<div class="row">
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Latest Alerts</h3>
	  <?php if (count($alerts) < 1) { ?>
		<div class="alert alert-info mb-0">No alerts generated yet.</div>
	  <?php } else { ?>
		<div class="table-responsive">
		  <table class="table table-hover">
			<thead><tr><th>Type</th><th>Message</th><th>Status</th><th>Date</th></tr></thead>
			<tbody>
			<?php foreach ($alerts as $a) { ?>
			  <tr>
				<td><?php echo htmlspecialchars((string)$a['alert_type']); ?></td>
				<td><?php echo htmlspecialchars((string)$a['message']); ?></td>
				<td><?php echo htmlspecialchars((string)$a['status']); ?></td>
				<td><?php echo htmlspecialchars((string)$a['created_at']); ?></td>
			  </tr>
			<?php } ?>
			</tbody>
		  </table>
		</div>
	  <?php } ?>
	</div>
  </div>
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Validation Issues</h3>
	  <?php if (count($issues) < 1) { ?>
		<div class="alert alert-info mb-0">No issues detected yet.</div>
	  <?php } else { ?>
		<div class="table-responsive">
		  <table class="table table-hover">
			<thead><tr><th>Issue</th><th>Message</th><th>Severity</th><th>Date</th></tr></thead>
			<tbody>
			<?php foreach ($issues as $i) { ?>
			  <tr>
				<td><?php echo htmlspecialchars((string)$i['issue_type']); ?></td>
				<td><?php echo htmlspecialchars((string)$i['message']); ?></td>
				<td><?php echo htmlspecialchars((string)$i['severity']); ?></td>
				<td><?php echo htmlspecialchars((string)$i['created_at']); ?></td>
			  </tr>
			<?php } ?>
			</tbody>
		  </table>
		</div>
	  <?php } ?>
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
