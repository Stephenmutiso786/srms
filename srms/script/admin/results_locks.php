<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}
app_require_permission('results.lock', 'admin');
app_require_unlocked('reports', 'admin');

$classes = [];
$terms = [];
$locks = [];
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterTerm = (int)($_GET['term_id'] ?? 0);
$currentLock = null;
$canUnlock = false;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_results_locks')) {
		throw new RuntimeException("Results lock module is not installed. Run migration 004_results_locking.sql.");
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$canUnlock = app_has_permission($conn, (string)$account_id, (string)$level, 'results.unlock');

	$stmt = $conn->prepare("SELECT rl.class_id, c.name AS class_name, rl.term_id, t.name AS term_name, rl.locked, rl.reason, rl.locked_at
		FROM tbl_results_locks rl
		LEFT JOIN tbl_classes c ON c.id = rl.class_id
		LEFT JOIN tbl_terms t ON t.id = rl.term_id
		WHERE rl.locked = 1
		ORDER BY rl.locked_at DESC");
	$stmt->execute();
	$locks = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($filterClass > 0 && $filterTerm > 0) {
		$stmt = $conn->prepare("SELECT locked, reason, locked_at FROM tbl_results_locks WHERE class_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([$filterClass, $filterTerm]);
		$currentLock = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['locked' => 0, 'reason' => '', 'locked_at' => null];
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Results Locks</title>
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
<h1>Results Locks</h1>
<p>Lock class results per term to prevent editing after approval.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Lock / Unlock</h3>
  <form class="row g-3" method="GET" action="admin/results_locks">
	<div class="col-md-5">
	  <label class="form-label">Class</label>
	  <select class="form-control" name="class_id" required>
		<option value="" disabled <?php echo $filterClass ? '' : 'selected'; ?>>Select class</option>
		<?php foreach ($classes as $c) { ?>
		  <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)$c['id'] === $filterClass) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$c['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-5">
	  <label class="form-label">Term</label>
	  <select class="form-control" name="term_id" required>
		<option value="" disabled <?php echo $filterTerm ? '' : 'selected'; ?>>Select term</option>
		<?php foreach ($terms as $t) { ?>
		  <option value="<?php echo (int)$t['id']; ?>" <?php echo ((int)$t['id'] === $filterTerm) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$t['name']); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-2 d-grid align-items-end">
	  <button class="btn btn-outline-primary" type="submit">Load</button>
	</div>
  </form>

  <?php if ($filterClass > 0 && $filterTerm > 0 && $currentLock) { ?>
	<hr>
	<div class="d-flex justify-content-between align-items-center">
	  <div>
		<b>Status:</b>
		<?php echo ((int)$currentLock['locked'] === 1) ? '<span class="badge bg-danger">LOCKED</span>' : '<span class="badge bg-success">UNLOCKED</span>'; ?>
		<?php if (!empty($currentLock['locked_at'])) { ?>
		  <span class="text-muted ms-2"><?php echo htmlspecialchars((string)$currentLock['locked_at']); ?></span>
		<?php } ?>
	  </div>
	</div>
	<form class="row g-3 mt-2" method="POST" action="admin/core/set_results_lock">
	  <input type="hidden" name="class_id" value="<?php echo $filterClass; ?>">
	  <input type="hidden" name="term_id" value="<?php echo $filterTerm; ?>">
	  <div class="col-md-8">
		<label class="form-label">Reason (optional)</label>
		<input class="form-control" name="reason" value="<?php echo htmlspecialchars((string)($currentLock['reason'] ?? '')); ?>" placeholder="Approved results">
	  </div>
	  <div class="col-md-4 d-grid align-items-end">
		<?php if ((int)$currentLock['locked'] === 1) { ?>
		  <?php if ($canUnlock) { ?>
			<button class="btn btn-success" type="submit" name="locked" value="0"><i class="bi bi-unlock me-1"></i>Unlock</button>
		  <?php } else { ?>
			<button class="btn btn-outline-secondary" type="button" disabled><i class="bi bi-lock me-1"></i>Unlock (Super Admin)</button>
		  <?php } ?>
		<?php } else { ?>
		  <button class="btn btn-danger" type="submit" name="locked" value="1"><i class="bi bi-lock me-1"></i>Lock</button>
		<?php } ?>
	  </div>
	</form>
  <?php } ?>
</div>

<div class="tile">
  <h3 class="tile-title">Currently Locked</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Class</th>
		  <th>Term</th>
		  <th>Reason</th>
		  <th>Locked At</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($locks) < 1) { ?>
		<tr><td colspan="4" class="text-muted">No locked results.</td></tr>
	  <?php } else { foreach ($locks as $l) { ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$l['class_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)$l['term_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)($l['reason'] ?? '')); ?></td>
		  <td><?php echo htmlspecialchars((string)$l['locked_at']); ?></td>
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
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
