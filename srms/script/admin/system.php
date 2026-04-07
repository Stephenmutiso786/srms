<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}

$settings = [
	'best_of' => 0,
	'use_weights' => 1,
	'require_fees_clear' => 0,
];
$subjects = [];
$weights = [];
$cbcGrading = [];

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

	if (app_table_exists($conn, 'tbl_cbc_grading')) {
		$stmt = $conn->prepare("SELECT id, level, min_mark, max_mark, points, sort_order, active FROM tbl_cbc_grading ORDER BY sort_order, min_mark DESC");
		$stmt->execute();
		$cbcGrading = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	// defaults only
}

if (count($cbcGrading) < 1) {
	$cbcGrading = [
		['id' => 0, 'level' => 'EE1', 'min_mark' => 90, 'max_mark' => 100, 'points' => 8, 'sort_order' => 1, 'active' => 1],
		['id' => 0, 'level' => 'EE2', 'min_mark' => 75, 'max_mark' => 89, 'points' => 7, 'sort_order' => 2, 'active' => 1],
		['id' => 0, 'level' => 'ME1', 'min_mark' => 58, 'max_mark' => 74, 'points' => 6, 'sort_order' => 3, 'active' => 1],
		['id' => 0, 'level' => 'ME2', 'min_mark' => 41, 'max_mark' => 57, 'points' => 5, 'sort_order' => 4, 'active' => 1],
		['id' => 0, 'level' => 'AE1', 'min_mark' => 31, 'max_mark' => 40, 'points' => 4, 'sort_order' => 5, 'active' => 1],
		['id' => 0, 'level' => 'AE2', 'min_mark' => 21, 'max_mark' => 30, 'points' => 3, 'sort_order' => 6, 'active' => 1],
		['id' => 0, 'level' => 'BE1', 'min_mark' => 11, 'max_mark' => 20, 'points' => 2, 'sort_order' => 7, 'active' => 1],
		['id' => 0, 'level' => 'BE2', 'min_mark' => 1, 'max_mark' => 10, 'points' => 1, 'sort_order' => 8, 'active' => 1],
		['id' => 0, 'level' => 'BE2', 'min_mark' => 0, 'max_mark' => 0, 'points' => 0, 'sort_order' => 9, 'active' => 1],
	];
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - System Settings</title>
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
<li><a class="app-menu__item" href="admin/academic"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Academic Account</span></a></li>
<li><a class="app-menu__item" href="admin/teachers"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Teachers</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="admin/register_students"><i class="icon bi bi-circle-fill"></i> Register Students</a></li>
<li><a class="treeview-item" href="admin/import_students"><i class="icon bi bi-circle-fill"></i> Import Students</a></li>
<li><a class="treeview-item" href="admin/manage_students"><i class="icon bi bi-circle-fill"></i> Manage Students</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="admin/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item" href="admin/smtp"><i class="app-menu__icon feather icon-mail"></i><span class="app-menu__label">SMTP Settings</span></a></li>
<li><a class="app-menu__item active" href="admin/system"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">System Settings</span></a></li>
</ul>
</aside>


<main class="app-content">
<div class="app-title">
<div>
<h1>System Settings</h1>
</div>

</div>
<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">School Profile</h3>
<div class="tile-body">
<form class="app_frm" method="POST" enctype="multipart/form-data" autocomplete="OFF" action="admin/core/update_system">
<div class="form-group mb-2">
<label class="control-label">School Name</label>
<input required type="text" name="name" value="<?php echo WBName; ?>" class="form-control" placeholder="Enter School Name">
</div>

<div class="form-group mb-3">
<label class="control-label">School Logo</label>
<input type="file" name="company_logo" class="form-control">
</div>
<input type="hidden" name="old_logo" value="<?php echo WBLogo; ?>">
<div class="box-footer">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Update</button>
</div>
</form>
</div>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Result Processing Settings</h3>
<form class="app_frm" action="admin/core/save_report_settings" method="POST">
<input type="hidden" name="return" value="system">
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
<button class="btn btn-primary app_btn">Save Settings</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Subject Weights</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Subject</th>
<th style="width:140px;">Weight</th>
<th></th>
</tr>
</thead>
<tbody>
<?php foreach ($subjects as $subject): ?>
<tr>
<td><?php echo htmlspecialchars($subject['name']); ?></td>
<td>
<form class="d-flex gap-2" action="admin/core/save_subject_weight" method="POST">
<input type="hidden" name="return" value="system">
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

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">CBC Grading Bands (Marks → Levels)</h3>
<form class="app_frm" action="admin/core/save_cbc_grading" method="POST">
<input type="hidden" name="return" value="system">
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th style="width:140px;">Level</th>
<th style="width:140px;">Min</th>
<th style="width:140px;">Max</th>
<th style="width:140px;">Points</th>
<th style="width:120px;">Order</th>
<th style="width:120px;">Active</th>
</tr>
</thead>
<tbody>
<?php foreach ($cbcGrading as $row): ?>
<tr>
<td>
<input type="hidden" name="id[]" value="<?php echo (int)$row['id']; ?>">
<input class="form-control" name="level[]" value="<?php echo htmlspecialchars($row['level']); ?>" required>
</td>
<td><input type="number" step="0.1" min="0" max="100" class="form-control" name="min_mark[]" value="<?php echo htmlspecialchars((string)$row['min_mark']); ?>" required></td>
<td><input type="number" step="0.1" min="0" max="100" class="form-control" name="max_mark[]" value="<?php echo htmlspecialchars((string)$row['max_mark']); ?>" required></td>
<td><input type="number" step="1" min="0" class="form-control" name="points[]" value="<?php echo htmlspecialchars((string)$row['points']); ?>" required></td>
<td><input type="number" step="1" min="0" class="form-control" name="sort_order[]" value="<?php echo htmlspecialchars((string)$row['sort_order']); ?>" required></td>
<td>
<select class="form-control" name="active[]">
<option value="1" <?php echo (int)$row['active'] === 1 ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo (int)$row['active'] === 0 ? 'selected' : ''; ?>>No</option>
</select>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<button class="btn btn-primary app_btn">Save CBC Grading</button>
</form>
<div class="text-muted mt-2">These bands are used for marks-based entry and automatic CBC level mapping.</div>
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
<?php require_once('const/check-reply.php'); ?>
</body>

</html>
