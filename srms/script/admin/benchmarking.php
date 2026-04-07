<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}
app_require_permission('results.approve', 'admin');

$terms = [];
$termId = (int)($_GET['term_id'] ?? 0);
$classBench = [];
$subjectBench = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($termId < 1 && count($terms) > 0) {
		$termId = (int)$terms[0]['id'];
	}

	if ($termId > 0 && app_table_exists($conn, 'tbl_exam_results')) {
		$stmt = $conn->prepare("SELECT c.id, c.name,
			COALESCE(AVG(r.score),0) AS avg_score,
			COUNT(DISTINCT r.student) AS students
			FROM tbl_classes c
			LEFT JOIN tbl_exam_results r ON r.class = c.id AND r.term = ?
			GROUP BY c.id, c.name
			ORDER BY avg_score DESC");
		$stmt->execute([$termId]);
		$classBench = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $conn->prepare("SELECT sb.id AS subject_id, sb.name AS subject_name,
			COALESCE(AVG(r.score),0) AS avg_score
			FROM tbl_exam_results r
			JOIN tbl_subject_combinations sc ON sc.id = r.subject_combination
			JOIN tbl_subjects sb ON sb.id = sc.subject
			WHERE r.term = ?
			GROUP BY sb.id, sb.name
			ORDER BY avg_score DESC, sb.name");
		$stmt->execute([$termId]);
		$subjectBench = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Benchmarking</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
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
<li><a class="app-menu__item" href="admin/analytics_engine"><i class="app-menu__icon feather icon-activity"></i><span class="app-menu__label">Analytics Engine</span></a></li>
<li><a class="app-menu__item" href="admin/results_analytics"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Results Analytics</span></a></li>
<li><a class="app-menu__item active" href="admin/benchmarking"><i class="app-menu__icon feather icon-trending-up"></i><span class="app-menu__label">Benchmarking</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>School Benchmarking</h1>
<p>Compare class and subject performance for the selected term.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <form class="row g-3" method="GET" action="admin/benchmarking">
	<div class="col-md-6">
	  <label class="form-label">Term</label>
	  <select class="form-control" name="term_id" required>
		<?php foreach ($terms as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>" <?php echo ((int)$t['id'] === $termId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$t['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-2 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit">View</button>
	</div>
  </form>
</div>

<div class="row">
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Class Rankings</h3>
	  <div id="chartClassBench" style="height:360px;"></div>
	</div>
  </div>
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Subject Rankings</h3>
	  <div id="chartSubjectBench" style="height:360px;"></div>
	</div>
  </div>
</div>

<div class="row">
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Class Performance Table</h3>
	  <div class="table-responsive">
		<table class="table table-hover">
		  <thead><tr><th>Class</th><th>Avg Score</th><th>Students</th></tr></thead>
		  <tbody>
		  <?php foreach ($classBench as $row) { ?>
			<tr>
			  <td><?php echo htmlspecialchars((string)$row['name']); ?></td>
			  <td><?php echo number_format((float)$row['avg_score'], 2); ?></td>
			  <td><?php echo number_format((int)$row['students']); ?></td>
			</tr>
		  <?php } ?>
		  </tbody>
		</table>
	  </div>
	</div>
  </div>
  <div class="col-lg-6 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Subject Performance Table</h3>
	  <div class="table-responsive">
		<table class="table table-hover">
		  <thead><tr><th>Subject</th><th>Avg Score</th></tr></thead>
		  <tbody>
		  <?php foreach ($subjectBench as $row) { ?>
			<tr>
			  <td><?php echo htmlspecialchars((string)$row['subject_name']); ?></td>
			  <td><?php echo number_format((float)$row['avg_score'], 2); ?></td>
			</tr>
		  <?php } ?>
		  </tbody>
		</table>
	  </div>
	</div>
  </div>
</div>

<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const classData = <?php echo json_encode($classBench); ?>;
const subjectData = <?php echo json_encode($subjectBench); ?>;

function initChart(id) {
  const el = document.getElementById(id);
  if (!el || !window.echarts) return null;
  const chart = echarts.init(el);
  window.addEventListener('resize', () => chart.resize());
  return chart;
}

const classChart = initChart('chartClassBench');
if (classChart) {
  classChart.setOption({
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: classData.map(r => r.name), axisLabel: { rotate: 20 } },
    yAxis: { type: 'value', min: 0, max: 100 },
    series: [{ type: 'bar', data: classData.map(r => Number(r.avg_score || 0)), itemStyle: { color: '#1f8b8b' } }]
  });
}

const subjectChart = initChart('chartSubjectBench');
if (subjectChart) {
  subjectChart.setOption({
    tooltip: { trigger: 'axis' },
    xAxis: { type: 'category', data: subjectData.map(r => r.subject_name), axisLabel: { rotate: 30 } },
    yAxis: { type: 'value', min: 0, max: 100 },
    series: [{ type: 'bar', data: subjectData.map(r => Number(r.avg_score || 0)), itemStyle: { color: '#0d6efd' } }]
  });
}
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
