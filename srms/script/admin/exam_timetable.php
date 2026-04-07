<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$classes = [];
$terms = [];
$combinations = [];
$teachers = [];
$rows = [];

$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_exam_schedule')) {
		throw new RuntimeException("Exam timetable is not installed. Run migration 005_exam_timetable.sql.");
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level IN (0,1,2,5) AND status = 1 ORDER BY level, id");
	$stmt->execute();
	$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT sc.id, sc.class AS class_list, sb.name AS subject_name,
		sc.teacher, concat_ws(' ', st.fname, st.lname) AS teacher_name
		FROM tbl_subject_combinations sc
		JOIN tbl_subjects sb ON sb.id = sc.subject
		JOIN tbl_staff st ON st.id = sc.teacher
		ORDER BY sb.name");
	$stmt->execute();
	$all = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Filter combinations by class (optional)
	foreach ($all as $c) {
		$list = app_unserialize($c['class_list']);
		if ($classId > 0 && !in_array((string)$classId, array_map('strval', $list), true)) {
			continue;
		}
		$combinations[] = $c;
	}

	if ($classId > 0 && $termId > 0) {
		$stmt = $conn->prepare("SELECT es.id, es.exam_date, es.start_time, es.end_time, es.room,
			c.name AS class_name, t.name AS term_name,
			sb.name AS subject_name,
			concat_ws(' ', st.fname, st.lname) AS invigilator_name
			FROM tbl_exam_schedule es
			JOIN tbl_classes c ON c.id = es.class_id
			JOIN tbl_terms t ON t.id = es.term_id
			JOIN tbl_subject_combinations sc ON sc.id = es.subject_combination_id
			JOIN tbl_subjects sb ON sb.id = sc.subject
			LEFT JOIN tbl_staff st ON st.id = es.invigilator
			WHERE es.class_id = ? AND es.term_id = ?
			ORDER BY es.exam_date, es.start_time");
		$stmt->execute([$classId, $termId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
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
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Exam Timetable</h1>
<p>Schedule exams per class and term (with room + invigilator).</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Class & Term</h3>
  <form class="row g-3" method="GET" action="admin/exam_timetable">
	<div class="col-md-5">
	  <label class="form-label">Class</label>
	  <select class="form-control" name="class_id" required>
		<option value="" disabled <?php echo $classId ? '' : 'selected'; ?>>Select class</option>
		<?php foreach ($classes as $c) { ?>
		  <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$c['id'] === $classId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$c['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-5">
	  <label class="form-label">Term</label>
	  <select class="form-control" name="term_id" required>
		<option value="" disabled <?php echo $termId ? '' : 'selected'; ?>>Select term</option>
		<?php foreach ($terms as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>" <?php echo ((int)$t['id'] === $termId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$t['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-2 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit">Load</button>
	</div>
  </form>
</div>

<?php if ($classId > 0 && $termId > 0) { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Add Schedule Entry</h3>
  <form class="row g-3" method="POST" action="admin/core/new_exam_schedule" autocomplete="off">
	<input type="hidden" name="class_id" value="<?php echo $classId; ?>">
	<input type="hidden" name="term_id" value="<?php echo $termId; ?>">
	<div class="col-md-6">
	  <label class="form-label">Subject Combination</label>
	  <select class="form-control" name="subject_combination_id" required>
		<option value="" disabled selected>Select</option>
		<?php foreach ($combinations as $c) { ?>
		  <option value="<?php echo (int)$c['id']; ?>">
			<?php echo htmlspecialchars((string)$c['subject_name'].' — '.$c['teacher_name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-3">
	  <label class="form-label">Date</label>
	  <input class="form-control" type="date" name="exam_date" required>
	</div>
	<div class="col-md-3">
	  <label class="form-label">Room (optional)</label>
	  <input class="form-control" name="room" placeholder="Room A">
	</div>
	<div class="col-md-3">
	  <label class="form-label">Start</label>
	  <input class="form-control" type="time" name="start_time" required>
	</div>
	<div class="col-md-3">
	  <label class="form-label">End</label>
	  <input class="form-control" type="time" name="end_time" required>
	</div>
	<div class="col-md-6">
	  <label class="form-label">Invigilator (optional)</label>
	  <select class="form-control" name="invigilator">
		<option value="">(none)</option>
		<?php foreach ($teachers as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars((string)$t['fname'].' '.$t['lname']); ?></option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-12 d-grid">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Add Entry</button>
	</div>
  </form>
  <p class="text-muted mt-2 mb-0">The system checks basic conflicts (same class/time, room/time, invigilator/time).</p>
</div>

<div class="tile">
  <h3 class="tile-title">Timetable Entries</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Date</th>
		  <th>Time</th>
		  <th>Subject</th>
		  <th>Room</th>
		  <th>Invigilator</th>
		  <th></th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($rows) < 1) { ?>
		<tr><td colspan="6" class="text-muted">No entries yet.</td></tr>
	  <?php } else { foreach ($rows as $r) { ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$r['exam_date']); ?></td>
		  <td><?php echo htmlspecialchars(substr((string)$r['start_time'], 0, 5).' - '.substr((string)$r['end_time'], 0, 5)); ?></td>
		  <td><?php echo htmlspecialchars((string)$r['subject_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)($r['room'] ?? '')); ?></td>
		  <td><?php echo htmlspecialchars((string)($r['invigilator_name'] ?? '')); ?></td>
		  <td>
			<a onclick="del('admin/core/drop_exam_schedule?id=<?php echo (int)$r['id']; ?>&class_id=<?php echo $classId; ?>&term_id=<?php echo $termId; ?>', 'Delete timetable entry?');" href="javascript:void(0);" class="btn btn-danger btn-sm">Delete</a>
		  </td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
  </div>
</div>

<?php } ?>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

