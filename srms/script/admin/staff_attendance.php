<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$rows = [];
$summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'not_marked' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_staff_attendance')) {
		throw new RuntimeException("Staff attendance is not installed on the server (run migration 001_rbac_attendance.sql).");
	}

	$stmt = $conn->prepare("SELECT s.id, s.fname, s.lname, s.level,
		COALESCE(a.status, 'not_marked') AS status,
		a.clock_in, a.clock_out
		FROM tbl_staff s
		LEFT JOIN tbl_staff_attendance a ON a.staff_id = s.id AND a.attendance_date = ?
		WHERE s.status = 1
		ORDER BY s.level, s.id");
	$stmt->execute([$date]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($rows as $r) {
		$k = (string)$r['status'];
		if (!isset($summary[$k])) $summary[$k] = 0;
		$summary[$k]++;
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Staff Attendance</title>
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
<h1>Staff Attendance</h1>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row mb-3">
  <div class="col-lg-4 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Date</h3>
	  <form method="GET" action="admin/staff_attendance" class="row g-2">
		<div class="col-8">
		  <input class="form-control" type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" required>
		</div>
		<div class="col-4 d-grid">
		  <button class="btn btn-primary" type="submit">Go</button>
		</div>
	  </form>
	</div>
  </div>
  <div class="col-lg-8 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Summary</h3>
	  <div id="chartStaffSummary" style="height:220px;"></div>
	</div>
  </div>
</div>

<div class="tile">
  <h3 class="tile-title">Mark / Update</h3>
  <form method="POST" action="admin/core/save_staff_attendance">
	<input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
	<div class="table-responsive">
	  <table class="table table-hover table-striped">
		<thead>
		  <tr>
			<th>ID</th>
			<th>Name</th>
			<th>Role</th>
			<th style="width:180px;">Status</th>
			<th>Clock In</th>
			<th>Clock Out</th>
		  </tr>
		</thead>
		<tbody>
		<?php foreach ($rows as $r) {
		  $sid = (int)$r['id'];
		  $status = (string)$r['status'];
		  $role = 'Staff';
		  if ((int)$r['level'] === 0) $role = 'Admin';
		  if ((int)$r['level'] === 1) $role = 'Academic';
		  if ((int)$r['level'] === 2) $role = 'Teacher';
		?>
		  <tr>
			<td><?php echo $sid; ?></td>
			<td><?php echo htmlspecialchars((string)$r['fname'].' '.(string)$r['lname']); ?></td>
			<td><?php echo htmlspecialchars($role); ?></td>
			<td>
			  <select class="form-control" name="status[<?php echo $sid; ?>]">
				<option value="not_marked" <?php echo $status === 'not_marked' ? 'selected' : ''; ?>>Not Marked</option>
				<option value="present" <?php echo $status === 'present' ? 'selected' : ''; ?>>Present</option>
				<option value="absent" <?php echo $status === 'absent' ? 'selected' : ''; ?>>Absent</option>
				<option value="late" <?php echo $status === 'late' ? 'selected' : ''; ?>>Late</option>
			  </select>
			</td>
			<td><input class="form-control" type="datetime-local" name="clock_in[<?php echo $sid; ?>]" value="<?php echo $r['clock_in'] ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime((string)$r['clock_in']))) : ''; ?>"></td>
			<td><input class="form-control" type="datetime-local" name="clock_out[<?php echo $sid; ?>]" value="<?php echo $r['clock_out'] ? htmlspecialchars(date('Y-m-d\\TH:i', strtotime((string)$r['clock_out']))) : ''; ?>"></td>
		  </tr>
		<?php } ?>
		</tbody>
	  </table>
	</div>
	<div class="d-grid">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save</button>
	</div>
  </form>
</div>

<script>
(function(){
  var el = document.getElementById('chartStaffSummary');
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
        { name: 'Present', value: <?php echo (int)$summary['present']; ?> },
        { name: 'Absent', value: <?php echo (int)$summary['absent']; ?> },
        { name: 'Late', value: <?php echo (int)$summary['late']; ?> },
        { name: 'Not Marked', value: <?php echo (int)$summary['not_marked']; ?> }
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
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

