<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '3') { header('location:../'); exit; }

$roles = app_student_role_catalog();
$leaders = [];
$myRoles = [];
$terms = [];
$currentClassId = 0;
$currentYear = (int)date('Y');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_roles_table($conn);
	app_ensure_student_leadership_reports_table($conn);

	$stmt = $conn->prepare('SELECT class FROM tbl_students WHERE id = ? LIMIT 1');
	$stmt->execute([(string)$account_id]);
	$currentClassId = (int)$stmt->fetchColumn();

	$stmt = $conn->prepare('SELECT id, name FROM tbl_terms ORDER BY id');
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT sr.role_code, sr.class_id, sr.term_id, sr.year, sr.responsibilities, c.name AS class_name, t.name AS term_name
		FROM tbl_student_roles sr
		LEFT JOIN tbl_classes c ON c.id = sr.class_id
		LEFT JOIN tbl_terms t ON t.id = sr.term_id
		WHERE sr.student_id = ? AND sr.status = 1
		ORDER BY sr.year DESC");
	$stmt->execute([(string)$account_id]);
	$myRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT sr.role_code, sr.responsibilities, sr.term_id, sr.year,
		concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
		c.name AS class_name,
		t.name AS term_name
		FROM tbl_student_roles sr
		JOIN tbl_students st ON st.id = sr.student_id
		LEFT JOIN tbl_classes c ON c.id = sr.class_id
		LEFT JOIN tbl_terms t ON t.id = sr.term_id
		WHERE sr.status = 1
		ORDER BY sr.year DESC, c.name, sr.role_code, st.fname");
	$stmt->execute();
	$leaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$leaders = [];
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<title><?php echo APP_NAME; ?> - Student Leadership</title>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head><body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a></header>
<aside class="app-sidebar"><ul class="app-menu"><li><a class="app-menu__item" href="student"><span class="app-menu__label">Dashboard</span></a></li><li><a class="app-menu__item" href="student/report_card"><span class="app-menu__label">Report Card</span></a></li><li><a class="app-menu__item active" href="student/leadership"><span class="app-menu__label">Student Leadership</span></a></li></ul></aside>
<main class="app-content"><div class="app-title"><div><h1>Student Leadership</h1></div></div>

<div class="tile mb-3">
<h3 class="tile-title">Leadership Roster</h3>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Role</th><th>Student</th><th>Class</th><th>Term/Year</th><th>Responsibilities</th></tr></thead><tbody>
<?php foreach($leaders as $row): ?>
<tr><td><?php echo htmlspecialchars((string)($roles[$row['role_code']] ?? $row['role_code'])); ?></td><td><?php echo htmlspecialchars((string)$row['student_name']); ?></td><td><?php echo htmlspecialchars((string)$row['class_name']); ?></td><td><?php echo htmlspecialchars(((string)($row['term_name'] ?? 'Whole Year')).' / '.(string)$row['year']); ?></td><td><?php echo htmlspecialchars((string)$row['responsibilities']); ?></td></tr>
<?php endforeach; ?>
<?php if(!$leaders): ?><tr><td colspan="5" class="text-center text-muted">No student leaders assigned yet.</td></tr><?php endif; ?>
</tbody></table></div>
</div>

<?php if ($myRoles): ?>
<div class="tile mb-3">
<h3 class="tile-title">My Leadership Roles</h3>
<ul>
<?php foreach ($myRoles as $mr): ?>
<li><?php echo htmlspecialchars((string)($roles[$mr['role_code']] ?? $mr['role_code']).' - '.(string)($mr['class_name'] ?? '').' ('.((string)($mr['term_name'] ?? 'Whole Year')).' / '.(string)$mr['year'].')'); ?></li>
<?php endforeach; ?>
</ul>
</div>

<div class="tile">
<h3 class="tile-title">Submit Leadership Report</h3>
<form method="POST" action="student/core/submit_leadership_report" class="app_frm">
<div class="row">
<div class="col-md-4 mb-2"><label class="form-label">Report Type</label><select class="form-control" name="report_type" required><option value="discipline">Discipline</option><option value="welfare">Welfare</option><option value="sanitation">Sanitation</option><option value="sports">Sports</option><option value="library">Library</option><option value="timekeeping">Timekeeping</option><option value="other">Other</option></select></div>
<div class="col-md-4 mb-2"><label class="form-label">Term</label><select class="form-control" name="term_id"><option value="">Current/None</option><?php foreach($terms as $t): ?><option value="<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars((string)$t['name']); ?></option><?php endforeach; ?></select></div>
<div class="col-md-4 mb-2"><label class="form-label">Year</label><input class="form-control" type="number" name="year" value="<?php echo $currentYear; ?>" required></div>
</div>
<div class="mb-2"><label class="form-label">Title</label><input class="form-control" type="text" name="title" required maxlength="200"></div>
<div class="mb-3"><label class="form-label">Details</label><textarea class="form-control" name="details" rows="4" required></textarea></div>
<input type="hidden" name="class_id" value="<?php echo $currentClassId; ?>">
<button class="btn btn-primary" type="submit">Submit Report</button>
</form>
</div>
<?php endif; ?>
</main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body></html>
