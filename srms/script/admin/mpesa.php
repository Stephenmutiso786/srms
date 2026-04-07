<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}
app_require_permission('finance.manage', 'admin');
app_require_unlocked('finance', 'admin');

$cfg = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	require_once('const/mpesa.php');

	if (!app_table_exists($conn, 'tbl_payment_settings')) {
		throw new RuntimeException("M-Pesa settings table missing. Run migration 006_mpesa_stk.sql.");
	}
	$cfg = mpesa_config($conn);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - M-Pesa</title>
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
<h1>M-Pesa STK Push</h1>
<p>Configure API credentials and callback URL.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Settings</h3>
  <form class="row g-3" method="POST" action="admin/core/update_mpesa_settings" autocomplete="off">
	<div class="col-md-3">
	  <label class="form-label">Enabled</label>
	  <select class="form-control" name="enabled" required>
		<option value="0" <?php echo ((int)$cfg['enabled'] === 0) ? 'selected' : ''; ?>>Disabled</option>
		<option value="1" <?php echo ((int)$cfg['enabled'] === 1) ? 'selected' : ''; ?>>Enabled</option>
	  </select>
	</div>
	<div class="col-md-3">
	  <label class="form-label">Environment</label>
	  <select class="form-control" name="environment" required>
		<option value="sandbox" <?php echo ($cfg['environment'] === 'sandbox') ? 'selected' : ''; ?>>Sandbox</option>
		<option value="live" <?php echo ($cfg['environment'] === 'live') ? 'selected' : ''; ?>>Live</option>
	  </select>
	</div>
	<div class="col-md-3">
	  <label class="form-label">Shortcode</label>
	  <input class="form-control" name="shortcode" value="<?php echo htmlspecialchars((string)$cfg['shortcode']); ?>" placeholder="174379">
	</div>
	<div class="col-md-3">
	  <label class="form-label">Passkey</label>
	  <input class="form-control" name="passkey" type="password" value="<?php echo htmlspecialchars((string)$cfg['passkey']); ?>">
	</div>
	<div class="col-md-6">
	  <label class="form-label">Consumer Key</label>
	  <input class="form-control" name="consumer_key" type="password" value="<?php echo htmlspecialchars((string)$cfg['consumer_key']); ?>">
	</div>
	<div class="col-md-6">
	  <label class="form-label">Consumer Secret</label>
	  <input class="form-control" name="consumer_secret" type="password" value="<?php echo htmlspecialchars((string)$cfg['consumer_secret']); ?>">
	</div>
	<div class="col-md-8">
	  <label class="form-label">Callback URL</label>
	  <input class="form-control" name="callback_url" value="<?php echo htmlspecialchars((string)$cfg['callback_url']); ?>" placeholder="https://YOUR-RENDER.onrender.com/api/mpesa_callback">
	</div>
	<div class="col-md-4 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save</button>
	</div>
  </form>
  <p class="text-muted mt-2 mb-0">Recommended: store secrets as Render environment variables instead of DB. DB settings are optional fallback.</p>
</div>

<div class="tile">
  <h3 class="tile-title">Next</h3>
  <ol class="mb-0">
	<li>Set callback URL to `https://YOUR-SERVICE.onrender.com/api/mpesa_callback`</li>
	<li>Enable M-Pesa, then use `Admin → Invoices → STK Push`</li>
  </ol>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
