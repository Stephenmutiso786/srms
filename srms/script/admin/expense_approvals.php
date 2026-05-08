<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
$isSuperAdmin = !empty($super_admin);
if ($res !== "1" || (((int)$level !== 0 && !$isSuperAdmin) || !app_current_user_has_any_permission(['finance.manage']))) { header("location:../"); exit; }

$pendingExpenses = [];
$recentlyReviewed = [];
try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_finance_tables($conn);
    $stmt = $conn->prepare("SELECT e.*, concat_ws(' ', s.fname, s.lname) AS creator_name
        FROM tbl_finance_expenses e
        LEFT JOIN tbl_staff s ON s.id = e.created_by
        WHERE e.status = 'pending_approval'
        ORDER BY e.expense_date DESC, e.id DESC");
    $stmt->execute();
    $pendingExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare("SELECT e.*, concat_ws(' ', s.fname, s.lname) AS creator_name
        FROM tbl_finance_expenses e
        LEFT JOIN tbl_staff s ON s.id = e.created_by
        WHERE e.status IN ('approved', 'rejected')
        ORDER BY e.approved_at DESC, e.id DESC
        LIMIT 15");
    $stmt->execute();
    $recentlyReviewed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $pendingExpenses = [];
    $recentlyReviewed = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Expense Approvals</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Expense Approvals</h1><p>Review and approve accountant-submitted expenses.</p></div></div>
<div class="tile mb-3"><div class="alert alert-info mb-0">Only Headteacher or Super Admin finance leadership can approve or reject these expense submissions.</div></div>
<div class="tile">
<h3 class="tile-title">Pending Approval</h3>
<div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Submitted By</th><th>Reference</th><th>Amount</th><th>Action</th></tr></thead><tbody>
<?php if (!$pendingExpenses): ?><tr><td colspan="7" class="text-muted">No expenses waiting for approval.</td></tr><?php endif; ?>
<?php foreach ($pendingExpenses as $expense): ?>
<tr>
<td><?php echo htmlspecialchars((string)$expense['expense_date']); ?></td>
<td><?php echo htmlspecialchars((string)$expense['category']); ?></td>
<td><?php echo htmlspecialchars((string)$expense['description']); ?></td>
<td><?php echo htmlspecialchars((string)($expense['creator_name'] ?: 'Accountant')); ?></td>
<td><?php echo htmlspecialchars((string)($expense['receipt_reference'] ?? '')); ?></td>
<td><?php echo number_format((float)$expense['amount'], 2); ?></td>
<td>
    <form method="POST" action="admin/core/review_expense" class="d-flex flex-column gap-2">
        <input type="hidden" name="expense_id" value="<?php echo (int)$expense['id']; ?>">
        <textarea class="form-control form-control-sm" name="approval_notes" rows="2" placeholder="Optional notes"></textarea>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-success" type="submit" name="decision" value="approved">Approve</button>
            <button class="btn btn-sm btn-danger" type="submit" name="decision" value="rejected">Reject</button>
        </div>
    </form>
</td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
<div class="tile mt-3">
<h3 class="tile-title">Recently Reviewed</h3>
<div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Status</th><th>Approval Notes</th><th>Amount</th></tr></thead><tbody>
<?php if (!$recentlyReviewed): ?><tr><td colspan="6" class="text-muted">No reviewed expenses yet.</td></tr><?php endif; ?>
<?php foreach ($recentlyReviewed as $expense): ?>
<tr><td><?php echo htmlspecialchars((string)$expense['expense_date']); ?></td><td><?php echo htmlspecialchars((string)$expense['category']); ?></td><td><?php echo htmlspecialchars((string)$expense['description']); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$expense['status'])); ?></td><td><?php echo htmlspecialchars((string)($expense['approval_notes'] ?? '')); ?></td><td><?php echo number_format((float)$expense['amount'], 2); ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body>
</html>
