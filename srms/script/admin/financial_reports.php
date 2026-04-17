<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../'); exit; }
app_require_permission('finance.view', '../');

$report_type = trim((string)($_GET['type'] ?? 'summary'));
$class_id = (int)($_GET['class'] ?? 0);
$term_id = (int)($_GET['term'] ?? 0);

$data = [
    'classes' => [],
    'terms' => [],
    'summary' => [],
    'classwise' => [],
    'termwise' => [],
    'aging' => [],
    'paymentmethods' => [],
    'topdefaulters' => []
];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_finance_tables($conn);
    $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    $openInvoiceExpr = $driver === 'pgsql'
        ? "COUNT(DISTINCT i.id) FILTER (WHERE i.status = 'open')"
        : "COUNT(DISTINCT CASE WHEN i.status = 'open' THEN i.id END)";
    $paidInvoiceExpr = $driver === 'pgsql'
        ? "COUNT(DISTINCT i.id) FILTER (WHERE i.status = 'paid')"
        : "COUNT(DISTINCT CASE WHEN i.status = 'paid' THEN i.id END)";
    $daysOverdueExpr = $driver === 'pgsql'
        ? "(CURRENT_DATE - i.due_date)"
        : "DATEDIFF(CURRENT_DATE, i.due_date)";
    $studentDaysOverdueExpr = $driver === 'pgsql'
        ? "(CURRENT_DATE - MAX(i.due_date))"
        : "DATEDIFF(CURRENT_DATE, MAX(i.due_date))";

    // Load classes and terms for filters
    $stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
    $stmt->execute();
    $data['classes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC LIMIT 10");
    $stmt->execute();
    $data['terms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // === SUMMARY REPORT ===
    if ($report_type === 'summary' || $report_type === 'all') {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(DISTINCT i.id) as total_invoices,
                COALESCE(SUM(il.amount), 0) as total_billed,
                COALESCE(SUM(p.amount), 0) as total_paid,
                COALESCE(SUM(il.amount), 0) - COALESCE(SUM(p.amount), 0) as outstanding
            FROM tbl_invoices i
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN tbl_payments p ON p.invoice_id = i.id
        ");
        $stmt->execute();
        $data['summary'] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // === CLASS-WISE BREAKDOWN ===
    if ($report_type === 'classwise' || $report_type === 'all') {
        $sql = "
            SELECT 
                c.id,
                c.name as class_name,
                COUNT(DISTINCT i.id) as num_students,
                {$openInvoiceExpr} as unpaid_count,
                COALESCE(SUM(il.amount), 0) as class_total,
                COALESCE(SUM(p.amount), 0) as class_paid,
                COALESCE(SUM(il.amount), 0) - COALESCE(SUM(p.amount), 0) as class_outstanding,
                ROUND(100.0 * COALESCE(SUM(p.amount), 0) / NULLIF(SUM(il.amount), 0), 2) as collection_rate
            FROM tbl_classes c
            LEFT JOIN tbl_invoices i ON i.class_id = c.id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN tbl_payments p ON p.invoice_id = i.id
        ";
        if ($class_id > 0) {
            $sql .= " WHERE c.id = ?";
            $stmt = $conn->prepare($sql . " GROUP BY c.id, c.name ORDER BY c.name");
            $stmt->execute([$class_id]);
        } else {
            $stmt = $conn->prepare($sql . " GROUP BY c.id, c.name ORDER BY c.name");
            $stmt->execute();
        }
        $data['classwise'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === TERM-WISE COMPARISON ===
    if ($report_type === 'termwise' || $report_type === 'all') {
        $stmt = $conn->prepare("
            SELECT 
                t.id,
                t.name as term_name,
                t.year,
                COUNT(DISTINCT i.id) as num_invoices,
                {$paidInvoiceExpr} as paid_invoices,
                COALESCE(SUM(il.amount), 0) as term_total,
                COALESCE(SUM(p.amount), 0) as term_paid,
                COALESCE(SUM(il.amount), 0) - COALESCE(SUM(p.amount), 0) as term_outstanding,
                ROUND(100.0 * COALESCE(SUM(p.amount), 0) / NULLIF(SUM(il.amount), 0), 2) as collection_rate
            FROM tbl_terms t
            LEFT JOIN tbl_invoices i ON i.term_id = t.id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN tbl_payments p ON p.invoice_id = i.id
            GROUP BY t.id, t.name, t.year
            ORDER BY t.year DESC, t.id DESC
        ");
        $stmt->execute();
        $data['termwise'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === AGING ANALYSIS (30/60/90 DAYS OVERDUE) ===
    if ($report_type === 'aging' || $report_type === 'all') {
        $stmt = $conn->prepare("
            SELECT 
                CASE 
                    WHEN {$daysOverdueExpr} BETWEEN 0 AND 29 THEN '0-30 days'
                    WHEN {$daysOverdueExpr} BETWEEN 30 AND 59 THEN '30-60 days'
                    WHEN {$daysOverdueExpr} BETWEEN 60 AND 89 THEN '60-90 days'
                    WHEN {$daysOverdueExpr} > 90 THEN '90+ days'
                    ELSE 'Not Due'
                END as age_bucket,
                COUNT(DISTINCT i.id) as invoice_count,
                COUNT(DISTINCT s.id) as student_count,
                COALESCE(SUM(il.amount - COALESCE(p.amount, 0)), 0) as amount_overdue
            FROM tbl_invoices i
            LEFT JOIN tbl_students s ON s.id = i.student_id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) as amount FROM tbl_payments GROUP BY invoice_id
            ) p ON p.invoice_id = i.id
            WHERE i.status = 'open' AND i.due_date < CURRENT_DATE
            GROUP BY age_bucket
            ORDER BY 
                CASE 
                    WHEN age_bucket = '0-30 days' THEN 1
                    WHEN age_bucket = '30-60 days' THEN 2
                    WHEN age_bucket = '60-90 days' THEN 3
                    WHEN age_bucket = '90+ days' THEN 4
                    ELSE 5
                END
        ");
        $stmt->execute();
        $data['aging'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === PAYMENT METHOD BREAKDOWN ===
    if ($report_type === 'methods' || $report_type === 'all') {
        $stmt = $conn->prepare("
            SELECT 
                p.method as payment_method,
                COUNT(*) as transaction_count,
                COALESCE(SUM(p.amount), 0) as total_amount,
                ROUND(100.0 * COALESCE(SUM(p.amount), 0) / NULLIF((SELECT SUM(amount) FROM tbl_payments), 0), 2) as percentage
            FROM tbl_payments p
            GROUP BY p.method
            ORDER BY total_amount DESC
        ");
        $stmt->execute();
        $data['paymentmethods'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // === TOP DEFAULTERS ===
    if ($report_type === 'defaulters' || $report_type === 'all') {
        $stmt = $conn->prepare("
            SELECT 
                s.id,
                s.fname,
                s.lname,
                c.name as class,
                COALESCE(SUM(il.amount - COALESCE(p.amount, 0)), 0) as total_outstanding,
                COUNT(DISTINCT i.id) as open_invoices,
                MAX(i.due_date) as earliest_due_date,
                {$studentDaysOverdueExpr} as days_overdue
            FROM tbl_students s
            LEFT JOIN tbl_invoices i ON i.student_id = s.id AND i.status = 'open'
            LEFT JOIN tbl_classes c ON c.id = i.class_id
            LEFT JOIN tbl_invoice_lines il ON il.invoice_id = i.id
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) as amount FROM tbl_payments GROUP BY invoice_id
            ) p ON p.invoice_id = i.id
            WHERE i.id IS NOT NULL
            GROUP BY s.id, s.fname, s.lname, c.name
            HAVING COALESCE(SUM(il.amount - COALESCE(p.amount, 0)), 0) > 0
            ORDER BY total_outstanding DESC
            LIMIT 50
        ");
        $stmt->execute();
        $data['topdefaulters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Throwable $e) {
    error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
    $_SESSION['reply'] = array(array('danger', 'Failed to load financial reports.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Financial Reports</title>
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
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div><p class="app-sidebar__user-name">Financial Reports</p><p class="app-sidebar__user-designation">Analytics</p></div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-home"></i><span class="app-menu__label">Admin Home</span></a></li>
<li><a class="app-menu__item" href="admin/invoices"><i class="app-menu__icon feather icon-file"></i><span class="app-menu__label">Invoices</span></a></li>
<li><a class="app-menu__item active" href="admin/financial_reports"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Financial Reports</span></a></li>
<li><a class="app-menu__item" href="admin/fee_structure"><i class="app-menu__icon feather icon-settings"></i><span class="app-menu__label">Fee Structure</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Financial Reports & Analytics</h1>
<p>Comprehensive financial analysis and reporting</p>
</div>
</div>

<div class="row mb-3">
<div class="col-12">
<div class="tile">
<div class="tile-body">
<ul class="nav nav-tabs" role="tablist">
<li class="nav-item"><a class="nav-link <?php echo $report_type === 'summary' ? 'active' : ''; ?>" href="?type=summary">Summary</a></li>
<li class="nav-item"><a class="nav-link <?php echo $report_type === 'classwise' ? 'active' : ''; ?>" href="?type=classwise">Class-wise</a></li>
<li class="nav-item"><a class="nav-link <?php echo $report_type === 'termwise' ? 'active' : ''; ?>" href="?type=termwise">Term-wise</a></li>
<li class="nav-item"><a class="nav-link <?php echo $report_type === 'aging' ? 'active' : ''; ?>" href="?type=aging">Aging Analysis</a></li>
<li class="nav-item"><a class="nav-link <?php echo $report_type === 'methods' ? 'active' : ''; ?>" href="?type=methods">Payment Methods</a></li>
<li class="nav-item"><a class="nav-link <?php echo $report_type === 'defaulters' ? 'active' : ''; ?>" href="?type=defaulters">Top Defaulters</a></li>
<li class="nav-item"><a class="nav-link" href="admin/financial_reports?type=export"><i class="bi bi-download me-1"></i>Export</a></li>
</ul>
</div>
</div>
</div>

<?php if ($report_type === 'summary' && $data['summary']): ?>
<div class="row">
<div class="col-md-3"><div class="tile"><h3 class="tile-title">Total Billed</h3><h2>KES <?php echo number_format((float)$data['summary']['total_billed'], 2); ?></h2></div></div>
<div class="col-md-3"><div class="tile"><h3 class="tile-title">Total Paid</h3><h2>KES <?php echo number_format((float)$data['summary']['total_paid'], 2); ?></h2></div></div>
<div class="col-md-3"><div class="tile"><h3 class="tile-title">Outstanding</h3><h2>KES <?php echo number_format((float)$data['summary']['outstanding'], 2); ?></h2></div></div>
<div class="col-md-3"><div class="tile"><h3 class="tile-title">Collection Rate</h3><h2><?php $rate = $data['summary']['total_billed'] > 0 ? round(100 * $data['summary']['total_paid'] / $data['summary']['total_billed'], 1) : 0; echo $rate; ?>%</h2></div></div>
</div>
<?php endif; ?>

<?php if ($report_type === 'classwise' && $data['classwise']): ?>
<div class="tile">
<h3 class="tile-title">Class-wise Fee Collection</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Class</th><th>Students</th><th>Total Billed</th><th>Paid</th><th>Outstanding</th><th>Collection %</th><th>Unpaid</th></tr></thead>
<tbody>
<?php foreach ($data['classwise'] as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['class_name']); ?></td>
<td><?php echo $row['num_students']; ?></td>
<td>KES <?php echo number_format((float)$row['class_total'], 2); ?></td>
<td>KES <?php echo number_format((float)$row['class_paid'], 2); ?></td>
<td>KES <?php echo number_format((float)$row['class_outstanding'], 2); ?></td>
<td><span class="badge bg-<?php echo $row['collection_rate'] >= 80 ? 'success' : 'warning'; ?>"><?php echo $row['collection_rate']; ?>%</span></td>
<td><?php echo $row['unpaid_count']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<?php if ($report_type === 'termwise' && $data['termwise']): ?>
<div class="tile">
<h3 class="tile-title">Term-wise Comparison</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Term</th><th>Year</th><th>Invoices</th><th>Paid</th><th>Total</th><th>Paid Amount</th><th>Outstanding</th><th>Collection %</th></tr></thead>
<tbody>
<?php foreach ($data['termwise'] as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['term_name']); ?></td>
<td><?php echo $row['year']; ?></td>
<td><?php echo $row['num_invoices']; ?></td>
<td><?php echo $row['paid_invoices']; ?></td>
<td>KES <?php echo number_format((float)$row['term_total'], 2); ?></td>
<td>KES <?php echo number_format((float)$row['term_paid'], 2); ?></td>
<td>KES <?php echo number_format((float)$row['term_outstanding'], 2); ?></td>
<td><?php echo $row['collection_rate']; ?>%</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<?php if ($report_type === 'aging' && $data['aging']): ?>
<div class="row">
<div class="col-md-8">
<div class="tile">
<h3 class="tile-title">Invoice Aging Analysis</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Age Bucket</th><th>Invoices</th><th>Students</th><th>Amount Overdue</th></tr></thead>
<tbody>
<?php foreach ($data['aging'] as $row): ?>
<tr>
<td><span class="badge bg-danger"><?php echo htmlspecialchars($row['age_bucket']); ?></span></td>
<td><?php echo $row['invoice_count']; ?></td>
<td><?php echo $row['student_count']; ?></td>
<td>KES <?php echo number_format((float)$row['amount_overdue'], 2); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<div class="col-md-4">
<div class="tile">
<h3 class="tile-title">Risk Indicator</h3>
<?php 
$aging_critical = array_filter($data['aging'], fn($x) => strpos($x['age_bucket'], '90+') !== false);
$critical_amount = array_reduce($aging_critical, fn($carry, $x) => $carry + (float)$x['amount_overdue'], 0);
?>
<div class="alert alert-danger">
<strong>Critical (90+ days):</strong><br>
KES <?php echo number_format($critical_amount, 2); ?>
</div>
</div>
</div>
</div>
<?php endif; ?>

<?php if ($report_type === 'methods' && $data['paymentmethods']): ?>
<div class="tile">
<h3 class="tile-title">Payment Method Breakdown</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Method</th><th>Transactions</th><th>Total Amount</th><th>Percentage</th></tr></thead>
<tbody>
<?php foreach ($data['paymentmethods'] as $row): ?>
<tr>
<td><strong><?php echo ucfirst(htmlspecialchars($row['payment_method'])); ?></strong></td>
<td><?php echo $row['transaction_count']; ?></td>
<td>KES <?php echo number_format((float)$row['total_amount'], 2); ?></td>
<td><div class="progress"><div class="progress-bar" style="width: <?php echo $row['percentage']; ?>%"><?php echo $row['percentage']; ?>%</div></div></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

<?php if ($report_type === 'defaulters' && $data['topdefaulters']): ?>
<div class="tile">
<h3 class="tile-title">Top 50 Students with Outstanding Fees</h3>
<div class="table-responsive">
<table class="table table-hover table-striped">
<thead><tr><th>Student</th><th>Class</th><th>Outstanding</th><th>Open Invoices</th><th>Days Overdue</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($data['topdefaulters'] as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['fname'] . ' ' . $row['lname']); ?></td>
<td><?php echo htmlspecialchars($row['class'] ?? 'N/A'); ?></td>
<td><strong>KES <?php echo number_format((float)$row['total_outstanding'], 2); ?></strong></td>
<td><?php echo $row['open_invoices']; ?></td>
<td><span class="badge bg-danger"><?php echo $row['days_overdue'] ?? 0; ?> days</span></td>
<td><a href="admin/invoices?student=<?php echo urlencode($row['id']); ?>" class="btn btn-sm btn-primary">View</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
