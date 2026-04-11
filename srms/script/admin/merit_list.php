<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('report.generate', 'admin');
app_require_unlocked('reports', 'admin');

$classes = [];
$terms = [];
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$rows = [];
$className = '';
$termName = '';
$locked = false;
$summary = ['students' => 0, 'avg' => 0, 'best' => 0, 'worst' => 0];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($classId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$className = (string)$stmt->fetchColumn();
	}
	if ($termId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();
	}

	if ($classId > 0 && $termId > 0) {
		$locked = app_results_locked($conn, $classId, $termId);
		$list = report_class_merit_list($conn, $classId, $termId, (int)$account_id);
		$rows = $list['rows'];
		$summary['students'] = (int)$list['total_students'];
		if (!empty($rows)) {
			$summary['best'] = (float)$rows[0]['mean'];
			$summary['worst'] = (float)$rows[count($rows) - 1]['mean'];
			$sum = 0;
			foreach ($rows as $row) {
				$sum += (float)$row['mean'];
			}
			$summary['avg'] = round($sum / max(1, count($rows)), 2);
		}
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Merit List</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.merit-hero{background:linear-gradient(135deg,#0d3b66,#0d64b0 55%,#1ca874);color:#fff;border-radius:24px;padding:22px;box-shadow:0 18px 50px rgba(13,59,102,.14)}
.merit-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}
.merit-stat{background:#fff;border-radius:18px;padding:16px;box-shadow:0 12px 32px rgba(9,30,66,.08)}
.merit-stat .label{font-size:.75rem;text-transform:uppercase;color:#6b7280}
.merit-stat .value{font-size:1.6rem;font-weight:800;color:#123}
.merit-card{background:#fff;border-radius:20px;box-shadow:0 12px 32px rgba(9,30,66,.08);overflow:hidden}
@media (max-width: 991px){.merit-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 576px){.merit-grid{grid-template-columns:1fr}}
@media print{.app-header,.app-sidebar,.app-title,.toolbar,.filter-card{display:none!important}.app-content{margin-left:0;padding:0}.merit-card,.merit-hero{box-shadow:none}}
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
<div class="app-title">
<div>
<h1>Merit List</h1>
<p class="mb-0 text-muted">Rank learners for a class and term, then export a printable merit list.</p>
</div>
</div>

<div class="merit-hero">
  <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
    <div>
      <div class="small opacity-75">Class Merit</div>
      <h3 class="mb-1"><?php echo htmlspecialchars($className !== '' ? $className : 'Select class'); ?></h3>
      <div class="small opacity-75"><?php echo htmlspecialchars($termName !== '' ? $termName : 'Select term'); ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-light" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print</button>
      <?php if ($classId > 0 && $termId > 0): ?>
      <a class="btn btn-outline-light" href="admin/merit_list_pdf?class_id=<?php echo $classId; ?>&term_id=<?php echo $termId; ?>" target="_blank"><i class="bi bi-download me-2"></i>PDF</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="merit-grid">
  <div class="merit-stat"><div class="label">Learners</div><div class="value"><?php echo (int)$summary['students']; ?></div></div>
  <div class="merit-stat"><div class="label">Average</div><div class="value"><?php echo number_format((float)$summary['avg'], 2); ?>%</div></div>
  <div class="merit-stat"><div class="label">Best</div><div class="value"><?php echo number_format((float)$summary['best'], 2); ?>%</div></div>
  <div class="merit-stat"><div class="label">Lowest</div><div class="value"><?php echo number_format((float)$summary['worst'], 2); ?>%</div></div>
</div>

<div class="tile filter-card mb-3">
<div class="tile-body">
<form class="d-flex flex-wrap gap-2 align-items-end" method="get">
<div>
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select class</option>
<?php foreach ($classes as $class): ?>
<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$class['id'] === $classId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$class['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select term</option>
<?php foreach ($terms as $term): ?>
<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$term['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<button class="btn btn-primary" type="submit">Generate Merit List</button>
</div>
</form>
</div>
</div>

<?php if ($classId > 0 && $termId > 0 && !$locked): ?>
<div class="alert alert-warning">Results are not locked yet. The merit list can be previewed, but the final class ranking should be locked before publishing.</div>
<?php endif; ?>

<div class="merit-card">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Rank</th>
          <th>School ID</th>
          <th>Student</th>
          <th>Total</th>
          <th>Mean</th>
          <th>Grade</th>
          <th>Trend</th>
          <th>Verification</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows) { ?>
        <tr><td colspan="8" class="text-muted">Select a class and term to generate the merit list.</td></tr>
      <?php } ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td><strong>#<?php echo (int)$row['position']; ?></strong></td>
          <td><?php echo htmlspecialchars((string)($row['school_id'] !== '' ? $row['school_id'] : $row['student_id'])); ?></td>
          <td><?php echo htmlspecialchars((string)$row['student_name']); ?></td>
          <td><?php echo number_format((float)$row['total'], 2); ?></td>
          <td><?php echo number_format((float)$row['mean'], 2); ?>%</td>
          <td><span class="badge bg-primary"><?php echo htmlspecialchars((string)$row['grade']); ?></span></td>
          <td><?php echo htmlspecialchars((string)$row['trend']); ?></td>
          <td class="text-muted small"><?php echo htmlspecialchars((string)$row['verification_code']); ?></td>
        </tr>
      <?php endforeach; ?>
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