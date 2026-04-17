<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations
LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id
LEFT JOIN tbl_staff ON tbl_subject_combinations.teacher = tbl_staff.id WHERE tbl_subject_combinations.teacher = ?");
	$stmt->execute([$account_id]);
	$result = $stmt->fetchAll();

	$myclasses = [];
	foreach ($result as $value) {
		$class_arr = app_unserialize($value[1]);
		foreach ($class_arr as $c) { $myclasses[] = $c; }
	}
	$myclasses = array_values(array_unique($myclasses));

	$classes = [];
	if (count($myclasses) > 0) {
		$matches = implode(',', array_fill(0, count($myclasses), '?'));
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($matches) ORDER BY id");
		$stmt->execute($myclasses);
		$classes = $stmt->fetchAll();
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE status = 1 ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll();

	$sessions = [];
	if (app_table_exists($conn, 'tbl_attendance_sessions') && count($myclasses) > 0) {
		$matches = implode(',', array_fill(0, count($myclasses), '?'));
		$stmt = $conn->prepare("SELECT s.id, s.session_date, c.name AS class_name
			FROM tbl_attendance_sessions s
			LEFT JOIN tbl_classes c ON c.id = s.class_id
			WHERE s.class_id IN ($matches) AND s.session_type = 'daily'
			ORDER BY s.session_date DESC, s.id DESC
			LIMIT 20");
		$stmt->execute($myclasses);
		$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (PDOException $e) {
	$classes = [];
	$terms = [];
	$sessions = [];
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

<?php include("teacher/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Attendance</h1>
<p>Mark daily attendance for your classes.</p>
</div>
</div>

<div class="tile">
<h3 class="tile-title">New Attendance Session</h3>
<?php if (count($classes) < 1) { ?>
<div class="alert alert-warning">No assigned classes found. Ask admin to assign you to a class/subject combination first.</div>
<?php } ?>
<form class="row g-3" method="POST" action="teacher/core/new_attendance_session">
<div class="col-md-4">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required <?php echo count($classes) < 1 ? 'disabled' : ''; ?>>
<option value="" disabled selected>Select class</option>
<?php foreach($classes as $c){ ?>
<option value="<?php echo $c[0]; ?>"><?php echo $c[1]; ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Term</label>
<select class="form-control" name="term_id" <?php echo count($classes) < 1 ? 'disabled' : ''; ?>>
<option value="">(optional)</option>
<?php foreach($terms as $t){ ?>
<option value="<?php echo $t[0]; ?>"><?php echo $t[1]; ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Date</label>
<input class="form-control" type="date" name="session_date" required value="<?php echo date('Y-m-d'); ?>" <?php echo count($classes) < 1 ? 'disabled' : ''; ?>>
</div>
<div class="col-12 d-grid">
<button class="btn btn-primary" type="submit" <?php echo count($classes) < 1 ? 'disabled' : ''; ?>>Start Session</button>
</div>
</form>
</div>

<div class="tile mt-3">
<h3 class="tile-title">Recent Sessions</h3>
<?php if (!isset($sessions) || count($sessions) < 1) { ?>
  <p class="text-muted mb-0">No attendance sessions yet.</p>
<?php } else { ?>
  <div class="table-responsive">
    <table class="table table-hover table-striped">
      <thead>
        <tr>
          <th>Date</th>
          <th>Class</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($sessions as $s) { ?>
        <tr>
          <td><?php echo htmlspecialchars((string)$s['session_date']); ?></td>
          <td><?php echo htmlspecialchars((string)$s['class_name']); ?></td>
          <td><a class="btn btn-sm btn-outline-primary" href="teacher/attendance_session?id=<?php echo (int)$s['id']; ?>">Open</a></td>
        </tr>
        <?php } ?>
      </tbody>
    </table>
  </div>
<?php } ?>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
