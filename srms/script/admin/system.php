<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/public_media.php');
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
$cbeGrading = [];
$appSettings = [
	'school_motto' => '',
	'school_code' => '',
	'school_email' => '',
	'school_phone' => '',
	'school_address' => '',
	'school_website' => '',
	'headteacher_name' => '',
	'headteacher_title' => 'Headteacher',
	'deputy_headteacher_name' => '',
	'deputy_headteacher_title' => 'Deputy Headteacher',
	'headteacher_signature_path' => '',
	'school_stamp_path' => '',
	'school_timezone' => 'Africa/Nairobi',
	'current_academic_year' => date('Y'),
	'current_session_label' => 'January ' . date('Y') . ' - December ' . date('Y'),
	'session_start_date' => date('Y-01-01'),
	'session_end_date' => date('Y-12-31'),
	'auto_promotion_enabled' => '0',
	'promotion_review_start_date' => '',
	'promotion_finalization_date' => '',
	'promotion_auto_last_generated_year' => '',
	'promotion_auto_last_generated_at' => '',
	'current_term_id' => '',
	'admission_start_number' => '1',
	'ranking_enabled' => '1',
	'cbe_public_ranking_enabled' => '0',
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
	'notification_email_enabled' => '1',
	'notification_email_min_priority' => '75',
	'send_results_automatically' => '0',
	'mark_entry_deadline_days' => '7',
	'ai_enabled' => '1',
	'ai_provider' => 'gemini',
	'ai_model' => 'gemini-2.0-flash',
	'ai_api_key' => '',
	'ai_temperature' => '0.2',
	'ai_max_output_tokens' => '700',
	'ai_fallback_enabled' => '1',
	'ai_public_widget_enabled' => '1',
	'default_school_days' => 'Monday,Tuesday,Wednesday,Thursday,Friday',
	'top_banner_enabled' => '0',
	'top_banner_type' => 'info',
	'top_banner_text' => '',
	'maintenance_mode_enabled' => '0',
	'maintenance_mode_message' => 'System is under maintenance. Please try again later.',
	'public_school_motto' => 'Real school management for every school',
	'public_school_tagline' => 'A platform for administration, teaching, communication, and learning.',
	'public_school_location' => 'Available to all schools on the platform',
	'public_school_location_map_url' => 'https://maps.google.com',
	'public_school_phone' => '+254700000000',
	'public_school_email' => '',
	'public_school_opening_date' => date('Y-m-d'),
	'public_school_closing_date' => date('Y-m-d'),
	'public_about_text' => '',
	'public_vision_text' => 'To make school administration simple, real-time, and accessible to every school.',
	'public_mission_text' => 'To deliver reliable school software that serves administrators, teachers, students, and parents from one platform.',
	'public_core_values' => 'Accuracy, Transparency, Reliability, Security, Support',
	'public_news_items' => "School Onboarding|Register a new school, seed the workspace, and start managing users immediately.\nPortal Access|Teachers, students, and parents log in through separate secure portals.\nPlatform Control|Super admins manage subscriptions, feature flags, and school activation.",
	'public_offers_items' => "Academics|Competency-Based Curriculum from PP1 to Grade 9.\nICT Studies|Foundational digital skills and guided computer learning.\nSports & Clubs|Co-curricular activities for fitness, teamwork, and talent growth.\nDay School|Structured day-learning program with strong parent partnership.\nTransport & Meals|Safe school transport and balanced meals for learners.\nQualified Staff|Dedicated teachers and mentorship-focused support team.",
	'public_facilities_items' => "Science Labs|Practical science exposure in structured learning spaces.\nLibrary|Reading resources that support independent study habits.\nComputer Lab|Guided access to computers and interactive learning tools.\nPlayground|Outdoor spaces for games, sports, and physical development.\nTransport System|Reliable school transport for day learners.\nSafe Environment|Secure and supervised campus for all learners.",
];
$gradingSystems = [];
$gradingScalesBySystem = [];
$terms = [];
$publicShowcaseCount = 0;
$hasLoginBackground = false;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_current_mode_mysql_schema($conn);
	app_ensure_overall_grading_defaults($conn);
	app_ensure_promotion_workflow_schema($conn);
	if (!empty($account_id)) {
		app_auto_prepare_year_end_promotions($conn, (int)$account_id);
	}

	if (app_table_exists($conn, 'tbl_result_settings')) {
		$hasTemplateColumn = app_column_exists($conn, 'tbl_result_settings', 'report_card_template');
		$select = "best_of, use_weights, require_fees_clear" . ($hasTemplateColumn ? ", report_card_template" : "");
		$stmt = $conn->prepare("SELECT {$select} FROM tbl_result_settings ORDER BY id DESC LIMIT 1");
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

	if (app_table_exists($conn, 'tbl_cbe_grading')) {
		$stmt = $conn->prepare("SELECT id, level, min_mark, max_mark, points, sort_order, active FROM tbl_cbe_grading ORDER BY sort_order, min_mark DESC");
		$stmt->execute();
		$cbeGrading = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($appSettings as $key => $defaultValue) {
		$appSettings[$key] = app_setting_get($conn, $key, (string)$defaultValue);
	}

	$publicShowcaseCount = count(app_public_showcase_images($conn));
	$hasLoginBackground = app_public_login_background($conn) !== '';

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

if (count($cbeGrading) < 1) {
	$cbeGrading = [
		['id' => 0, 'level' => 'EE1', 'min_mark' => 90, 'max_mark' => 100, 'points' => 8, 'sort_order' => 1, 'active' => 1],
		['id' => 0, 'level' => 'EE2', 'min_mark' => 75, 'max_mark' => 89.99, 'points' => 7, 'sort_order' => 2, 'active' => 1],
		['id' => 0, 'level' => 'ME1', 'min_mark' => 58, 'max_mark' => 74.99, 'points' => 6, 'sort_order' => 3, 'active' => 1],
		['id' => 0, 'level' => 'ME2', 'min_mark' => 41, 'max_mark' => 57.99, 'points' => 5, 'sort_order' => 4, 'active' => 1],
		['id' => 0, 'level' => 'AE1', 'min_mark' => 31, 'max_mark' => 40.99, 'points' => 4, 'sort_order' => 5, 'active' => 1],
		['id' => 0, 'level' => 'AE2', 'min_mark' => 21, 'max_mark' => 30.99, 'points' => 3, 'sort_order' => 6, 'active' => 1],
		['id' => 0, 'level' => 'BE1', 'min_mark' => 11, 'max_mark' => 20.99, 'points' => 2, 'sort_order' => 7, 'active' => 1],
		['id' => 0, 'level' => 'BE2', 'min_mark' => 0, 'max_mark' => 10.99, 'points' => 1, 'sort_order' => 8, 'active' => 1],
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
<style>
.settings-flow {
	display: flex;
	flex-direction: column;
	gap: 1.25rem;
}
.settings-hero {
	background: linear-gradient(135deg, #f7fbff 0%, #eef8f4 100%);
	border: 1px solid #dbe6ef;
	border-radius: 18px;
	padding: 1.4rem;
}
.settings-hero p {
	margin: 0.35rem 0 0;
	color: #627181;
	max-width: 780px;
}
.settings-quicknav {
	display: flex;
	flex-wrap: wrap;
	gap: 0.65rem;
	margin-top: 1rem;
}
.settings-quicknav a {
	display: inline-flex;
	align-items: center;
	gap: 0.45rem;
	padding: 0.55rem 0.9rem;
	border-radius: 999px;
	border: 1px solid #d8e2eb;
	background: #fff;
	color: #24425c;
	font-weight: 700;
	text-decoration: none;
}
.settings-quicknav a:hover {
	background: #f3f8fb;
	text-decoration: none;
}
.settings-group-label {
	margin: 0;
	font-size: 1.15rem;
	font-weight: 800;
	color: #17324d;
}
.settings-group-copy {
	margin: 0.3rem 0 0;
	color: #657384;
}
.settings-group-head {
	margin-bottom: -0.25rem;
}
.settings-subgroup {
	margin: 1.25rem 0 0.85rem;
	padding-top: 1rem;
	border-top: 1px solid #e5edf4;
	font-size: 0.96rem;
	font-weight: 800;
	color: #17486a;
}
.settings-subgroup:first-child {
	margin-top: 0;
	padding-top: 0;
	border-top: 0;
}
</style>
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
<p>Organized controls for school identity, academic processing, operations, grading, website content, and reset tools.</p>
</div>

</div>
<div class="settings-flow">
<div class="settings-hero">
	<h2 class="settings-group-label">Settings Overview</h2>
	<p>Use the quick links to jump to the part you need instead of scrolling through unrelated settings mixed together.</p>
	<div class="settings-quicknav">
		<a href="admin/system#settings-school"><i class="bi bi-building"></i>School</a>
		<a href="admin/system#settings-public"><i class="bi bi-globe2"></i>Public Site</a>
		<a href="admin/system#settings-results"><i class="bi bi-journal-check"></i>Results</a>
		<a href="admin/system#settings-core"><i class="bi bi-sliders"></i>Core App</a>
		<a href="admin/system#settings-grading"><i class="bi bi-award"></i>Grading</a>
		<a href="admin/system#settings-danger"><i class="bi bi-exclamation-triangle"></i>Danger Zone</a>
	</div>
</div>

<div class="settings-group-head" id="settings-school">
<h2 class="settings-group-label">School Identity</h2>
<p class="settings-group-copy">Branding, profile details, and school-level information used across the platform.</p>
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
<input type="file" name="company_logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
<small class="text-muted">Uploading a new logo now replaces the old school logo everywhere in the system, including headers, website, PDFs, ID cards, and document branding.</small>
</div>
<?php if (trim((string)WBLogo) !== '' && is_file('images/logo/' . trim((string)WBLogo))): ?>
<div class="mb-3">
	<div class="border rounded-3 p-3 bg-light d-inline-flex align-items-center gap-3">
		<img src="images/logo/<?php echo htmlspecialchars(trim((string)WBLogo)); ?>" alt="Current logo" style="width:72px;height:72px;object-fit:contain;background:#fff;border-radius:12px;padding:6px;border:1px solid #d9e3ec;">
		<div>
			<div class="fw-bold">Current active logo</div>
			<div class="small text-muted"><?php echo htmlspecialchars(trim((string)WBLogo)); ?></div>
		</div>
	</div>
</div>
<?php endif; ?>
<input type="hidden" name="old_logo" value="<?php echo WBLogo; ?>">
<div class="box-footer">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Update</button>
</div>
</form>
</div>
</div>

<div class="col-md-12" id="settings-public">
<div class="tile">
<h3 class="tile-title">Public Website Media & Content</h3>
<div class="tile-body">
<p class="text-muted">Upload the school showcase photos and login background image. These files are saved permanently in the database without compression.</p>
<p class="text-muted mb-2">Any image dimensions are accepted. Upload higher-resolution files for best quality (login max 12MB, showcase max 8MB each).</p>
<p class="mb-2"><strong>Current gallery images:</strong> <?php echo (int)$publicShowcaseCount; ?></p>
<p class="mb-3"><strong>Login background:</strong> <?php echo $hasLoginBackground ? 'Set' : 'Not set'; ?></p>
<form class="app_frm" method="POST" enctype="multipart/form-data" autocomplete="OFF" action="admin/core/save_public_media">
<div class="form-group mb-3">
<label class="control-label">Login Background Image</label>
<input type="file" name="login_background" class="form-control" accept=".jpg,.jpeg,.png,.webp">
</div>

<div class="form-group mb-3">
<label class="control-label">Showcase Gallery Images</label>
<input type="file" name="showcase_images[]" class="form-control" accept=".jpg,.jpeg,.png,.webp" multiple>
<small class="text-muted">You can select multiple photos at once.</small>
</div>

<div class="form-group mb-3">
<label class="control-label">Captions (optional, one caption per line)</label>
<textarea class="form-control" name="showcase_captions" rows="4" placeholder="Modern Classrooms&#10;CBE Learning in Action&#10;Co-curricular Activities"></textarea>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" name="replace_gallery" value="1" id="replaceGallery" checked>
<label class="form-check-label" for="replaceGallery">Replace existing gallery with new upload</label>
</div>

<div class="form-check mb-2">
<input class="form-check-input" type="checkbox" name="use_first_showcase_as_login" value="1" id="useFirstAsBg">
<label class="form-check-label" for="useFirstAsBg">Use first gallery image as login background</label>
</div>

<div class="form-check mb-3">
<input class="form-check-input" type="checkbox" name="clear_gallery" value="1" id="clearGallery">
<label class="form-check-label" for="clearGallery">Clear existing gallery images from database</label>
</div>

<button type="submit" class="btn btn-primary app_btn">Save Public Media</button>
</form>

<hr>
<h5 class="mb-3">Public Website Content</h5>
<form class="app_frm" action="admin/core/save_app_settings" method="POST">
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Public Motto</label>
<input class="form-control" name="settings[public_school_motto]" value="<?php echo htmlspecialchars($appSettings['public_school_motto']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Public Tagline</label>
<input class="form-control" name="settings[public_school_tagline]" value="<?php echo htmlspecialchars($appSettings['public_school_tagline']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Public Phone</label>
<input class="form-control" name="settings[public_school_phone]" value="<?php echo htmlspecialchars($appSettings['public_school_phone']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Public Email</label>
<input class="form-control" name="settings[public_school_email]" value="<?php echo htmlspecialchars($appSettings['public_school_email']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">School Opening Date</label>
<input type="date" class="form-control" name="settings[public_school_opening_date]" value="<?php echo htmlspecialchars($appSettings['public_school_opening_date']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">School Closing Date</label>
<input type="date" class="form-control" name="settings[public_school_closing_date]" value="<?php echo htmlspecialchars($appSettings['public_school_closing_date']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Public Location Label</label>
<input class="form-control" name="settings[public_school_location]" value="<?php echo htmlspecialchars($appSettings['public_school_location']); ?>">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Google Maps Link</label>
<input class="form-control" name="settings[public_school_location_map_url]" value="<?php echo htmlspecialchars($appSettings['public_school_location_map_url']); ?>">
</div>
<div class="col-md-12 mb-3">
<label class="form-label">About the School</label>
<textarea class="form-control" rows="3" name="settings[public_about_text]"><?php echo htmlspecialchars($appSettings['public_about_text']); ?></textarea>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Vision</label>
<textarea class="form-control" rows="3" name="settings[public_vision_text]"><?php echo htmlspecialchars($appSettings['public_vision_text']); ?></textarea>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Mission</label>
<textarea class="form-control" rows="3" name="settings[public_mission_text]"><?php echo htmlspecialchars($appSettings['public_mission_text']); ?></textarea>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Core Values (comma-separated)</label>
<textarea class="form-control" rows="3" name="settings[public_core_values]"><?php echo htmlspecialchars($appSettings['public_core_values']); ?></textarea>
</div>
<div class="col-md-12 mb-3">
<label class="form-label">What We Offer (one per line as Title|Description)</label>
<textarea class="form-control" rows="6" name="settings[public_offers_items]"><?php echo htmlspecialchars($appSettings['public_offers_items']); ?></textarea>
</div>
<div class="col-md-12 mb-3">
<label class="form-label">Facilities (one per line as Title|Description)</label>
<textarea class="form-control" rows="6" name="settings[public_facilities_items]"><?php echo htmlspecialchars($appSettings['public_facilities_items']); ?></textarea>
</div>
<div class="col-md-12 mb-3">
<label class="form-label">News & Events (one per line as Title|Description)</label>
<textarea class="form-control" rows="5" name="settings[public_news_items]"><?php echo htmlspecialchars($appSettings['public_news_items']); ?></textarea>
</div>
</div>
<button class="btn btn-outline-primary app_btn" type="submit">Save Public Website Content</button>
</form>
</div>
</div>
</div>
</div>

<div class="col-md-6" id="settings-results">
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

<div class="col-md-6">
<div class="tile border border-warning">
<h3 class="tile-title">Banner & Maintenance</h3>
<p class="text-muted mb-3">Quick controls for the top scrolling information banner and maintenance mode.</p>
<form class="app_frm" action="admin/core/save_app_settings" method="POST">
<div class="mb-3">
<label class="form-label">Top Banner Enabled</label>
<select class="form-control" name="settings[top_banner_enabled]">
<option value="1" <?php echo $appSettings['top_banner_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['top_banner_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Top Banner Type</label>
<select class="form-control" name="settings[top_banner_type]">
<option value="info" <?php echo $appSettings['top_banner_type'] === 'info' ? 'selected' : ''; ?>>Information</option>
<option value="warning" <?php echo $appSettings['top_banner_type'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Top Banner Running Text</label>
<input class="form-control" name="settings[top_banner_text]" value="<?php echo htmlspecialchars($appSettings['top_banner_text']); ?>" placeholder="e.g. Important: Fee deadline Friday 5 PM">
</div>
<div class="mb-3">
<label class="form-label">Maintenance Mode</label>
<select class="form-control" name="settings[maintenance_mode_enabled]">
<option value="1" <?php echo $appSettings['maintenance_mode_enabled'] === '1' ? 'selected' : ''; ?>>On</option>
<option value="0" <?php echo $appSettings['maintenance_mode_enabled'] === '0' ? 'selected' : ''; ?>>Off</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Maintenance Message</label>
<input class="form-control" name="settings[maintenance_mode_message]" value="<?php echo htmlspecialchars($appSettings['maintenance_mode_message']); ?>" placeholder="Shown to non-admin users during maintenance.">
</div>
<button class="btn btn-warning app_btn" type="submit">Save Banner & Maintenance</button>
</form>
</div>
</div>
</div>

<div class="settings-group-head" id="settings-core">
<h2 class="settings-group-label">Core App & Academic Workflow</h2>
<p class="settings-group-copy">Calendar, promotion, ranking, communication, AI, and runtime controls.</p>
</div>

<div class="row">
<div class="col-md-12">
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

<div class="row" id="settings-danger">
<div class="col-md-12">
<div class="tile border border-danger">
<h3 class="tile-title text-danger">Danger Zone</h3>
<div class="tile-body">
<p class="text-muted">Use this only when handing the platform over to a completely new school. It removes old students, parents, teachers, class-teacher links, results, reports, timetable entries, e-learning records, and related school operations while keeping admin and school-admin accounts plus core setup like classes, subjects, terms, and school settings.</p>
<a href="admin/reset_new_school" class="btn btn-danger">Preview Reset For New School</a>
</div>
</div>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h3 class="tile-title">Core School & App Settings</h3>
<form class="app_frm" action="admin/core/save_app_settings" method="POST" enctype="multipart/form-data">
<div class="row">
<div class="col-md-12">
<div class="settings-subgroup">School Contact & Calendar</div>
</div>
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
<div class="col-md-4 mb-3">
<label class="form-label">Headteacher Name</label>
<input class="form-control" name="settings[headteacher_name]" value="<?php echo htmlspecialchars($appSettings['headteacher_name']); ?>" placeholder="e.g. Jane Wambui">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Headteacher Title</label>
<input class="form-control" name="settings[headteacher_title]" value="<?php echo htmlspecialchars($appSettings['headteacher_title']); ?>" placeholder="Headteacher">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Deputy Headteacher Name</label>
<input class="form-control" name="settings[deputy_headteacher_name]" value="<?php echo htmlspecialchars($appSettings['deputy_headteacher_name']); ?>" placeholder="e.g. Peter Mwangi">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Deputy Headteacher Title</label>
<input class="form-control" name="settings[deputy_headteacher_title]" value="<?php echo htmlspecialchars($appSettings['deputy_headteacher_title']); ?>" placeholder="Deputy Headteacher">
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
<label class="form-label">Auto Promotion After Year End</label>
<select class="form-control" name="settings[auto_promotion_enabled]">
<option value="1" <?php echo $appSettings['auto_promotion_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['auto_promotion_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Promotion Review Start Date</label>
<input class="form-control" type="date" name="settings[promotion_review_start_date]" value="<?php echo htmlspecialchars($appSettings['promotion_review_start_date']); ?>">
</div>
<div class="col-md-4 mb-3">
<label class="form-label">Promotion Finalization Date</label>
<input class="form-control" type="date" name="settings[promotion_finalization_date]" value="<?php echo htmlspecialchars($appSettings['promotion_finalization_date']); ?>">
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
<div class="col-md-12 mb-3">
<div class="alert alert-warning mb-0">
<strong>Auto promotion flow:</strong> When the academic year end date passes, the system will automatically create year-end promotion batches for classes with active students. Headteacher or Deputy reviews them first, then Super Admin completes the final promotion.
<?php if (trim((string)$appSettings['promotion_auto_last_generated_at']) !== ''): ?>
Last auto-generation: <strong><?php echo htmlspecialchars((string)$appSettings['promotion_auto_last_generated_at']); ?></strong>.
<?php endif; ?>
</div>
</div>
<div class="col-md-12">
<div class="settings-subgroup">Document Branding</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">School Logo</label>
<input class="form-control" type="file" name="school_logo" accept=".jpg,.jpeg,.png,.webp,image/png,image/jpeg,image/webp">
<small class="text-muted d-block mt-2">Used automatically on report cards, merit lists, ID cards, and printable school forms.</small>
<?php if (defined('WBLogo') && trim((string)WBLogo) !== '' && is_file('images/logo/' . trim((string)WBLogo))): ?>
<div class="mt-2 p-2 border rounded bg-light">
	<div class="small text-muted mb-2">Current logo</div>
	<img src="images/logo/<?php echo htmlspecialchars((string)WBLogo); ?>" alt="School logo" style="max-width:100%;max-height:100px;object-fit:contain;">
</div>
<?php endif; ?>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Headteacher Signature</label>
<input class="form-control" type="file" name="headteacher_signature" accept=".jpg,.jpeg,.png,.webp,image/png,image/jpeg,image/webp">
<small class="text-muted d-block mt-2">Used automatically on report cards and other shared PDF documents. Transparent PNG works best.</small>
<?php if (trim((string)$appSettings['headteacher_signature_path']) !== ''): ?>
<div class="mt-2 p-2 border rounded bg-light">
	<div class="small text-muted mb-2">Current signature</div>
	<img src="images/signatures/<?php echo htmlspecialchars((string)$appSettings['headteacher_signature_path']); ?>" alt="Headteacher signature" style="max-width:100%;max-height:90px;object-fit:contain;">
</div>
<div class="form-check mt-2">
	<input class="form-check-input" type="checkbox" name="remove_headteacher_signature" value="1" id="removeHeadteacherSignature">
	<label class="form-check-label" for="removeHeadteacherSignature">Remove current signature</label>
</div>
<?php endif; ?>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">School Stamp</label>
<input class="form-control" type="file" name="school_stamp" accept=".jpg,.jpeg,.png,.webp,image/png,image/jpeg,image/webp">
<small class="text-muted d-block mt-2">This stamp will be reused across report cards and official PDF outputs instead of uploading it on each document.</small>
<?php if (trim((string)$appSettings['school_stamp_path']) !== ''): ?>
<div class="mt-2 p-2 border rounded bg-light">
	<div class="small text-muted mb-2">Current stamp</div>
	<img src="images/stamps/<?php echo htmlspecialchars((string)$appSettings['school_stamp_path']); ?>" alt="School stamp" style="max-width:100%;max-height:110px;object-fit:contain;">
</div>
<div class="form-check mt-2">
	<input class="form-check-input" type="checkbox" name="remove_school_stamp" value="1" id="removeSchoolStamp">
	<label class="form-check-label" for="removeSchoolStamp">Remove current stamp</label>
</div>
<?php endif; ?>
</div>
<div class="col-md-12 mb-3">
<div class="alert alert-info mb-0">
These document assets are saved once in settings and then reused automatically by shared report card and certificate PDF templates.
</div>
</div>
<div class="col-md-12">
<div class="settings-subgroup">Assessment Workflow</div>
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
<label class="form-label">CBE Public Ranking</label>
<select class="form-control" name="settings[cbe_public_ranking_enabled]">
<option value="1" <?php echo $appSettings['cbe_public_ranking_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['cbe_public_ranking_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
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
<label class="form-label">Notification Emails</label>
<select class="form-control" name="settings[notification_email_enabled]">
<option value="1" <?php echo $appSettings['notification_email_enabled'] === '1' ? 'selected' : ''; ?>>Enabled</option>
<option value="0" <?php echo $appSettings['notification_email_enabled'] === '0' ? 'selected' : ''; ?>>Disabled</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Email Priority Threshold</label>
<input class="form-control" type="number" name="settings[notification_email_min_priority]" value="<?php echo htmlspecialchars($appSettings['notification_email_min_priority']); ?>" min="0" max="100">
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
<div class="col-md-12"><hr></div>
<div class="col-md-12 mb-2">
<h5 class="mb-1">Edu AI Settings</h5>
<p class="text-muted mb-0">Configure the upgraded Edu AI assistant, Gemini provider, floating widget, and fallback behavior.</p>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">AI Enabled</label>
<select class="form-control" name="settings[ai_enabled]">
<option value="1" <?php echo $appSettings['ai_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['ai_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">AI Provider</label>
<select class="form-control" name="settings[ai_provider]">
<option value="gemini" <?php echo $appSettings['ai_provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
<option value="openai" <?php echo $appSettings['ai_provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Model</label>
<input class="form-control" name="settings[ai_model]" value="<?php echo htmlspecialchars($appSettings['ai_model']); ?>" placeholder="gemini-2.0-flash">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Floating Widget</label>
<select class="form-control" name="settings[ai_public_widget_enabled]">
<option value="1" <?php echo $appSettings['ai_public_widget_enabled'] === '1' ? 'selected' : ''; ?>>Enabled</option>
<option value="0" <?php echo $appSettings['ai_public_widget_enabled'] === '0' ? 'selected' : ''; ?>>Disabled</option>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">API Key</label>
<input class="form-control" type="password" name="settings[ai_api_key]" value="" placeholder="<?php echo $appSettings['ai_api_key'] !== '' ? 'Saved. Leave blank to keep current key.' : 'Paste provider API key'; ?>">
<small class="text-muted">Leave blank when editing other settings to keep the saved key unchanged.</small>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Temperature</label>
<input class="form-control" type="number" step="0.1" min="0" max="2" name="settings[ai_temperature]" value="<?php echo htmlspecialchars($appSettings['ai_temperature']); ?>">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Max Output Tokens</label>
<input class="form-control" type="number" min="128" max="4096" name="settings[ai_max_output_tokens]" value="<?php echo htmlspecialchars($appSettings['ai_max_output_tokens']); ?>">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Internal Fallback</label>
<select class="form-control" name="settings[ai_fallback_enabled]">
<option value="1" <?php echo $appSettings['ai_fallback_enabled'] === '1' ? 'selected' : ''; ?>>Enabled</option>
<option value="0" <?php echo $appSettings['ai_fallback_enabled'] === '0' ? 'selected' : ''; ?>>Disabled</option>
</select>
</div>
<div class="col-md-9 mb-3">
<div class="alert alert-info mb-0">
Edu AI uses the configured provider and internal fallback tools inside the school system. Keep the provider key here for hosted reasoning when needed.
</div>
</div>
<div class="col-md-12">
<div class="settings-subgroup">Duplicate Note</div>
<div class="alert alert-light mb-3">Banner and maintenance controls are already available in the dedicated <strong>Banner &amp; Maintenance</strong> card above. These repeated fields are kept here only for compatibility with the current save handler.</div>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Top Banner Enabled</label>
<select class="form-control" name="settings[top_banner_enabled]">
<option value="1" <?php echo $appSettings['top_banner_enabled'] === '1' ? 'selected' : ''; ?>>Yes</option>
<option value="0" <?php echo $appSettings['top_banner_enabled'] === '0' ? 'selected' : ''; ?>>No</option>
</select>
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Top Banner Type</label>
<select class="form-control" name="settings[top_banner_type]">
<option value="info" <?php echo $appSettings['top_banner_type'] === 'info' ? 'selected' : ''; ?>>Information</option>
<option value="warning" <?php echo $appSettings['top_banner_type'] === 'warning' ? 'selected' : ''; ?>>Warning</option>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Top Banner Running Text</label>
<input class="form-control" name="settings[top_banner_text]" value="<?php echo htmlspecialchars($appSettings['top_banner_text']); ?>" placeholder="e.g. Warning: Fee payment deadline is Friday 5 PM.">
</div>
<div class="col-md-3 mb-3">
<label class="form-label">Maintenance Mode</label>
<select class="form-control" name="settings[maintenance_mode_enabled]">
<option value="1" <?php echo $appSettings['maintenance_mode_enabled'] === '1' ? 'selected' : ''; ?>>On</option>
<option value="0" <?php echo $appSettings['maintenance_mode_enabled'] === '0' ? 'selected' : ''; ?>>Off</option>
</select>
</div>
<div class="col-md-9 mb-3">
<label class="form-label">Maintenance Message</label>
<input class="form-control" name="settings[maintenance_mode_message]" value="<?php echo htmlspecialchars($appSettings['maintenance_mode_message']); ?>" placeholder="Shown when non-admin users try to login during maintenance.">
</div>
</div>
<button class="btn btn-primary app_btn">Save App Settings</button>
</form>
</div>
</div>
</div>

<div class="settings-group-head" id="settings-grading">
<h2 class="settings-group-label">Grading & CBE Bands</h2>
<p class="settings-group-copy">Exam-linked grading systems and the CBE bands used for mark-to-level conversion.</p>
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
<div class="col-md-2 mb-3"><label class="form-label">Type</label><select class="form-control" name="type"><option value="cbe" selected>CBE</option><option value="marks">Marks</option></select></div>
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
<div class="col-md-2 mb-3"><label class="form-label">Type</label><select class="form-control" name="type"><option value="marks" <?php echo $system['type'] === 'marks' ? 'selected' : ''; ?>>Marks</option><option value="cbe" <?php echo $system['type'] === 'cbe' ? 'selected' : ''; ?>>CBE</option></select></div>
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
<h3 class="tile-title">CBE Grading Bands (Marks → Levels)</h3>
<p class="text-muted">These default CBE bands are now aligned with the Overall Grading System and used across the system.</p>
<form class="app_frm" action="admin/core/save_cbe_grading" method="POST">
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
<?php foreach ($cbeGrading as $row): ?>
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
<button class="btn btn-primary app_btn">Save CBE Grading</button>
</form>
<div class="text-muted mt-2">These bands are used for marks-based entry and automatic CBE level mapping.</div>
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
