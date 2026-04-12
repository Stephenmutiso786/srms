<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../'); exit; }
app_require_permission('student.leadership.manage', '../admin');

$classes = [];
$students = [];
$terms = [];
$roles = app_student_role_catalog();
$leaders = [];
$reports = [];
$year = (int)date('Y');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_roles_table($conn);
	app_ensure_student_leadership_reports_table($conn);

	$stmt = $conn->prepare('SELECT id, name FROM tbl_classes ORDER BY name');
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare('SELECT id, name FROM tbl_terms ORDER BY id');
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT st.id, st.class, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		ORDER BY c.name, st.fname, st.lname");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT sr.id, sr.student_id, sr.class_id, sr.role_code, sr.responsibilities, sr.term_id, sr.year,
		concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
		c.name AS class_name, t.name AS term_name
		FROM tbl_student_roles sr
		JOIN tbl_students st ON st.id = sr.student_id
		LEFT JOIN tbl_classes c ON c.id = sr.class_id
		LEFT JOIN tbl_terms t ON t.id = sr.term_id
		WHERE sr.status = 1
		ORDER BY sr.year DESC, c.name, sr.role_code, st.fname");
	$stmt->execute();
	$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT r.id, r.report_type, r.title, r.details, r.status, r.created_at,
		concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
		c.name AS class_name,
		h.fname AS handled_fname,
		h.lname AS handled_lname
		FROM tbl_student_leadership_reports r
		JOIN tbl_students st ON st.id = r.student_id
		LEFT JOIN tbl_classes c ON c.id = r.class_id
		LEFT JOIN tbl_staff h ON h.id = r.handled_by
		ORDER BY r.id DESC
		LIMIT 200");
	$stmt->execute();
	$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to load student leadership module.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Student Leadership</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Student Leadership</h1><p>Assign student leaders, track responsibilities, and manage discipline reports.</p></div></div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Assign Student Leader</h3>
<form method="POST" action="admin/core/save_student_leader" class="app_frm">
<div class="mb-2"><label class="form-label">Student</label>
<select class="form-control" name="student_id" required>
<option value="">Select student</option>
<?php foreach ($students as $s): ?>
<option value="<?php echo htmlspecialchars((string)$s['id']); ?>"><?php echo htmlspecialchars((string)$s['student_name'].' - '.(string)($s['class_name'] ?? '')); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2"><label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select class</option>
<?php foreach ($classes as $c): ?>
<option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars((string)$c['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2"><label class="form-label">Leadership Role</label>
<select class="form-control" name="role_code" required>
<option value="">Select role</option>
<?php foreach ($roles as $code => $label): ?>
<option value="<?php echo htmlspecialchars($code); ?>"><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2"><label class="form-label">Term</label>
<select class="form-control" name="term_id">
<option value="">Whole Year</option>
<?php foreach ($terms as $t): ?>
<option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars((string)$t['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-2"><label class="form-label">Year</label><input class="form-control" type="number" name="year" value="<?php echo $year; ?>" required></div>
<div class="mb-3"><label class="form-label">Responsibilities</label><textarea class="form-control" name="responsibilities" rows="3" placeholder="Describe duties and expectations"></textarea></div>
<button class="btn btn-primary" type="submit">Save Assignment</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Current Leadership List</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Student</th><th>Class</th><th>Role</th><th>Term/Year</th><th>Responsibilities</th><th></th></tr></thead>
<tbody>
<?php foreach ($leaders as $row): ?>
<tr>
<td><?php echo htmlspecialchars((string)$row['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)$row['class_name']); ?></td>
<td><?php echo htmlspecialchars((string)($roles[$row['role_code']] ?? $row['role_code'])); ?></td>
<td><?php echo htmlspecialchars(((string)($row['term_name'] ?? 'Whole Year')).' / '.(string)$row['year']); ?></td>
<td><?php echo htmlspecialchars((string)($row['responsibilities'] ?? '')); ?></td>
<td><a class="btn btn-sm btn-danger" href="admin/core/delete_student_leader?id=<?php echo (int)$row['id']; ?>" onclick="return confirm('Remove this assignment?');">Remove</a></td>
</tr>
<?php endforeach; ?>
<?php if (!$leaders): ?><tr><td colspan="6" class="text-center text-muted">No leadership assignments found.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="tile mt-3">
<h3 class="tile-title">Student Leadership Reports</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Type</th><th>Issue</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($reports as $r): ?>
<tr>
<td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
<td><?php echo htmlspecialchars((string)$r['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)($r['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$r['report_type']); ?></td>
<td><strong><?php echo htmlspecialchars((string)$r['title']); ?></strong><br><small><?php echo htmlspecialchars((string)$r['details']); ?></small></td>
<td><?php echo htmlspecialchars((string)$r['status']); ?><?php if (!empty($r['handled_fname'])) { ?><br><small>By <?php echo htmlspecialchars((string)$r['handled_fname'].' '.(string)$r['handled_lname']); ?></small><?php } ?></td>
<td>
<form method="POST" action="admin/core/update_student_leader_report" class="d-flex gap-1">
<input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
<select class="form-control form-control-sm" name="status">
<?php foreach (['open','in_review','resolved','dismissed'] as $st): ?>
<option value="<?php echo $st; ?>" <?php echo ($st === (string)$r['status']) ? 'selected' : ''; ?>><?php echo $st; ?></option>
<?php endforeach; ?>
</select>
<button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$reports): ?><tr><td colspan="7" class="text-center text-muted">No reports submitted yet.</td></tr><?php endif; ?>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
