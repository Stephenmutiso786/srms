<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}

// Reuse the same logic as admin fees, but for accountant role.
$counts = ['invoiced' => 0, 'paid' => 0, 'balance' => 0, 'open_invoices' => 0];
$topDefaulters = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);

	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines') || !app_table_exists($conn, 'tbl_payments')) {
		throw new RuntimeException("Fees module is not installed. Run migration 003_fees_finance.sql.");
	}

	$stmt = $conn->prepare("SELECT
		COALESCE(SUM(l.amount), 0) AS invoiced,
		COALESCE((SELECT SUM(p.amount) FROM tbl_payments p), 0) AS paid
		FROM tbl_invoice_lines l");
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['invoiced' => 0, 'paid' => 0];
	$counts['invoiced'] = (float)$row['invoiced'];
	$counts['paid'] = (float)$row['paid'];
	$counts['balance'] = max(0, $counts['invoiced'] - $counts['paid']);

	$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'open'");
	$stmt->execute();
	$counts['open_invoices'] = (int)$stmt->fetchColumn();

	$stmt = $conn->prepare("SELECT i.student_id,
		concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
		c.name AS class_name,
		COALESCE(SUM(l.amount),0) - COALESCE(SUM(p.amount),0) AS balance
		FROM tbl_invoices i
		JOIN tbl_students s ON s.id = i.student_id
		LEFT JOIN tbl_classes c ON c.id = i.class_id
		LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
		LEFT JOIN tbl_payments p ON p.invoice_id = i.id
		WHERE i.status = 'open'
		GROUP BY i.student_id, student_name, class_name
		HAVING (COALESCE(SUM(l.amount),0) - COALESCE(SUM(p.amount),0)) > 0
		ORDER BY balance DESC
		LIMIT 8");
	$stmt->execute();
	$topDefaulters = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Fees & Finance</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">ELIMU HUB</a>
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
<h1>Fees & Finance</h1>
<p>Overview of invoiced, paid, and outstanding balances.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row">
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-file-text fs-1"></i>
	  <div class="info">
		<h4>Invoiced</h4>
		<p><b><?php echo number_format($counts['invoiced'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-check-circle fs-1"></i>
	  <div class="info">
		<h4>Paid</h4>
		<p><b><?php echo number_format($counts['paid'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-alert-circle fs-1"></i>
	  <div class="info">
		<h4>Outstanding</h4>
		<p><b><?php echo number_format($counts['balance'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-folder fs-1"></i>
	  <div class="info">
		<h4>Open Invoices</h4>
		<p><b><?php echo number_format($counts['open_invoices']); ?></b></p>
	  </div>
	</div>
  </div>
</div>

<div class="tile mt-3 mb-3">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
	<div>
	  <h3 class="tile-title mb-1">Fee Recording</h3>
	  <div class="text-muted">Record student fee payments and deduct balances from invoices.</div>
	</div>
	<div class="d-flex gap-2 flex-wrap">
	  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickPaymentModal"><i class="bi bi-plus-circle me-1"></i>Record Fee Payment</button>
	  <a class="btn btn-outline-primary" href="accountant/invoices"><i class="bi bi-file-text me-1"></i>View Invoices</a>
	</div>
  </div>
</div>

<div class="tile mt-3">
  <div class="d-flex justify-content-between align-items-center">
	<h3 class="tile-title mb-0">Top Defaulters</h3>
	<div class="d-flex gap-2">
	  <a class="btn btn-outline-primary btn-sm" href="accountant/fee_structure">Set Fee Structure</a>
	  <a class="btn btn-primary btn-sm" href="accountant/invoices">Generate Invoices</a>
	</div>
  </div>
  <div class="table-responsive mt-3">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Student</th>
		  <th>Class</th>
		  <th>Balance</th>
		  <th style="width:180px;" class="text-center">Action</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($topDefaulters) < 1) { ?>
		<tr><td colspan="4" class="text-muted">No outstanding balances found.</td></tr>
	  <?php } else { foreach ($topDefaulters as $d) { ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$d['student_id'].' — '.$d['student_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)($d['class_name'] ?? '')); ?></td>
		  <td><b><?php echo number_format((float)$d['balance'], 2); ?></b></td>
		  <td class="text-center">
			<button class="btn btn-sm btn-success" onclick="recordPaymentFor(<?php echo (int)$d['student_id']; ?>, '<?php echo htmlspecialchars((string)$d['student_name']); ?>')">
			  <i class="bi bi-cash-coin me-1"></i>Record
			</button>
		  </td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
  </div>
</div>

<?php } ?>

</main>

<!-- Quick Payment Modal -->
<div class="modal fade" id="quickPaymentModal" tabindex="-1" aria-labelledby="quickPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
	<div class="modal-content">
	  <div class="modal-header border-0">
		<h5 class="modal-title" id="quickPaymentModalLabel"><i class="bi bi-cash-coin me-2 text-primary"></i>Record Fee Payment</h5>
		<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
	  </div>
	  <div class="modal-body">
		<form id="quickPaymentForm" onsubmit="handleQuickPayment(event)">
		  <div class="mb-3">
			<label for="studentSearch" class="form-label">Search Student</label>
			<input type="text" class="form-control form-control-lg" id="studentSearch" placeholder="Student name or ID..." autocomplete="off" required>
			<div id="studentSuggestions" class="list-group mt-2" style="max-height:250px;overflow-y:auto;display:none;"></div>
			<input type="hidden" id="selectedStudentId" required>
		  </div>
		  <div class="mb-3">
			<label for="paymentAmount" class="form-label">Amount (Ksh)</label>
			<input type="number" class="form-control form-control-lg" id="paymentAmount" placeholder="0.00" step="0.01" min="0.01" required>
		  </div>
		  <div class="mb-3">
			<label for="paymentMethod" class="form-label">Payment Method</label>
			<select class="form-select form-select-lg" id="paymentMethod" required>
			  <option value="">-- Select method --</option>
			  <option value="cash">Cash</option>
			  <option value="cheque">Cheque</option>
			  <option value="bank">Bank Transfer</option>
			  <option value="mpesa">M-Pesa</option>
			</select>
		  </div>
		  <div class="d-grid gap-2">
			<button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle me-1"></i>Record Payment</button>
		  </div>
		</form>
		<div id="paymentStatus" class="mt-3"></div>
	  </div>
	</div>
  </div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
// Pre-fill and open modal when clicking Record button
function recordPaymentFor(studentId, studentName) {
	document.getElementById('selectedStudentId').value = studentId;
	document.getElementById('studentSearch').value = studentName;
	document.getElementById('paymentAmount').value = '';
	document.getElementById('paymentMethod').value = '';
	document.getElementById('paymentStatus').innerHTML = '';
	
	const modal = new bootstrap.Modal(document.getElementById('quickPaymentModal'));
	modal.show();
}

// Student search with live filtering
document.getElementById('studentSearch').addEventListener('input', async function() {
	const query = this.value.trim();
	if (query.length < 2) {
		document.getElementById('studentSuggestions').style.display = 'none';
		return;
	}
	
	try {
		const res = await fetch('api/search_students?q=' + encodeURIComponent(query));
		const data = await res.json();
		const suggestions = document.getElementById('studentSuggestions');
		suggestions.innerHTML = '';
		
		if (data.students && data.students.length > 0) {
			data.students.forEach(student => {
				const item = document.createElement('button');
				item.type = 'button';
				item.className = 'list-group-item list-group-item-action';
				item.textContent = student.name + ' (ID: ' + student.id + ')';
				item.onclick = (e) => {
					e.preventDefault();
					document.getElementById('studentSearch').value = student.name + ' (ID: ' + student.id + ')';
					document.getElementById('selectedStudentId').value = student.id;
					suggestions.style.display = 'none';
				};
				suggestions.appendChild(item);
			});
			suggestions.style.display = 'block';
		}
	} catch (err) {
		console.error('Search error:', err);
	}
});

// Handle payment submission
async function handleQuickPayment(event) {
	event.preventDefault();
	
	const studentId = document.getElementById('selectedStudentId').value;
	const amount = document.getElementById('paymentAmount').value;
	const method = document.getElementById('paymentMethod').value;
	const statusDiv = document.getElementById('paymentStatus');
	
	statusDiv.innerHTML = '<div class="alert alert-info">Processing...</div>';
	
	try {
		const res = await fetch('api/record_payment', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ student_id: studentId, amount: amount, method: method })
		});
		
		const result = await res.json();
		
		if (result.success) {
			statusDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>' + result.message + '</div>';
			document.getElementById('quickPaymentForm').reset();
			document.getElementById('selectedStudentId').value = '';
			setTimeout(() => {
				document.getElementById('quickPaymentModal').querySelector('.btn-close').click();
				location.reload();
			}, 1500);
		} else {
			statusDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>' + (result.message || 'Payment recording failed') + '</div>';
		}
	} catch (err) {
		statusDiv.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle me-1"></i>Error: ' + err.message + '</div>';
	}
}

// Clear suggestions on modal close
document.getElementById('quickPaymentModal').addEventListener('hidden.bs.modal', function() {
	document.getElementById('quickPaymentForm').reset();
	document.getElementById('selectedStudentId').value = '';
	document.getElementById('studentSuggestions').style.display = 'none';
	document.getElementById('paymentStatus').innerHTML = '';
});
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
