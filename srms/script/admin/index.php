<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/academic_dashboard.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
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
<li><a class="app-menu__item" href="admin/results_locks"><i class="app-menu__icon feather icon-lock"></i><span class="app-menu__label">Results Locks</span></a></li>
<li><a class="app-menu__item" href="admin/results_analytics"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Results Analytics</span></a></li>
<li><a class="app-menu__item" href="admin/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item" href="admin/smtp"><i class="app-menu__icon feather icon-mail"></i><span class="app-menu__label">SMTP Settings</span></a></li>
<li><a class="app-menu__item" href="admin/system"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">System Settings</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>Dashboard</h1>
</div>

</div>
<div class="row">
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-folder fs-1"></i>
<div class="info">
<h4>Academic Terms</h4>
<p><b><?php echo number_format($academic_terms); ?></b></p>
</div>
</div>
</div>
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-user fs-1"></i>
<div class="info">
<h4>Teachers</h4>
<p><b><?php echo number_format($teachers); ?></b></p>
</div>
</div>
</div>
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-users fs-1"></i>
<div class="info">
<h4>Students</h4>
<p><b><?php echo number_format($my_students); ?></b></p>
</div>
</div>
</div>
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-book-open fs-1"></i>
<div class="info">
<h4>Subjects</h4>
<p><b><?php echo number_format($subjects); ?></b></p>
</div>
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

  function ensureChartsSection() {
    if (el('chartStudentsByClass')) return;

    var container = document.createElement('div');
    container.className = 'row mt-4';
    container.innerHTML = ''
      + '<div class="col-lg-8 mb-3">'
      + '  <div class="tile">'
      + '    <h3 class="tile-title">Students by Class</h3>'
      + '    <div id="chartStudentsByClass" style="height:320px;"></div>'
      + '  </div>'
      + '</div>'
      + '<div class="col-lg-4 mb-3">'
      + '  <div class="tile">'
      + '    <h3 class="tile-title">Students by Gender</h3>'
      + '    <div id="chartStudentsByGender" style="height:320px;"></div>'
      + '  </div>'
      + '</div>'
      + '<div class="col-12 mb-3">'
      + '  <div class="tile">'
      + '    <h3 class="tile-title">Average Score by Term</h3>'
      + '    <div id="chartAvgScoreByTerm" style="height:280px;"></div>'
      + '  </div>'
      + '</div>';

    var main = document.querySelector('main.app-content');
    if (main) main.appendChild(container);
  }

  ensureChartsSection();

  var chartStudentsByClass = initChart('chartStudentsByClass');
  var chartStudentsByGender = initChart('chartStudentsByGender');
  var chartAvgScoreByTerm = initChart('chartAvgScoreByTerm');

  fetchJson('admin/api/dashboard_stats')
    .then(function (data) {
      if (!data || data.error) return;

      if (chartStudentsByClass) {
        var labels = (data.studentsByClass || []).map(function (r) { return r.name; });
        var values = (data.studentsByClass || []).map(function (r) { return Number(r.count || 0); });
        chartStudentsByClass.setOption({
          tooltip: { trigger: 'axis' },
          grid: { left: 40, right: 20, top: 20, bottom: 60 },
          xAxis: { type: 'category', data: labels, axisLabel: { rotate: 30 } },
          yAxis: { type: 'value' },
          series: [{ type: 'bar', data: values, itemStyle: { color: '#0d6efd' } }]
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

      if (chartAvgScoreByTerm) {
        var tLabels = (data.avgScoreByTerm || []).map(function (r) { return r.name; });
        var tValues = (data.avgScoreByTerm || []).map(function (r) { return Number(r.avg_score || 0); });
        chartAvgScoreByTerm.setOption({
          tooltip: { trigger: 'axis' },
          grid: { left: 40, right: 20, top: 20, bottom: 60 },
          xAxis: { type: 'category', data: tLabels, axisLabel: { rotate: 20 } },
          yAxis: { type: 'value', min: 0, max: 100 },
          series: [{ type: 'line', smooth: true, data: tValues, itemStyle: { color: '#198754' } }]
        });
      }
    })
    .catch(function () { /* ignore */ });
})();
</script>

</body>

</html>
