<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [0, 1, 9], true)) { header('location:../'); exit; }
app_require_permission('report.view', 'admin');

$alumniRows = [];
$summary = ['total' => 0, 'with_certificates' => 0, 'latest_year' => ''];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_student_alumni_schema($conn);
	app_ensure_certificates_table($conn);

	$sql = "SELECT st.id, st.school_id, st.fname, st.mname, st.lname, st.alumni_year, st.alumni_at, st.alumni_notes,
			COALESCE(c.name, 'Graduated Class') AS class_name,
			(SELECT COUNT(*) FROM tbl_certificates cert WHERE cert.student_id = st.id) AS certificate_count
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = " . (app_column_exists($conn, 'tbl_students', 'class_id') ? 'st.class_id' : 'st.class') . "
		WHERE COALESCE(st.is_alumni, 0) = 1
		ORDER BY COALESCE(st.alumni_at, st.id) DESC";
	$stmt = $conn->query($sql);
	$alumniRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	$summary['total'] = count($alumniRows);
	$summary['with_certificates'] = count(array_filter($alumniRows, static function (array $row): bool {
		return (int)($row['certificate_count'] ?? 0) > 0;
	}));
	$summary['latest_year'] = (string)($alumniRows[0]['alumni_year'] ?? '');
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to load alumni register.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Alumni Register</title>
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
<div class="app-title"><div><h1>Alumni Register</h1><p>Completed learners are kept here permanently for future reference.</p></div></div>
<div class="row">
<div class="col-md-4"><div class="tile"><div class="tile-body"><h5>Total Alumni</h5><h2><?php echo (int)$summary['total']; ?></h2></div></div></div>
<div class="col-md-4"><div class="tile"><div class="tile-body"><h5>With Certificates</h5><h2><?php echo (int)$summary['with_certificates']; ?></h2></div></div></div>
<div class="col-md-4"><div class="tile"><div class="tile-body"><h5>Latest Alumni Year</h5><h2><?php echo htmlspecialchars($summary['latest_year'] !== '' ? $summary['latest_year'] : 'N/A'); ?></h2></div></div></div>
</div>
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<table class="table table-hover table-bordered">
<thead><tr><th>Adm/ID</th><th>Learner</th><th>Last Class</th><th>Alumni Year</th><th>Moved On</th><th>Certificates</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach ($alumniRows as $row): ?>
<tr>
<td><?php echo htmlspecialchars((string)($row['school_id'] ?? $row['id'])); ?></td>
<td><?php echo htmlspecialchars(trim((string)($row['fname'] ?? '') . ' ' . (string)($row['mname'] ?? '') . ' ' . (string)($row['lname'] ?? ''))); ?></td>
<td><?php echo htmlspecialchars((string)($row['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)($row['alumni_year'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)($row['alumni_at'] ?? '')); ?></td>
<td><?php echo (int)($row['certificate_count'] ?? 0); ?></td>
<td><?php echo htmlspecialchars((string)($row['alumni_notes'] ?? '')); ?></td>
</tr>
<?php endforeach; ?>
<?php if (!$alumniRows): ?>
<tr><td colspan="7" class="text-center text-muted">No alumni records yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
