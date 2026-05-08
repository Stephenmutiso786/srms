<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}

$cashIn = 0.0;
$cashOut = 0.0;
$mpesaTotal = 0.0;
$bankTotal = 0.0;
$recentPayments = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	if (app_table_exists($conn, 'tbl_payments')) {
		$cashIn = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments")->fetchColumn();
		$mpesaTotal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE LOWER(method) = 'mpesa'")->fetchColumn();
		$bankTotal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE LOWER(method) IN ('bank','bank transfer')")->fetchColumn();
		$stmt = $conn->prepare("SELECT paid_at, method, reference, amount FROM tbl_payments ORDER BY id DESC LIMIT 15");
		$stmt->execute();
		$recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (app_table_exists($conn, 'tbl_finance_expenses')) {
		$cashOut = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_finance_expenses")->fetchColumn();
	}
} catch (Throwable $e) {
	$recentPayments = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head><title><?php echo APP_NAME; ?> - Cashbook & Banking</title><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico"><link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini"><header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('accountant/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Cashbook & Banking</h1><p>Track overall money movement across cash, M-Pesa, and bank payments.</p></div></div>
<div class="row">
<div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-arrow-down-circle fs-1"></i><div class="info"><h4>Total Income</h4><p><b><?php echo number_format($cashIn, 2); ?></b></p></div></div></div>
<div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-arrow-up-circle fs-1"></i><div class="info"><h4>Total Expenses</h4><p><b><?php echo number_format($cashOut, 2); ?></b></p></div></div></div>
<div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-smartphone fs-1"></i><div class="info"><h4>M-Pesa Total</h4><p><b><?php echo number_format($mpesaTotal, 2); ?></b></p></div></div></div>
<div class="col-md-3"><div class="widget-small primary coloured-icon"><i class="icon feather icon-briefcase fs-1"></i><div class="info"><h4>Cashbook Balance</h4><p><b><?php echo number_format($cashIn - $cashOut, 2); ?></b></p></div></div></div>
</div>
<div class="tile mt-3"><h3 class="tile-title">Banking Notes</h3><div class="alert alert-info mb-0">Use this module to monitor cash movement. M-Pesa and bank totals are derived from recorded payments, while expenses reduce the cashbook balance automatically.</div></div>
<div class="tile mt-3"><h3 class="tile-title">Recent Receipts</h3><div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Time</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead><tbody><?php if (!$recentPayments): ?><tr><td colspan="4" class="text-muted">No payment activity recorded yet.</td></tr><?php endif; ?><?php foreach ($recentPayments as $payment): ?><tr><td><?php echo htmlspecialchars((string)$payment['paid_at']); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$payment['method'])); ?></td><td><?php echo htmlspecialchars((string)$payment['reference']); ?></td><td><?php echo number_format((float)$payment['amount'], 2); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main><script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script></body></html>
