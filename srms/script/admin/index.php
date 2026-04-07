<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/academic_dashboard.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
$students_total = $my_students;
$teachers_total = $teachers;
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$students_total = (int)$conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn();
	$teachers_total = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff WHERE level = 2")->fetchColumn();
} catch (Throwable $e) {
	// Keep fallback values from academic dashboard.
}
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
<li><a class="app-menu__item active" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="admin/academic"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Academic Account</span></a></li>
<li><a class="app-menu__item" href="admin/teachers"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">Teachers</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="admin/register_students"><i class="icon bi bi-circle-fill"></i> Register Students</a></li>
<li><a class="treeview-item" href="admin/import_students"><i class="icon bi bi-circle-fill"></i> Import Students</a></li>
<li><a class="treeview-item" href="admin/manage_students"><i class="icon bi bi-circle-fill"></i> Manage Students</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="admin/parents"><i class="app-menu__icon feather icon-user-plus"></i><span class="app-menu__label">Parents</span></a></li>
<li><a class="app-menu__item" href="admin/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
<li><a class="app-menu__item" href="admin/staff_attendance"><i class="app-menu__icon feather icon-clock"></i><span class="app-menu__label">Staff Attendance</span></a></li>
<li><a class="app-menu__item" href="admin/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees & Finance</span></a></li>
<li><a class="app-menu__item" href="admin/import_export"><i class="app-menu__icon feather icon-upload-cloud"></i><span class="app-menu__label">Import / Export</span></a></li>
<li><a class="app-menu__item" href="admin/communication"><i class="app-menu__icon feather icon-message-circle"></i><span class="app-menu__label">Communication</span></a></li>
<li><a class="app-menu__item" href="admin/library"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Library</span></a></li>
<li><a class="app-menu__item" href="admin/inventory"><i class="app-menu__icon feather icon-box"></i><span class="app-menu__label">Inventory</span></a></li>
<li><a class="app-menu__item" href="admin/transport"><i class="app-menu__icon feather icon-truck"></i><span class="app-menu__label">Transport</span></a></li>
<li><a class="app-menu__item" href="admin/results_locks"><i class="app-menu__icon feather icon-lock"></i><span class="app-menu__label">Results Locks</span></a></li>
<li><a class="app-menu__item" href="admin/results_analytics"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Results Analytics</span></a></li>
<li><a class="app-menu__item" href="admin/exams"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span></a></li>
<li><a class="app-menu__item" href="admin/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
<li><a class="app-menu__item" href="admin/notifications"><i class="app-menu__icon feather icon-bell"></i><span class="app-menu__label">Notifications</span></a></li>
<li><a class="app-menu__item" href="admin/audit_logs"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Audit Logs</span></a></li>
<li><a class="app-menu__item" href="admin/roles"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Roles & Permissions</span></a></li>
<li><a class="app-menu__item" href="admin/mpesa"><i class="app-menu__icon feather icon-smartphone"></i><span class="app-menu__label">M-Pesa</span></a></li>
<li><a class="app-menu__item" href="admin/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item" href="admin/report_settings"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">Report Settings</span></a></li>
<li><a class="app-menu__item" href="admin/smtp"><i class="app-menu__icon feather icon-mail"></i><span class="app-menu__label">SMTP Settings</span></a></li>
<li><a class="app-menu__item" href="admin/migrations"><i class="app-menu__icon feather icon-database"></i><span class="app-menu__label">Migrations</span></a></li>
<li><a class="app-menu__item" href="admin/module_locks"><i class="app-menu__icon feather icon-lock"></i><span class="app-menu__label">Module Locks</span></a></li>
<li><a class="app-menu__item" href="admin/system"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">System Settings</span></a></li>
</ul>
</aside>
<main class="app-content dashboard">
<div class="dashboard-hero">
	<div class="hero-main">
		<span class="hero-kicker">Administrator Overview</span>
		<h1>Welcome back, <?php echo $fname; ?></h1>
		<p>Track enrollment, attendance, and finance performance at a glance.</p>
		<div class="hero-actions">
			<a class="btn btn-light" href="admin/register_students"><i class="bi bi-plus-circle me-2"></i>New Student</a>
			<a class="btn btn-outline-light" href="admin/fees"><i class="bi bi-cash-coin me-2"></i>Fees & Finance</a>
			<a class="btn btn-outline-light" href="admin/attendance"><i class="bi bi-check2-square me-2"></i>Attendance</a>
		</div>
	</div>
	<div class="hero-meta">
		<div class="meta-card">
			<span class="meta-label">Today</span>
			<strong class="meta-value"><?php echo date('l, d M Y'); ?></strong>
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
</div>

