<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/academic_dashboard.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

$roleNames = [];
$permissionCodes = [];
$visibleModules = [];
$allocatedModules = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$roleNames = app_staff_role_names($conn, (int)$account_id);
	$permissionCodes = app_get_permissions($conn, (string)$account_id, (string)$level);
	$visibleModules = app_portal_visible_modules($conn, 'academic', (string)$account_id, (string)$level);
	$allocatedModules = app_portal_allocated_modules($conn, 'academic', (string)$account_id, (string)$level);
} catch (Throwable $e) {
	$roleNames = [];
	$permissionCodes = [];
	$visibleModules = [];
	$allocatedModules = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<style>
.access-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px;margin:18px 0 18px}
.access-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:16px;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.access-card.roles,.access-card.permissions,.access-card.modules{grid-column:span 4}
.chip-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.access-chip,.module-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;font-size:.82rem;font-weight:700}
.access-chip{background:#e7f1ef;color:#00695C}
.module-chip{background:#eef4fb;color:#27405c}
.module-list{display:grid;gap:10px;margin-top:12px}
.module-link{display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border:1px solid #e7edf5;border-radius:16px;text-decoration:none;color:#203040;background:#fbfdff}
.module-link:hover{border-color:#cfe3db;background:#f4fbf8}
.module-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#e7f1ef;color:#00695C;flex:0 0 auto}
.module-title{font-weight:800;color:#123;line-height:1.2}
.module-desc{font-size:.84rem;color:#6f7e8f;margin-top:2px}
.module-perms{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.module-perms span{font-size:.72rem;background:#eef4fb;color:#4d647d;padding:4px 8px;border-radius:999px}
@media (max-width: 1100px){.access-card.roles,.access-card.permissions,.access-card.modules{grid-column:span 12}}
</style>
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="academic/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('academic/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Dashboard</h1>
</div>

</div>
<div class="access-grid">
	<div class="access-card roles">
		<h3 class="tile-title mb-2">Assigned Roles</h3>
		<div class="small text-muted">Roles attached to this academic account.</div>
		<div class="chip-wrap">
			<?php if (!empty($roleNames)): ?>
				<?php foreach ($roleNames as $roleName): ?>
					<span class="access-chip"><?php echo htmlspecialchars($roleName); ?></span>
				<?php endforeach; ?>
			<?php else: ?>
				<span class="access-chip">Academic</span>
			<?php endif; ?>
		</div>
	</div>
	<div class="access-card permissions">
		<h3 class="tile-title mb-2">Allocated Permissions</h3>
		<div class="small text-muted">Permission codes active in this portal.</div>
		<div class="chip-wrap">
			<?php if (!empty($permissionCodes)): ?>
				<?php foreach ($permissionCodes as $permissionCode): ?>
					<span class="module-chip"><?php echo htmlspecialchars((string)$permissionCode); ?></span>
				<?php endforeach; ?>
			<?php else: ?>
				<span class="module-chip">No extra permissions</span>
			<?php endif; ?>
		</div>
	</div>
	<div class="access-card modules">
		<h3 class="tile-title mb-2">Allocated Modules</h3>
		<div class="small text-muted">Modules unlocked by your permissions.</div>
		<div class="module-list">
			<?php if (!empty($allocatedModules)): ?>
				<?php foreach ($allocatedModules as $module): ?>
					<a class="module-link" href="<?php echo htmlspecialchars((string)$module['href']); ?>">
						<div class="module-icon"><i class="<?php echo htmlspecialchars((string)$module['icon']); ?>"></i></div>
						<div>
							<div class="module-title"><?php echo htmlspecialchars((string)$module['label']); ?></div>
							<div class="module-desc"><?php echo htmlspecialchars((string)$module['description']); ?></div>
							<div class="module-perms">
								<?php foreach ((array)$module['permissions'] as $permission): ?>
									<span><?php echo htmlspecialchars((string)$permission); ?></span>
								<?php endforeach; ?>
							</div>
						</div>
					</a>
				<?php endforeach; ?>
			<?php else: ?>
				<div class="text-muted">No additional modules found yet.</div>
			<?php endif; ?>
		</div>
	</div>
</div>
<div class="row">
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-folder fs-1"></i>
<div class="info">
<h4>Academic Terms</h4>
<p><b><?php echo number_format($academic_terms); ?></b></p>
</div>
</div>
</div>
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-user fs-1"></i>
<div class="info">
<h4>Teachers</h4>
<p><b><?php echo number_format($teachers); ?></b></p>
</div>
</div>
</div>
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-users fs-1"></i>
<div class="info">
<h4>Students</h4>
<p><b><?php echo number_format($my_students); ?></b></p>
</div>
</div>
</div>
<div class="col-md-6 col-lg-3">
<div class="widget-small primary coloured-icon"><i class="icon feather icon-book-open fs-1"></i>
<div class="info">
<h4>Subjects</h4>
<p><b><?php echo number_format($subjects); ?></b></p>
</div>
</div>
</div>



</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>

</body>

</html>
