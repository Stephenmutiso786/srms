<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}

$summary = ['gross' => 0, 'deductions' => 0, 'net' => 0];
$records = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$payrollMonth = trim((string)($_POST['payroll_month'] ?? date('Y-m')));
		$gross = (float)($_POST['gross_amount'] ?? 0);
		$paye = (float)($_POST['paye_amount'] ?? 0);
		$nhif = (float)($_POST['nhif_amount'] ?? 0);
		$nssf = (float)($_POST['nssf_amount'] ?? 0);
		$loan = (float)($_POST['loan_amount'] ?? 0);
		$other = (float)($_POST['other_deductions_amount'] ?? 0);
		$deductions = $paye + $nhif + $nssf + $loan + $other;
		$net = (float)($_POST['net_amount'] ?? ($gross - $deductions));
		if ($payrollMonth !== '' && $gross > 0 && $net >= 0) {
			$stmt = $conn->prepare("INSERT INTO tbl_finance_salary_records (payroll_month, gross_amount, paye_amount, nhif_amount, nssf_amount, loan_amount, other_deductions_amount, deductions_amount, net_amount, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$stmt->execute([$payrollMonth, $gross, $paye, $nhif, $nssf, $loan, $other, $deductions, $net, (int)$account_id]);
			$_SESSION['reply'] = [[ 'success', 'Payroll record saved successfully.' ]];
			header('location:payroll');
			exit;
		}
	}
	$stmt = $conn->prepare("SELECT COALESCE(SUM(gross_amount),0) AS gross_total, COALESCE(SUM(deductions_amount),0) AS deductions_total, COALESCE(SUM(net_amount),0) AS net_total FROM tbl_finance_salary_records WHERE payroll_month = ?");
	$stmt->execute([date('Y-m')]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
	$summary['gross'] = (float)($row['gross_total'] ?? 0);
	$summary['deductions'] = (float)($row['deductions_total'] ?? 0);
	$summary['net'] = (float)($row['net_total'] ?? 0);
	$stmt = $conn->prepare("SELECT payroll_month, gross_amount, paye_amount, nhif_amount, nssf_amount, loan_amount, other_deductions_amount, deductions_amount, net_amount, status, created_at FROM tbl_finance_salary_records ORDER BY id DESC LIMIT 20");
	$stmt->execute();
	$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$records = [];
}
?>
<!DOCTYPE html>
<html lang="en"><head><title><?php echo APP_NAME; ?> - Payroll</title><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico"><link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini"><header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header><?php include('accountant/partials/sidebar.php'); ?><main class="app-content">
<div class="app-title"><div><h1>Payroll</h1><p>Track salary totals, deductions, and net pay records.</p></div></div>
<div class="row"><div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-users fs-1"></i><div class="info"><h4>Gross This Month</h4><p><b><?php echo number_format($summary['gross'], 2); ?></b></p></div></div></div><div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-minus-circle fs-1"></i><div class="info"><h4>Deductions</h4><p><b><?php echo number_format($summary['deductions'], 2); ?></b></p></div></div></div><div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-check-circle fs-1"></i><div class="info"><h4>Net Pay</h4><p><b><?php echo number_format($summary['net'], 2); ?></b></p></div></div></div></div>
<div class="tile mt-3"><h3 class="tile-title">Post Payroll Record</h3><form class="row g-3" method="POST" action="accountant/payroll"><div class="col-md-3"><label class="form-label">Payroll Month</label><input class="form-control" type="month" name="payroll_month" value="<?php echo date('Y-m'); ?>" required></div><div class="col-md-3"><label class="form-label">Gross Amount</label><input class="form-control payroll-input" type="number" step="0.01" min="0.01" name="gross_amount" required></div><div class="col-md-2"><label class="form-label">PAYE</label><input class="form-control payroll-deduction" type="number" step="0.01" min="0" name="paye_amount" value="0"></div><div class="col-md-2"><label class="form-label">NHIF</label><input class="form-control payroll-deduction" type="number" step="0.01" min="0" name="nhif_amount" value="0"></div><div class="col-md-2"><label class="form-label">NSSF</label><input class="form-control payroll-deduction" type="number" step="0.01" min="0" name="nssf_amount" value="0"></div><div class="col-md-2"><label class="form-label">Loan</label><input class="form-control payroll-deduction" type="number" step="0.01" min="0" name="loan_amount" value="0"></div><div class="col-md-2"><label class="form-label">Other Deductions</label><input class="form-control payroll-deduction" type="number" step="0.01" min="0" name="other_deductions_amount" value="0"></div><div class="col-md-3"><label class="form-label">Net Amount</label><input class="form-control" id="netAmountField" type="number" step="0.01" min="0" name="net_amount" required></div><div class="col-md-12 d-grid"><button class="btn btn-primary" type="submit">Save Payroll Record</button></div></form></div>
<div class="tile"><h3 class="tile-title">Payroll History</h3><div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Month</th><th>Gross</th><th>PAYE</th><th>NHIF</th><th>NSSF</th><th>Loan</th><th>Other</th><th>Deductions</th><th>Net</th><th>Status</th><th>Created</th></tr></thead><tbody><?php if (!$records): ?><tr><td colspan="11" class="text-muted">No payroll records posted yet.</td></tr><?php endif; ?><?php foreach ($records as $record): ?><tr><td><?php echo htmlspecialchars((string)$record['payroll_month']); ?></td><td><?php echo number_format((float)$record['gross_amount'], 2); ?></td><td><?php echo number_format((float)($record['paye_amount'] ?? 0), 2); ?></td><td><?php echo number_format((float)($record['nhif_amount'] ?? 0), 2); ?></td><td><?php echo number_format((float)($record['nssf_amount'] ?? 0), 2); ?></td><td><?php echo number_format((float)($record['loan_amount'] ?? 0), 2); ?></td><td><?php echo number_format((float)($record['other_deductions_amount'] ?? 0), 2); ?></td><td><?php echo number_format((float)$record['deductions_amount'], 2); ?></td><td><?php echo number_format((float)$record['net_amount'], 2); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$record['status'])); ?></td><td><?php echo htmlspecialchars((string)$record['created_at']); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main><script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script><script>
(function () {
    var grossField = document.querySelector('input[name="gross_amount"]');
    var deductionFields = document.querySelectorAll('.payroll-deduction');
    var netField = document.getElementById('netAmountField');
    if (!grossField || !netField || !deductionFields.length) {
        return;
    }
    function recalcNet() {
        var gross = parseFloat(grossField.value || '0') || 0;
        var deductions = 0;
        deductionFields.forEach(function (field) {
            deductions += parseFloat(field.value || '0') || 0;
        });
        netField.value = Math.max(0, gross - deductions).toFixed(2);
    }
    grossField.addEventListener('input', recalcNet);
    deductionFields.forEach(function (field) {
        field.addEventListener('input', recalcNet);
    });
    recalcNet();
})();
</script></body></html>
