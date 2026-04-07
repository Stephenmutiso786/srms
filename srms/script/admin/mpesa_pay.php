<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && ($level == "0" || $level == "5")) {}else{header("location:../"); exit;}

$invoiceId = (int)($_GET['invoice_id'] ?? 0);
$invoice = null;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_mpesa_stk_requests')) {
		throw new RuntimeException("M-Pesa module is not installed. Run migration 006_mpesa_stk.sql.");
	}

	$stmt = $conn->prepare("SELECT i.id, i.student_id, concat_ws(' ', s.fname, s.mname, s.lname) AS student_name,
		t.name AS term_name, c.name AS class_name,
		COALESCE((SELECT SUM(l.amount) FROM tbl_invoice_lines l WHERE l.invoice_id = i.id), 0) AS total,
		COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id), 0) AS paid
		FROM tbl_invoices i
		JOIN tbl_students s ON s.id = i.student_id
		JOIN tbl_terms t ON t.id = i.term_id
		JOIN tbl_classes c ON c.id = i.class_id
		WHERE i.id = ? LIMIT 1");
	$stmt->execute([$invoiceId]);
	$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$invoice) {
		throw new RuntimeException("Invoice not found.");
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - M-Pesa Payment</title>
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation"><?php echo ($level == "5") ? "Accountant" : "Administrator"; ?></p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="<?php echo ($level == "5") ? 'accountant' : 'admin'; ?>"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/mpesa_pay?invoice_id=<?php echo $invoiceId; ?>"><i class="app-menu__icon feather icon-smartphone"></i><span class="app-menu__label">M-Pesa Pay</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>M-Pesa STK Push</h1>
<p>Invoice payment request</p>
</div>
<div>
<a class="btn btn-outline-secondary" href="<?php echo ($level == "5") ? 'accountant/invoices' : 'admin/invoices'; ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else {
  $total = (float)$invoice['total'];
  $paid = (float)$invoice['paid'];
  $bal = max(0, $total - $paid);
?>

<div class="tile mb-3">
  <h3 class="tile-title">Invoice</h3>
  <div class="row">
	<div class="col-md-6"><b>Student:</b> <?php echo htmlspecialchars((string)$invoice['student_id'].' — '.$invoice['student_name']); ?></div>
	<div class="col-md-6"><b>Class/Term:</b> <?php echo htmlspecialchars((string)$invoice['class_name'].' / '.$invoice['term_name']); ?></div>
	<div class="col-md-4 mt-2"><b>Total:</b> <?php echo number_format($total, 2); ?></div>
	<div class="col-md-4 mt-2"><b>Paid:</b> <?php echo number_format($paid, 2); ?></div>
	<div class="col-md-4 mt-2"><b>Balance:</b> <span class="text-danger"><b><?php echo number_format($bal, 2); ?></b></span></div>
  </div>
</div>

<div class="tile">
  <h3 class="tile-title">Send STK Push</h3>
  <form class="row g-3" method="POST" action="admin/core/mpesa_stk_push" autocomplete="off">
	<input type="hidden" name="invoice_id" value="<?php echo (int)$invoice['id']; ?>">
	<div class="col-md-4">
	  <label class="form-label">Phone (2547XXXXXXXX)</label>
	  <input class="form-control" name="phone" placeholder="2547..." required>
	</div>
	<div class="col-md-4">
	  <label class="form-label">Amount</label>
	  <input class="form-control" type="number" step="1" min="1" name="amount" value="<?php echo (int)max(1, round($bal)); ?>" required>
	</div>
	<div class="col-md-4 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Send</button>
	</div>
	<p class="text-muted mb-0">After the customer approves on their phone, the callback auto-posts a payment to the invoice.</p>
  </form>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