<section class="intel-section">
	<div class="intel-header">
		<div>
			<span class="intel-kicker">Zeraki-style Analytics</span>
			<h2>School Intelligence System</h2>
			<p>Data → Insight → Decisions → Impact</p>
		</div>
		<div class="intel-cta">
			Turning School Data into Smarter Decisions and Stronger Outcomes
		</div>
	</div>

	<div class="intel-portals">
		<div class="intel-card portal-card portal-super">
			<div class="portal-title"><i class="bi bi-gem"></i> Super Admin</div>
			<ul>
				<li>System control</li>
				<li>School subscriptions</li>
				<li>Analytics + audits</li>
			</ul>
		</div>
		<div class="intel-card portal-card portal-admin">
			<div class="portal-title"><i class="bi bi-building"></i> School Admin</div>
			<ul>
				<li>Manage staff & students</li>
				<li>Approve results</li>
				<li>Reports & finance</li>
			</ul>
		</div>
		<div class="intel-card portal-card portal-teacher">
			<div class="portal-title"><i class="bi bi-mortarboard"></i> Teacher</div>
			<ul>
				<li>Marks entry (CBC/KNEC)</li>
				<li>Attendance</li>
				<li>Class reports</li>
			</ul>
		</div>
		<div class="intel-card portal-card portal-parent">
			<div class="portal-title"><i class="bi bi-people"></i> Parent</div>
			<ul>
				<li>Child progress</li>
				<li>Attendance alerts</li>
				<li>Fees status</li>
			</ul>
		</div>
		<div class="intel-card portal-card portal-student">
			<div class="portal-title"><i class="bi bi-journal-text"></i> Student</div>
			<ul>
				<li>Results & feedback</li>
				<li>Assignments</li>
				<li>Timetable</li>
			</ul>
		</div>
		<div class="intel-card portal-card portal-accountant">
			<div class="portal-title"><i class="bi bi-cash-coin"></i> Accountant</div>
			<ul>
				<li>Fees collection</li>
				<li>Invoices & receipts</li>
				<li>Financial reports</li>
			</ul>
		</div>
		<div class="intel-card portal-card portal-others">
			<div class="portal-title"><i class="bi bi-diagram-3"></i> Other Roles</div>
			<ul>
				<li>HR, Librarian, Nurse</li>
				<li>Transport manager</li>
				<li>Custom access</li>
			</ul>
		</div>
	</div>

	<div class="intel-flow">
		<div class="intel-card intel-block">
			<div class="block-title"><i class="bi bi-hdd-network"></i> Data Sources</div>
			<ul>
				<li>Web & mobile apps</li>
				<li>CSV / Excel imports</li>
				<li>M-Pesa & payments</li>
				<li>Biometric / QR devices</li>
				<li>GPS trackers & APIs</li>
			</ul>
		</div>
		<div class="intel-card intel-block">
			<div class="block-title"><i class="bi bi-grid-1x2"></i> Core Platform</div>
			<div class="block-grid">
				<div>
					<strong>Academic</strong>
					<span>CBC, Exams, Results</span>
				</div>
				<div>
					<strong>Finance</strong>
					<span>Fees, Payments</span>
				</div>
				<div>
					<strong>Communication</strong>
					<span>SMS, Email, Alerts</span>
				</div>
				<div>
					<strong>Operations</strong>
					<span>HR, Library, Transport</span>
				</div>
			</div>
			<div class="block-footer">
				<span>RBAC</span>
				<span>Workflows</span>
				<span>Audit Logs</span>
				<span>Notifications</span>
			</div>
		</div>
		<div class="intel-card intel-block">
			<div class="block-title"><i class="bi bi-graph-up-arrow"></i> Analytics Engine</div>
			<ul>
				<li>Data processing + validation</li>
				<li>ETL cleaning & aggregation</li>
				<li>AI models & insights</li>
				<li>Alert generation</li>
				<li>Visualization layer</li>
			</ul>
		</div>
	</div>

	<div class="intel-dashboards">
		<div>
			<h3>Analytics Dashboards</h3>
			<p>Academic, finance, attendance, and operations dashboards with real-time insights.</p>
		</div>
		<div class="dashboard-badges">
			<span><i class="bi bi-mortarboard"></i> Academic</span>
			<span><i class="bi bi-cash-stack"></i> Finance</span>
			<span><i class="bi bi-check2-square"></i> Attendance</span>
			<span><i class="bi bi-truck"></i> Operations</span>
		</div>
	</div>
</section>

<div class="dashboard-grid intel-charts">
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

<section class="intel-footer">
	<div class="intel-card intel-block">
		<div class="block-title"><i class="bi bi-shield-check"></i> Security Layer</div>
		<div class="security-grid">
			<span>Data Encryption</span>
			<span>2FA Authentication</span>
			<span>Backup & Recovery</span>
			<span>Compliance (DPA, GDPR)</span>
		</div>
	</div>
	<div class="intel-card intel-block">
		<div class="block-title"><i class="bi bi-stars"></i> Key Features</div>
		<div class="feature-grid">
			<span>CBC / KNEC grading</span>
			<span>Attendance analytics</span>
			<span>Report cards (QR secured)</span>
			<span>Finance dashboards</span>
			<span>Alerts & notifications</span>
			<span>Exports & integrations</span>
		</div>
	</div>
	<div class="intel-card intel-block">
		<div class="block-title"><i class="bi bi-diagram-3"></i> Database Schema (Simplified)</div>
		<div class="schema-grid">
			<span>students</span>
			<span>classes</span>
			<span>subjects</span>
			<span>assessments</span>
			<span>results</span>
			<span>attendance</span>
			<span>payments</span>
			<span>invoices</span>
			<span>audit_logs</span>
		</div>
	</div>
</section>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>

<script type="text/javascript">
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

  var chartStudentsByClass = initChart('chartStudentsByClass');
  var chartStudentsByGender = initChart('chartStudentsByGender');
  var chartAttendanceToday = initChart('chartAttendanceToday');
  var chartPaymentsByDay = initChart('chartPaymentsByDay');
  var chartPaymentsByMethod = initChart('chartPaymentsByMethod');
  var chartAvgScoreByTerm = initChart('chartAvgScoreByTerm');

  fetchJson('admin/api/dashboard_stats')
    .then(function (data) {
      if (!data || data.error) return;

      if (data.counts) {
        var students = document.querySelector('[data-stat=\"students\"]');
        var teachers = document.querySelector('[data-stat=\"teachers\"]');
        if (students) students.textContent = data.counts.students || 0;
        if (teachers) teachers.textContent = data.counts.teachers || 0;
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
