<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || $level !== '0') {
	header('location:../');
	exit;
}
app_require_permission('system.manage', 'admin');

function app_find_migrations_dir_for_diagnostics(): ?string
{
	$candidates = [
		dirname(__DIR__, 2).'/database/pg_migrations',
		dirname(__DIR__, 3).'/database/pg_migrations',
		dirname(__DIR__, 4).'/database/pg_migrations',
		getcwd().'/database/pg_migrations',
		getcwd().'/srms/database/pg_migrations',
	];

	foreach ($candidates as $dir) {
		if (is_dir($dir) && count(glob($dir.'/*.sql') ?: []) > 0) {
			return $dir;
		}
	}

	foreach ($candidates as $dir) {
		if (is_dir($dir)) {
			return $dir;
		}
	}

	return null;
}

function app_diagnostics_item(string $label, string $status, string $message, array $details = []): array
{
	return [
		'label' => $label,
		'status' => $status,
		'message' => $message,
		'details' => $details,
	];
}

function app_diagnostics_writable(string $label, string $path): array
{
	if (!file_exists($path)) {
		return app_diagnostics_item($label, 'warning', 'Path does not exist: '.$path, ['path' => $path]);
	}

	if (is_writable($path)) {
		return app_diagnostics_item($label, 'pass', 'Writable', ['path' => $path]);
	}

	return app_diagnostics_item($label, 'fail', 'Not writable', ['path' => $path]);
}

function app_diagnostics_check_table(PDO $conn, string $table): array
{
	if (app_table_exists($conn, $table)) {
		return app_diagnostics_item('Table: '.$table, 'pass', 'Present');
	}

	return app_diagnostics_item('Table: '.$table, 'fail', 'Missing');
}

function app_diagnostics_check_extension(string $extension): array
{
	if (extension_loaded($extension)) {
		return app_diagnostics_item('Extension: '.$extension, 'pass', 'Loaded');
	}

	return app_diagnostics_item('Extension: '.$extension, 'fail', 'Not loaded');
}

function app_diagnostics_check_file_size(string $path): array
{
	if (!is_file($path)) {
		return app_diagnostics_item('File', 'warning', 'Not found: '.$path, ['path' => $path]);
	}

	$size = filesize($path);
	return app_diagnostics_item('File', 'pass', 'Readable file detected', ['path' => $path, 'size' => $size]);
}

$results = [];
$summary = [
	'pass' => 0,
	'warning' => 0,
	'fail' => 0,
];
$lastRunAt = null;
$pendingMigrations = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$lastRunAt = date('Y-m-d H:i:s');
		$results[] = app_diagnostics_item('Application', 'pass', APP_NAME.' is responding');
		$results[] = app_diagnostics_item('PHP Version', version_compare(PHP_VERSION, '8.0.0', '>=') ? 'pass' : 'warning', PHP_VERSION);
		$results[] = app_diagnostics_item('Database Driver', DBDriver === 'pgsql' ? 'pass' : 'warning', DBDriver);
		$results[] = app_diagnostics_check_extension('pdo');
		$results[] = app_diagnostics_check_extension('json');
		$results[] = app_diagnostics_check_extension('mbstring');
		$results[] = app_diagnostics_check_extension('curl');
		$results[] = app_diagnostics_check_extension(DBDriver === 'pgsql' ? 'pdo_pgsql' : 'pdo_mysql');

		try {
			$conn->query('SELECT 1');
			$results[] = app_diagnostics_item('Database Connection', 'pass', 'Connection successful');
		} catch (Throwable $e) {
			$results[] = app_diagnostics_item('Database Connection', 'fail', 'Connection failed: '.$e->getMessage());
		}

		$requiredTables = [
			'tbl_school',
			'tbl_staff',
			'tbl_students',
			'tbl_classes',
			'tbl_terms',
			'tbl_invoices',
			'tbl_payments',
			'tbl_parent_students',
			'tbl_schema_migrations',
		];

		foreach ($requiredTables as $table) {
			$results[] = app_diagnostics_check_table($conn, $table);
		}

		$migrationsDir = app_find_migrations_dir_for_diagnostics();
		if ($migrationsDir) {
			$results[] = app_diagnostics_item('Migrations Folder', 'pass', 'Found: '.$migrationsDir);
			$migrationFiles = glob($migrationsDir.'/*.sql') ?: [];
			sort($migrationFiles, SORT_NATURAL);
			if (app_table_exists($conn, 'tbl_schema_migrations')) {
				$stmt = $conn->prepare('SELECT name FROM tbl_schema_migrations');
				$stmt->execute();
				$applied = $stmt->fetchAll(PDO::FETCH_COLUMN);
				$appliedMap = array_fill_keys($applied, true);
				foreach ($migrationFiles as $file) {
					$name = basename($file);
					if (!isset($appliedMap[$name])) {
						$pendingMigrations[] = $name;
					}
				}
				if (count($pendingMigrations) > 0) {
					$results[] = app_diagnostics_item('Migration Status', 'warning', count($pendingMigrations).' pending migration(s) found', ['pending' => $pendingMigrations]);
				} else {
					$results[] = app_diagnostics_item('Migration Status', 'pass', 'All detected migrations are applied');
				}
			} else {
				$results[] = app_diagnostics_item('Migration Status', 'warning', 'Migration tracking table is missing');
			}
		} else {
			$results[] = app_diagnostics_item('Migrations Folder', 'warning', 'No migration folder detected');
		}

		$writablePaths = [
			'dir_uploads' => dirname(__DIR__, 2).'/uploads',
			'dir_uploads_elearning' => dirname(__DIR__, 2).'/uploads/elearning',
			'dir_images_logo' => dirname(__DIR__).'/images/logo',
			'dir_images_signatures' => dirname(__DIR__).'/images/signatures',
		];

		foreach ($writablePaths as $label => $path) {
			$results[] = app_diagnostics_writable(ucwords(str_replace('_', ' ', $label)), $path);
		}

		$freeSpace = @disk_free_space(dirname(__DIR__, 2));
		if ($freeSpace === false) {
			$results[] = app_diagnostics_item('Disk Space', 'warning', 'Unable to detect disk space');
		} elseif ($freeSpace < 1024 * 1024 * 1024) {
			$results[] = app_diagnostics_item('Disk Space', 'warning', 'Less than 1 GB free', ['free_bytes' => $freeSpace]);
		} else {
			$results[] = app_diagnostics_item('Disk Space', 'pass', 'Sufficient free space', ['free_bytes' => $freeSpace]);
		}

		$summary = ['pass' => 0, 'warning' => 0, 'fail' => 0];
		foreach ($results as $result) {
			if (isset($summary[$result['status']])) {
				$summary[$result['status']]++;
			}
		}
	}
} catch (Throwable $e) {
	$results[] = app_diagnostics_item('Diagnostics', 'fail', 'System diagnostics failed to load: '.$e->getMessage());
	$summary = ['pass' => 0, 'warning' => 0, 'fail' => 1];
}

