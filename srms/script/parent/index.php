<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "4") {}else{header("location:../"); exit;}

$students = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException("Parent module is not installed on the server.");
	}

	$stmt = $conn->prepare("SELECT st.id, concat_ws(' ', st.fname, st.mname, st.lname) AS name, c.name AS class_name
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE ps.parent_id = ?
		ORDER BY st.id");
	$stmt->execute([(int)$account_id]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Parent Dashboard</title>
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Parent</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item active" href="parent"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="parent/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
<li><a class="app-menu__item" href="parent/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Parent Dashboard</h1>
<p>Linked students</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="tile">
  <h3 class="tile-title">Students</h3>
  <?php if (count($students) < 1) { ?>
	<div class="alert alert-info mb-0">No students linked yet. Ask the school admin to link your account.</div>
  <?php } else { ?>
	<div class="table-responsive">
	  <table class="table table-hover table-striped">
		<thead>
		  <tr>
			<th>Student ID</th>
			<th>Name</th>
			<th>Class</th>
			<th>Action</th>
		  </tr>
		</thead>
		<tbody>
		<?php foreach ($students as $st) { ?>
		  <tr>
			<td><?php echo htmlspecialchars((string)$st['id']); ?></td>
			<td><?php echo htmlspecialchars((string)$st['name']); ?></td>
			<td><?php echo htmlspecialchars((string)($st['class_name'] ?? '')); ?></td>
			<td><a class="btn btn-sm btn-outline-primary" href="parent/attendance?student_id=<?php echo htmlspecialchars((string)$st['id']); ?>">View Attendance</a></td>
		  </tr>
		<?php } ?>
		</tbody>
	  </table>
	</div>
  <?php } ?>
</div>
<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
