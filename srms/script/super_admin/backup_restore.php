<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

$_appBaseUrl = app_base_url();
$superAdminBase = $_appBaseUrl !== '' ? $_appBaseUrl . '/super_admin' : '';
$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (function_exists('app_ensure_school_subscription_schema')) {
  app_ensure_school_subscription_schema($conn);
}
$schools = [];
$owners = [];
try {
	if (app_table_exists($conn, 'tbl_school')) {
		$stmt = $conn->query('SELECT id, name, logo, result_system, allow_results FROM tbl_school ORDER BY id ASC');
		$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (app_table_exists($conn, 'tbl_staff')) {
		$stmt = $conn->query("SELECT id, fname, lname, email, status FROM tbl_staff WHERE level = 9 ORDER BY id ASC");
		$owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	// keep page usable
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Backup & Restore</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body class="app sidebar-mini">
<main class="app-content">
<h1>Backup & Restore</h1>
<div class="row">
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Export</strong></div>
      <div class="card-body">
        <p>Download the current school registry and owner accounts as JSON.</p>
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(($superAdminBase !== '' ? $superAdminBase : '') . '/core/export_platform_state.php'); ?>">Download Backup</a>
      </div>
    </div>
  </div>
  <div class="col-md-6">
    <div class="card">
      <div class="card-header"><strong>Restore</strong></div>
      <div class="card-body">
        <form action="<?php echo htmlspecialchars(($superAdminBase !== '' ? $superAdminBase : '') . '/core/import_platform_state.php'); ?>" method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label">Backup JSON</label>
            <input class="form-control" type="file" name="backup_file" accept=".json,application/json" required>
          </div>
          <button class="btn btn-danger" type="submit" onclick="return confirm('Import this backup file now? Existing matching records will be updated.');">Restore Backup</button>
        </form>
      </div>
    </div>
  </div>
</div>
<div class="card mt-3">
  <div class="card-header"><strong>Current Snapshot</strong></div>
  <div class="card-body">
    <p><strong>Schools:</strong> <?php echo number_format(count($schools)); ?></p>
    <p><strong>Owner accounts:</strong> <?php echo number_format(count($owners)); ?></p>
  </div>
</div>
</main>
</body>
</html>