function app_diag_badge_class(string $status): string
{
	switch ($status) {
		case 'pass': return 'bg-success';
		case 'warning': return 'bg-warning text-dark';
		default: return 'bg-danger';
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - System Diagnostics</title>
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
<h1>System Diagnostics</h1>
<p>Run a live check of the application, database, migrations, PHP extensions, and writable paths.</p>
</div>
</div>

<div class="tile">
	<div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
		<div>
			<div class="text-muted">Click the button to run the check now.</div>
			<?php if ($lastRunAt !== null) { ?>
				<div class="small mt-1">Last run: <?php echo htmlspecialchars($lastRunAt); ?></div>
			<?php } ?>
		</div>
		<form method="POST">
			<button class="btn btn-primary" type="submit"><i class="bi bi-play-circle me-2"></i>Run System Check</button>
		</form>
	</div>
</div>

<div class="dashboard-stats mb-4">
	<div class="stat-card">
		<div>
			<div class="stat-label">Passed</div>
			<div class="stat-value"><?php echo (int)$summary['pass']; ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-check-circle"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Warnings</div>
			<div class="stat-value"><?php echo (int)$summary['warning']; ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-exclamation-triangle"></i></div>
	</div>
	<div class="stat-card">
		<div>
			<div class="stat-label">Failures</div>
			<div class="stat-value"><?php echo (int)$summary['fail']; ?></div>
		</div>
		<div class="stat-icon"><i class="bi bi-x-circle"></i></div>
	</div>
</div>

<div class="tile">
	<h3 class="tile-title">Check Results</h3>
	<div class="table-responsive">
		<table class="table table-hover align-middle">
			<thead>
				<tr>
					<th>Check</th>
					<th>Status</th>
					<th>Message</th>
				</tr>
			</thead>
			<tbody>
				<?php if (count($results) === 0) { ?>
					<tr>
						<td colspan="3" class="text-muted">No check has been run yet. Click Run System Check to start.</td>
					</tr>
				<?php } ?>
				<?php foreach ($results as $result): ?>
					<tr>
						<td><?php echo htmlspecialchars($result['label']); ?></td>
						<td><span class="badge <?php echo app_diag_badge_class($result['status']); ?>"><?php echo strtoupper(htmlspecialchars($result['status'])); ?></span></td>
						<td>
							<?php echo htmlspecialchars($result['message']); ?>
							<?php if (!empty($result['details']['pending']) && is_array($result['details']['pending'])) { ?>
								<div class="small text-muted mt-1">
									Pending: <?php echo htmlspecialchars(implode(', ', $result['details']['pending'])); ?>
								</div>
							<?php } ?>
							<?php if (!empty($result['details']['path'])) { ?>
								<div class="small text-muted mt-1">Path: <?php echo htmlspecialchars($result['details']['path']); ?></div>
							<?php } ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
