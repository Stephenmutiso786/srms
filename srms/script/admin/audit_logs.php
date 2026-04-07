<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$actorType = trim((string)($_GET['actor_type'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));
$limit = (int)($_GET['limit'] ?? 200);
if ($limit < 50) $limit = 50;
if ($limit > 500) $limit = 500;

$rows = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_audit_logs')) {
		throw new RuntimeException("Audit logs are not installed. Run migration 001_rbac_attendance.sql.");
	}

	$where = [];
	$params = [];

	if ($actorType !== '') {
		$where[] = "actor_type = ?";
		$params[] = $actorType;
	}
	if ($action !== '') {
		$where[] = "action ILIKE ?";
		$params[] = '%' . $action . '%';
	}
	if ($from !== '') {
		$where[] = "created_at >= ?";
		$params[] = $from . " 00:00:00";
	}
	if ($to !== '') {
		$where[] = "created_at <= ?";
		$params[] = $to . " 23:59:59";
	}

	$sql = "SELECT id, actor_type, actor_id, action, entity, entity_id, ip, created_at
		FROM tbl_audit_logs";
	if (count($where) > 0) {
		$sql .= " WHERE " . implode(" AND ", $where);
	}
	$sql .= " ORDER BY id DESC LIMIT " . $limit;

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Audit Logs</title>
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/audit_logs"><i class="app-menu__icon feather icon-shield"></i><span class="app-menu__label">Audit Logs</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Audit Logs</h1>
<p>Security and activity log (logins, attendance, finance, etc.).</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Filters</h3>
  <form class="row g-3" method="GET" action="admin/audit_logs">
	<div class="col-md-2">
	  <label class="form-label">Actor</label>
	  <select class="form-control" name="actor_type">
		<option value="" <?php echo $actorType === '' ? 'selected' : ''; ?>>(any)</option>
		<option value="staff" <?php echo $actorType === 'staff' ? 'selected' : ''; ?>>Staff</option>
		<option value="student" <?php echo $actorType === 'student' ? 'selected' : ''; ?>>Student</option>
		<option value="parent" <?php echo $actorType === 'parent' ? 'selected' : ''; ?>>Parent</option>
	  </select>
	</div>
	<div class="col-md-4">
	  <label class="form-label">Action contains</label>
	  <input class="form-control" name="action" value="<?php echo htmlspecialchars($action); ?>" placeholder="auth.login">
	</div>
	<div class="col-md-2">
	  <label class="form-label">From</label>
	  <input class="form-control" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
	</div>
	<div class="col-md-2">
	  <label class="form-label">To</label>
	  <input class="form-control" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
	</div>
	<div class="col-md-2 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit">Apply</button>
	</div>
  </form>
</div>

<div class="tile">
  <div class="d-flex justify-content-between align-items-center">
	<h3 class="tile-title mb-0">Latest (<?php echo (int)$limit; ?>)</h3>
	<div class="text-muted">Tip: filter by `auth.login` or `payment.add`</div>
  </div>
  <div class="table-responsive mt-3">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>ID</th>
		  <th>Time</th>
		  <th>Actor</th>
		  <th>Action</th>
		  <th>Entity</th>
		  <th>IP</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($rows) < 1) { ?>
		<tr><td colspan="6" class="text-muted">No logs found.</td></tr>
	  <?php } else { foreach ($rows as $r) { ?>
		<tr>
		  <td><?php echo (int)$r['id']; ?></td>
		  <td><?php echo htmlspecialchars((string)$r['created_at']); ?></td>
		  <td><?php echo htmlspecialchars((string)$r['actor_type'].'#'.$r['actor_id']); ?></td>
		  <td><code><?php echo htmlspecialchars((string)$r['action']); ?></code></td>
		  <td><?php echo htmlspecialchars((string)$r['entity'].($r['entity_id'] !== '' ? '#'.$r['entity_id'] : '')); ?></td>
		  <td><?php echo htmlspecialchars((string)$r['ip']); ?></td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
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

