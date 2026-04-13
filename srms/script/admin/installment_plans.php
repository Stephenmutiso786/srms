<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != '1') { header('location:../'); exit; }
app_require_permission('finance.manage', '../');

$action = trim((string)($_GET['action'] ?? 'list'));
$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$message = '';
$invoices = [];
$installments = [];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get list of open invoices
    $stmt = $conn->prepare("
        SELECT 
            i.id,
            CONCAT(s.fname, ' ', s.lname) as student_name,
            s.id as student_id,
            c.name as class,
            sumi.total as invoice_total,
            COALESCE(sump.total, 0) as paid_amount,
            sumi.total - COALESCE(sump.total, 0) as outstanding,
            i.due_date,
            i.status,
            (SELECT COUNT(*) FROM tbl_fee_installments WHERE invoice_id = i.id) as installment_count
        FROM tbl_invoices i
        JOIN tbl_students s ON s.id = i.student_id
        JOIN tbl_classes c ON c.id = i.class_id
        JOIN (SELECT invoice_id, SUM(amount) as total FROM tbl_invoice_lines GROUP BY invoice_id) sumi ON sumi.invoice_id = i.id
        LEFT JOIN (SELECT invoice_id, SUM(amount) as total FROM tbl_payments GROUP BY invoice_id) sump ON sump.invoice_id = i.id
        ORDER BY i.due_date DESC, i.id DESC
        LIMIT 100
    ");
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get installment details if viewing specific invoice
    if ($invoice_id > 0) {
        $stmt = $conn->prepare("
            SELECT 
                fi.id,
                fi.number_of_installments,
                fi.installment_amount,
                fi.first_due_date,
                (SELECT COUNT(*) FROM tbl_installment_schedule WHERE installment_id = fi.id AND status = 'paid') as paid_count,
                (SELECT SUM(amount_paid) FROM tbl_installment_schedule WHERE installment_id = fi.id) as total_paid
            FROM tbl_fee_installments fi
            WHERE fi.invoice_id = ?
        ");
        $stmt->execute([$invoice_id]);
        $installments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Handle POST actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_action = trim((string)($_POST['action'] ?? ''));

        if ($post_action === 'create_installment') {
            $inv_id = (int)($_POST['invoice_id'] ?? 0);
            $num_installments = (int)($_POST['num_installments'] ?? 0);
            $first_due_date = trim((string)($_POST['first_due_date'] ?? ''));

            if ($inv_id < 1 || $num_installments < 2 || $num_installments > 12 || !$first_due_date) {
                $message = 'Invalid installment plan. Must be 2-12 installments with valid due date.';
            } else {
                // Get invoice total
                $stmt = $conn->prepare("SELECT SUM(amount) as total FROM tbl_invoice_lines WHERE invoice_id = ?");
                $stmt->execute([$inv_id]);
                $total = (float)$stmt->fetchColumn();

                if ($total <= 0) {
                    $message = 'Invoice total must be greater than 0.';
                } else {
                    $installment_amount = round($total / $num_installments, 2);

                    // Insert installment plan
                    $stmt = $conn->prepare("
                        INSERT INTO tbl_fee_installments (invoice_id, number_of_installments, installment_amount, first_due_date, created_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$inv_id, $num_installments, $installment_amount, $first_due_date, (int)$account_id]);
                    $installment_id = (int)$conn->lastInsertId();

                    // Create payment schedule
                    $due_dt = new DateTime($first_due_date);
                    for ($i = 1; $i <= $num_installments; $i++) {
                        $stmt = $conn->prepare("
                            INSERT INTO tbl_installment_schedule (installment_id, installment_number, due_date, amount_due, status)
                            VALUES (?, ?, ?, ?, 'pending')
                        ");
                        $stmt->execute([$installment_id, $i, $due_dt->format('Y-m-d'), $installment_amount]);
                        $due_dt->add(new DateInterval('P1M'));
                    }

                    $message = 'Installment plan created successfully.';
                }
            }
        }
    }

} catch (Throwable $e) {
    error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
    $message = 'Failed to load installment data.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Installment Plans</title>
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
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu dropdown-menu-right"><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li></ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user"><div><p class="app-sidebar__user-name">Finance</p></div></div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-home"></i><span class="app-menu__label">Admin Home</span></a></li>
<li><a class="app-menu__item" href="admin/invoices"><i class="app-menu__icon feather icon-file"></i><span class="app-menu__label">Invoices</span></a></li>
<li><a class="app-menu__item active" href="admin/installment_plans"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Installment Plans</span></a></li>
<li><a class="app-menu__item" href="admin/financial_reports"><i class="app-menu__icon feather icon-bar-chart"></i><span class="app-menu__label">Reports</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title"><div><h1>Installment Payment Plans</h1><p>Allow students to pay fees in installments</p></div></div>

<?php if ($message): ?>
<div class="alert alert-info alert-dismissible fade show" role="alert">
<?php echo htmlspecialchars($message); ?>
<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!$invoice_id): ?>
<div class="tile">
<h3 class="tile-title">Open Invoices</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Student</th><th>Class</th><th>Due Date</th><th>Total</th><th>Paid</th><th>Outstanding</th><th>Plans</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($invoices as $inv): ?>
<tr>
<td><?php echo htmlspecialchars($inv['student_name']); ?></td>
<td><?php echo htmlspecialchars($inv['class']); ?></td>
<td><?php echo $inv['due_date']; ?></td>
<td>KES <?php echo number_format($inv['invoice_total'], 2); ?></td>
<td>KES <?php echo number_format($inv['paid_amount'], 2); ?></td>
<td>KES <?php echo number_format($inv['outstanding'], 2); ?></td>
<td><?php echo $inv['installment_count']; ?></td>
<td><a href="?invoice_id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<?php else: ?>

<?php 
$current_inv = array_filter($invoices, fn($x) => $x['id'] == $invoice_id);
if ($current_inv) { 
    $inv = array_values($current_inv)[0];
?>
<div class="row mb-3">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Invoice Details</h3>
<div class="tile-body">
<p><strong>Student:</strong> <?php echo htmlspecialchars($inv['student_name']); ?></p>
<p><strong>Class:</strong> <?php echo htmlspecialchars($inv['class']); ?></p>
<p><strong>Total:</strong> KES <?php echo number_format($inv['invoice_total'], 2); ?></p>
<p><strong>Paid:</strong> KES <?php echo number_format($inv['paid_amount'], 2); ?></p>
<p><strong>Outstanding:</strong> KES <?php echo number_format($inv['outstanding'], 2); ?></p>
<a href="admin/installment_plans" class="btn btn-secondary btn-sm">← Back</a>
</div>
</div>
</div>
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Create Installment Plan</h3>
<form method="POST" class="app_frm">
<input type="hidden" name="action" value="create_installment">
<input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
<div class="mb-2">
<label class="form-label">Number of Installments (2-12)</label>
<input type="number" name="num_installments" min="2" max="12" value="3" required class="form-control">
</div>
<div class="mb-2">
<label class="form-label">First Due Date</label>
<input type="date" name="first_due_date" required class="form-control">
</div>
<button type="submit" class="btn btn-primary btn-sm">Create Plan</button>
</form>
</div>
</div>
</div>

<?php if ($installments): ?>
<div class="tile">
<h3 class="tile-title">Installment Plans</h3>
<?php foreach ($installments as $plan): ?>
<div class="mb-3 p-3 border rounded">
<strong>Plan: <?php echo $plan['number_of_installments']; ?> installments of KES <?php echo number_format($plan['installment_amount'], 2); ?></strong><br>
<small>Starting: <?php echo $plan['first_due_date']; ?> | Paid: <?php echo $plan['paid_count']; ?>/<?php echo $plan['number_of_installments']; ?></small>
<div class="progress" style="height: 20px; margin-top: 10px;">
<div class="progress-bar bg-success" style="width: <?php echo round(100 * $plan['paid_count'] / $plan['number_of_installments']); ?>%">
<?php echo round(100 * $plan['paid_count'] / $plan['number_of_installments']); ?>%
</div>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php  } ?>
<?php endif; ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
