<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../"); exit;}

$invoices = [];
$error = '';
$hasReceipts = false;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines') || !app_table_exists($conn, 'tbl_payments')) {
		throw new RuntimeException("Fees module is not installed on the server yet.");
	}
	$hasReceipts = app_table_exists($conn, 'tbl_receipts');

	if ($hasReceipts) {
		$stmt = $conn->prepare("SELECT i.id, t.name AS term_name, i.issue_date, i.due_date,
			COALESCE(SUM(l.amount),0) AS total,
			COALESCE(paid.total_paid, 0) AS paid,
			lr.latest_receipt_id,
			lr.receipt_number AS latest_receipt_no
			FROM tbl_invoices i
			JOIN tbl_terms t ON t.id = i.term_id
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
			WHERE i.student_id = ? AND i.status != 'void'
			GROUP BY i.id, t.name, i.issue_date, i.due_date, paid.total_paid, lr.latest_receipt_id, lr.receipt_number
			ORDER BY i.term_id DESC, i.id DESC");
	} else {
		$stmt = $conn->prepare("SELECT i.id, t.name AS term_name, i.issue_date, i.due_date,
			COALESCE(SUM(l.amount),0) AS total,
			COALESCE(paid.total_paid, 0) AS paid
			FROM tbl_invoices i
			JOIN tbl_terms t ON t.id = i.term_id
			LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
			LEFT JOIN (
				SELECT invoice_id, SUM(amount) AS total_paid
				FROM tbl_payments
				GROUP BY invoice_id
			) paid ON paid.invoice_id = i.id
			WHERE i.student_id = ? AND i.status != 'void'
			GROUP BY i.id, t.name, i.issue_date, i.due_date, paid.total_paid
			ORDER BY i.term_id DESC, i.id DESC");
	}
	$stmt->execute([(string)$account_id]);
	$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Fees</title>
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
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">My Attendance</span></a></li>
<li><a class="app-menu__item active" href="student/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Fees</h1>
<p>Invoices and balances.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="tile">
  <h3 class="tile-title">My Invoices</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Term</th>
		  <th>Issue</th>
		  <th>Due</th>
		  <th>Total</th>
		  <th>Paid</th>
		  <th>Balance</th>
		  <th>Receipt</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($invoices) < 1) { ?>
		<tr><td colspan="7" class="text-muted">No invoices yet.</td></tr>
	  <?php } else { foreach ($invoices as $inv) {
		$total = (float)$inv['total'];
		$paid = (float)$inv['paid'];
		$bal = max(0, $total - $paid);
	  ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$inv['term_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)$inv['issue_date']); ?></td>
		  <td><?php echo htmlspecialchars((string)($inv['due_date'] ?? '-')); ?></td>
		  <td><?php echo number_format($total, 2); ?></td>
		  <td><?php echo number_format($paid, 2); ?></td>
		  <td><b><?php echo number_format($bal, 2); ?></b></td>
		  <td>
			<?php if ($hasReceipts && (int)($inv['latest_receipt_id'] ?? 0) > 0): ?>
			  <a class="btn btn-sm btn-outline-secondary" target="_blank" href="receipt?id=<?php echo (int)$inv['latest_receipt_id']; ?>&download=1"><?php echo htmlspecialchars((string)$inv['latest_receipt_no']); ?></a>
			<?php else: ?>
			  <span class="text-muted">-</span>
			<?php endif; ?>
		  </td>
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

