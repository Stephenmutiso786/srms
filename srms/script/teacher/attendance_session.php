<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../"); exit;}

$sessionId = (int)($_GET['id'] ?? 0);
if ($sessionId < 1) {
	header("location:attendance");
	exit;
}

$session = null;
$students = [];
$existing = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_attendance_sessions') || !app_table_exists($conn, 'tbl_attendance_records')) {
		throw new RuntimeException("Attendance tables are not installed. Run the Postgres migration 001_rbac_attendance.sql.");
	}

	// Allowed classes for this teacher.
	$stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations WHERE teacher = ?");
	$stmt->execute([(int)$account_id]);
	$rows = $stmt->fetchAll(PDO::FETCH_NUM);
	$allowed = [];
	foreach ($rows as $r) {
		foreach (app_unserialize($r[0]) as $c) {
			$allowed[] = (int)$c;
		}
	}
	$allowed = array_values(array_unique($allowed));

	$stmt = $conn->prepare("SELECT s.id, s.class_id, s.session_date, s.term_id, c.name AS class_name
		FROM tbl_attendance_sessions s
		LEFT JOIN tbl_classes c ON c.id = s.class_id
		WHERE s.id = ?
		LIMIT 1");
	$stmt->execute([$sessionId]);
	$session = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$session) {
		throw new RuntimeException("Attendance session not found.");
	}

	$classId = (int)$session['class_id'];
	if (!in_array($classId, $allowed, true)) {
		throw new RuntimeException("You are not allowed to view this session.");
	}

	$stmt = $conn->prepare("SELECT id, fname, mname, lname, gender FROM tbl_students WHERE class = ? AND status = 1 ORDER BY id");
	$stmt->execute([$classId]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT student_id, status FROM tbl_attendance_records WHERE session_id = ?");
	$stmt->execute([$sessionId]);
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
		$existing[(string)$r['student_id']] = (string)$r['status'];
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
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
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
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
<p class="app-sidebar__user-designation">Teacher</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item active" href="teacher/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Attendance Session</h1>
<?php if ($session) { ?>
<p><?php echo htmlspecialchars((string)$session['class_name']); ?> — <?php echo htmlspecialchars((string)$session['session_date']); ?></p>
<?php } ?>
</div>
<div>
<a class="btn btn-outline-secondary" href="teacher/attendance"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
</div>

<?php if ($error !== '') { ?>
<div class="tile">
  <div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div>
</div>
<?php } else { ?>

<div class="tile">
<h3 class="tile-title">Mark Attendance</h3>
<form method="POST" action="teacher/core/save_attendance">
<input type="hidden" name="session_id" value="<?php echo $sessionId; ?>">

<div class="table-responsive">
<table class="table table-hover table-striped">
<thead>
  <tr>
    <th>Student ID</th>
    <th>Name</th>
    <th style="width:220px;">Status</th>
  </tr>
</thead>
<tbody>
<?php foreach ($students as $st) {
	$sid = (string)$st['id'];
	$name = trim((string)$st['fname'].' '.(string)$st['mname'].' '.(string)$st['lname']);
	$current = $existing[$sid] ?? 'present';
?>
<tr>
  <td><?php echo htmlspecialchars($sid); ?></td>
  <td><?php echo htmlspecialchars($name); ?></td>
  <td>
	<select class="form-control" name="status[<?php echo htmlspecialchars($sid); ?>]">
	  <option value="present" <?php echo $current === 'present' ? 'selected' : ''; ?>>Present</option>
	  <option value="absent" <?php echo $current === 'absent' ? 'selected' : ''; ?>>Absent</option>
	  <option value="late" <?php echo $current === 'late' ? 'selected' : ''; ?>>Late</option>
	  <option value="excused" <?php echo $current === 'excused' ? 'selected' : ''; ?>>Excused</option>
	</select>
  </td>
</tr>
<?php } ?>
</tbody>
</table>
</div>

<div class="d-grid">
  <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Attendance</button>
</div>
</form>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

