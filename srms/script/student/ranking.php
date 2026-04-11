<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../"); exit;}

$terms = [];
$termId = (int)($_GET['term_id'] ?? 0);
$rank = null;
$top = [];
$locked = false;
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

	if ($termId > 0) {
		$locked = app_results_locked($conn, (int)$class, $termId);

		$stmt = $conn->prepare("SELECT r.student AS student_id,
			concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
			COUNT(*) AS subjects,
			COALESCE(AVG(r.score),0) AS avg_score,
			COALESCE(SUM(r.score),0) AS total_score
			FROM tbl_exam_results r
			JOIN tbl_students s ON s.id = r.student
			WHERE r.class = ? AND r.term = ?
			GROUP BY r.student, student_name");
		$stmt->execute([(int)$class, $termId]);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
			$position = $pos;
			if ($lastAvg !== null && abs($avg - $lastAvg) < 0.00001) {
				$position = $lastPos;
			} else {
				$lastPos = $pos;
				$lastAvg = $avg;
			}
			$rows[$i]['position'] = $position;
			if ((string)$r['student_id'] === (string)$account_id) {
				$rank = $rows[$i];
			}
		}

		$top = array_slice($rows, 0, 10);
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - My Ranking</title>
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
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/settings"><i class="bi bi-person me-2 fs-5"></i> Change Password</a></li>
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
<p class="app-sidebar__user-designation">Student</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Results</span></a></li>
<li><a class="app-menu__item active" href="student/ranking"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">My Ranking</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>My Ranking</h1>
<p><?php echo htmlspecialchars($act_class ?? ''); ?></p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Term</h3>
  <form class="row g-3" method="GET" action="student/ranking">
	<div class="col-md-10">
	  <select class="form-control" name="term_id" required>
		<?php foreach ($terms as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>" <?php echo ((int)$t['id'] === $termId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$t['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-2 d-grid">
	  <button class="btn btn-primary" type="submit">View</button>
	</div>
  </form>
</div>

<?php if ($locked) { ?>
  <div class="alert alert-warning"><i class="bi bi-lock me-1"></i>Results are currently <b>LOCKED</b> for this term.</div>
<?php } ?>

<div class="tile mb-3">
  <h3 class="tile-title">My Position</h3>
  <?php if (!$rank) { ?>
	<div class="alert alert-info mb-0">No results found for this term yet.</div>
  <?php } else { ?>
	<div class="row">
	  <div class="col-md-3"><b>Position:</b> <?php echo (int)$rank['position']; ?></div>
	  <div class="col-md-3"><b>Avg:</b> <?php echo number_format((float)$rank['avg_score'], 2); ?></div>
	  <div class="col-md-3"><b>Total:</b> <?php echo number_format((float)$rank['total_score'], 2); ?></div>
	  <div class="col-md-3"><b>Subjects:</b> <?php echo (int)$rank['subjects']; ?></div>
	</div>
  <?php } ?>
</div>

<div class="tile">
  <h3 class="tile-title">Top 10</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Pos</th>
		  <th>Student</th>
		  <th>Avg</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($top) < 1) { ?>
		<tr><td colspan="3" class="text-muted">No results yet.</td></tr>
	  <?php } else { foreach ($top as $r) { ?>
		<tr>
		  <td><b><?php echo (int)$r['position']; ?></b></td>
		  <td><?php echo htmlspecialchars((string)$r['student_id'].' — '.$r['student_name']); ?></td>
		  <td><?php echo number_format((float)$r['avg_score'], 2); ?></td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
  </div>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>

