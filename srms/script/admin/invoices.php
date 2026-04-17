<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}
app_require_permission('finance.manage', 'admin');
app_require_unlocked('finance', 'admin');

$classes = [];
$terms = [];
$filterClass = (int)($_GET['class_id'] ?? 0);
$filterTerm = (int)($_GET['term_id'] ?? 0);
$invoices = [];
$error = '';
$hasReceipts = false;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines') || !app_table_exists($conn, 'tbl_payments')) {
		throw new RuntimeException("Fees module is not installed. Run migration 003_fees_finance.sql.");
	}
	$hasReceipts = app_table_exists($conn, 'tbl_receipts');

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($filterClass > 0 && $filterTerm > 0) {
		if ($hasReceipts) {
			$stmt = $conn->prepare("SELECT i.id, i.student_id, concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
				COALESCE(SUM(l.amount),0) AS total,
				COALESCE(paid.total_paid, 0) AS paid,
				lr.latest_receipt_id,
				lr.receipt_number AS latest_receipt_no
				FROM tbl_invoices i
				JOIN tbl_students s ON s.id = i.student_id
				LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = i.id
				LEFT JOIN (
					SELECT pr.invoice_id, MAX(r.id) AS latest_receipt_id
					FROM tbl_receipts r
					JOIN tbl_payments pr ON pr.id = r.payment_id
					GROUP BY pr.invoice_id
				) lr_map ON lr_map.invoice_id = i.id
				LEFT JOIN tbl_receipts lr ON lr.id = lr_map.latest_receipt_id
				WHERE i.class_id = ? AND i.term_id = ? AND i.status != 'void'
				GROUP BY i.id, i.student_id, student_name, paid.total_paid, lr.latest_receipt_id, lr.receipt_number
				ORDER BY i.student_id");
		} else {
			$stmt = $conn->prepare("SELECT i.id, i.student_id, concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
				COALESCE(SUM(l.amount),0) AS total,
				COALESCE(paid.total_paid, 0) AS paid
				FROM tbl_invoices i
				JOIN tbl_students s ON s.id = i.student_id
				LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = i.id
				WHERE i.class_id = ? AND i.term_id = ? AND i.status != 'void'
				GROUP BY i.id, i.student_id, student_name, paid.total_paid
				ORDER BY i.student_id");
		}
		$stmt->execute([$filterClass, $filterTerm]);
		$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<title><?php echo APP_NAME; ?> - Invoices</title>
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
<h1>Invoices</h1>
<p>Generate and record payments.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Class & Term</h3>
  <form class="row g-3" method="GET" action="admin/invoices">
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
<div class="tile mb-3">
  <h3 class="tile-title">Generate Invoices</h3>
  <p class="text-muted mb-2">Cash workflow enabled: payments recorded by accounts office with official receipt numbers.</p>
  <form class="row g-3" method="POST" action="admin/core/generate_invoices">
	<input type="hidden" name="class_id" value="<?php echo $filterClass; ?>">
	<input type="hidden" name="term_id" value="<?php echo $filterTerm; ?>">
	<div class="col-md-4">
	  <label class="form-label">Due Date (optional)</label>
	  <input class="form-control" type="date" name="due_date">
	</div>
	<div class="col-md-8 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-gear me-1"></i>Generate / Update Invoices</button>
	</div>
	<p class="text-muted mb-0">This creates invoices for all active students in the class using the saved fee structure for the term.</p>
  </form>
</div>

<div class="tile">
  <h3 class="tile-title">Invoices</h3>
  <div class="table-responsive">
	<form id="bulkInvoicesForm" method="POST" action="admin/core/bulk_delete_invoices" onsubmit="return confirmBulkDeleteInvoices();">
	<input type="hidden" name="class_id" value="<?php echo $filterClass; ?>">
	<input type="hidden" name="term_id" value="<?php echo $filterTerm; ?>">
	<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
	  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
	  <div class="form-check ms-2">
		<input class="form-check-input" type="checkbox" id="selectAllInvoices">
		<label class="form-check-label" for="selectAllInvoices">Select all</label>
	  </div>
	</div>
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th width="40"><input class="form-check-input" type="checkbox" id="selectAllInvoicesHead"></th>
		  <th>Student</th>
		  <th>Total</th>
		  <th>Paid</th>
		  <th>Balance</th>
		  <th style="width:420px;">Payments</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($invoices) < 1) { ?>
		<tr><td colspan="5" class="text-muted">No invoices found. Generate invoices first.</td></tr>
	  <?php } else { foreach ($invoices as $inv) {
		$total = (float)$inv['total'];
		$paid = (float)$inv['paid'];
		$bal = max(0, $total - $paid);
	  ?>
		<tr>
		  <td><input class="form-check-input invoice-checkbox" type="checkbox" name="invoice_ids[]" value="<?php echo (int)$inv['id']; ?>"></td>
		  <td><?php echo htmlspecialchars((string)$inv['student_id'].' — '.$inv['student_name']); ?></td>
		  <td><?php echo number_format($total, 2); ?></td>
		  <td><?php echo number_format($paid, 2); ?></td>
		  <td><b><?php echo number_format($bal, 2); ?></b></td>
		  <td>
			<?php
			$latestReceiptId = 0;
			$latestReceiptNo = '';
			if ($hasReceipts) {
				$stmtR = $conn->prepare("SELECT r.id, r.receipt_number FROM tbl_receipts r JOIN tbl_payments p ON p.id = r.payment_id WHERE p.invoice_id = ? ORDER BY r.id DESC LIMIT 1");
				$stmtR->execute([(int)$inv['id']]);
				$rRow = $stmtR->fetch(PDO::FETCH_ASSOC) ?: [];
				$latestReceiptId = (int)($rRow['id'] ?? 0);
				$latestReceiptNo = (string)($rRow['receipt_number'] ?? '');
			}
			?>
			<div class="row g-2" style="min-width:380px;">
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
				<button class="btn btn-sm btn-outline-primary" type="submit">Record Cash Payment</button>
			  </div>
			</form>
			<?php if ($latestReceiptId > 0): ?>
			  <div class="col-12"><a class="btn btn-sm btn-secondary" target="_blank" href="receipt?id=<?php echo $latestReceiptId; ?>&download=1">Latest Receipt: <?php echo htmlspecialchars($latestReceiptNo); ?></a></div>
			<?php endif; ?>
			</div>
		  </td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
	</form>
  </div>
</div>
<?php } ?>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
function confirmBulkDeleteInvoices(){
  var checked = document.querySelectorAll('.invoice-checkbox:checked');
  if (!checked.length) {
    alert('Please select at least one invoice to delete.');
    return false;
  }
  return confirm('Delete selected invoices? This action cannot be undone.');
}
function bindSelectAll(sourceId, targetClass) {
  var source = document.getElementById(sourceId);
  if (!source) return;
  source.addEventListener('change', function(){
    document.querySelectorAll(targetClass).forEach(function(cb){
      cb.checked = source.checked;
    });
  });
}
bindSelectAll('selectAllInvoices', '.invoice-checkbox');
bindSelectAll('selectAllInvoicesHead', '.invoice-checkbox');
</script>
</body>
</html>
