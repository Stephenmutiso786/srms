<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$sessions = [];
$counts = ['present' => 0, 'absent' => 0, 'late' => 0, 'excused' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		throw new RuntimeException("Attendance tables are not installed. Run the Postgres migration 001_rbac_attendance.sql.");
	}

	$stmt = $conn->prepare("SELECT s.id, s.session_date, c.name AS class_name, COUNT(r.student_id) AS marked
		FROM tbl_attendance_sessions s
		LEFT JOIN tbl_classes c ON c.id = s.class_id
		LEFT JOIN tbl_attendance_records r ON r.session_id = s.id
		WHERE s.session_type = 'daily'
		GROUP BY s.id, s.session_date, c.name
		ORDER BY s.session_date DESC, s.id DESC
		LIMIT 50");
	$stmt->execute();
	$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT r.status, COUNT(*) AS c
		FROM tbl_attendance_records r
		JOIN tbl_attendance_sessions s ON s.id = r.session_id
		WHERE s.session_date = CURRENT_DATE
		GROUP BY r.status");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
		$k = (string)$r['status'];
		$counts[$k] = (int)$r['c'];
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Attendance</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
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
<h1>Attendance</h1>
<p>Daily attendance sessions and quick insights.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row">
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Today Summary</h3>
	  <div id="chartAttendanceToday" style="height:260px;"></div>
	</div>
  </div>
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Recent Sessions</h3>
	  <div class="table-responsive">
		<table class="table table-hover table-striped">
		  <thead>
			<tr>
			  <th>Date</th>
			  <th>Class</th>
			  <th>Marked</th>
			  <th>Action</th>
			</tr>
		  </thead>
		  <tbody>
		  <?php foreach ($sessions as $s) { ?>
			<tr>
			  <td><?php echo htmlspecialchars((string)$s['session_date']); ?></td>
			  <td><?php echo htmlspecialchars((string)$s['class_name']); ?></td>
			  <td><?php echo (int)$s['marked']; ?></td>
			  <td><a class="btn btn-sm btn-outline-primary" href="admin/attendance_session?id=<?php echo (int)$s['id']; ?>">View</a></td>
			</tr>
		  <?php } ?>
		  </tbody>
		</table>
	  </div>
	</div>
  </div>
</div>

<script>
(function(){
  var el = document.getElementById('chartAttendanceToday');
  if (!el || !window.echarts) return;
  var chart = echarts.init(el);
  window.addEventListener('resize', function(){ chart.resize(); });
  chart.setOption({
    tooltip: { trigger: 'item' },
    series: [{
      type: 'pie',
      radius: ['35%', '70%'],
      label: { show: true },
      data: [
        { name: 'Present', value: <?php echo (int)$counts['present']; ?> },
        { name: 'Absent', value: <?php echo (int)$counts['absent']; ?> },
        { name: 'Late', value: <?php echo (int)$counts['late']; ?> },
        { name: 'Excused', value: <?php echo (int)$counts['excused']; ?> }
      ]
    }]
  });
})();
</script>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>

