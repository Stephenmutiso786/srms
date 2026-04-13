<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}

$settings = [
	'best_of' => 0,
	'use_weights' => 1,
	'require_fees_clear' => 0,
];
$subjects = [];
$weights = [];
$cbcGrading = [];
$appSettings = [
	'school_motto' => '',
	'school_code' => '',
	'school_email' => '',
	'school_phone' => '',
	'school_address' => '',
	'school_website' => '',
	'school_timezone' => 'Africa/Nairobi',
	'current_academic_year' => date('Y'),
	'current_session_label' => 'January ' . date('Y') . ' - December ' . date('Y'),
	'session_start_date' => date('Y-01-01'),
	'session_end_date' => date('Y-12-31'),
	'current_term_id' => '',
	'admission_start_number' => '1',
	'ranking_enabled' => '1',
	'cbc_public_ranking_enabled' => '0',
	'allow_mark_adjustments' => '1',
	'require_review_before_finalizing' => '1',
	'block_finalization_on_missing_marks' => '1',
	'allow_partial_results' => '0',
	'continuous_weight' => '60',
	'summative_weight' => '40',
	'autosave_interval_seconds' => '10',
	'session_timeout_minutes' => '60',
	'sms_enabled' => '0',
	'email_enabled' => '0',
	'send_results_automatically' => '0',
	'mark_entry_deadline_days' => '7',
	'default_school_days' => 'Monday,Tuesday,Wednesday,Thursday,Friday',
];
$gradingSystems = [];
$gradingScalesBySystem = [];
$terms = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_overall_grading_defaults($conn);

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

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($appSettings as $key => $defaultValue) {
		$appSettings[$key] = app_setting_get($conn, $key, (string)$defaultValue);
	}

	if (app_table_exists($conn, 'tbl_grading_systems')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_grading_systems ORDER BY is_default DESC, name");
		$stmt->execute();
		$gradingSystems = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	foreach ($gradingSystems as $system) {
		$gradingScalesBySystem[(int)$system['id']] = report_grading_scales($conn, (int)$system['id']);
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

<?php include('admin/partials/sidebar.php'); ?>


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

<div class="row">
<div class="col-md-12">
<div class="tile border border-danger">
<h3 class="tile-title text-danger">Danger Zone</h3>
<div class="tile-body">
<p class="text-muted">Use this only when handing the platform over to a completely new school. It removes old students, parents, teachers, class-teacher links, results, reports, timetable entries, e-learning records, and related school operations while keeping admin and school-admin accounts plus core setup like classes, subjects, terms, and school settings.</p>
<form method="POST" action="admin/core/reset_new_school" onsubmit="return confirm('Reset this school for a new rollout? This will permanently remove students, parents, teachers, reports, timetable entries, and related records, while keeping admin accounts and core setup.');">
<button type="submit" class="btn btn-danger">Reset for New School</button>
</form>
</div>
</div>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Full School Management Settings</h3>
<form class="app_frm" action="admin/core/save_app_settings" method="POST">
<div class="row">
<div class="col-md-4 mb-3">
<label class="form-label">Motto</label>
<input class="form-control" name="settings[school_motto]" value="<?php echo htmlspecialchars($appSettings['school_motto']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">School Code</label>
<input class="form-control" name="settings[school_code]" value="<?php echo htmlspecialchars($appSettings['school_code']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Timezone</label>
<input class="form-control" name="settings[school_timezone]" value="<?php echo htmlspecialchars($appSettings['school_timezone']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Email</label>
<input class="form-control" name="settings[school_email]" value="<?php echo htmlspecialchars($appSettings['school_email']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Phone</label>
<input class="form-control" name="settings[school_phone]" value="<?php echo htmlspecialchars($appSettings['school_phone']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Website</label>
<input class="form-control" name="settings[school_website]" value="<?php echo htmlspecialchars($appSettings['school_website']); ?>">
</div>
<div class="col-md-8 mb-3">
<label class="form-label">Address</label>
<input class="form-control" name="settings[school_address]" value="<?php echo htmlspecialchars($appSettings['school_address']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Academic Year</label>
<input class="form-control" type="number" name="settings[current_academic_year]" value="<?php echo htmlspecialchars($appSettings['current_academic_year']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Academic Session</label>
<input class="form-control" name="settings[current_session_label]" value="<?php echo htmlspecialchars($appSettings['current_session_label']); ?>" placeholder="e.g. 2026 Academic Session">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Current Term</label>
<select class="form-control" name="settings[current_term_id]">
<option value="">Select term</option>
<?php foreach ($terms as $term): ?>
<option value="<?php echo (int)$term['id']; ?>" <?php echo ((string)$term['id'] === (string)$appSettings['current_term_id']) ? 'selected' : ''; ?>>
	<?php echo htmlspecialchars($term['name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Session Start Date</label>
<input class="form-control" type="date" name="settings[session_start_date]" value="<?php echo htmlspecialchars($appSettings['session_start_date']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Session End Date</label>
<input class="form-control" type="date" name="settings[session_end_date]" value="<?php echo htmlspecialchars($appSettings['session_end_date']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Admission Start Number</label>
<input class="form-control" type="number" min="1" name="settings[admission_start_number]" value="<?php echo htmlspecialchars($appSettings['admission_start_number']); ?>">
</div>
<div class="col-md-12 mb-3">
<div class="alert alert-info mb-0">
Manage school terms directly from <a href="admin/terms">Academic Terms</a>. Current term and academic session values here are used across exams, admissions, and timetable planning.
</div>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">School Days</label>
<input class="form-control" name="settings[default_school_days]" value="<?php echo htmlspecialchars($appSettings['default_school_days']); ?>">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Ranking Enabled</label>
<select class="form-control" name="settings[ranking_enabled]">
<option value="1" <?php echo $appSettings['ranking_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['ranking_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">CBC Public Ranking</label>
<select class="form-control" name="settings[cbc_public_ranking_enabled]">
<option value="1" <?php echo $appSettings['cbc_public_ranking_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['cbc_public_ranking_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Allow Mark Adjustments</label>
<select class="form-control" name="settings[allow_mark_adjustments]">
<option value="1" <?php echo $appSettings['allow_mark_adjustments'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['allow_mark_adjustments'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Require Review Before Finalizing</label>
<select class="form-control" name="settings[require_review_before_finalizing]">
<option value="1" <?php echo $appSettings['require_review_before_finalizing'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['require_review_before_finalizing'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Block Finalization if Marks Missing</label>
<select class="form-control" name="settings[block_finalization_on_missing_marks]">
<option value="1" <?php echo $appSettings['block_finalization_on_missing_marks'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['block_finalization_on_missing_marks'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Allow Partial Results</label>
<select class="form-control" name="settings[allow_partial_results]">
<option value="1" <?php echo $appSettings['allow_partial_results'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['allow_partial_results'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Continuous Weight (%)</label>
<input class="form-control" type="number" name="settings[continuous_weight]" value="<?php echo htmlspecialchars($appSettings['continuous_weight']); ?>" min="0" max="100">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Summative Weight (%)</label>
<input class="form-control" type="number" name="settings[summative_weight]" value="<?php echo htmlspecialchars($appSettings['summative_weight']); ?>" min="0" max="100">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Autosave Interval (sec)</label>
<input class="form-control" type="number" name="settings[autosave_interval_seconds]" value="<?php echo htmlspecialchars($appSettings['autosave_interval_seconds']); ?>" min="1">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Session Timeout (min)</label>
<input class="form-control" type="number" name="settings[session_timeout_minutes]" value="<?php echo htmlspecialchars($appSettings['session_timeout_minutes']); ?>" min="5">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">SMS Enabled</label>
<select class="form-control" name="settings[sms_enabled]">
<option value="1" <?php echo $appSettings['sms_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['sms_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Email Enabled</label>
<select class="form-control" name="settings[email_enabled]">
<option value="1" <?php echo $appSettings['email_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['email_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Send Results Automatically</label>
<select class="form-control" name="settings[send_results_automatically]">
<option value="1" <?php echo $appSettings['send_results_automatically'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['send_results_automatically'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Mark Entry Deadline (days)</label>
<input class="form-control" type="number" name="settings[mark_entry_deadline_days]" value="<?php echo htmlspecialchars($appSettings['mark_entry_deadline_days']); ?>" min="0">
</div>
</div>
<button class="btn btn-primary app_btn">Save App Settings</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Grading Systems Linked to Exam Engine</h3>
<p class="text-muted">The system now provisions one default grading profile called <strong>Overall Grading System</strong> and attaches it to new exams unless you choose another one.</p>
<form class="app_frm mb-4" action="admin/core/save_grading_system" method="POST">
<input type="hidden" name="grading_system_id" value="0">
<div class="row">
<div class="col-md-4 mb-3"><label class="form-label">System Name</label><input class="form-control" name="name" required placeholder="Overall Grading System" value="Overall Grading System"></div>
<div class="col-md-2 mb-3"><label class="form-label">Type</label><select class="form-control" name="type"><option value="cbc" selected>CBC</option><option value="marks">Marks</option></select></div>
<div class="col-md-4 mb-3"><label class="form-label">Description</label><input class="form-control" name="description" placeholder="System-wide default competency grading" value="System-wide default competency grading"></div>
<div class="col-md-2 mb-3"><label class="form-label">Default</label><select class="form-control" name="is_default"><option value="1" selected>Yes</option><option value="0">No</option></select></div>
<div class="col-md-12">
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Grade</th><th>Min</th><th>Max</th><th>Points</th><th>Remark</th><th>Order</th></tr></thead>
<tbody>
<?php $defaultOverallRows = app_default_overall_grading_rows(); ?>
<?php foreach ($defaultOverallRows as $index => $row): ?>
<tr>
<td><input class="form-control" name="scale_grade[]" value="<?php echo htmlspecialchars($row['grade']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_min[]" value="<?php echo htmlspecialchars((string)$row['min']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_max[]" value="<?php echo htmlspecialchars((string)$row['max']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_points[]" value="<?php echo htmlspecialchars((string)$row['points']); ?>"></td>
<td><input class="form-control" name="scale_remark[]" value="<?php echo htmlspecialchars($row['remark']); ?>"></td>
<td><input class="form-control" type="number" name="scale_order[]" value="<?php echo htmlspecialchars((string)$row['order']); ?>"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<button class="btn btn-outline-primary">Save Overall Grading System</button>
</form>

<?php foreach ($gradingSystems as $system): ?>
<div class="border rounded p-3 mb-3">
<form class="app_frm" action="admin/core/save_grading_system" method="POST">
<input type="hidden" name="grading_system_id" value="<?php echo (int)$system['id']; ?>">
<div class="row">
<div class="col-md-3 mb-3"><label class="form-label">System Name</label><input class="form-control" name="name" value="<?php echo htmlspecialchars($system['name']); ?>" required></div>
<div class="col-md-2 mb-3"><label class="form-label">Type</label><select class="form-control" name="type"><option value="marks" <?php echo $system['type'] === 'marks' ? 'selected' : ''; ?>>Marks</option><option value="cbc" <?php echo $system['type'] === 'cbc' ? 'selected' : ''; ?>>CBC</option></select></div>
<div class="col-md-5 mb-3"><label class="form-label">Description</label><input class="form-control" name="description" value="<?php echo htmlspecialchars((string)($system['description'] ?? '')); ?>"></div>
<div class="col-md-2 mb-3"><label class="form-label">Active / Default</label><div class="d-flex gap-2"><select class="form-control" name="is_active"><option value="1" <?php echo (int)$system['is_active'] === 1 ? 'selected' : ''; ?>>Active</option><option value="0" <?php echo (int)$system['is_active'] === 0 ? 'selected' : ''; ?>>Inactive</option></select><select class="form-control" name="is_default"><option value="0" <?php echo (int)$system['is_default'] === 0 ? 'selected' : ''; ?>>Normal</option><option value="1" <?php echo (int)$system['is_default'] === 1 ? 'selected' : ''; ?>>Default</option></select></div></div>
<div class="col-md-12">
<div class="table-responsive">
<table class="table table-sm table-hover">
<thead><tr><th>Grade</th><th>Min</th><th>Max</th><th>Points</th><th>Remark</th><th>Order</th><th>Active</th></tr></thead>
<tbody>
<?php foreach (($gradingScalesBySystem[(int)$system['id']] ?? []) as $scale): ?>
<tr>
<td><input class="form-control" name="scale_grade[]" value="<?php echo htmlspecialchars($scale['name']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_min[]" value="<?php echo htmlspecialchars((string)$scale['min']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_max[]" value="<?php echo htmlspecialchars((string)$scale['max']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_points[]" value="<?php echo htmlspecialchars((string)($scale['points'] ?? 0)); ?>"></td>
<td><input class="form-control" name="scale_remark[]" value="<?php echo htmlspecialchars((string)($scale['remark'] ?? '')); ?>"></td>
<td><input class="form-control" type="number" name="scale_order[]" value="<?php echo htmlspecialchars((string)($scale['sort_order'] ?? 0)); ?>"></td>
<td><select class="form-control" name="scale_active[]"><option value="1" <?php echo ((int)($scale['is_active'] ?? 1) === 1) ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo ((int)($scale['is_active'] ?? 1) === 0) ? 'selected' : ''; ?>>No</option></select></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<button class="btn btn-outline-primary">Save Changes</button>
</form>
</div>
<?php endforeach; ?>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">CBC Grading Bands (Marks → Levels)</h3>
<p class="text-muted">These default CBC bands are now aligned with the Overall Grading System and used across the system.</p>
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
