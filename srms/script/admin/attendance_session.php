<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$sessionId = (int)($_GET['id'] ?? 0);
if ($sessionId < 1) {
	header("location:attendance");
	exit;
}

$session = null;
$rows = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		throw new RuntimeException("Attendance tables are not installed.");
	}

	$stmt = $conn->prepare("SELECT s.id, s.class_id, s.session_date, c.name AS class_name
		FROM tbl_attendance_sessions s
		LEFT JOIN tbl_classes c ON c.id = s.class_id
		WHERE s.id = ?
		LIMIT 1");
	$stmt->execute([$sessionId]);
	$session = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$session) {
		throw new RuntimeException("Attendance session not found.");
	}

	$stmt = $conn->prepare("SELECT st.id AS student_id,
		TRIM(concat_ws(' ', st.fname, st.mname, st.lname)) AS student_name,
		COALESCE(r.status, 'not_marked') AS status
		FROM tbl_students st
		LEFT JOIN tbl_attendance_records r ON r.session_id = ? AND r.student_id = st.id
		WHERE st.class = ?
		ORDER BY st.id");
	$stmt->execute([$sessionId, (int)$session['class_id']]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Attendance Session</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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
<h1>Attendance Session</h1>
<?php if ($session) { ?>
<p><?php echo htmlspecialchars((string)$session['class_name']); ?> — <?php echo htmlspecialchars((string)$session['session_date']); ?></p>
<?php } ?>
</div>
<div>
<a class="btn btn-outline-secondary" href="admin/attendance"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="tile">
  <h3 class="tile-title">Students</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Student ID</th>
		  <th>Name</th>
		  <th>Status</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php foreach ($rows as $r) {
		$status = (string)$r['status'];
		$badge = 'secondary';
		if ($status === 'present') $badge = 'success';
		if ($status === 'absent') $badge = 'danger';
		if ($status === 'late') $badge = 'warning';
		if ($status === 'excused') $badge = 'info';
	  ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$r['student_id']); ?></td>
		  <td><?php echo htmlspecialchars((string)$r['student_name']); ?></td>
		  <td><span class="badge bg-<?php echo $badge; ?>"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $status))); ?></span></td>
		</tr>
	  <?php } ?>
	  </tbody>
	</table>
  </div>
</div>
<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
