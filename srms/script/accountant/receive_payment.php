<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {} else { header("location:../"); exit; }

$classes = [];
$terms = [];
$invoices = [];
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterTerm = (int)($_GET['term_id'] ?? 0);
$studentQuery = trim((string)($_GET['student_query'] ?? ''));
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	app_sync_student_finance_class_links($conn);

	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines') || !app_table_exists($conn, 'tbl_payments')) {
		throw new RuntimeException('Fees module is not installed. Run migration 003_fees_finance.sql.');
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($filterClass > 0 && $filterTerm > 0) {
		$sql = "SELECT i.id, i.student_id, concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
			COALESCE(SUM(l.amount),0) AS total,
			COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id),0) AS paid
			FROM tbl_invoices i
			JOIN tbl_students s ON s.id = i.student_id
			LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
			WHERE i.class_id = ? AND i.term_id = ? AND i.status != 'void'";
		$params = [$filterClass, $filterTerm];
		if ($studentQuery !== '') {
			$sql .= " AND (i.student_id LIKE ? OR s.fname LIKE ? OR s.mname LIKE ? OR s.lname LIKE ? OR concat_ws(' ', s.fname, s.mname, s.lname) LIKE ? )";
			$like = '%' . $studentQuery . '%';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$sql .= " GROUP BY i.id, i.student_id, student_name ORDER BY i.student_id";
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] ' . $e->getMessage());
	$error = 'An internal error occurred.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Receive Payment</title>
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
<h1>Receive Payment</h1>
<p>Select a class and term, then record a payment against an invoice. The amount is deducted automatically from the invoice balance.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Choose Class & Term</h3>
  <form class="row g-3" method="GET" action="accountant/receive_payment">
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
	<div class="col-md-10">
	  <label class="form-label">Student Search</label>
	  <input class="form-control" type="text" name="student_query" value="<?php echo htmlspecialchars($studentQuery, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by admission number or student name">
	</div>
	<div class="col-md-2 d-grid align-items-end">
	  <button class="btn btn-outline-primary" type="submit">Load</button>
	</div>
  </form>
</div>

<?php if ($filterClass > 0 && $filterTerm > 0) { ?>
<div class="tile">
  <h3 class="tile-title">Open Invoices</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Student</th>
		  <th>Total</th>
		  <th>Paid</th>
		  <th>Balance</th>
		  <th style="width:420px;">Record Payment</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($invoices) < 1) { ?>
		<tr><td colspan="5" class="text-muted">No invoices found. Generate invoices first.</td></tr>
	  <?php } else { foreach ($invoices as $inv) {
		$total = (float)$inv['total'];
		$paid = (float)$inv['paid'];
		$balance = max(0, $total - $paid);
		$studentSearch = strtolower(trim((string)$inv['student_id'] . ' ' . (string)$inv['student_name']));
	  ?>
		<tr data-student-search="<?php echo htmlspecialchars($studentSearch, ENT_QUOTES, 'UTF-8'); ?>">
		  <td><?php echo htmlspecialchars((string)$inv['student_id'].' — '.$inv['student_name']); ?></td>
		  <td><?php echo number_format($total, 2); ?></td>
		  <td><?php echo number_format($paid, 2); ?></td>
		  <td><b><?php echo number_format($balance, 2); ?></b></td>
		  <td>
			<form class="row g-2" method="POST" action="admin/core/add_payment" style="margin:0;">
			  <input type="hidden" name="invoice_id" value="<?php echo (int)$inv['id']; ?>">
			  <input type="hidden" name="class_id" value="<?php echo $filterClass; ?>">
			  <input type="hidden" name="term_id" value="<?php echo $filterTerm; ?>">
			  <div class="col-5">
				<input class="form-control" name="amount" type="number" min="0" step="0.01" placeholder="Cash amount" required>
			  </div>
			  <div class="col-7">
				<input class="form-control" name="reference" placeholder="Cashbook ref (optional)">
			  </div>
			  <div class="col-12 d-grid">
				<button class="btn btn-sm btn-primary" type="submit">Save Payment</button>
			  </div>
			</form>
		  </td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
	<datalist id="studentSuggestions">
	<?php foreach ($invoices as $inv): ?>
	  <option value="<?php echo htmlspecialchars((string)$inv['student_id'].' - '.$inv['student_name'], ENT_QUOTES, 'UTF-8'); ?>"></option>
	<?php endforeach; ?>
	</datalist>
  </div>
</div>
<?php } ?>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	var searchInput = document.querySelector('input[name="student_query"]');
	var rows = document.querySelectorAll('tr[data-student-search]');
	if (!searchInput || !rows.length) {
		return;
	}

	function filterRows() {
		var query = (searchInput.value || '').trim().toLowerCase();
		rows.forEach(function (row) {
			var haystack = (row.getAttribute('data-student-search') || '').toLowerCase();
			row.style.display = !query || haystack.indexOf(query) !== -1 ? '' : 'none';
		});
	}

	searchInput.setAttribute('list', 'studentSuggestions');
	searchInput.addEventListener('input', filterRows);
	filterRows();
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
