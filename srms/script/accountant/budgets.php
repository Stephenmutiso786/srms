<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$budgetLines = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$academicYear = trim((string)($_POST['academic_year'] ?? date('Y')));
		$department = trim((string)($_POST['department'] ?? ''));
		$category = trim((string)($_POST['category'] ?? ''));
		$allocatedAmount = (float)($_POST['allocated_amount'] ?? 0);
		$actualAmount = (float)($_POST['actual_amount'] ?? 0);
		if ($academicYear !== '' && $department !== '' && $category !== '' && $allocatedAmount > 0) {
			$stmt = $conn->prepare("INSERT INTO tbl_finance_budget_lines (academic_year, department, category, allocated_amount, actual_amount, created_by) VALUES (?,?,?,?,?,?)");
			$stmt->execute([$academicYear, $department, $category, $allocatedAmount, $actualAmount, (int)$account_id]);
			$_SESSION['reply'] = [[ 'success', 'Budget line saved successfully.' ]];
			header('location:budgets');
			exit;
		}
	}
	$stmt = $conn->prepare("SELECT academic_year, department, category, allocated_amount, actual_amount, status FROM tbl_finance_budget_lines ORDER BY id DESC LIMIT 20");
	$stmt->execute();
	$budgetLines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$budgetLines = [];
}
?>
<!DOCTYPE html>
<html lang="en"><head><title><?php echo APP_NAME; ?> - Budgeting</title><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico"><link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini"><header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header><?php include('accountant/partials/sidebar.php'); ?><main class="app-content">
<div class="app-title"><div><h1>Budgeting</h1><p>Create and monitor departmental budget lines.</p></div></div>
<div class="tile"><h3 class="tile-title">Add Budget Line</h3><form class="row g-3" method="POST" action="accountant/budgets"><div class="col-md-3"><label class="form-label">Academic Year</label><input class="form-control" name="academic_year" value="<?php echo date('Y'); ?>" required></div><div class="col-md-3"><label class="form-label">Department</label><input class="form-control" name="department" placeholder="Finance, ICT, Transport" required></div><div class="col-md-3"><label class="form-label">Category</label><input class="form-control" name="category" placeholder="Operations, Repairs" required></div><div class="col-md-3"><label class="form-label">Allocated Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="allocated_amount" required></div><div class="col-md-3"><label class="form-label">Actual Spent</label><input class="form-control" type="number" step="0.01" min="0" name="actual_amount" value="0"></div><div class="col-md-12 d-grid"><button class="btn btn-primary" type="submit">Save Budget Line</button></div></form></div>
<div class="tile mt-3"><h3 class="tile-title">Budget Lines</h3><div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Year</th><th>Department</th><th>Category</th><th>Allocated</th><th>Actual</th><th>Variance</th><th>Status</th></tr></thead><tbody><?php if (!$budgetLines): ?><tr><td colspan="7" class="text-muted">No budget lines added yet.</td></tr><?php endif; ?><?php foreach ($budgetLines as $line): $variance = (float)$line['allocated_amount'] - (float)$line['actual_amount']; ?><tr><td><?php echo htmlspecialchars((string)$line['academic_year']); ?></td><td><?php echo htmlspecialchars((string)$line['department']); ?></td><td><?php echo htmlspecialchars((string)$line['category']); ?></td><td><?php echo number_format((float)$line['allocated_amount'], 2); ?></td><td><?php echo number_format((float)$line['actual_amount'], 2); ?></td><td><?php echo number_format($variance, 2); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$line['status'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main><script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script></body></html>
