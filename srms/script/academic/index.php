<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/academic_dashboard.php');
require_once('const/report_engine.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

$roleNames = [];
$visibleModules = [];
$showMarksEntryShortcut = false;
$showAttendanceShortcut = false;
$activeDisciplineCases = 0;
$ongoingExams = 0;
$attendanceAlerts = 0;
$teacherAllocationCount = 0;
$promotionQueue = [];
$autoPromotionRun = [];
$academicFirstName = trim((string)($fname ?? ''));
if ($academicFirstName === '') {
	$academicFirstName = 'Academic Lead';
}
$leadershipTitle = trim((string)($designation ?? 'Academic Leadership'));
if ($leadershipTitle === '') {
	$leadershipTitle = 'Academic Leadership';
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_promotion_workflow_schema($conn);
	$autoPromotionRun = app_auto_prepare_year_end_promotions($conn, (int)($account_id ?? 0));
	$promotionQueue = app_promotion_queue_summary($conn);
	$roleNames = app_staff_role_names($conn, (int)$account_id);
	$visibleModules = app_portal_visible_modules($conn, 'academic', (string)$account_id, (string)$level);
	$showMarksEntryShortcut = (
		app_staff_has_active_teaching_assignment($conn, (int)($account_id ?? 0))
		|| app_current_user_can_override_marks()
	) && app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'marks.enter');
	$showAttendanceShortcut = app_staff_has_active_teaching_assignment($conn, (int)($account_id ?? 0)) && app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'attendance.manage');
	if (app_table_exists($conn, 'tbl_discipline_cases')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_discipline_cases WHERE COALESCE(case_status, 'Reported') IN ('Reported','Under Investigation','Hearing Scheduled')");
		$stmt->execute();
		$activeDisciplineCases = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_exams')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exams WHERE COALESCE(status, 'draft') IN ('draft','active','reviewed','finalized')");
		$stmt->execute();
		$ongoingExams = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM (
			SELECT r.student_id
			FROM tbl_attendance_records r
			INNER JOIN tbl_attendance_sessions s ON s.id = r.session_id
			WHERE LOWER(COALESCE(r.status, '')) = 'absent'
			AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
			GROUP BY r.student_id
			HAVING COUNT(*) >= 3
		) AS attendance_alerts");
		try {
			$stmt->execute();
			$attendanceAlerts = (int)$stmt->fetchColumn();
		} catch (Throwable $mysqlDateError) {
			$stmt = $conn->prepare("SELECT COUNT(*) FROM (
				SELECT r.student_id
				FROM tbl_attendance_records r
				INNER JOIN tbl_attendance_sessions s ON s.id = r.session_id
				WHERE LOWER(COALESCE(r.status, '')) = 'absent'
				AND s.session_date >= CURRENT_DATE - INTERVAL '7 day'
				GROUP BY r.student_id
				HAVING COUNT(*) >= 3
			) AS attendance_alerts");
			$stmt->execute();
			$attendanceAlerts = (int)$stmt->fetchColumn();
		}
	}
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_teacher_assignments WHERE status = 1");
		$stmt->execute();
		$teacherAllocationCount = (int)$stmt->fetchColumn();
	}
} catch (Throwable $e) {
	$roleNames = [];
	$visibleModules = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Academic Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<style>
.module-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:16px}
.module-panel,.role-panel,.shortcut-panel{background:#fff;border:1px solid #e7edf5;border-radius:18px;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.module-panel,.role-panel{padding:18px}
.role-panel{grid-column:span 4}
.module-panel{grid-column:span 8}
.role-chip-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.role-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;font-size:.82rem;font-weight:700;background:#e7f1ef;color:#00695C}
.module-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:14px}
.module-link{display:flex;gap:12px;align-items:flex-start;padding:14px 15px;border:1px solid #e7edf5;border-radius:18px;text-decoration:none;color:#203040;background:linear-gradient(180deg,#ffffff,#f8fbff);box-shadow:0 8px 18px rgba(16,41,38,.04);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.module-link:hover{border-color:#00695C;background:linear-gradient(180deg,#ffffff,#eefaf7);box-shadow:0 14px 26px rgba(0,105,92,.10);transform:translateY(-1px)}
.module-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#e7f1ef;color:#00695C;flex:0 0 auto}
.module-title{font-weight:800;color:#123;line-height:1.2}
.module-desc{font-size:.84rem;color:#6f7e8f;margin-top:2px}
.module-cta{margin-left:auto;align-self:center;font-size:.75rem;font-weight:800;color:#00695C;background:#e7f1ef;border-radius:999px;padding:7px 10px;white-space:nowrap}
.shortcut-panel{padding:18px;margin-bottom:18px}
.shortcut-actions{display:flex;flex-wrap:wrap;gap:10px}
@media (max-width:1100px){.role-panel,.module-panel{grid-column:span 12}.module-list{grid-template-columns:1fr}}
</style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="academic/profile.php"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('academic/partials/sidebar.php'); ?>
<main class="app-content dashboard">
<div class="dashboard-hero">
	<div class="hero-main">
		<span class="hero-kicker">Academic Leadership</span>
		<h1>Welcome back, <?php echo htmlspecialchars($academicFirstName); ?></h1>
		<p>Manage academic structure, teaching workflows, attendance, results, and reporting from one dashboard that now matches the leadership experience more closely.</p>
		<div class="hero-actions">
			<a class="btn btn-light" href="academic/terms.php"><i class="bi bi-folder me-2"></i>Academic Terms</a>
			<a class="btn btn-outline-light" href="academic/classes.php"><i class="bi bi-diagram-3 me-2"></i>Classes</a>
			<a class="btn btn-outline-light" href="academic/subjects.php"><i class="bi bi-book me-2"></i>Subjects</a>
			<?php if ($showMarksEntryShortcut) { ?>
			<a class="btn btn-outline-light" href="academic/exam_marks_entry.php"><i class="bi bi-pencil-square me-2"></i>Marks Entry</a>
			<?php } ?>
			<?php if ($showAttendanceShortcut) { ?>
			<a class="btn btn-outline-light" href="academic/attendance.php"><i class="bi bi-calendar-check me-2"></i>Mark Attendance</a>
			<?php } ?>
		</div>
	</div>
	<div class="hero-meta">
		<div class="meta-card">
			<span class="meta-label">Today</span>
			<strong class="meta-value"><?php echo date('l, d M Y'); ?></strong>
		</div>
		<div class="meta-card">
			<span class="meta-label">Current Time</span>
			<strong class="meta-value" id="academicCurrentTime"><?php echo date('H:i:s'); ?></strong>
		</div>
		<div class="meta-card">
			<span class="meta-label">Academic Terms</span>
			<strong class="meta-value"><?php echo number_format($academic_terms); ?></strong>
		</div>
	</div>
</div>

<div class="dashboard-stats">
	<div class="stat-card">
		<div>
			<div class="stat-label">Teachers</div>
			<div class="stat-value"><?php echo number_format($teachers); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-person-badge"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Students</div>
			<div class="stat-value"><?php echo number_format($my_students); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-people"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Subjects</div>
			<div class="stat-value"><?php echo number_format($subjects); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-book"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Active Discipline</div>
			<div class="stat-value"><?php echo number_format($activeDisciplineCases); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-shield-check"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Exams Ongoing</div>
			<div class="stat-value"><?php echo number_format($ongoingExams); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-journal-check"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Attendance Alerts</div>
			<div class="stat-value"><?php echo number_format($attendanceAlerts); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-exclamation-diamond"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Teacher Allocations</div>
			<div class="stat-value"><?php echo number_format($teacherAllocationCount); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-person-workspace"></i></div>
	</div>
</div>

<div class="shortcut-panel">
	<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
		<div>
			<strong>Academic Quick Actions</strong>
			<div class="text-muted">Run academics, discipline, exams, communication, and reports from one place.</div>
		</div>
		<div class="shortcut-actions">
			<a class="btn btn-primary" href="admin/exams.php"><i class="bi bi-plus-square me-2"></i>Create Exam</a>
			<a class="btn btn-outline-primary" href="academic/discipline.php"><i class="bi bi-shield-exclamation me-2"></i>New Discipline Case</a>
			<a class="btn btn-outline-primary" href="academic/report.php"><i class="bi bi-file-earmark-pdf me-2"></i>Generate Reports</a>
			<a class="btn btn-outline-primary" href="academic/announcement.php"><i class="bi bi-megaphone me-2"></i>Send Notice</a>
		</div>
	</div>
</div>

<?php if ($showMarksEntryShortcut || $showAttendanceShortcut): ?>
<div class="shortcut-panel">
	<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
		<div>
			<strong>Teaching Shortcuts</strong>
			<div class="text-muted">Your active teaching allocations now stay inside the academic portal.</div>
		</div>
		<div class="shortcut-actions">
			<?php if ($showMarksEntryShortcut): ?>
			<a class="btn btn-primary" href="academic/exam_marks_entry.php"><i class="bi bi-pencil-square me-2"></i>Open Marks Entry</a>
			<?php endif; ?>
			<?php if ($showAttendanceShortcut): ?>
			<a class="btn btn-outline-primary" href="academic/attendance.php"><i class="bi bi-calendar-check me-2"></i>Mark Attendance</a>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php endif; ?>

<div class="module-grid">
	<div class="role-panel">
		<h3 class="tile-title mb-2">Assigned Roles</h3>
		<div class="small text-muted">Roles attached to this academic leadership account.</div>
		<div class="role-chip-wrap">
			<?php if (!empty($roleNames)): ?>
				<?php foreach ($roleNames as $roleName): ?>
					<span class="role-chip"><?php echo htmlspecialchars($roleName); ?></span>
				<?php endforeach; ?>
			<?php else: ?>
				<span class="role-chip">Academic</span>
			<?php endif; ?>
		</div>
	</div>
	<div class="module-panel">
		<h3 class="tile-title mb-2">Available Modules</h3>
		<div class="small text-muted">Everything currently visible to this portal, including the core modules that were previously hidden from this dashboard.</div>
		<div class="module-list">
			<?php if (!empty($visibleModules)): ?>
				<?php foreach ($visibleModules as $module): ?>
					<a class="module-link" href="<?php echo htmlspecialchars((string)$module['href']); ?>">
						<div class="module-icon"><i class="<?php echo htmlspecialchars((string)$module['icon']); ?>"></i></div>
						<div>
							<div class="module-title"><?php echo htmlspecialchars((string)$module['label']); ?></div>
							<div class="module-desc"><?php echo htmlspecialchars((string)$module['description']); ?></div>
						</div>
						<span class="module-cta">Open</span>
					</a>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="text-muted">No modules are visible yet for this account.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<?php if (!empty($promotionQueue)): ?>
<div class="tile mt-4">
	<h3 class="tile-title">Promotion Queue</h3>
	<div class="alert alert-warning mb-3">
		<strong><?php echo (int)$promotionQueue['pending_review']; ?> batch(es)</strong> waiting for leadership review under <?php echo htmlspecialchars($leadershipTitle); ?>.
	</div>
	<div class="alert alert-info mb-3">
		<strong><?php echo (int)$promotionQueue['ready_for_super_admin']; ?> batch(es)</strong> waiting for Super Admin completion.
	</div>
	<div class="alert alert-success mb-3">
		<strong><?php echo (int)$promotionQueue['completed']; ?> batch(es)</strong> already completed.
	</div>
	<div class="alert alert-light mb-0">
		<strong>Auto promotion:</strong> <?php echo !empty($promotionQueue['auto_enabled']) ? 'Enabled' : 'Disabled'; ?>.
		Your role is to review pending batches, approve or reject them, then send them to Super Admin.
		<a href="admin/promotions">Open Promotions</a>.
		<?php if (!empty($autoPromotionRun['message'])): ?>
		<span class="ms-2"><?php echo htmlspecialchars((string)$autoPromotionRun['message']); ?></span>
		<?php endif; ?>
	</div>
</div>
<?php endif; ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script>
(function () {
	function updateClock() {
		var node = document.getElementById('academicCurrentTime');
		if (!node) return;
		node.textContent = new Intl.DateTimeFormat('en-KE', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Africa/Nairobi' }).format(new Date());
	}
	updateClock();
	setInterval(updateClock, 1000);
})();
</script>

</body>
</html>
