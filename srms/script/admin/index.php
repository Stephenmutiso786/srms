<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/academic_dashboard.php');
require_once('const/report_engine.php');
require_once('const/network_access.php');
$isSuperAdmin = !empty($super_admin);
$isLeadershipRole = in_array((int)$level, [0, 1], true);
if ($res == "1" && ($isLeadershipRole || $isSuperAdmin)) {}else{header("location:../");}
$students_total = $my_students;
$teachers_total = $teachers;
$showMarksEntryShortcut = false;
$showAttendanceShortcut = false;
$showTermsShortcut = false;
$showDownloadsShortcut = false;
$adminAnnouncements = [];
$promotionQueue = [];
$autoPromotionRun = [];
$classMemberSummary = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_promotion_workflow_schema($conn);
	$autoPromotionRun = app_auto_prepare_year_end_promotions($conn, (int)($account_id ?? 0));
	$promotionQueue = app_promotion_queue_summary($conn);
		$students_total = (int)$conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
		$teachers_total = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
		$showMarksEntryShortcut = (
			app_staff_has_active_teaching_assignment($conn, (int)($account_id ?? 0))
			|| app_current_user_can_override_marks()
		) && app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'marks.enter');
	$showAttendanceShortcut = app_staff_has_active_teaching_assignment($conn, (int)($account_id ?? 0)) && app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'attendance.manage');
	$showTermsShortcut = app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'academic.manage') || app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'system.manage');
	$showDownloadsShortcut = app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'report.view') || app_has_permission($conn, (string)($account_id ?? ''), (string)($level ?? ''), 'report.generate');
	$stmt = $conn->prepare("SELECT c.id, c.name, COUNT(s.id) AS student_count
		FROM tbl_classes c
		LEFT JOIN tbl_students s ON s.class = c.id
		GROUP BY c.id, c.name
		ORDER BY c.name");
	$stmt->execute();
	$classMemberSummary = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if (app_table_exists($conn, 'tbl_notifications')) {
			$stmt = $conn->prepare("SELECT title, message, created_at FROM tbl_notifications WHERE audience IN ('all','staff') ORDER BY created_at DESC LIMIT 5");
			$stmt->execute();
		$adminAnnouncements = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	// Keep fallback values from academic dashboard.
}
$adminFirstName = trim((string)($fname ?? ''));
if ($adminFirstName === '') {
	$adminFirstName = 'Administrator';
}
$networkAccess = app_network_access_data();
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<style>
.network-access-card {
	background: linear-gradient(145deg, #ffffff 0%, #f2f8f7 100%);
	border: 1px solid #d7e8e3;
}
.network-access-layout {
	display: grid;
	grid-template-columns: minmax(180px, 240px) 1fr;
	gap: 1.5rem;
	align-items: center;
}
.network-access-qr {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 1rem;
	border-radius: 18px;
	background: #ffffff;
	border: 1px solid #d9e3ec;
	min-height: 220px;
}
.network-access-qr img {
	width: 100%;
	max-width: 210px;
	height: auto;
	display: block;
}
.network-access-actions {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 1rem;
	margin-bottom: 1rem;
}
.network-access-url {
	font-size: 1rem;
	font-weight: 700;
	color: #0f172a;
	word-break: break-all;
}
.network-access-meta {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
	gap: 0.75rem;
	margin: 1rem 0;
}
.network-access-meta .meta-box {
	background: #ffffff;
	border: 1px solid #d9e3ec;
	border-radius: 14px;
	padding: 0.9rem 1rem;
}
.network-access-meta .meta-box span {
	display: block;
	font-size: 0.78rem;
	font-weight: 700;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: #6b7280;
	margin-bottom: 0.2rem;
}
.network-access-status {
	display: inline-flex;
	align-items: center;
	gap: 0.45rem;
	padding: 0.35rem 0.75rem;
	border-radius: 999px;
	font-size: 0.82rem;
	font-weight: 700;
	background: #e8f7ef;
	color: #0f7a42;
}
.network-access-status.is-warning {
	background: #fff4e5;
	color: #a15c00;
}
@media (max-width: 768px) {
	.network-access-layout {
		grid-template-columns: 1fr;
	}
	.network-access-actions {
		flex-direction: column;
		align-items: flex-start;
	}
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
<main class="app-content dashboard">
<div class="dashboard-hero">
	<div class="hero-main">
		<span class="hero-kicker">Administrator Overview</span>
		<h1>Welcome back, <?php echo htmlspecialchars($adminFirstName); ?></h1>
		<p>Track enrollment, attendance, and finance performance at a glance.</p>
		<div class="hero-actions">
			<a class="btn btn-light" href="admin/register_students"><i class="bi bi-plus-circle me-2"></i>New Student</a>
			<a class="btn btn-outline-light" href="admin/fees"><i class="bi bi-cash-coin me-2"></i>Fees & Finance</a>
				<a class="btn btn-outline-light" href="admin/attendance"><i class="bi bi-check2-square me-2"></i>Attendance</a>
				<a class="btn btn-outline-light" href="admin/grading_system"><i class="bi bi-award me-2"></i>Grading Management</a>
				<a class="btn btn-outline-light" href="admin/data_camp"><i class="bi bi-database me-2"></i>Data Camp</a>
				<?php if ($showDownloadsShortcut) { ?>
				<a class="btn btn-outline-light" href="admin/downloads_center"><i class="bi bi-download me-2"></i>Downloads Hub</a>
				<?php } ?>
				<?php if ($showTermsShortcut) { ?>
				<a class="btn btn-outline-light" href="admin/terms"><i class="bi bi-folder2-open me-2"></i>Academic Terms</a>
				<?php } ?>
				<?php if ($showMarksEntryShortcut) { ?>
				<a class="btn btn-outline-light" href="admin/exam_marks_entry"><i class="bi bi-pencil-square me-2"></i>Marks Entry</a>
				<?php } ?>
			<?php if ($showAttendanceShortcut) { ?>
			<a class="btn btn-outline-light" href="admin/attendance_mark"><i class="bi bi-calendar-check me-2"></i>Mark Attendance</a>
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
			<strong class="meta-value" id="adminCurrentTime"><?php echo date('H:i:s'); ?></strong>
		</div>
		<div class="meta-card">
			<span class="meta-label">Active Terms</span>
			<strong class="meta-value"><?php echo number_format($academic_terms); ?></strong>
		</div>
	</div>
</div>

<div class="dashboard-stats">
	<div class="stat-card">
		<div>
			<div class="stat-label">Students</div>
			<div class="stat-value" data-stat="students"><?php echo number_format($students_total); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-people"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Teachers</div>
			<div class="stat-value" data-stat="teachers"><?php echo number_format($teachers_total); ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-person-badge"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Staff Present Today</div>
			<div class="stat-value" data-stat="staffToday">0</div>
		</div>
		<div class="stat-icon"><i class="bi bi-clock-history"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Open Invoices</div>
			<div class="stat-value" data-stat="openInvoices">0</div>
		</div>
		<div class="stat-icon"><i class="bi bi-receipt"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Payments Today</div>
			<div class="stat-value" data-stat="paymentsToday">0</div>
		</div>
		<div class="stat-icon"><i class="bi bi-cash-stack"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Outstanding Balance</div>
			<div class="stat-value" data-stat="outstandingBalance">0</div>
		</div>
		<div class="stat-icon"><i class="bi bi-wallet2"></i></div>
	</div>
  <div class="stat-card">
    <div>
      <div class="stat-label">Boarders</div>
      <div class="stat-value" data-stat="boarders">0</div>
    </div>
    <div class="stat-icon"><i class="bi bi-house-door"></i></div>
  </div>
  <div class="stat-card">
    <div>
      <div class="stat-label">Active Timetables</div>
      <div class="stat-value" data-stat="timetables">0</div>
    </div>
    <div class="stat-icon"><i class="bi bi-calendar2-week"></i></div>
  </div>
</div>

<div class="tile network-access-card">
	<div class="network-access-actions">
		<div>
			<h3 class="tile-title mb-2">Network Access QR</h3>
			<p class="text-muted mb-0">Generated by this server and refreshed from the current XAMPP LAN address so nearby devices can scan and open SRMS instantly.</p>
		</div>
		<button type="button" class="btn btn-outline-primary btn-sm" id="networkAccessRefreshBtn">
			<i class="bi bi-arrow-clockwise me-1"></i>Refresh QR
		</button>
	</div>
	<div class="network-access-layout">
		<div class="network-access-qr">
			<img id="networkAccessQr" src="qr_image.php?size=180&data=<?php echo rawurlencode((string)$networkAccess['url']); ?>&v=<?php echo rawurlencode((string)$networkAccess['generated_at']); ?>" alt="SRMS network access QR code">
		</div>
		<div>
			<div class="network-access-status<?php echo !empty($networkAccess['is_private_ip']) ? '' : ' is-warning'; ?>" id="networkAccessStatus">
				<i class="bi <?php echo !empty($networkAccess['is_private_ip']) ? 'bi-wifi' : 'bi-exclamation-triangle'; ?>"></i>
				<span id="networkAccessStatusText"><?php echo !empty($networkAccess['is_private_ip']) ? 'LAN address detected' : 'LAN IP not detected cleanly'; ?></span>
			</div>
			<div class="network-access-url mt-3" id="networkAccessUrl"><?php echo htmlspecialchars((string)$networkAccess['url']); ?></div>
			<div class="network-access-meta">
				<div class="meta-box">
					<span>Server IP</span>
					<strong id="networkAccessIp"><?php echo htmlspecialchars((string)$networkAccess['ip']); ?></strong>
				</div>
				<div class="meta-box">
					<span>Port</span>
					<strong id="networkAccessPort"><?php echo (int)$networkAccess['port']; ?></strong>
				</div>
				<div class="meta-box">
					<span>Path</span>
					<strong id="networkAccessPath"><?php echo htmlspecialchars((string)$networkAccess['path']); ?></strong>
				</div>
				<div class="meta-box">
					<span>Last Updated</span>
					<strong id="networkAccessUpdated"><?php echo htmlspecialchars(date('H:i:s')); ?></strong>
				</div>
			</div>
			<p class="mb-2">Scan this on phones, tablets, or laptops connected to the same school network.</p>
			<p class="text-muted mb-0" id="networkAccessHint"><?php echo !empty($networkAccess['is_private_ip']) ? 'Users will still need to sign in after opening the link.' : 'If this shows 127.0.0.1, confirm Apache is reachable on the LAN and Windows firewall allows the server port.'; ?></p>
		</div>
	</div>
</div>

<div class="dashboard-grid">
	<div class="tile">
		<h3 class="tile-title">Class Members</h3>
		<div class="small text-muted mb-3">Total learners in each class, shown for leadership oversight.</div>
		<div class="table-responsive">
			<table class="table table-sm table-hover align-middle mb-0">
				<thead>
					<tr>
						<th>Class</th>
						<th class="text-end">Learners</th>
					</tr>
				</thead>
				<tbody>
					<?php if (empty($classMemberSummary)) { ?>
					<tr><td colspan="2" class="text-muted">No classes found.</td></tr>
					<?php } ?>
					<?php foreach ($classMemberSummary as $classRow) { ?>
					<tr>
						<td><?php echo htmlspecialchars((string)$classRow['name']); ?></td>
						<td class="text-end fw-bold"><?php echo number_format((int)$classRow['student_count']); ?></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Announcements</h3>
		<div class="note-list">
			<?php if (!$adminAnnouncements) { ?>
			<div class="note-item text-muted">No announcements right now.</div>
			<?php } ?>
			<?php foreach ($adminAnnouncements as $announcement) { ?>
			<div class="note-item">
				<div class="fw-bold"><?php echo htmlspecialchars((string)$announcement['title']); ?></div>
				<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$announcement['message']); ?></div>
				<div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$announcement['created_at']); ?></div>
			</div>
			<?php } ?>
		</div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Students by Class</h3>
		<div id="chartStudentsByClass" class="chart-lg"></div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Students by Gender</h3>
		<div id="chartStudentsByGender" class="chart-lg"></div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Attendance Today</h3>
		<div id="chartAttendanceToday" class="chart-lg"></div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Payments (Last 7 Days)</h3>
		<div id="chartPaymentsByDay" class="chart-lg"></div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Payment Methods</h3>
		<div id="chartPaymentsByMethod" class="chart-lg"></div>
	</div>
	<div class="tile">
		<h3 class="tile-title">Average Score by Term</h3>
		<div id="chartAvgScoreByTerm" class="chart-lg"></div>
	</div>
</div>

<?php if (!empty($promotionQueue)): ?>
<div class="tile mt-4">
	<h3 class="tile-title">Promotion Queue</h3>
	<div class="alert alert-warning mb-3">
		<strong><?php echo (int)$promotionQueue['pending_review']; ?> batch(es)</strong> waiting for Headteacher or Deputy review.
	</div>
	<div class="alert alert-info mb-3">
		<strong><?php echo (int)$promotionQueue['ready_for_super_admin']; ?> batch(es)</strong> waiting for Super Admin completion.
	</div>
	<div class="alert alert-success mb-3">
		<strong><?php echo (int)$promotionQueue['completed']; ?> batch(es)</strong> already completed.
	</div>
	<div class="alert alert-light mb-0">
		<strong>Auto promotion:</strong> <?php echo !empty($promotionQueue['auto_enabled']) ? 'Enabled' : 'Disabled'; ?>.
		<?php if ($isSuperAdmin): ?>
		Your role is to complete reviewed batches.
		<?php else: ?>
		Your role is to review pending batches, approve or reject them, then send them to Super Admin.
		<?php endif; ?>
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
<script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script type="text/javascript">
(function () {
  function updateClock() {
    var node = document.getElementById('adminCurrentTime');
    if (!node) return;
    node.textContent = new Intl.DateTimeFormat('en-KE', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Africa/Nairobi' }).format(new Date());
  }
  updateClock();
  setInterval(updateClock, 1000);
})();
(function () {
  function el(id) { return document.getElementById(id); }

  function initChart(id) {
    var node = el(id);
    if (!node || !window.echarts) return null;
    var chart = echarts.init(node);
    window.addEventListener('resize', function () { chart.resize(); });
    return chart;
  }

  function fetchJson(url) {
    return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('en-KE', { style: 'currency', currency: 'KES', maximumFractionDigits: 0 }).format(value || 0);
  }

  function refreshNetworkAccess() {
    fetchJson('admin/api/network_access.php?ts=' + Date.now())
      .then(function (data) {
        if (!data || data.error) return;
        var qr = el('networkAccessQr');
        var url = el('networkAccessUrl');
        var ip = el('networkAccessIp');
        var port = el('networkAccessPort');
        var path = el('networkAccessPath');
        var updated = el('networkAccessUpdated');
        var status = el('networkAccessStatus');
        var statusText = el('networkAccessStatusText');
        var hint = el('networkAccessHint');

        if (qr) qr.src = (data.qr_image_url || 'admin/api/network_access_qr.php') + '&ts=' + Date.now();
        if (url) url.textContent = data.url || '';
        if (ip) ip.textContent = data.ip || '';
        if (port) port.textContent = data.port || '';
        if (path) path.textContent = data.path || '';
        if (updated) {
          updated.textContent = new Intl.DateTimeFormat('en-KE', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
            timeZone: 'Africa/Nairobi'
          }).format(new Date());
        }

        if (status && statusText) {
          if (data.is_private_ip) {
            status.classList.remove('is-warning');
            status.innerHTML = '<i class="bi bi-wifi"></i><span id="networkAccessStatusText">LAN address detected</span>';
          } else {
            status.classList.add('is-warning');
            status.innerHTML = '<i class="bi bi-exclamation-triangle"></i><span id="networkAccessStatusText">LAN IP not detected cleanly</span>';
          }
        }

        if (hint) {
          hint.textContent = data.is_private_ip
            ? 'Users will still need to sign in after opening the link.'
            : 'If this shows 127.0.0.1, confirm Apache is reachable on the LAN and Windows firewall allows the server port.';
        }
      })
      .catch(function () { /* ignore */ });
  }

  var refreshBtn = el('networkAccessRefreshBtn');
  if (refreshBtn) {
    refreshBtn.addEventListener('click', refreshNetworkAccess);
  }
  refreshNetworkAccess();
  setInterval(refreshNetworkAccess, 60000);

  var chartStudentsByClass = initChart('chartStudentsByClass');
  var chartStudentsByGender = initChart('chartStudentsByGender');
  var chartAttendanceToday = initChart('chartAttendanceToday');
  var chartPaymentsByDay = initChart('chartPaymentsByDay');
  var chartPaymentsByMethod = initChart('chartPaymentsByMethod');
  var chartAvgScoreByTerm = initChart('chartAvgScoreByTerm');

  fetchJson('admin/api/dashboard_stats.php')
    .then(function (data) {
      if (!data || data.error) return;

      if (data.counts) {
        var students = document.querySelector('[data-stat=\"students\"]');
        var teachers = document.querySelector('[data-stat=\"teachers\"]');
          var boarders = document.querySelector('[data-stat="boarders"]');
          var timetables = document.querySelector('[data-stat="timetables"]');
        if (students) students.textContent = data.counts.students || 0;
        if (teachers) teachers.textContent = data.counts.teachers || 0;
          if (boarders) boarders.textContent = data.counts.boarders || 0;
          if (timetables) timetables.textContent = data.counts.timetables || 0;
      }

      var staffToday = document.querySelector('[data-stat=\"staffToday\"]');
      if (staffToday) staffToday.textContent = data.staffAttendanceToday || 0;

      var openInvoices = document.querySelector('[data-stat=\"openInvoices\"]');
      var paymentsToday = document.querySelector('[data-stat=\"paymentsToday\"]');
      var outstanding = document.querySelector('[data-stat=\"outstandingBalance\"]');
      if (data.fees) {
        if (openInvoices) openInvoices.textContent = data.fees.open_invoices || 0;
        if (paymentsToday) paymentsToday.textContent = formatCurrency(data.fees.payments_today || 0);
        if (outstanding) outstanding.textContent = formatCurrency(data.fees.outstanding_total || 0);
      }

      if (chartStudentsByClass) {
        var labels = (data.studentsByClass || []).map(function (r) { return r.name; });
        var values = (data.studentsByClass || []).map(function (r) { return Number(r.count || 0); });
        chartStudentsByClass.setOption({
          tooltip: { trigger: 'axis' },
          grid: { left: 40, right: 20, top: 20, bottom: 60 },
          xAxis: { type: 'category', data: labels, axisLabel: { rotate: 30 } },
          yAxis: { type: 'value' },
          series: [{ type: 'bar', data: values, itemStyle: { color: '#00695c' } }]
        });
      }

      if (chartStudentsByGender) {
        var items = (data.studentsByGender || []).map(function (r) {
          return { name: r.gender || 'Unknown', value: Number(r.count || 0) };
        });
        chartStudentsByGender.setOption({
          tooltip: { trigger: 'item' },
          series: [{
            type: 'pie',
            radius: ['35%', '70%'],
            avoidLabelOverlap: true,
            label: { show: true },
            data: items
          }]
        });
      }

      if (chartAttendanceToday) {
        var att = data.attendanceToday || {};
        var attItems = [
          { name: 'Present', value: Number(att.present || 0) },
          { name: 'Absent', value: Number(att.absent || 0) },
          { name: 'Late', value: Number(att.late || 0) },
          { name: 'Excused', value: Number(att.excused || 0) }
        ];
        chartAttendanceToday.setOption({
          tooltip: { trigger: 'item' },
          series: [{
            type: 'pie',
            radius: ['30%', '70%'],
            label: { show: true },
            data: attItems
          }]
        });
      }

      if (chartPaymentsByDay) {
        var payLabels = (data.paymentsByDay || []).map(function (r) { return r.day; });
        var payValues = (data.paymentsByDay || []).map(function (r) { return Number(r.total || 0); });
        chartPaymentsByDay.setOption({
          tooltip: { trigger: 'axis' },
          grid: { left: 40, right: 20, top: 20, bottom: 40 },
          xAxis: { type: 'category', data: payLabels },
          yAxis: { type: 'value' },
          series: [{ type: 'line', smooth: true, data: payValues, itemStyle: { color: '#198754' } }]
        });
      }

      if (chartPaymentsByMethod) {
        var methodItems = (data.paymentsByMethod || []).map(function (r) {
          return { name: r.method || 'unknown', value: Number(r.total || 0) };
        });
        chartPaymentsByMethod.setOption({
          tooltip: { trigger: 'item' },
          series: [{
            type: 'pie',
            radius: ['35%', '70%'],
            label: { show: true },
            data: methodItems
          }]
        });
      }

      if (chartAvgScoreByTerm) {
        var tLabels = (data.avgScoreByTerm || []).map(function (r) { return r.name; });
        var tValues = (data.avgScoreByTerm || []).map(function (r) { return Number(r.avg_score || 0); });
        chartAvgScoreByTerm.setOption({
          tooltip: { trigger: 'axis' },
          grid: { left: 40, right: 20, top: 20, bottom: 60 },
          xAxis: { type: 'category', data: tLabels, axisLabel: { rotate: 20 } },
          yAxis: { type: 'value', min: 0, max: 100 },
          series: [{ type: 'line', smooth: true, data: tValues, itemStyle: { color: '#0d6efd' } }]
        });
      }
    })
    .catch(function () { /* ignore */ });
})();
</script>

</body>

</html>
