<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$summary = ['collections' => 0, 'expenses' => 0, 'outstanding' => 0, 'net_cash' => 0];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	if (app_table_exists($conn, 'tbl_payments')) {
		$summary['collections'] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments")->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_finance_expenses')) {
		$summary['expenses'] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_finance_expenses")->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_payments')) {
		$stmt = $conn->prepare("SELECT COALESCE(SUM(invoice_totals.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
			FROM (
				SELECT i.id, SUM(l.amount) AS total_amount
				FROM tbl_invoices i
				INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
				WHERE i.status <> 'void'
				GROUP BY i.id
			) invoice_totals
			LEFT JOIN (
				SELECT invoice_id, SUM(amount) AS total_paid
				FROM tbl_payments
				GROUP BY invoice_id
			) paid ON paid.invoice_id = invoice_totals.id");
		$stmt->execute();
		$summary['outstanding'] = (float)$stmt->fetchColumn();
	}
	$summary['net_cash'] = $summary['collections'] - $summary['expenses'];
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en"><head><title><?php echo APP_NAME; ?> - Financial Reports</title><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico"><link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini"><header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header><?php include('accountant/partials/sidebar.php'); ?><main class="app-content">
<div class="app-title"><div><h1>Financial Reports</h1><p>View collections, expense summaries, balances, and reporting shortcuts.</p></div></div>
<div class="row"><div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-trending-up fs-1"></i><div class="info"><h4>Total Collections</h4><p><b><?php echo number_format($summary['collections'], 2); ?></b></p></div></div></div><div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-trending-down fs-1"></i><div class="info"><h4>Total Expenses</h4><p><b><?php echo number_format($summary['expenses'], 2); ?></b></p></div></div></div><div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-alert-octagon fs-1"></i><div class="info"><h4>Outstanding</h4><p><b><?php echo number_format($summary['outstanding'], 2); ?></b></p></div></div></div><div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-bar-chart fs-1"></i><div class="info"><h4>Net Cash</h4><p><b><?php echo number_format($summary['net_cash'], 2); ?></b></p></div></div></div></div>
<div class="tile mt-3"><h3 class="tile-title">Report Shortcuts</h3><div class="list-group"><a class="list-group-item list-group-item-action" href="accountant/fees">Collection and debtor overview</a><a class="list-group-item list-group-item-action" href="accountant/invoices">Student invoice and payment report</a><a class="list-group-item list-group-item-action" href="accountant/expenses">Expense report</a><a class="list-group-item list-group-item-action" href="accountant/payroll">Payroll summary</a><a class="list-group-item list-group-item-action" href="accountant/cashbook">Cashbook and banking report</a></div></div>
<div class="tile mt-3"><div class="alert alert-info mb-0">This finance portal is intentionally separated from academics, exams, discipline, and promotion. The accountant controls school fees, receipts, expenses, payroll, budgeting, and financial reporting only.</div></div>
</main><script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script></body></html>
