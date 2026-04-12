<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}
app_require_permission('sms.wallet.manage', 'admin');
app_require_unlocked('communication', 'admin');

$wallet = ['wallet_name' => 'School SMS Wallet', 'phone_number' => '', 'balance_tokens' => 0, 'status' => 1];
$topups = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_sms_wallet_tables($conn);
	app_ensure_school_roles($conn);

	$stmt = $conn->prepare('SELECT wallet_name, phone_number, balance_tokens, status FROM tbl_sms_wallets WHERE id = 1 LIMIT 1');
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		$wallet = $row;
	}

	if (app_table_exists($conn, 'tbl_sms_topup_requests')) {
		$stmt = $conn->prepare("SELECT phone, tokens, amount, status, customer_message, created_at, updated_at FROM tbl_sms_topup_requests ORDER BY id DESC LIMIT 10");
		$stmt->execute();
		$topups = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$error = 'Unable to load SMS wallet.';
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - SMS Tokens</title>
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
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('admin/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Buy SMS Tokens</h1>
<p>Top up the school SMS wallet with M-Pesa STK push.</p>
</div>
<div>
  <a class="btn btn-outline-secondary" href="admin/communication"><i class="bi bi-arrow-left me-1"></i>Back to Communication</a>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="row">
  <div class="col-lg-5">
    <div class="tile mb-3">
      <h3 class="tile-title">Wallet Status</h3>
      <div class="row g-3">
        <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">Wallet</div><div class="fw-bold"><?php echo htmlspecialchars((string)$wallet['wallet_name']); ?></div></div></div>
        <div class="col-6"><div class="border rounded p-3"><div class="text-muted small">Balance</div><div class="fw-bold"><?php echo number_format((int)$wallet['balance_tokens']); ?> tokens</div></div></div>
        <div class="col-12"><div class="border rounded p-3"><div class="text-muted small">Default Payment Rule</div><div class="fw-bold">1 SMS token = 1 SMS segment</div></div></div>
      </div>
    </div>

    <div class="tile">
      <h3 class="tile-title">Purchase Tokens</h3>
      <form class="row g-3" method="POST" action="admin/core/mpesa_sms_topup" autocomplete="off">
        <div class="col-md-12">
          <label class="form-label">Phone Number</label>
          <input class="form-control" name="phone" placeholder="2547XXXXXXXX" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tokens</label>
          <input class="form-control" type="number" min="1" step="1" name="tokens" value="100" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Rate per Token</label>
          <input class="form-control" value="1" disabled>
        </div>
        <div class="col-12 d-grid">
          <button class="btn btn-primary" type="submit"><i class="bi bi-phone-vibrate me-1"></i>Buy Tokens</button>
        </div>
        <p class="text-muted mb-0">M-Pesa will prompt the PIN on the phone you enter here. The system never collects or stores the PIN.</p>
      </form>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="tile">
      <h3 class="tile-title">Recent Token Purchases</h3>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead><tr><th>Phone</th><th>Tokens</th><th>Amount</th><th>Status</th><th>Updated</th></tr></thead>
          <tbody>
            <?php if (count($topups) === 0) { ?>
              <tr><td colspan="5" class="text-muted">No SMS top-ups yet.</td></tr>
            <?php } else { foreach ($topups as $topup) { ?>
              <tr>
                <td><?php echo htmlspecialchars((string)$topup['phone']); ?></td>
                <td><?php echo number_format((int)$topup['tokens']); ?></td>
                <td><?php echo number_format((float)$topup['amount'], 2); ?></td>
                <td><?php echo htmlspecialchars((string)$topup['status']); ?></td>
                <td><?php echo htmlspecialchars((string)($topup['updated_at'] ?: $topup['created_at'])); ?></td>
              </tr>
            <?php } } ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
