<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}

// Same UI as admin, but accountant access.
$classes = [];
$terms = [];
$items = [];
$structures = [];
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterTerm = (int)($_GET['term_id'] ?? 0);
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_fee_structure_tables($conn);

	if (!app_table_exists($conn, 'tbl_fee_items') || !app_table_exists($conn, 'tbl_fee_structures')) {
		throw new RuntimeException("Fees module is not installed. Run migration 003_fees_finance.sql.");
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name, status FROM tbl_fee_items ORDER BY name");
	$stmt->execute();
	$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($filterClass > 0 && $filterTerm > 0) {
		$stmt = $conn->prepare("SELECT fs.item_id, fs.amount, fi.name
			FROM tbl_fee_structures fs
			JOIN tbl_fee_items fi ON fi.id = fs.item_id
			WHERE fs.class_id = ? AND fs.term_id = ?
			ORDER BY fi.name");
		$stmt->execute([$filterClass, $filterTerm]);
		$structures = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<title><?php echo APP_NAME; ?> - Fee Structure</title>
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
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('accountant/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Fee Structure</h1>
<p>Set amounts per class and term.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Fee Items</h3>
  <form class="row g-3" method="POST" autocomplete="off" action="admin/core/new_fee_item">
	<div class="col-md-4">
	  <label class="form-label">Name</label>
	  <input class="form-control" name="name" placeholder="Tuition" required>
	</div>
	<div class="col-md-6">
	  <label class="form-label">Description (optional)</label>
	  <input class="form-control" name="description" placeholder="Term tuition fees">
	</div>
	<div class="col-md-2 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Add</button>
	</div>
  </form>

  <div class="table-responsive mt-3">
	<table class="table table-hover table-striped">
	  <thead><tr><th>Name</th><th>Status</th></tr></thead>
	  <tbody>
	  <?php foreach ($items as $it) { ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$it['name']); ?></td>
		  <td><?php echo ((int)$it['status'] === 1) ? '<span class="badge bg-success">ACTIVE</span>' : '<span class="badge bg-danger">DISABLED</span>'; ?></td>
		</tr>
	  <?php } ?>
	  </tbody>
	</table>
  </div>
</div>

<div class="tile mb-3">
  <h3 class="tile-title">Select Class & Term</h3>
  <form class="row g-3" method="GET" action="accountant/fee_structure">
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
</div>

<?php if ($filterClass > 0 && $filterTerm > 0) { ?>
<div class="tile">
  <h3 class="tile-title">Set Amounts</h3>
  <form method="POST" action="admin/core/save_fee_structure">
	<input type="hidden" name="class_id" value="<?php echo $filterClass; ?>">
	<input type="hidden" name="term_id" value="<?php echo $filterTerm; ?>">
	<div class="table-responsive">
	  <table class="table table-hover table-striped">
		<thead><tr><th>Item</th><th style="width:220px;">Amount</th></tr></thead>
		<tbody>
		<?php
		  $current = [];
		  foreach ($structures as $s) { $current[(int)$s['item_id']] = (float)$s['amount']; }
		  foreach ($items as $it) {
			if ((int)$it['status'] !== 1) continue;
			$iid = (int)$it['id'];
			$val = $current[$iid] ?? 0;
		?>
		  <tr>
			<td><?php echo htmlspecialchars((string)$it['name']); ?></td>
			<td><input class="form-control" name="amount[<?php echo $iid; ?>]" type="number" step="0.01" min="0" value="<?php echo htmlspecialchars((string)$val); ?>"></td>
		  </tr>
		<?php } ?>
		</tbody>
	  </table>
	</div>
	<div class="d-grid">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Fee Structure</button>
	</div>
  </form>
</div>
<?php } ?>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
