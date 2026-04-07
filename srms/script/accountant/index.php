<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$summary = ['open_invoices' => 0, 'paid_today' => 0, 'outstanding' => 0, 'payments_month' => 0];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_invoices')) {
		$summary['open_invoices'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'open'")->fetchColumn();
	}

	if (app_table_exists($conn, 'tbl_payments')) {
		$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
		$todayExpr = $driver === 'mysql' ? "DATE(paid_at)" : "paid_at::date";
		$todayValue = $driver === 'mysql' ? "CURDATE()" : "CURRENT_DATE";
		$monthExpr = $driver === 'mysql' ? "DATE_FORMAT(paid_at, '%Y-%m')" : "TO_CHAR(paid_at, 'YYYY-MM')";
		$currentMonth = date('Y-m');

		$summary['paid_today'] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE $todayExpr = $todayValue")->fetchColumn();
		$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE $monthExpr = ?");
		$stmt->execute([$currentMonth]);
		$summary['payments_month'] = (float)$stmt->fetchColumn();
	}

	if (app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_invoices')) {
		if (app_table_exists($conn, 'tbl_payments')) {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(lines.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
				FROM (
					SELECT i.id, SUM(l.amount) AS total_amount
					FROM tbl_invoices i
					INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.status <> 'void'
					GROUP BY i.id
				) lines
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = lines.id
			");
			$stmt->execute();
			$summary['outstanding'] = (float)$stmt->fetchColumn();
		}
	}
} catch (Throwable $e) {
	// keep defaults
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Accountant Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
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
<li><a class="app-menu__item active" href="accountant"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="accountant/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees & Finance</span></a></li>
<li><a class="app-menu__item" href="accountant/fee_structure"><i class="app-menu__icon feather icon-sliders"></i><span class="app-menu__label">Fee Structure</span></a></li>
<li><a class="app-menu__item" href="accountant/invoices"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Invoices</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Accountant Dashboard</h1>
<p>Manage fee structures, invoices, and payments.</p>
</div>
</div>

<div class="row mb-3">
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-file-text fs-1"></i>
	  <div class="info">
		<h4>Open Invoices</h4>
		<p><b><?php echo number_format((int)$summary['open_invoices']); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-cash-stack fs-1"></i>
	  <div class="info">
		<h4>Paid Today</h4>
		<p><b><?php echo number_format((float)$summary['paid_today'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-credit-card fs-1"></i>
	  <div class="info">
		<h4>Outstanding</h4>
		<p><b><?php echo number_format((float)$summary['outstanding'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-bar-chart-2 fs-1"></i>
	  <div class="info">
		<h4>Month Total</h4>
		<p><b><?php echo number_format((float)$summary['payments_month'], 2); ?></b></p>
	  </div>
	</div>
  </div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Quick Links</h3>
	  <div class="d-grid gap-2">
		<a class="btn btn-primary" href="accountant/fees"><i class="bi bi-credit-card me-1"></i>Fees Overview</a>
		<a class="btn btn-outline-primary" href="accountant/fee_structure"><i class="bi bi-sliders me-1"></i>Fee Structure</a>
		<a class="btn btn-outline-primary" href="accountant/invoices"><i class="bi bi-file-text me-1"></i>Invoices & Payments</a>
	  </div>
	</div>
  </div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
