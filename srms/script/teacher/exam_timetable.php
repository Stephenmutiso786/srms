<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../"); exit;}

$terms = [];
$termId = (int)($_GET['term_id'] ?? 0);
$rows = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_exam_schedule')) {
		throw new RuntimeException("Exam timetable is not installed on the server (run migration 005_exam_timetable.sql).");
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($termId < 1 && count($terms) > 0) {
		$termId = (int)$terms[0]['id'];
	}

	$stmt = $conn->prepare("SELECT es.exam_date, es.start_time, es.end_time, es.room,
		c.name AS class_name,
		sb.name AS subject_name,
		concat_ws(' ', st.fname, st.lname) AS invigilator_name
		FROM tbl_exam_schedule es
		JOIN tbl_classes c ON c.id = es.class_id
		JOIN tbl_subject_combinations sc ON sc.id = es.subject_combination_id
		JOIN tbl_subjects sb ON sb.id = sc.subject
		LEFT JOIN tbl_staff st ON st.id = es.invigilator
		WHERE es.term_id = ? AND sc.teacher = ?
		ORDER BY es.exam_date, es.start_time");
	$stmt->execute([$termId, (int)$account_id]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Exam Timetable</title>
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
<li><a class="app-menu__item active" href="teacher/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Exam Timetable</h1>
<p>Schedule for your subjects.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Term</h3>
  <form class="row g-3" method="GET" action="teacher/exam_timetable">
	<div class="col-md-10">
	  <select class="form-control" name="term_id" required>
		<?php foreach ($terms as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>" <?php echo ((int)$t['id'] === $termId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$t['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-2 d-grid">
	  <button class="btn btn-primary" type="submit">View</button>
	</div>
  </form>
</div>

<div class="tile">
  <h3 class="tile-title">Entries</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Date</th>
		  <th>Time</th>
		  <th>Class</th>
		  <th>Subject</th>
		  <th>Room</th>
		  <th>Invigilator</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($rows) < 1) { ?>
		<tr><td colspan="6" class="text-muted">No entries found.</td></tr>
	  <?php } else { foreach ($rows as $r) { ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$r['exam_date']); ?></td>
		  <td><?php echo htmlspecialchars(substr((string)$r['start_time'], 0, 5).' - '.substr((string)$r['end_time'], 0, 5)); ?></td>
		  <td><?php echo htmlspecialchars((string)$r['class_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)$r['subject_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)($r['room'] ?? '')); ?></td>
		  <td><?php echo htmlspecialchars((string)($r['invigilator_name'] ?? '')); ?></td>
		</tr>
	  <?php } } ?>
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

