<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res !== '1' || $level !== '2') { header('location:../'); exit; }

$students = [];
$cases = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_cases_table($conn);

	$classMap = [];
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$year = (int)date('Y');
		$stmt = $conn->prepare('SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1 AND year = ?');
		$stmt->execute([(int)$account_id, $year]);
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $classId) {
			$classId = (int)$classId;
			if ($classId > 0) {
				$classMap[$classId] = true;
			}
		}
	}

	if (count($classMap) > 0) {
		$placeholders = implode(',', array_fill(0, count($classMap), '?'));
		$params = array_map('intval', array_keys($classMap));
		$stmt = $conn->prepare("SELECT st.id, st.class AS class_id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
			FROM tbl_students st
			LEFT JOIN tbl_classes c ON c.id = st.class
			WHERE st.class IN ($placeholders)
			ORDER BY c.name, st.fname, st.lname");
		$stmt->execute($params);
	} else {
		$stmt = $conn->prepare("SELECT st.id, st.class AS class_id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
			FROM tbl_students st
			LEFT JOIN tbl_classes c ON c.id = st.class
			ORDER BY c.name, st.fname, st.lname
			LIMIT 500");
		$stmt->execute();
	}
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT d.id, d.student_id, d.incident_type, d.description, d.severity, d.status, d.created_at,
		concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
		c.name AS class_name
		FROM tbl_discipline_cases d
		JOIN tbl_students st ON st.id = d.student_id
		LEFT JOIN tbl_classes c ON c.id = d.class_id
		WHERE d.teacher_id = ?
		ORDER BY d.id DESC
		LIMIT 200");
	$stmt->execute([(int)$account_id]);
	$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$error = 'Failed to load discipline module.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Teacher Discipline</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.sev-low{color:#1f8f52;font-weight:700}
.sev-medium{color:#b88900;font-weight:700}
.sev-high{color:#d33c2d;font-weight:700}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('teacher/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Discipline Cases</h1><p>Create student incidents and notify parents immediately.</p></div></div>

<?php if ($error !== ''): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">New Incident</h3>
<form method="POST" action="teacher/core/submit_discipline_case" class="app_frm" id="disciplineForm">
<div class="mb-2">
<label class="form-label">Student</label>
<select class="form-control" name="student_id" required>
<option value="">Select student</option>
<?php foreach ($students as $st): ?>
<option value="<?php echo htmlspecialchars((string)$st['id']); ?>"><?php echo htmlspecialchars((string)$st['student_name'].' - '.(string)($st['class_name'] ?? '')); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2">
<label class="form-label">Incident Type</label>
<select class="form-control" name="incident_type" required>
<option value="Late">Late</option>
<option value="Fighting">Fighting</option>
<option value="Disrespect">Disrespect</option>
<option value="Absenteeism">Absenteeism</option>
<option value="Uniform">Uniform</option>
<option value="Other">Other</option>
</select>
</div>
<div class="mb-2">
<label class="form-label">Severity</label>
<select class="form-control" name="severity" required>
<option value="low">Low</option>
<option value="medium" selected>Medium</option>
<option value="high">High</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Description</label>
<textarea class="form-control" name="description" rows="4" required></textarea>
</div>
<button class="btn btn-primary" type="submit">Submit Incident</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">My Submitted Cases</h3>
<div class="small text-muted mb-2">Auto refresh every 5 seconds for live updates.</div>
<div class="table-responsive">
<table class="table table-hover table-striped">
<thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Type</th><th>Severity</th><th>Status</th><th>Details</th></tr></thead>
<tbody>
<?php foreach ($cases as $case): ?>
<tr>
<td><?php echo htmlspecialchars((string)$case['created_at']); ?></td>
<td><?php echo htmlspecialchars((string)$case['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)($case['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$case['incident_type']); ?></td>
<td class="sev-<?php echo htmlspecialchars((string)$case['severity']); ?>"><?php echo ucfirst(htmlspecialchars((string)$case['severity'])); ?></td>
<td><?php echo htmlspecialchars((string)$case['status']); ?></td>
<td><?php echo htmlspecialchars((string)$case['description']); ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$cases): ?><tr><td colspan="7" class="text-center text-muted">No discipline incidents submitted yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
let pauseRefresh = false;
document.addEventListener('focusin', function() { pauseRefresh = true; });
document.addEventListener('focusout', function() { pauseRefresh = false; });
setInterval(function() {
	if (!pauseRefresh) {
		window.location.reload();
	}
}, 5000);
</script>
</body>
</html>
