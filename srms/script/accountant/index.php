<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$summary = ['open_invoices' => 0, 'paid_today' => 0, 'outstanding' => 0, 'payments_month' => 0];
$roleNames = [];
$permissionCodes = [];
$visibleModules = [];
$allocatedModules = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);

	if (app_table_exists($conn, 'tbl_invoices')) {
		$summary['open_invoices'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_invoices WHERE status = 'open'")->fetchColumn();
	}

	$roleNames = app_staff_role_names($conn, (int)$account_id);
	$permissionCodes = app_get_permissions($conn, (string)$account_id, (string)$level);
	$visibleModules = app_portal_visible_modules($conn, 'accountant', (string)$account_id, (string)$level);
	$allocatedModules = app_portal_allocated_modules($conn, 'accountant', (string)$account_id, (string)$level);

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
<style>
.access-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;margin:18px 0 18px}
.access-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:16px;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.access-card.roles,.access-card.permissions,.access-card.modules{grid-column:span 4}
.chip-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.access-chip,.module-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;font-size:.82rem;font-weight:700}
.access-chip{background:#eef4fb;color:#27405c}
.module-chip{background:#e7f1ef;color:#00695C}
.module-list{display:grid;gap:10px;margin-top:12px}
.module-link{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border:1px solid #e7edf5;border-radius:16px;text-decoration:none;color:#203040;background:#fbfdff}
.module-link:hover{border-color:#cfe3db;background:#f4fbf8}
.module-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#e7f1ef;color:#00695C;flex:0 0 auto}
.module-title{font-weight:800;color:#123;line-height:1.2}
.module-desc{font-size:.84rem;color:#6f7e8f;margin-top:2px}
.module-perms{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.module-perms span{font-size:.72rem;background:#eef4fb;color:#4d647d;padding:4px 8px;border-radius:999px}
@media (max-width: 1100px){.access-card.roles,.access-card.permissions,.access-card.modules{grid-column:span 12}}
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Accountant</p>
</div>
</div>
<ul class="app-menu">
<?php foreach ($visibleModules as $module): ?>
<li><a class="app-menu__item<?php echo basename((string)$module['href']) === basename($_SERVER['PHP_SELF'], '.php') ? ' active' : ''; ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
<?php endforeach; ?>
</ul>
</aside>

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
			<span class="meta-label">Month Total</span>
			<strong class="meta-value"><?php echo number_format((float)$summary['payments_month'], 2); ?></strong>
		</div>
	</div>
</div>

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
	<div class="access-card permissions">
		<h3 class="tile-title mb-2">Allocated Permissions</h3>
		<div class="small text-muted">Permission codes active in this portal.</div>
		<div class="chip-wrap">
			<?php if (!empty($permissionCodes)): ?>
				<?php foreach ($permissionCodes as $permissionCode): ?>
					<span class="module-chip"><?php echo htmlspecialchars((string)$permissionCode); ?></span>
				<?php endforeach; ?>
			<?php else: ?>
				<span class="module-chip">No extra permissions</span>
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
							<div class="module-perms">
								<?php foreach ((array)$module['permissions'] as $permission): ?>
									<span><?php echo htmlspecialchars((string)$permission); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
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
