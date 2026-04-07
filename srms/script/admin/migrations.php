<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('system.manage', 'admin');

$migrations = [];
$applied = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$dir = dirname(__DIR__).'/database/pg_migrations';
	$files = glob($dir.'/*.sql') ?: [];
	sort($files, SORT_NATURAL);
	foreach ($files as $file) {
		$migrations[] = basename($file);
	}

	if (app_table_exists($conn, 'tbl_schema_migrations')) {
		$stmt = $conn->prepare("SELECT name FROM tbl_schema_migrations");
		$stmt->execute();
		$applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title>Migrations - Elimu Hub</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);">Elimu Hub</a>
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
<li><a class="app-menu__item active" href="admin/migrations"><i class="app-menu__icon feather icon-database"></i><span class="app-menu__label">Migrations</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Database Migrations</h1>
<p>Apply missing migrations to install modules.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="tile">
  <div class="d-flex justify-content-between align-items-center">
	<div>
	  <strong>Driver:</strong> <?php echo htmlspecialchars((string)DBDriver); ?>
	  <?php if (DBDriver !== 'pgsql') { ?>
		<div class="text-muted">Migrations are built for Postgres. For MySQL, import the clean schema and re-load the app.</div>
	  <?php } ?>
	</div>
	<form action="admin/core/run_migrations" method="POST">
	  <button class="btn btn-primary" <?php echo DBDriver !== 'pgsql' ? 'disabled' : ''; ?>>Apply All Migrations</button>
	</form>
  </div>
  <hr>
  <div class="table-responsive">
	<table class="table table-hover">
	  <thead><tr><th>Migration</th><th>Status</th></tr></thead>
	  <tbody>
	  <?php foreach ($migrations as $m): ?>
		<tr>
		  <td><?php echo htmlspecialchars($m); ?></td>
		  <td><?php echo in_array($m, $applied, true) ? '<span class="badge bg-success">Applied</span>' : '<span class="badge bg-warning text-dark">Pending</span>'; ?></td>
		</tr>
	  <?php endforeach; ?>
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
