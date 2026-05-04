<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($level != "5") { header("location:../"); }
app_require_permission('finance.manage', '../finances');
app_require_unlocked('finance', '../finances');

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tab = strtolower(trim((string)($_GET['tab'] ?? 'accounts')));
$tab = in_array($tab, ['accounts', 'entries', 'trial_balance']) ? $tab : 'accounts';

$accounts = [];
$entries = [];
$trial_balance = [];

try {
	if (!app_table_exists($conn, 'tbl_chart_of_accounts')) {
		// Tables not created yet
	} else {
		// Load accounts
		$stmt = $conn->prepare("SELECT id, code, name, type FROM tbl_chart_of_accounts ORDER BY code ASC");
		$stmt->execute();
		$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if ($tab === 'trial_balance') {
			// Load trial balance data
			$stmt = $conn->prepare("SELECT id, code, name, type FROM tbl_chart_of_accounts ORDER BY type, code ASC");
			$stmt->execute();
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $account) {
				$bal = $conn->prepare("SELECT
					COALESCE(SUM(debit), 0) as total_debit,
					COALESCE(SUM(credit), 0) as total_credit
					FROM tbl_gl_entries WHERE account_id = ?")->execute([(int)$account['id']]);
				$sums = $conn->prepare("SELECT
					COALESCE(SUM(debit), 0) as total_debit,
					COALESCE(SUM(credit), 0) as total_credit
					FROM tbl_gl_entries WHERE account_id = ?")->fetch(PDO::FETCH_ASSOC);
				
				$debit = (float)($sums['total_debit'] ?? 0);
				$credit = (float)($sums['total_credit'] ?? 0);
				$trial_balance[] = [
					'id' => (int)$account['id'],
					'code' => (string)$account['code'],
					'name' => (string)$account['name'],
					'type' => (string)$account['type'],
					'debit' => $debit > $credit ? round($debit - $credit, 2) : 0,
					'credit' => $credit > $debit ? round($credit - $debit, 2) : 0,
				];
			}
		}
	}
} catch (Throwable $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . "] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - General Ledger</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
</head>
<body class="app sidebar-mini">

<header class="app-header">
<a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="accountant/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('accountant/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1><i class="bi bi-graph-up me-2"></i>General Ledger</h1>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tabs-wrapper">
<ul class="nav nav-tabs mb-3" role="tablist">
<li class="nav-item"><a class="nav-link<?php echo $tab === 'accounts' ? ' active' : ''; ?>" href="?tab=accounts" role="tab">Chart of Accounts</a></li>
<li class="nav-item"><a class="nav-link<?php echo $tab === 'entries' ? ' active' : ''; ?>" href="?tab=entries" role="tab">Journal Entries</a></li>
<li class="nav-item"><a class="nav-link<?php echo $tab === 'trial_balance' ? ' active' : ''; ?>" href="?tab=trial_balance" role="tab">Trial Balance</a></li>
</ul>

<div class="tab-content">
<?php if ($tab === 'accounts'): ?>
<div class="tab-pane active">
<div class="row mb-3">
<div class="col-md-12">
<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addAccountModal"><i class="bi bi-plus me-2"></i>New Account</button>
</div>
</div>

<div class="table-responsive">
<table class="table table-sm table-hover table-bordered" id="accountsTable">
<thead>
<tr>
<th>Code</th>
<th>Name</th>
<th>Type</th>
<th>Parent</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($accounts as $account): ?>
<tr>
<td><strong><?php echo htmlspecialchars((string)$account['code']); ?></strong></td>
<td><?php echo htmlspecialchars((string)$account['name']); ?></td>
<td><span class="badge bg-info"><?php echo htmlspecialchars((string)$account['type']); ?></span></td>
<td><?php echo isset($account['parent_id']) && $account['parent_id'] > 0 ? htmlspecialchars((string)($account['parent_id'])) : '-'; ?></td>
<td>
<button class="btn btn-sm btn-primary" onclick="editAccount(<?php echo (int)$account['id']; ?>)" title="Edit"><i class="bi bi-pencil"></i></button>
<button class="btn btn-sm btn-danger" onclick="deleteAccount(<?php echo (int)$account['id']; ?>)" title="Delete"><i class="bi bi-trash"></i></button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<?php elseif ($tab === 'entries'): ?>
<div class="tab-pane active">
<div class="row mb-3">
<div class="col-md-12">
<button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#postEntryModal"><i class="bi bi-plus me-2"></i>Post Entry</button>
</div>
</div>

<div class="table-responsive">
<table class="table table-sm table-hover table-bordered" id="entriesTable">
<thead>
<tr>
<th>Date</th>
<th>Account</th>
<th>Description</th>
<th>Debit</th>
<th>Credit</th>
<th>Created By</th>
</tr>
</thead>
<tbody>
<?php
if (!empty($accounts)) {
	$stmt = $conn->prepare("SELECT e.id, e.account_id, e.date, e.description, e.debit, e.credit, e.created_by,
		a.code, a.name, CONCAT(s.fname, ' ', s.lname) as creator
		FROM tbl_gl_entries e
		JOIN tbl_chart_of_accounts a ON a.id = e.account_id
		LEFT JOIN tbl_staff s ON s.id = e.created_by
		ORDER BY e.date DESC, e.id DESC
		LIMIT 100");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $entry) {
		?>
<tr>
<td><?php echo htmlspecialchars((string)$entry['date']); ?></td>
<td><?php echo htmlspecialchars((string)$entry['code'] . ' - ' . $entry['name']); ?></td>
<td><?php echo htmlspecialchars((string)$entry['description']); ?></td>
<td><?php echo number_format((float)$entry['debit'], 2); ?></td>
<td><?php echo number_format((float)$entry['credit'], 2); ?></td>
<td><?php echo htmlspecialchars((string)$entry['creator']); ?></td>
</tr>
		<?php
	}
}
?>
</tbody>
</table>
</div>
</div>

<?php elseif ($tab === 'trial_balance'): ?>
<div class="tab-pane active">
<div class="row mb-3">
<div class="col-md-12">
<button class="btn btn-primary btn-sm" onclick="location.reload()"><i class="bi bi-arrow-clockwise me-2"></i>Refresh</button>
<button class="btn btn-secondary btn-sm" onclick="window.print()"><i class="bi bi-printer me-2"></i>Print</button>
</div>
</div>

<div class="table-responsive">
<table class="table table-sm table-hover table-bordered">
<thead>
<tr>
<th>Account Code</th>
<th>Account Name</th>
<th>Type</th>
<th class="text-end">Debit</th>
<th class="text-end">Credit</th>
</tr>
</thead>
<tbody>
<?php
$total_debit = 0;
$total_credit = 0;
$current_type = '';
foreach ($trial_balance as $acct):
	if ($current_type !== $acct['type']):
		if ($current_type !== ''):
			?>
<tr class="table-secondary">
<td colspan="2"><strong><?php echo htmlspecialchars(ucfirst($current_type)); ?> Total</strong></td>
<td></td>
<td class="text-end"><strong>--</strong></td>
<td class="text-end"><strong>--</strong></td>
</tr>
			<?php
		endif;
		$current_type = $acct['type'];
	endif;
	$total_debit += (float)$acct['debit'];
	$total_credit += (float)$acct['credit'];
	?>
<tr>
<td><?php echo htmlspecialchars((string)$acct['code']); ?></td>
<td><?php echo htmlspecialchars((string)$acct['name']); ?></td>
<td><?php echo htmlspecialchars((string)$acct['type']); ?></td>
<td class="text-end"><?php echo number_format((float)$acct['debit'], 2); ?></td>
<td class="text-end"><?php echo number_format((float)$acct['credit'], 2); ?></td>
</tr>
<?php endforeach; ?>
<tr class="table-secondary">
<td colspan="2"><strong>Final Total</strong></td>
<td></td>
<td class="text-end"><strong><?php echo number_format($total_debit, 2); ?></strong></td>
<td class="text-end"><strong><?php echo number_format($total_credit, 2); ?></strong></td>
</tr>
<tr class="<?php echo abs($total_debit - $total_credit) < 0.01 ? 'table-success' : 'table-danger'; ?>">
<td colspan="3"><strong><?php echo abs($total_debit - $total_credit) < 0.01 ? '✓ Trial Balance Balanced' : '✗ Trial Balance Out of Balance'; ?></strong></td>
<td class="text-end">Difference: <?php echo number_format(abs($total_debit - $total_credit), 2); ?></td>
<td></td>
</tr>
</tbody>
</table>
</div>
</div>
<?php endif; ?>

</div>
</div>
</div>
</div>

</main>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">New Account</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="post" onsubmit="return postToAPI(event, 'api/accounting_api.php?action=create_account')">
<div class="modal-body">
<div class="form-group mb-3">
<label>Account Code</label>
<input type="text" class="form-control" name="code" required placeholder="e.g., 1000">
</div>
<div class="form-group mb-3">
<label>Account Name</label>
<input type="text" class="form-control" name="name" required placeholder="e.g., Cash">
</div>
<div class="form-group mb-3">
<label>Account Type</label>
<select class="form-control select2" name="type" required>
<option value="">-- Select Type --</option>
<option value="asset">Asset</option>
<option value="liability">Liability</option>
<option value="equity">Equity</option>
<option value="income">Income</option>
<option value="expense">Expense</option>
</select>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary">Create</button>
</div>
</form>
</div>
</div>
</div>

<!-- Post Entry Modal -->
<div class="modal fade" id="postEntryModal">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title">Post Journal Entry</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="post" onsubmit="return postToAPI(event, 'api/accounting_api.php?action=post_entry')">
<div class="modal-body">
<div class="form-group mb-3">
<label>Account</label>
<select class="form-control select2" name="account_id" required>
<option value="">-- Select Account --</option>
<?php foreach ($accounts as $acct): ?>
<option value="<?php echo (int)$acct['id']; ?>"><?php echo htmlspecialchars($acct['code'] . ' - ' . $acct['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="form-group mb-3">
<label>Date</label>
<input type="date" class="form-control" name="date" value="<?php echo date('Y-m-d'); ?>" required>
</div>
<div class="form-group mb-3">
<label>Description</label>
<input type="text" class="form-control" name="description" placeholder="Optional description">
</div>
<div class="row">
<div class="col-md-6">
<div class="form-group mb-3">
<label>Debit (Dr)</label>
<input type="number" step="0.01" class="form-control" name="debit" min="0" placeholder="0.00">
</div>
</div>
<div class="col-md-6">
<div class="form-group mb-3">
<label>Credit (Cr)</label>
<input type="number" step="0.01" class="form-control" name="credit" min="0" placeholder="0.00">
</div>
</div>
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" class="btn btn-primary">Post Entry</button>
</div>
</form>
</div>
</div>
</div>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<script src="js/sweetalert2@11.js"></script>

<script>
$('.select2').select2();
$('#accountsTable').DataTable({"sort": false});
$('#entriesTable').DataTable({"sort": false});

function postToAPI(e, url) {
	e.preventDefault();
	const formData = new FormData(e.target);
	const data = Object.fromEntries(formData);

	fetch(url, {
		method: 'POST',
		headers: {'Content-Type': 'application/json'},
		body: JSON.stringify(data)
	})
	.then(r => r.json())
	.then(result => {
		if (result.success) {
			Swal.fire({text: result.message || 'Operation successful', icon: 'success'}).then(() => location.reload());
		} else {
			Swal.fire({text: result.error || 'Operation failed', icon: 'error'});
		}
	})
	.catch(err => Swal.fire({text: err.message, icon: 'error'}));
	return false;
}

function deleteAccount(id) {
	Swal.fire({
		title: 'Delete Account?',
		text: 'This action cannot be undone.',
		icon: 'warning',
		showCancelButton: true,
		confirmButtonText: 'Delete'
	}).then(r => {
		if (r.isConfirmed) {
			fetch(`api/accounting_api.php?action=delete_account&id=${id}`, {method: 'DELETE'})
			.then(r => r.json())
			.then(result => {
				Swal.fire({text: result.message || result.error, icon: result.success ? 'success' : 'error'}).then(() => location.reload());
			});
		}
	});
}

function editAccount(id) {
	Swal.fire({text: 'Edit functionality coming soon', icon: 'info'});
}
</script>
</body>
</html>
