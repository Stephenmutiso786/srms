<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('results.approve', 'admin');
app_require_unlocked('reports', 'admin');

$settings = [
  'best_of' => 0,
  'use_weights' => 1,
  'require_fees_clear' => 0,
];
$subjects = [];
$weights = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_result_settings')) {
		$stmt = $conn->prepare("SELECT best_of, use_weights, require_fees_clear FROM tbl_result_settings ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$settings['best_of'] = (int)$row['best_of'];
			$settings['use_weights'] = (int)$row['use_weights'];
			$settings['require_fees_clear'] = (int)$row['require_fees_clear'];
		}
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_subject_weights')) {
		$stmt = $conn->prepare("SELECT subject_id, weight FROM tbl_subject_weights");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$weights[(int)$row['subject_id']] = (float)$row['weight'];
		}
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load settings."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Report Settings</title>
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
<li><a class="app-menu__item" href="admin/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item active" href="admin/report_settings"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">Report Settings</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Report Settings</h1>
<p>Configure result processing, weights, and fees lock.</p>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Processing Rules</h3>
<form class="app_frm" action="admin/core/save_report_settings" method="POST">
<div class="mb-3">
<label class="form-label">Best Of Subjects (0 = all)</label>
<input type="number" class="form-control" name="best_of" min="0" value="<?php echo $settings['best_of']; ?>" required>
</div>
<div class="mb-3">
<label class="form-label">Use Subject Weights</label>
<select class="form-control" name="use_weights">
<option value="1" <?php echo $settings['use_weights'] ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo !$settings['use_weights'] ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Block Reports If Fees Due</label>
<select class="form-control" name="require_fees_clear">
<option value="1" <?php echo $settings['require_fees_clear'] ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo !$settings['require_fees_clear'] ? 'selected' : ''; ?>>No</option>
</select>
</div>
<button class="btn btn-primary">Save Settings</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Subject Weights</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Subject</th>
<th style="width:120px;">Weight</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach ($subjects as $subject): ?>
<tr>
<td><?php echo htmlspecialchars($subject['name']); ?></td>
<td>
<form class="d-flex gap-2" action="admin/core/save_subject_weight" method="POST">
<input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
<input type="number" step="0.1" min="0" class="form-control" name="weight" value="<?php echo isset($weights[$subject['id']]) ? $weights[$subject['id']] : 1; ?>">
<button class="btn btn-outline-primary btn-sm">Save</button>
</form>
</td>
<td></td>
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
