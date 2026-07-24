<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/school.php');

if ($res !== '1') { header('location:../'); exit; }
app_require_any_permission(['report.view', 'academic.manage', 'finance.view']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - AI Command Center</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
	<div class="app-title">
		<div>
			<h1>AI Command Center</h1>
			<p>School-wide intelligence, risks, predictions, and system health in one place.</p>
		</div>
	</div>

	<div class="row" id="aiCcSummaryRow"></div>

	<div class="row">
		<div class="col-lg-6">
			<div class="tile">
				<h3 class="tile-title">Priority Alerts</h3>
				<div id="aiCcAlerts">Loading alerts...</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="tile">
				<h3 class="tile-title">Risk Learners</h3>
				<div id="aiCcRisks">Loading risk learners...</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="col-lg-6">
			<div class="tile">
				<h3 class="tile-title">Predictions & Recommendations</h3>
				<div id="aiCcPredictions">Loading predictions...</div>
			</div>
		</div>
		<div class="col-lg-6">
			<div class="tile">
				<h3 class="tile-title">Insight Timeline</h3>
				<div id="aiCcTimeline">Loading timeline...</div>
			</div>
		</div>
	</div>

	<div class="tile">
		<h3 class="tile-title">System Status Monitor</h3>
		<div id="aiCcSystem">Loading system status...</div>
	</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	function esc(text) {
		return String(text || '').replace(/[&<>\"']/g, function (m) {
			return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
		});
	}
	function statCard(label, value, accent) {
		return '<div class="col-md-6 col-xl-3"><div class="tile" style="border-top:4px solid ' + accent + ';"><div class="small text-muted">' + esc(label) + '</div><div style="font-size:2rem;font-weight:800;color:#173042;">' + esc(value) + '</div></div></div>';
	}
	function listHtml(items, emptyText) {
		if (!items || !items.length) return '<div class="text-muted">' + esc(emptyText) + '</div>';
		return items.map(function (item) {
			return '<div style="padding:12px 0;border-bottom:1px solid #eef2f7;">' +
				'<div style="font-weight:800;color:#173042;">' + esc(item.title || item.name || 'Item') + '</div>' +
				'<div class="small text-muted">' + esc(item.detail || item.description || item.reason || '') + (item.metric ? ' • ' + esc(item.metric) : '') + '</div>' +
			'</div>';
		}).join('');
	}
	fetch('core/ai_command_center.php', { credentials: 'same-origin' })
		.then(function (r) { return r.ok ? r.json() : null; })
		.then(function (data) {
			if (!data || !data.ok) return;
			var s = data.summary || {};
			document.getElementById('aiCcSummaryRow').innerHTML =
				statCard('School Health Score', (s.overall || 0) + '%', '#0d6efd') +
				statCard('Academics', (s.academics || 0) + '%', '#198754') +
				statCard('Attendance', (s.attendance || 0) + '%', '#fd7e14') +
				statCard('Finance', (s.finance || 0) + '%', '#6f42c1');
			document.getElementById('aiCcAlerts').innerHTML = listHtml(data.alerts, 'No urgent alerts right now.');
			document.getElementById('aiCcRisks').innerHTML = listHtml(data.risk_learners, 'No high-risk learner flags detected.');
			document.getElementById('aiCcPredictions').innerHTML = listHtml(data.predictions, 'No prediction data available yet.');
			document.getElementById('aiCcTimeline').innerHTML = listHtml(data.timeline, 'No recent system timeline entries.');
			var sys = data.system_status || {};
			document.getElementById('aiCcSystem').innerHTML =
				'<div class="row">' +
				'<div class="col-md-3"><strong>Database</strong><div class="text-muted">' + esc(sys.database || 'unknown') + '</div></div>' +
				'<div class="col-md-3"><strong>Notifications</strong><div class="text-muted">' + esc(sys.notifications || 'unknown') + '</div></div>' +
				'<div class="col-md-3"><strong>AI Provider</strong><div class="text-muted">' + esc(sys.ai_provider || 'unknown') + '</div></div>' +
				'<div class="col-md-3"><strong>Backup</strong><div class="text-muted">' + esc(sys.backup_warning || 'No data') + '</div></div>' +
				'</div>';
		});
})();
</script>
</body>
</html>
