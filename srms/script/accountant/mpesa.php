<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$todayTotal = 0.0;
$overallTotal = 0.0;
$transactions = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	if (app_table_exists($conn, 'tbl_payments')) {
		$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
		$todayExpr = $driver === 'mysql' ? "DATE(paid_at)" : "paid_at::date";
		$todayValue = $driver === 'mysql' ? "CURDATE()" : "CURRENT_DATE";
		$todayTotal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE LOWER(method) = 'mpesa' AND $todayExpr = $todayValue")->fetchColumn();
		$overallTotal = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE LOWER(method) = 'mpesa'")->fetchColumn();
		$stmt = $conn->prepare("SELECT paid_at, reference, amount FROM tbl_payments WHERE LOWER(method) = 'mpesa' ORDER BY id DESC LIMIT 20");
		$stmt->execute();
		$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$transactions = [];
}
?>
<!DOCTYPE html>
<html lang="en"><head><title><?php echo APP_NAME; ?> - M-Pesa</title><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico"><link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini"><header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header><?php include('accountant/partials/sidebar.php'); ?><main class="app-content">
<div class="app-title"><div><h1>M-Pesa</h1><p>Review M-Pesa collections and reconcile references.</p></div></div>
<div class="row"><div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-smartphone fs-1"></i><div class="info"><h4>M-Pesa Today</h4><p><b><?php echo number_format($todayTotal, 2); ?></b></p></div></div></div><div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-credit-card fs-1"></i><div class="info"><h4>M-Pesa Total</h4><p><b><?php echo number_format($overallTotal, 2); ?></b></p></div></div></div></div>
<div class="tile mt-3"><div class="alert alert-info mb-0">This portal tracks M-Pesa payments recorded in the finance system. For gateway credentials or paybill settings, coordinate with the Super Admin.</div></div>
<div class="tile mt-3"><h3 class="tile-title">Recent M-Pesa Transactions</h3><div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Time</th><th>Reference</th><th>Amount</th></tr></thead><tbody><?php if (!$transactions): ?><tr><td colspan="3" class="text-muted">No M-Pesa transactions recorded yet.</td></tr><?php endif; ?><?php foreach ($transactions as $transaction): ?><tr><td><?php echo htmlspecialchars((string)$transaction['paid_at']); ?></td><td><?php echo htmlspecialchars((string)$transaction['reference']); ?></td><td><?php echo number_format((float)$transaction['amount'], 2); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main><script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script></body></html>
