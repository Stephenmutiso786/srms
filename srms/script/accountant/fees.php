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
<p class="app-sidebar__user-designation">Accountant</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="accountant"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="accountant/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees & Finance</span></a></li>
<li><a class="app-menu__item" href="accountant/fee_structure"><i class="app-menu__icon feather icon-sliders"></i><span class="app-menu__label">Fee Structure</span></a></li>
<li><a class="app-menu__item" href="accountant/invoices"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Invoices</span></a></li>
</ul>
</aside>

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
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($topDefaulters) < 1) { ?>
		<tr><td colspan="3" class="text-muted">No outstanding balances found.</td></tr>
	  <?php } else { foreach ($topDefaulters as $d) { ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$d['student_id'].' — '.$d['student_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)($d['class_name'] ?? '')); ?></td>
		  <td><b><?php echo number_format((float)$d['balance'], 2); ?></b></td>
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

