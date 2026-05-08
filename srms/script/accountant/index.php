<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$summary = ['open_invoices' => 0, 'paid_today' => 0, 'outstanding' => 0, 'payments_month' => 0, 'debtors' => 0, 'expenses_month' => 0, 'salary_month' => 0, 'mpesa_today' => 0];
$roleNames = [];
$visibleModules = [];
$allocatedModules = [];
$recentTransactions = [];
$financeAlerts = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	app_sync_student_finance_class_links($conn);

	if (app_table_exists($conn, 'tbl_invoices')) {
		$summary['open_invoices'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'open'")->fetchColumn();
	}

	$roleNames = app_staff_role_names($conn, (int)$account_id);
	$visibleModules = app_portal_visible_modules($conn, 'accountant', (string)$account_id, (string)$level);
	$allocatedModules = app_portal_allocated_modules($conn, 'accountant', (string)$account_id, (string)$level);

	if (app_table_exists($conn, 'tbl_payments')) {
		$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
		$todayExpr = $driver === 'mysql' ? "DATE(paid_at)" : "paid_at::date";
		$todayValue = $driver === 'mysql' ? "CURDATE()" : "CURRENT_DATE";
		$monthExpr = $driver === 'mysql' ? "DATE_FORMAT(paid_at, '%Y-%m')" : "TO_CHAR(paid_at, 'YYYY-MM')";
		$currentMonth = date('Y-m');

		$summary['paid_today'] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE $todayExpr = $todayValue")->fetchColumn();
		$summary['mpesa_today'] = (float)$conn->query("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE LOWER(method) = 'mpesa' AND $todayExpr = $todayValue")->fetchColumn();
		$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_payments WHERE $monthExpr = ?");
		$stmt->execute([$currentMonth]);
		$summary['payments_month'] = (float)$stmt->fetchColumn();
		$stmt = $conn->prepare("SELECT p.paid_at, p.amount, p.method, p.reference, i.student_id
			FROM tbl_payments p
			LEFT JOIN tbl_invoices i ON i.id = p.invoice_id
			ORDER BY p.id DESC
			LIMIT 8");
		$stmt->execute();
		$recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_invoice_lines') && app_table_exists($conn, 'tbl_invoices')) {
		if (app_table_exists($conn, 'tbl_payments')) {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(invoice_totals.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
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
				) paid ON paid.invoice_id = invoice_totals.id
			");
			$stmt->execute();
			$summary['outstanding'] = (float)$stmt->fetchColumn();
		}
	}
	if (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM (
			SELECT i.student_id
			FROM tbl_invoices i
			LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
			LEFT JOIN (
				SELECT invoice_id, SUM(amount) AS total_paid
				FROM tbl_payments
				GROUP BY invoice_id
			) paid ON paid.invoice_id = i.id
			GROUP BY i.student_id
			HAVING COALESCE(SUM(l.amount),0) > COALESCE(SUM(paid.total_paid),0)
		) debtors");
		$stmt->execute();
		$summary['debtors'] = (int)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_finance_expenses')) {
		$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_finance_expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
		try {
			$stmt->execute([date('Y-m')]);
		} catch (Throwable $mysqlDateError) {
			$stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM tbl_finance_expenses WHERE TO_CHAR(expense_date, 'YYYY-MM') = ?");
			$stmt->execute([date('Y-m')]);
		}
		$summary['expenses_month'] = (float)$stmt->fetchColumn();
	}
	if (app_table_exists($conn, 'tbl_finance_salary_records')) {
		$stmt = $conn->prepare("SELECT COALESCE(SUM(net_amount),0) FROM tbl_finance_salary_records WHERE payroll_month = ?");
		$stmt->execute([date('Y-m')]);
		$summary['salary_month'] = (float)$stmt->fetchColumn();
	}
	if ($summary['debtors'] > 0) {
		$financeAlerts[] = $summary['debtors'] . ' student fee account(s) still have arrears.';
	}
	if ($summary['outstanding'] > 0) {
		$financeAlerts[] = 'Outstanding balances need follow-up and reminders.';
	}
	if ($summary['salary_month'] <= 0) {
		$financeAlerts[] = 'No salary records have been posted for ' . date('F Y') . '.';
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
<style>
.access-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;margin:18px 0 18px}
.access-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:16px;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.access-card.roles,.access-card.modules{grid-column:span 6}
.chip-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.access-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;font-size:.82rem;font-weight:700}
.access-chip{background:#eef4fb;color:#27405c}
.module-list{display:grid;gap:10px;margin-top:12px}
.module-link{display:flex;gap:12px;align-items:flex-start;padding:14px 15px;border:1px solid #e7edf5;border-radius:18px;text-decoration:none;color:#203040;background:linear-gradient(180deg,#ffffff,#f8fbff);box-shadow:0 8px 18px rgba(16,41,38,.04);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.module-link:hover{border-color:#00695C;background:linear-gradient(180deg,#ffffff,#eefaf7);box-shadow:0 14px 26px rgba(0,105,92,.10);transform:translateY(-1px)}
.module-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#e7f1ef;color:#00695C;flex:0 0 auto}
.module-title{font-weight:800;color:#123;line-height:1.2}
.module-desc{font-size:.84rem;color:#6f7e8f;margin-top:2px}
.module-cta{margin-left:auto;align-self:center;font-size:.75rem;font-weight:800;color:#00695C;background:#e7f1ef;border-radius:999px;padding:7px 10px;white-space:nowrap}
@media (max-width: 1100px){.access-card.roles,.access-card.modules{grid-column:span 12}}
</style>
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

<?php include('accountant/partials/sidebar.php'); ?>

<main class="app-content">
<div class="dashboard-hero">
	<div class="hero-main">
		<span class="hero-kicker">Accountant Overview</span>
		<h1>Manage fees, invoices, and collections</h1>
		<p>Use the quick actions below to review fee flow, issue invoices, and keep the finance ledger clean.</p>
	</div>
	<div class="hero-meta">
		<div class="meta-card">
			<span class="meta-label">Today</span>
			<strong class="meta-value"><?php echo date('l, d M Y'); ?></strong>
		</div>
		<div class="meta-card">
			<span class="meta-label">Current Time</span>
			<strong class="meta-value" id="accountantCurrentTime"><?php echo date('H:i:s'); ?></strong>
		</div>
		<div class="meta-card">
			<span class="meta-label">Month Total</span>
			<strong class="meta-value"><?php echo number_format((float)$summary['payments_month'], 2); ?></strong>
		</div>
	</div>
</div>

<?php if (!empty($financeAlerts)): ?>
<div class="tile mb-3">
	<h3 class="tile-title">Finance Alerts</h3>
	<?php foreach ($financeAlerts as $alertText): ?>
	<div class="alert alert-warning mb-2"><?php echo htmlspecialchars($alertText); ?></div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="access-grid">
	<div class="access-card roles">
		<h3 class="tile-title mb-2">Assigned Roles</h3>
		<div class="small text-muted">Roles attached to this accountant account.</div>
		<div class="chip-wrap">
			<?php if (!empty($roleNames)): ?>
				<?php foreach ($roleNames as $roleName): ?>
					<span class="access-chip"><?php echo htmlspecialchars($roleName); ?></span>
				<?php endforeach; ?>
			<?php else: ?>
				<span class="access-chip">Accountant</span>
			<?php endif; ?>
		</div>
	</div>
	<div class="access-card modules">
		<h3 class="tile-title mb-2">Allocated Modules</h3>
		<div class="small text-muted">Modules unlocked by your permissions.</div>
		<div class="module-list">
			<?php if (!empty($allocatedModules)): ?>
				<?php foreach ($allocatedModules as $module): ?>
					<a class="module-link" href="<?php echo htmlspecialchars((string)$module['href']); ?>">
						<div class="module-icon"><i class="<?php echo htmlspecialchars((string)$module['icon']); ?>"></i></div>
						<div>
							<div class="module-title"><?php echo htmlspecialchars((string)$module['label']); ?></div>
							<div class="module-desc"><?php echo htmlspecialchars((string)$module['description']); ?></div>
						</div>
						<span class="module-cta">Open</span>
					</a>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="text-muted">No additional modules found yet.</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div class="dashboard-stats">
	<div class="stat-card"><div><div class="stat-label">Open Invoices</div><div class="stat-value"><?php echo number_format((int)$summary['open_invoices']); ?></div></div><div class="stat-icon"><i class="bi bi-file-text"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">Paid Today</div><div class="stat-value"><?php echo number_format((float)$summary['paid_today'], 2); ?></div></div><div class="stat-icon"><i class="bi bi-cash-stack"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">Outstanding</div><div class="stat-value"><?php echo number_format((float)$summary['outstanding'], 2); ?></div></div><div class="stat-icon"><i class="bi bi-wallet2"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">Month Total</div><div class="stat-value"><?php echo number_format((float)$summary['payments_month'], 2); ?></div></div><div class="stat-icon"><i class="bi bi-bar-chart-2"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">Debtors</div><div class="stat-value"><?php echo number_format((int)$summary['debtors']); ?></div></div><div class="stat-icon"><i class="bi bi-exclamation-circle"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">Expenses This Month</div><div class="stat-value"><?php echo number_format((float)$summary['expenses_month'], 2); ?></div></div><div class="stat-icon"><i class="bi bi-cart"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">Salary Expenses</div><div class="stat-value"><?php echo number_format((float)$summary['salary_month'], 2); ?></div></div><div class="stat-icon"><i class="bi bi-people"></i></div></div>
	<div class="stat-card"><div><div class="stat-label">M-Pesa Today</div><div class="stat-value"><?php echo number_format((float)$summary['mpesa_today'], 2); ?></div></div><div class="stat-icon"><i class="bi bi-phone"></i></div></div>
</div>

<div class="row">
  <div class="col-lg-4 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Quick Links</h3>
	  <div class="d-grid gap-2">
		<a class="btn btn-primary" href="accountant/fees"><i class="bi bi-credit-card me-1"></i>Fees Overview</a>
		<a class="btn btn-outline-primary" href="accountant/receive_payment"><i class="bi bi-plus-circle me-1"></i>Record Fee Payment</a>
		<a class="btn btn-outline-primary" href="accountant/fee_structure"><i class="bi bi-sliders me-1"></i>Fee Structure</a>
		<a class="btn btn-outline-primary" href="accountant/invoices"><i class="bi bi-file-text me-1"></i>Invoices & Payments</a>
		<a class="btn btn-outline-primary" href="accountant/expenses"><i class="bi bi-cart me-1"></i>Expenses</a>
		<a class="btn btn-outline-primary" href="accountant/cashbook"><i class="bi bi-bank me-1"></i>Cashbook & Banking</a>
		<a class="btn btn-outline-primary" href="accountant/payroll"><i class="bi bi-people me-1"></i>Payroll</a>
		<a class="btn btn-outline-primary" href="accountant/financial_reports"><i class="bi bi-bar-chart me-1"></i>Financial Reports</a>
		<a class="btn btn-outline-primary" href="accountant/budgets"><i class="bi bi-pie-chart me-1"></i>Budgeting</a>
		<a class="btn btn-outline-primary" href="accountant/bursaries"><i class="bi bi-heart me-1"></i>Bursaries</a>
		<a class="btn btn-outline-primary" href="accountant/mpesa"><i class="bi bi-phone me-1"></i>M-Pesa</a>
		<a class="btn btn-outline-primary" href="accountant/ledger"><i class="bi bi-journal-bookmark me-1"></i>General Ledger</a>
	  </div>
	</div>
  </div>
  <div class="col-lg-8 mb-3">
	<div class="tile">
	  <h3 class="tile-title">Recent Transactions</h3>
	  <div class="table-responsive">
		<table class="table table-hover table-striped">
		  <thead><tr><th>Time</th><th>Student</th><th>Method</th><th>Reference</th><th>Amount</th></tr></thead>
		  <tbody>
		  <?php if (!$recentTransactions) { ?>
		  <tr><td colspan="5" class="text-muted">No recent payments recorded.</td></tr>
		  <?php } ?>
		  <?php foreach ($recentTransactions as $transaction): ?>
		  <tr>
			<td><?php echo htmlspecialchars((string)$transaction['paid_at']); ?></td>
			<td><?php echo htmlspecialchars((string)($transaction['student_id'] ?? '')); ?></td>
			<td><?php echo htmlspecialchars(ucfirst((string)($transaction['method'] ?? 'cash'))); ?></td>
			<td><?php echo htmlspecialchars((string)($transaction['reference'] ?? '')); ?></td>
			<td><?php echo number_format((float)($transaction['amount'] ?? 0), 2); ?></td>
		  </tr>
		  <?php endforeach; ?>
		  </tbody>
		</table>
	  </div>
	</div>
  </div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
	function updateClock() {
		var node = document.getElementById('accountantCurrentTime');
		if (!node) return;
		node.textContent = new Intl.DateTimeFormat('en-KE', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Africa/Nairobi' }).format(new Date());
	}
	updateClock();
	setInterval(updateClock, 1000);
})();
</script>
</body>
</html>
