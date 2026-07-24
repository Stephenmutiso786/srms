<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [0, 1, 9], true)) { header('location:../'); exit; }

$records = [];
$summary = ['records' => 0, 'alumni' => 0, 'reports' => 0, 'certificates' => 0, 'students' => 0, 'teachers' => 0, 'parents' => 0, 'payload_bytes' => 0, 'payload_original_bytes' => 0];
$typeFilter = trim((string)($_GET['type'] ?? ''));
$dataCampOpenUrlBase = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/srms/script/admin/data_camp.php'))), '/') . '/data_camp_open.php';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_data_camp_schema($conn);
	app_ensure_student_alumni_schema($conn);

	$where = '';
	$params = [];
	if ($typeFilter !== '') {
		$where = 'WHERE record_type = ?';
		$params[] = $typeFilter;
	}

	$stmt = $conn->prepare("SELECT dc.*, COALESCE(c.name, '') AS class_name,
			CONCAT(COALESCE(st.fname, ''), ' ', COALESCE(st.mname, ''), ' ', COALESCE(st.lname, '')) AS student_name
		FROM tbl_data_camp_records dc
		LEFT JOIN tbl_classes c ON c.id = dc.class_id
		LEFT JOIN tbl_students st ON st.id = dc.student_id
		{$where}
		ORDER BY dc.created_at DESC, dc.id DESC
		LIMIT 300");
	$stmt->execute($params);
	$records = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

	$summary['records'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_data_camp_records")->fetchColumn();
	$summary['alumni'] = app_table_exists($conn, 'tbl_students') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_students WHERE COALESCE(is_alumni, 0) = 1")->fetchColumn() : 0;
	$summary['reports'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_data_camp_records WHERE record_type = 'report_card'")->fetchColumn();
	$summary['certificates'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_data_camp_records WHERE record_type = 'certificate'")->fetchColumn();
	$summary['students'] = app_table_exists($conn, 'tbl_students') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_students")->fetchColumn() : 0;
	$summary['teachers'] = app_table_exists($conn, 'tbl_staff') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn() : 0;
	$summary['parents'] = app_table_exists($conn, 'tbl_parents') ? (int)$conn->query("SELECT COUNT(*) FROM tbl_parents")->fetchColumn() : 0;
	$stmt = $conn->query("SELECT COALESCE(SUM(payload_bytes), 0) AS payload_bytes, COALESCE(SUM(payload_original_bytes), 0) AS payload_original_bytes FROM tbl_data_camp_records");
	$sizeRow = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
	$summary['payload_bytes'] = (int)($sizeRow['payload_bytes'] ?? 0);
	$summary['payload_original_bytes'] = (int)($sizeRow['payload_original_bytes'] ?? 0);
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to load Data Camp.'));
}

function app_data_camp_human_bytes(int $bytes): string
{
	$bytes = max(0, $bytes);
	$units = ['B', 'KB', 'MB', 'GB', 'TB'];
	$idx = 0;
	$value = (float)$bytes;
	while ($value >= 1024 && $idx < count($units) - 1) {
		$value /= 1024;
		$idx++;
	}
	return number_format($value, $idx === 0 ? 0 : 2) . ' ' . $units[$idx];
}

function app_data_camp_record_target_label(array $row): string
{
	$recordType = strtolower(trim((string)($row['record_type'] ?? '')));
	$filePath = trim((string)($row['file_path'] ?? ''));
	$sourceUrl = trim((string)($row['source_url'] ?? ''));
	if ($filePath !== '') {
		return 'Open File';
	}
	if ($recordType === 'report_card') {
		return 'Open Report';
	}
	if ($recordType === 'certificate') {
		return 'Open Certificate';
	}
	if ($sourceUrl !== '') {
		return 'Open Source';
	}
	return 'View Archive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Data Camp</title>
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
<div class="app-title"><div><h1>Data Camp</h1><p>Permanent archive for school history, generated records, and retained documents.</p></div></div>
<div class="row">
<div class="col-md-3"><div class="tile"><div class="tile-body"><h5>Archive Records</h5><h2><?php echo (int)$summary['records']; ?></h2></div></div></div>
<div class="col-md-3"><div class="tile"><div class="tile-body"><h5>Report Cards</h5><h2><?php echo (int)$summary['reports']; ?></h2></div></div></div>
<div class="col-md-3"><div class="tile"><div class="tile-body"><h5>Certificates</h5><h2><?php echo (int)$summary['certificates']; ?></h2></div></div></div>
<div class="col-md-3"><div class="tile"><div class="tile-body"><h5>Alumni</h5><h2><?php echo (int)$summary['alumni']; ?></h2></div></div></div>
</div>
<div class="row">
<div class="col-md-4"><div class="tile"><div class="tile-body"><h5>Total Students Stored</h5><h2><?php echo (int)$summary['students']; ?></h2></div></div></div>
<div class="col-md-4"><div class="tile"><div class="tile-body"><h5>Total Teachers Stored</h5><h2><?php echo (int)$summary['teachers']; ?></h2></div></div></div>
<div class="col-md-4"><div class="tile"><div class="tile-body"><h5>Total Parents Stored</h5><h2><?php echo (int)$summary['parents']; ?></h2></div></div></div>
</div>
<div class="tile mb-3">
<div class="tile-body d-flex flex-wrap justify-content-between gap-3">
<div><strong>Archive Payload Storage</strong><br><small class="text-muted">Compressed retained metadata used by Data Camp snapshots.</small></div>
<div><span class="badge bg-info text-dark">Stored: <?php echo htmlspecialchars(app_data_camp_human_bytes((int)$summary['payload_bytes'])); ?></span></div>
<div><span class="badge bg-secondary">Original: <?php echo htmlspecialchars(app_data_camp_human_bytes((int)$summary['payload_original_bytes'])); ?></span></div>
<div><span class="badge bg-success">Saved: <?php echo htmlspecialchars(app_data_camp_human_bytes(max(0, (int)$summary['payload_original_bytes'] - (int)$summary['payload_bytes']))); ?></span></div>
</div>
</div>
<div class="tile">
<div class="tile-body">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3">
<div><label class="form-label">Record Type</label><input class="form-control" type="text" name="type" value="<?php echo htmlspecialchars($typeFilter); ?>" placeholder="report_card, certificate, alumni_student"></div>
<div><button class="btn btn-primary">Filter</button></div>
<div><a class="btn btn-outline-secondary" href="admin/data_camp">Reset</a></div>
</form>
<div class="table-responsive">
<table class="table table-striped table-bordered">
<thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Student</th><th>Class</th><th>Storage</th><th>Status</th><th>Source</th></tr></thead>
<tbody>
<?php foreach ($records as $row): ?>
<tr>
<td><?php echo htmlspecialchars((string)($row['created_at'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)($row['record_type'] ?? '')); ?></td>
<td>
<div><?php echo htmlspecialchars((string)($row['title'] ?? '')); ?></div>
<small class="text-muted"><?php echo htmlspecialchars((string)($row['description'] ?? '')); ?></small>
</td>
<td><?php echo htmlspecialchars(trim((string)($row['student_name'] ?? ''))); ?></td>
<td><?php echo htmlspecialchars((string)($row['class_name'] ?? '')); ?></td>
<td>
<?php
  $storedBytes = (int)($row['payload_bytes'] ?? 0);
  $originalBytes = (int)($row['payload_original_bytes'] ?? 0);
  $encoding = trim((string)($row['payload_encoding'] ?? 'json'));
  if ($storedBytes > 0):
?>
<small><?php echo htmlspecialchars(app_data_camp_human_bytes($storedBytes)); ?></small>
<?php if ($originalBytes > $storedBytes): ?>
<br><small class="text-muted"><?php echo htmlspecialchars($encoding !== '' ? $encoding : 'json'); ?>, saved <?php echo htmlspecialchars(app_data_camp_human_bytes($originalBytes - $storedBytes)); ?></small>
<?php endif; ?>
<?php else: ?>
<span class="text-muted">Metadata only</span>
<?php endif; ?>
</td>
<td><?php echo htmlspecialchars((string)($row['status'] ?? '')); ?></td>
<td>
<a href="<?php echo htmlspecialchars($dataCampOpenUrlBase . '?id=' . (int)($row['id'] ?? 0)); ?>" target="_blank"><?php echo htmlspecialchars(app_data_camp_record_target_label($row)); ?></a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$records): ?>
<tr><td colspan="8" class="text-center text-muted">No archived records found for this filter.</td></tr>
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
