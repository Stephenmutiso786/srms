<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');

if ($res == "1" && $level == "2") {} else { header("location:../"); exit; }
app_require_permission('communication.send', 'teacher');
app_require_unlocked('communication', 'teacher');

$classes = [];
$wallet = ['balance_tokens' => 0];
$smsSettings = ['provider' => 'ots', 'api_url' => '', 'api_key' => '', 'sender_id' => '', 'status' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_sms_wallet_tables($conn);
	app_ensure_sms_settings_table($conn);

	$classIds = app_staff_class_teacher_ids($conn, (int)$account_id);
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1");
		$stmt->execute([(int)$account_id]);
		$classIds = array_merge($classIds, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
	}
	$classIds = array_values(array_unique(array_filter($classIds)));
	if (!empty($classIds) && app_table_exists($conn, 'tbl_classes')) {
		$placeholders = implode(',', array_fill(0, count($classIds), '?'));
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
		$stmt->execute($classIds);
		$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare('SELECT balance_tokens FROM tbl_sms_wallets WHERE id = 1 LIMIT 1');
	$stmt->execute();
	$walletRow = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($walletRow) {
		$wallet = $walletRow;
	}

	$settings = app_get_sms_settings($conn);
	if ($settings) {
		$smsSettings = $settings;
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$error = 'Unable to load SMS portal.';
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Teacher SMS</title>
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
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>
<?php include('teacher/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Teacher SMS</h1>
<p>Send SMS to your assigned classes or a direct contact.</p>
</div>
<div><a class="btn btn-primary" href="teacher/sms_topup"><i class="bi bi-wallet2 me-1"></i>Buy SMS Tokens</a></div>
</div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } ?>

<div class="row">
<div class="col-lg-4">
<div class="tile mb-3">
<h3 class="tile-title">SMS Wallet</h3>
<div class="border rounded p-3 mb-2"><div class="text-muted small">Available Balance</div><div class="fw-bold fs-4"><?php echo number_format((int)$wallet['balance_tokens']); ?> tokens</div></div>
<div class="border rounded p-3"><div class="text-muted small">Gateway</div><div class="fw-bold"><?php echo htmlspecialchars((string)$smsSettings['provider']); ?> <?php echo ((int)$smsSettings['status'] === 1) ? '<span class="badge bg-success">Enabled</span>' : '<span class="badge bg-warning text-dark">Disabled</span>'; ?></div></div>
</div>
</div>
<div class="col-lg-8">
<div class="tile">
<h3 class="tile-title">Send SMS</h3>
<form class="row g-3 app_frm" method="POST" action="teacher/core/send_sms">
<div class="col-md-6">
<label class="form-label">Target</label>
<select class="form-control" name="target_type" id="target_type">
<option value="">Direct phone number</option>
<option value="class_students">Students in my class</option>
<option value="class_parents">Parents in my class</option>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Class</label>
<select class="form-control" name="class_id">
<option value="">Select class for class SMS</option>
<?php foreach ($classes as $classRow): ?>
<option value="<?php echo (int)$classRow['id']; ?>"><?php echo htmlspecialchars((string)$classRow['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-12">
<label class="form-label">Direct Phone</label>
<input class="form-control" name="recipient" placeholder="2547XXXXXXXX">
<small class="form-text text-muted">Use direct phone for one-off messages. Class messages only send to your assigned classes.</small>
</div>
<div class="col-md-12">
<label class="form-label">Message</label>
<textarea class="form-control" name="message" rows="5" maxlength="918" required></textarea>
</div>
<div class="col-md-12 d-grid">
<button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Send SMS</button>
</div>
</form>
<?php if (empty($classes)) { ?>
<p class="text-muted small mt-3 mb-0">No class is currently allocated to this teacher, so only direct SMS is available.</p>
<?php } ?>
</div>
</div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
