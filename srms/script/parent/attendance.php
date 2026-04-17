<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "4") {}else{header("location:../"); exit;}

$studentId = trim((string)($_GET['student_id'] ?? ''));
$linked = [];
$rows = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException("Parent module is not installed on the server.");
	}
	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		throw new RuntimeException("Attendance module is not installed on the server.");
	}

	$stmt = $conn->prepare("SELECT st.id, concat_ws(' ', st.fname, st.mname, st.lname) AS name, c.name AS class_name, st.class AS class_id
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE ps.parent_id = ?
		ORDER BY st.id");
	$stmt->execute([(int)$account_id]);
	$linked = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (count($linked) < 1) {
		throw new RuntimeException("No students linked to your account.");
	}

	if ($studentId === '') {
		$studentId = (string)$linked[0]['id'];
	}

	$student = null;
	foreach ($linked as $st) {
		if ((string)$st['id'] === $studentId) {
			$student = $st;
			break;
		}
	}
	if (!$student) {
		throw new RuntimeException("This student is not linked to your account.");
	}

	$stmt = $conn->prepare("SELECT s.session_date, COALESCE(r.status, 'not_marked') AS status
		FROM tbl_attendance_sessions s
		LEFT JOIN tbl_attendance_records r ON r.session_id = s.id AND r.student_id = ?
		WHERE s.class_id = ?
		ORDER BY s.session_date DESC, s.id DESC
		LIMIT 30");
	$stmt->execute([$studentId, (int)$student['class_id']]);
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
<title><?php echo APP_NAME; ?> - Parent Attendance</title>
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
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("parent/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Attendance</h1>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Student</h3>
  <form method="GET" action="parent/attendance" class="row g-2">
	<div class="col-md-8">
	  <select class="form-control" name="student_id" required>
		<?php foreach ($linked as $st) { ?>
		  <option value="<?php echo htmlspecialchars((string)$st['id']); ?>" <?php echo ((string)$st['id'] === $studentId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$st['id'].' — '.$st['name'].' ('.($st['class_name'] ?? '').')'); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-4 d-grid">
	  <button class="btn btn-primary" type="submit">View</button>
	</div>
  </form>
</div>

<div class="tile">
  <h3 class="tile-title">Recent Sessions</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Date</th>
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
		  <td><?php echo htmlspecialchars((string)$r['session_date']); ?></td>
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

