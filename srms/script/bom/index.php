<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res !== '1') { header('location:../'); exit; }
app_require_permission('bom.view', '../');

$visibleModules = app_current_user_visible_portal_modules('bom');
$allocatedModules = app_current_user_allocated_portal_modules('bom');

$profile = [];
$meetings = [];
$approvals = [];
$documents = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_bom_tables($conn);

	$stmt = $conn->prepare('SELECT id, fname, lname, email, gender, level FROM tbl_staff WHERE id = ? LIMIT 1');
	$stmt->execute([(int)$account_id]);
	$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

	$stmt = $conn->prepare('SELECT id, meeting_date, title, agenda, minutes, decisions, status FROM tbl_bom_meetings ORDER BY meeting_date DESC, id DESC LIMIT 50');
	$stmt->execute();
	$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare('SELECT id, approval_date, item_title, amount, status, notes FROM tbl_bom_financial_approvals ORDER BY approval_date DESC, id DESC LIMIT 50');
	$stmt->execute();
	$approvals = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare('SELECT id, title, document_type, file_path, uploaded_at FROM tbl_bom_documents ORDER BY id DESC LIMIT 100');
	$stmt->execute();
	$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to load BOM portal.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - BOM Portal</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.module-launch-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:0 0 18px}
.module-launch-tile{display:flex;flex-direction:column;gap:10px;padding:16px 18px;border:1px solid #dfe9e5;border-radius:20px;background:linear-gradient(180deg,#ffffff,#f7fbfa);box-shadow:0 10px 24px rgba(16,41,38,.05);text-decoration:none;color:#21303a;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.module-launch-tile:hover{transform:translateY(-1px);border-color:#9ecdc3;box-shadow:0 16px 30px rgba(0,105,92,.10)}
.module-launch-top{display:flex;align-items:flex-start;gap:12px}
.module-launch-icon{width:42px;height:42px;border-radius:14px;background:#e7f1ef;color:#00695C;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.module-launch-title{font-weight:800;line-height:1.2}
.module-launch-desc{font-size:.84rem;color:#6f7e8f;line-height:1.5}
.module-launch-cta{align-self:flex-start;margin-top:auto;font-size:.75rem;font-weight:800;color:#00695C;background:#e7f1ef;border-radius:999px;padding:7px 10px}
.module-launch-empty{background:#fff;border:1px dashed #cfe0da;border-radius:18px;padding:14px 16px;color:#667788}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?> BOM</a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user"><div><p class="app-sidebar__user-name"><?php echo htmlspecialchars(trim(($profile['fname'] ?? '') . ' ' . ($profile['lname'] ?? ''))); ?></p><p class="app-sidebar__user-designation"><?php echo htmlspecialchars((string)($designation ?? 'BOM Member')); ?></p></div></div>
<ul class="app-menu">
<?php foreach ($visibleModules as $module): ?>
<li><a class="app-menu__item<?php echo basename((string)$module['href']) === basename($_SERVER['PHP_SELF'], '.php') ? ' active' : ''; ?>" href="<?php echo htmlspecialchars((string)$module['href']); ?>"><i class="app-menu__icon <?php echo htmlspecialchars((string)$module['icon']); ?>"></i><span class="app-menu__label"><?php echo htmlspecialchars((string)$module['label']); ?></span></a></li>
<?php endforeach; ?>
</ul>
</aside>

<main class="app-content">
<div class="app-title"><div><h1>BOM Portal</h1><p>Meetings, approvals, and governance documents.</p></div></div>

<div class="tile mb-3">
<h3 class="tile-title">Quick Module Access</h3>
<div class="module-launch-grid">
<?php if (!empty($allocatedModules)) { ?>
<?php foreach ($allocatedModules as $module): ?>
<a class="module-launch-tile" href="<?php echo htmlspecialchars((string)$module['href']); ?>">
<div class="module-launch-top">
<div class="module-launch-icon"><i class="<?php echo htmlspecialchars((string)$module['icon']); ?>"></i></div>
<div>
<div class="module-launch-title"><?php echo htmlspecialchars((string)$module['label']); ?></div>
<div class="module-launch-desc"><?php echo htmlspecialchars((string)$module['description']); ?></div>
</div>
</div>
<span class="module-launch-cta">Open</span>
</a>
<?php endforeach; ?>
<?php } else { ?>
<div class="module-launch-empty">No additional modules are available yet.</div>
<?php } ?>
</div>
</div>

<div class="row">
<div class="col-md-4"><div class="tile"><h3 class="tile-title">Meetings</h3><h2><?php echo count($meetings); ?></h2></div></div>
<div class="col-md-4"><div class="tile"><h3 class="tile-title">Financial Approvals</h3><h2><?php echo count($approvals); ?></h2></div></div>
<div class="col-md-4"><div class="tile"><h3 class="tile-title">Documents</h3><h2><?php echo count($documents); ?></h2></div></div>
</div>

<div class="tile mb-3">
<h3 class="tile-title">Recent Meetings</h3>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Date</th><th>Title</th><th>Status</th><th>Agenda</th></tr></thead><tbody>
<?php foreach ($meetings as $m): ?>
<tr><td><?php echo htmlspecialchars((string)$m['meeting_date']); ?></td><td><?php echo htmlspecialchars((string)$m['title']); ?></td><td><?php echo htmlspecialchars((string)$m['status']); ?></td><td><?php echo htmlspecialchars((string)$m['agenda']); ?></td></tr>
<?php endforeach; ?>
<?php if (!$meetings): ?><tr><td colspan="4" class="text-center text-muted">No meetings found.</td></tr><?php endif; ?>
</tbody></table></div>
</div>

<div class="tile mb-3">
<h3 class="tile-title">Financial Approvals</h3>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Date</th><th>Item</th><th>Amount</th><th>Status</th><th>Notes</th></tr></thead><tbody>
<?php foreach ($approvals as $a): ?>
<tr><td><?php echo htmlspecialchars((string)$a['approval_date']); ?></td><td><?php echo htmlspecialchars((string)$a['item_title']); ?></td><td><?php echo number_format((float)$a['amount'], 2); ?></td><td><?php echo htmlspecialchars((string)$a['status']); ?></td><td><?php echo htmlspecialchars((string)$a['notes']); ?></td></tr>
<?php endforeach; ?>
<?php if (!$approvals): ?><tr><td colspan="5" class="text-center text-muted">No approvals found.</td></tr><?php endif; ?>
</tbody></table></div>
</div>

<div class="tile">
<h3 class="tile-title">BOM Documents</h3>
<div class="table-responsive"><table class="table table-hover"><thead><tr><th>Title</th><th>Type</th><th>Uploaded</th><th>File</th></tr></thead><tbody>
<?php foreach ($documents as $d): ?>
<tr><td><?php echo htmlspecialchars((string)$d['title']); ?></td><td><?php echo htmlspecialchars((string)$d['document_type']); ?></td><td><?php echo htmlspecialchars((string)$d['uploaded_at']); ?></td><td><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars((string)$d['file_path']); ?>" target="_blank">Open</a></td></tr>
<?php endforeach; ?>
<?php if (!$documents): ?><tr><td colspan="4" class="text-center text-muted">No documents found.</td></tr><?php endif; ?>
</tbody></table></div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
