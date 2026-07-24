<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

app_require_discipline_access();

$cases = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_cases_table($conn);

	$stmt = $conn->prepare("SELECT d.id, d.incident_type, d.description, d.severity, d.status, d.action_taken, d.created_at,
		concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
		concat_ws(' ', t.fname, t.lname) AS teacher_name,
		c.name AS class_name,
		concat_ws(' ', rv.fname, rv.lname) AS reviewer_name
		FROM tbl_discipline_cases d
		JOIN tbl_students st ON st.id = d.student_id
		JOIN tbl_staff t ON t.id = d.teacher_id
		LEFT JOIN tbl_classes c ON c.id = d.class_id
		LEFT JOIN tbl_staff rv ON rv.id = d.reviewed_by
		ORDER BY d.id DESC
		LIMIT 300");
	$stmt->execute();
	$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$error = 'Failed to load discipline cases.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Discipline Cases</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<style>
.sev-low{color:#1f8f52;font-weight:700}
.sev-medium{color:#b88900;font-weight:700}
.sev-high{color:#d33c2d;font-weight:700}
</style>
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
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Discipline Cases</h1><p>Review and resolve teacher-submitted incidents.</p></div></div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<div class="tile">
<div class="small text-muted mb-2">Latest updates appear after saving or reloading this page.</div>
<div class="table-responsive">
<table class="table table-hover table-striped">
<thead><tr><th>Date</th><th>Student</th><th>Class</th><th>Teacher</th><th>Type</th><th>Severity</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($cases as $case): ?>
<tr>
<td><?php echo htmlspecialchars((string)$case['created_at']); ?></td>
<td><?php echo htmlspecialchars((string)$case['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)($case['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$case['teacher_name']); ?></td>
<td><strong><?php echo htmlspecialchars((string)$case['incident_type']); ?></strong><br><small><?php echo htmlspecialchars((string)$case['description']); ?></small></td>
<td class="sev-<?php echo htmlspecialchars((string)$case['severity']); ?>"><?php echo ucfirst(htmlspecialchars((string)$case['severity'])); ?></td>
<td><?php echo htmlspecialchars((string)$case['status']); ?><?php if (!empty($case['reviewer_name'])): ?><br><small>By <?php echo htmlspecialchars((string)$case['reviewer_name']); ?></small><?php endif; ?></td>
<td>
<form method="POST" action="admin/core/update_discipline_case" class="d-grid gap-1">
<input type="hidden" name="id" value="<?php echo (int)$case['id']; ?>">
<select class="form-control form-control-sm" name="status">
<?php foreach (['pending','reviewed','resolved'] as $st): ?>
<option value="<?php echo $st; ?>" <?php echo $st === (string)$case['status'] ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
<?php endforeach; ?>
</select>
<input class="form-control form-control-sm" type="text" name="action_taken" placeholder="Action taken" value="<?php echo htmlspecialchars((string)($case['action_taken'] ?? '')); ?>">
<button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
</form>
<div class="d-flex flex-wrap gap-1 mt-2">
<a class="btn btn-sm btn-outline-secondary" href="academic/discipline_letter.php?id=<?php echo (int)$case['id']; ?>" target="_blank">Print Letter</a>
<form method="POST" action="admin/core/send_discipline_notice.php" class="d-inline">
<input type="hidden" name="case_id" value="<?php echo (int)$case['id']; ?>">
<input type="hidden" name="return_to" value="../../admin/discipline.php">
<button class="btn btn-sm btn-outline-success" type="submit">Send WhatsApp Notice</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$cases): ?><tr><td colspan="8" class="text-center text-muted">No discipline incidents found.</td></tr><?php endif; ?>
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
