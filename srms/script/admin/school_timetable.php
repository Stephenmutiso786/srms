<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('academic.manage', 'admin');

$classes = [];
$terms = [];
$rows = [];
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$error = '';
$schoolDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_school_timetable_table($conn);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$schoolDays = app_school_days($conn);

	if ($classId > 0 && $termId > 0) {
		$stmt = $conn->prepare("SELECT st.id, st.day_name, st.session_label, st.start_time, st.end_time, st.room,
			sb.name AS subject_name, concat_ws(' ', t.fname, t.lname) AS teacher_name
			FROM tbl_school_timetable st
			JOIN tbl_subjects sb ON sb.id = st.subject_id
			JOIN tbl_staff t ON t.id = st.teacher_id
			WHERE st.class_id = ? AND st.term_id = ?
			ORDER BY CASE st.day_name
				WHEN 'Monday' THEN 1
				WHEN 'Tuesday' THEN 2
				WHEN 'Wednesday' THEN 3
				WHEN 'Thursday' THEN 4
				WHEN 'Friday' THEN 5
				WHEN 'Saturday' THEN 6
				WHEN 'Sunday' THEN 7
				ELSE 8 END, st.start_time");
		$stmt->execute([$classId, $termId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - School Timetable</title>
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
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
	<div>
		<h1>School Timetable</h1>
		<p>Generate a clash-free teaching timetable from real teacher allocations.</p>
	</div>
</div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
	<h3 class="tile-title">Select Class & Term</h3>
	<form class="row g-3" method="GET" action="admin/school_timetable">
		<div class="col-md-5">
			<label class="form-label">Class</label>
			<select class="form-control" name="class_id" required>
				<option value="" disabled <?php echo $classId ? '' : 'selected'; ?>>Select class</option>
				<?php foreach ($classes as $class): ?>
				<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$class['id'] === $classId) ? 'selected' : ''; ?>>
					<?php echo htmlspecialchars($class['name']); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-5">
			<label class="form-label">Term</label>
			<select class="form-control" name="term_id" required>
				<option value="" disabled <?php echo $termId ? '' : 'selected'; ?>>Select term</option>
				<?php foreach ($terms as $term): ?>
				<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>>
					<?php echo htmlspecialchars($term['name']); ?>
				</option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-2 d-grid align-items-end">
			<button class="btn btn-primary" type="submit">Load</button>
		</div>
	</form>
</div>

<?php if ($classId > 0 && $termId > 0) { ?>
<div class="tile mb-3">
	<h3 class="tile-title">Smart Auto Generate</h3>
	<form class="row g-3" method="POST" action="admin/core/auto_generate_school_timetable">
		<input type="hidden" name="class_id" value="<?php echo $classId; ?>">
		<input type="hidden" name="term_id" value="<?php echo $termId; ?>">
		<div class="col-md-4">
			<label class="form-label">Academic Year</label>
			<input class="form-control" type="number" name="year" value="<?php echo (int)date('Y'); ?>" min="2000" required>
		</div>
		<div class="col-md-4">
			<label class="form-label">School Days</label>
			<input class="form-control" name="days" value="<?php echo htmlspecialchars(implode(',', $schoolDays)); ?>" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">Daily Sessions</label>
			<input class="form-control" type="number" name="sessions_per_day" min="1" max="8" value="6" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">Session Minutes</label>
			<input class="form-control" type="number" name="duration_minutes" min="30" max="180" value="40" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">Break Minutes</label>
			<input class="form-control" type="number" name="break_minutes" min="0" max="60" value="10" required>
		</div>
		<div class="col-md-2">
			<label class="form-label">First Start</label>
			<input class="form-control" type="time" name="first_start_time" value="08:00" required>
		</div>
		<div class="col-md-3">
			<label class="form-label">Room Prefix</label>
			<input class="form-control" name="room_prefix" value="Class Room" placeholder="Class Room">
		</div>
		<div class="col-md-5">
			<label class="form-label">Generation Mode</label>
			<select class="form-control" name="clear_existing">
				<option value="1">Replace this class timetable for the selected term</option>
				<option value="0">Append only if free slots exist</option>
			</select>
		</div>
		<div class="col-md-12 d-grid">
			<button class="btn btn-success" type="submit"><i class="bi bi-stars me-1"></i>Generate School Timetable</button>
		</div>
	</form>
	<p class="text-muted mt-2 mb-0">The generator uses teacher allocations and avoids putting one teacher in two classes during the same session.</p>
</div>

<div class="tile">
	<h3 class="tile-title">Current Timetable</h3>
	<div class="table-responsive">
		<table class="table table-hover">
			<thead>
				<tr><th>Day</th><th>Session</th><th>Start</th><th>End</th><th>Subject</th><th>Teacher</th><th>Room</th></tr>
			</thead>
			<tbody>
				<?php foreach ($rows as $row): ?>
				<tr>
					<td><?php echo htmlspecialchars($row['day_name']); ?></td>
					<td><?php echo htmlspecialchars($row['session_label']); ?></td>
					<td><?php echo htmlspecialchars(substr((string)$row['start_time'], 0, 5)); ?></td>
					<td><?php echo htmlspecialchars(substr((string)$row['end_time'], 0, 5)); ?></td>
					<td><?php echo htmlspecialchars($row['subject_name']); ?></td>
					<td><?php echo htmlspecialchars($row['teacher_name']); ?></td>
					<td><?php echo htmlspecialchars((string)($row['room'] ?? '')); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$rows): ?>
				<tr><td colspan="7" class="text-center text-muted">No school timetable generated yet for this class and term.</td></tr>
				<?php endif; ?>
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
