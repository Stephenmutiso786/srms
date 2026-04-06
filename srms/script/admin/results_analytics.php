<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$classes = [];
$terms = [];
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);

$rankings = [];
$subjects = [];
$stats = ['students' => 0, 'avg' => 0, 'best' => 0, 'worst' => 0];
$locked = false;
$error = '';

function compute_positions(array $rows): array {
	// Sort by avg_score desc, then total_score desc, then student_id asc
	usort($rows, function($a, $b) {
		$ad = (float)$a['avg_score']; $bd = (float)$b['avg_score'];
		if ($ad === $bd) {
			$at = (float)$a['total_score']; $bt = (float)$b['total_score'];
			if ($at === $bt) return strcmp((string)$a['student_id'], (string)$b['student_id']);
			return ($at < $bt) ? 1 : -1;
		}
		return ($ad < $bd) ? 1 : -1;
	});

	$pos = 0;
	$lastAvg = null;
	$lastPos = 0;
	foreach ($rows as $i => $r) {
		$pos = $i + 1;
		$avg = (float)$r['avg_score'];
		if ($lastAvg !== null && abs($avg - $lastAvg) < 0.00001) {
			$rows[$i]['position'] = $lastPos;
		} else {
			$rows[$i]['position'] = $pos;
			$lastPos = $pos;
			$lastAvg = $avg;
		}
	}
	return $rows;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($classId > 0 && $termId > 0) {
		$locked = app_results_locked($conn, $classId, $termId);

		// Student ranking (aggregate)
		$stmt = $conn->prepare("SELECT r.student AS student_id,
			concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
			COUNT(*) AS subjects,
			COALESCE(AVG(r.score),0) AS avg_score,
			COALESCE(SUM(r.score),0) AS total_score
			FROM tbl_exam_results r
			JOIN tbl_students s ON s.id = r.student
			WHERE r.class = ? AND r.term = ?
			GROUP BY r.student, student_name
			ORDER BY student_id");
		$stmt->execute([$classId, $termId]);
		$rankings = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$rankings = compute_positions($rankings);

		$stats['students'] = count($rankings);
		if ($stats['students'] > 0) {
			$avgSum = 0;
			$stats['best'] = (float)$rankings[0]['avg_score'];
			$stats['worst'] = (float)$rankings[$stats['students'] - 1]['avg_score'];
			foreach ($rankings as $r) { $avgSum += (float)$r['avg_score']; }
			$stats['avg'] = $avgSum / max(1, $stats['students']);
		}

		// Subject performance (avg)
		$stmt = $conn->prepare("SELECT sb.id AS subject_id, sb.name AS subject_name,
			COALESCE(AVG(r.score),0) AS avg_score
			FROM tbl_exam_results r
			JOIN tbl_subject_combinations sc ON sc.id = r.subject_combination
			JOIN tbl_subjects sb ON sb.id = sc.subject
			WHERE r.class = ? AND r.term = ?
			GROUP BY sb.id, sb.name
			ORDER BY avg_score DESC, sb.name");
		$stmt->execute([$classId, $termId]);
		$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Results Analytics</title>
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
<li><a class="app-menu__item active" href="admin/results_analytics"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Results Analytics</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Results Analytics</h1>
<p>Rankings and performance by class and term.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Class & Term</h3>
  <form class="row g-3" method="GET" action="admin/results_analytics">
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
	  <button class="btn btn-primary" type="submit">View</button>
	</div>
  </form>
</div>

<?php if ($classId > 0 && $termId > 0) { ?>

<?php if ($locked) { ?>
  <div class="alert alert-warning"><i class="bi bi-lock me-1"></i>Results are currently <b>LOCKED</b> for this class/term.</div>
<?php } ?>

<div class="row">
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-users fs-1"></i>
	  <div class="info">
		<h4>Students</h4>
		<p><b><?php echo number_format((int)$stats['students']); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-activity fs-1"></i>
	  <div class="info">
		<h4>Class Avg</h4>
		<p><b><?php echo number_format((float)$stats['avg'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-trending-up fs-1"></i>
	  <div class="info">
		<h4>Best Avg</h4>
		<p><b><?php echo number_format((float)$stats['best'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-trending-down fs-1"></i>
	  <div class="info">
		<h4>Worst Avg</h4>
		<p><b><?php echo number_format((float)$stats['worst'], 2); ?></b></p>
	  </div>
	</div>
  </div>
</div>

<div class="row mt-3">
  <div class="col-lg-5 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Top 10 Students</h3>
	  <div id="chartTopStudents" style="height:360px;"></div>
	</div>
  </div>
  <div class="col-lg-7 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Subject Performance (Avg)</h3>
	  <div id="chartSubjects" style="height:360px;"></div>
	</div>
  </div>
</div>

<div class="tile">
  <h3 class="tile-title">Ranking Table</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Pos</th>
		  <th>Student</th>
		  <th>Subjects</th>
		  <th>Avg</th>
		  <th>Total</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($rankings) < 1) { ?>
		<tr><td colspan="5" class="text-muted">No results found for this class/term.</td></tr>
	  <?php } else { foreach ($rankings as $r) { ?>
		<tr>
		  <td><b><?php echo (int)$r['position']; ?></b></td>
		  <td><?php echo htmlspecialchars((string)$r['student_id'].' — '.$r['student_name']); ?></td>
		  <td><?php echo (int)$r['subjects']; ?></td>
		  <td><?php echo number_format((float)$r['avg_score'], 2); ?></td>
		  <td><?php echo number_format((float)$r['total_score'], 2); ?></td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
  </div>
</div>

<script>
(function(){
  function init(id){ var el=document.getElementById(id); if(!el||!window.echarts) return null; var c=echarts.init(el); window.addEventListener('resize', function(){c.resize();}); return c; }
  var top = init('chartTopStudents');
  var sub = init('chartSubjects');

  var topRows = <?php echo json_encode(array_slice($rankings, 0, 10)); ?>;
  if (top && topRows && topRows.length) {
	var labels = topRows.map(function(r){ return (r.student_id + ''); });
	var values = topRows.map(function(r){ return Number(r.avg_score || 0); });
	top.setOption({
	  tooltip: { trigger: 'axis' },
	  grid: { left: 40, right: 20, top: 10, bottom: 60 },
	  xAxis: { type: 'category', data: labels, axisLabel: { rotate: 30 } },
	  yAxis: { type: 'value', min: 0, max: 100 },
	  series: [{ type: 'bar', data: values, itemStyle: { color: '#0d6efd' } }]
	});
  }

  var subRows = <?php echo json_encode($subjects); ?>;
  if (sub && subRows && subRows.length) {
	var sLabels = subRows.map(function(r){ return r.subject_name; });
	var sValues = subRows.map(function(r){ return Number(r.avg_score || 0); });
	sub.setOption({
	  tooltip: { trigger: 'axis' },
	  grid: { left: 50, right: 20, top: 10, bottom: 90 },
	  xAxis: { type: 'category', data: sLabels, axisLabel: { rotate: 40 } },
	  yAxis: { type: 'value', min: 0, max: 100 },
	  series: [{ type: 'line', smooth: true, data: sValues, itemStyle: { color: '#198754' } }]
	});
  }
})();
</script>

<?php } ?>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

