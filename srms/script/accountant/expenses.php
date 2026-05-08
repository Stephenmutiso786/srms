<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}

$summary = ['month_total' => 0, 'count' => 0, 'pending' => 0];
$expenses = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$expenseDate = trim((string)($_POST['expense_date'] ?? date('Y-m-d')));
		$category = trim((string)($_POST['category'] ?? ''));
		$description = trim((string)($_POST['description'] ?? ''));
		$amount = (float)($_POST['amount'] ?? 0);
		$reference = trim((string)($_POST['receipt_reference'] ?? ''));
		if ($category !== '' && $description !== '' && $amount > 0) {
			$stmt = $conn->prepare("INSERT INTO tbl_finance_expenses (expense_date, category, description, amount, status, receipt_reference, created_by) VALUES (?,?,?,?,?,?,?)");
			$stmt->execute([$expenseDate, $category, $description, $amount, 'pending_approval', $reference !== '' ? $reference : null, (int)$account_id]);
			$_SESSION['reply'] = [[ 'success', 'Expense saved successfully.' ]];
			header('location:expenses');
			exit;
		}
		$_SESSION['reply'] = [[ 'danger', 'Category, description, and amount are required.' ]];
		header('location:expenses');
		exit;
	}

	$stmt = $conn->prepare("SELECT COUNT(*) AS expense_count, COALESCE(SUM(amount),0) AS total_amount, COALESCE(SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END),0) AS pending_count FROM tbl_finance_expenses WHERE DATE_FORMAT(expense_date, '%Y-%m') = ?");
	try {
		$stmt->execute([date('Y-m')]);
	} catch (Throwable $mysqlDateError) {
		$stmt = $conn->prepare("SELECT COUNT(*) AS expense_count, COALESCE(SUM(amount),0) AS total_amount FROM tbl_finance_expenses WHERE TO_CHAR(expense_date, 'YYYY-MM') = ?");
		$stmt->execute([date('Y-m')]);
	}
	$row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
	$summary['count'] = (int)($row['expense_count'] ?? 0);
	$summary['pending'] = (int)($row['pending_count'] ?? 0);
	$summary['month_total'] = (float)($row['total_amount'] ?? 0);

	$stmt = $conn->prepare("SELECT expense_date, category, description, amount, receipt_reference, status, approval_notes, approved_at FROM tbl_finance_expenses ORDER BY expense_date DESC, id DESC LIMIT 20");
	$stmt->execute();
	$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$expenses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Expenses</title>
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
<?php include('accountant/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Expenses</h1><p>Record and review school operating expenses.</p></div></div>
<div class="row">
<div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-shopping-cart fs-1"></i><div class="info"><h4>This Month</h4><p><b><?php echo number_format($summary['month_total'], 2); ?></b></p></div></div></div>
<div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-file-text fs-1"></i><div class="info"><h4>Entries</h4><p><b><?php echo number_format($summary['count']); ?></b></p></div></div></div>
<div class="col-md-4"><div class="widget-small primary coloured-icon"><i class="icon feather icon-clock fs-1"></i><div class="info"><h4>Pending Approval</h4><p><b><?php echo number_format($summary['pending']); ?></b></p></div></div></div>
</div>
<div class="tile mt-3">
<h3 class="tile-title">Add Expense</h3>
<form class="row g-3" method="POST" action="accountant/expenses">
<div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" required></div>
<div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="category" placeholder="Electricity, Food, Repairs" required></div>
<div class="col-md-3"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div>
<div class="col-md-3"><label class="form-label">Receipt / Invoice Ref</label><input class="form-control" name="receipt_reference" placeholder="Optional reference"></div>
<div class="col-md-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" placeholder="What was this expense for?" required></textarea></div>
<div class="col-md-12 d-grid"><button class="btn btn-primary" type="submit">Submit For Approval</button></div>
</form>
</div>
<div class="tile mt-3"><div class="alert alert-info mb-0">Expense workflow: the accountant records the expense first, then the Super Admin approves or rejects it from the finance approval queue before it is treated as fully approved.</div></div>
<div class="tile">
<h3 class="tile-title">Recent Expenses</h3>
<div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Reference</th><th>Status</th><th>Approval Notes</th><th>Amount</th></tr></thead><tbody>
<?php if (!$expenses): ?><tr><td colspan="7" class="text-muted">No expenses recorded yet.</td></tr><?php endif; ?>
<?php foreach ($expenses as $expense): ?><tr><td><?php echo htmlspecialchars((string)$expense['expense_date']); ?></td><td><?php echo htmlspecialchars((string)$expense['category']); ?></td><td><?php echo htmlspecialchars((string)$expense['description']); ?></td><td><?php echo htmlspecialchars((string)($expense['receipt_reference'] ?? '')); ?></td><td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', (string)$expense['status']))); ?></td><td><?php echo htmlspecialchars((string)($expense['approval_notes'] ?? '')); ?><?php if (!empty($expense['approved_at'])): ?><div class="small text-muted"><?php echo htmlspecialchars((string)$expense['approved_at']); ?></div><?php endif; ?></td><td><?php echo number_format((float)$expense['amount'], 2); ?></td></tr><?php endforeach; ?>
</tbody></table></div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body>
</html>
