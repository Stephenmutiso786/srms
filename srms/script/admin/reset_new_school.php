<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res !== "1" || !in_array((string)$level, ['0', '9'], true)) { header("location:../"); exit; }

$preview = [
	'students' => 0,
	'parents' => 0,
	'staff_to_remove' => 0,
	'admins_to_keep' => 0,
	'tables' => [],
	'total_rows_to_clear' => 0,
	'backup_tables' => [],
];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$preview = app_reset_school_preview($conn);
} catch (Throwable $e) {
	// Keep zeroed preview.
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Reset Preview</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
	<div class="app-title">
		<div>
			<h1>Reset Preview</h1>
			<p>Review the impact before starting a new-school reset. A backup snapshot will be exported automatically first.</p>
		</div>
	</div>

	<div class="row">
		<div class="col-md-12">
			<div class="tile border border-warning">
				<h3 class="tile-title text-warning">What Will Happen</h3>
				<div class="tile-body">
					<div class="row">
						<div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="small text-muted">Students</div><div class="h4 mb-0"><?php echo number_format((int)$preview['students']); ?></div></div></div>
						<div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="small text-muted">Parents</div><div class="h4 mb-0"><?php echo number_format((int)$preview['parents']); ?></div></div></div>
						<div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="small text-muted">Staff To Remove</div><div class="h4 mb-0"><?php echo number_format((int)$preview['staff_to_remove']); ?></div></div></div>
						<div class="col-md-3 mb-3"><div class="border rounded p-3"><div class="small text-muted">Admins To Keep</div><div class="h4 mb-0"><?php echo number_format((int)$preview['admins_to_keep']); ?></div></div></div>
					</div>
					<div class="alert alert-danger">
						This will clear operational data such as results, reports, attendance, timetable, e-learning activity, invoices, payments, notifications, and login sessions.
					</div>
					<div class="alert alert-info">
						This will preserve classes, subjects, terms, school settings, and admin accounts. A backup export is created automatically before deletion begins.
					</div>
				</div>
			</div>
		</div>

		<div class="col-md-7">
			<div class="tile">
				<h3 class="tile-title">Tables To Be Cleared</h3>
				<div class="tile-body table-responsive">
					<table class="table table-bordered">
						<thead><tr><th>Table</th><th>Rows</th></tr></thead>
						<tbody>
						<?php foreach ($preview['tables'] as $table => $count): ?>
							<tr>
								<td><?php echo htmlspecialchars((string)$table); ?></td>
								<td><?php echo $count >= 0 ? number_format((int)$count) : 'Unknown'; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
						<tfoot><tr><th>Total rows to clear</th><th><?php echo number_format((int)$preview['total_rows_to_clear']); ?></th></tr></tfoot>
					</table>
				</div>
			</div>
		</div>

		<div class="col-md-5">
			<div class="tile">
				<h3 class="tile-title">Backup Package</h3>
				<div class="tile-body">
					<p class="text-muted">The automatic backup export will include the people data being removed plus the main academic, finance, attendance, timetable, and setup tables needed for rollback or archive.</p>
					<p><strong>Tables included:</strong> <?php echo number_format(count((array)$preview['backup_tables'])); ?></p>
					<div class="d-grid gap-2">
						<form method="POST" action="admin/core/reset_new_school">
							<input type="hidden" name="confirm_reset" value="1">
							<button type="submit" class="btn btn-danger" onclick="return confirm('Create backup export and continue with reset for new school?');">Create Backup And Reset</button>
						</form>
						<a href="admin/system" class="btn btn-outline-secondary">Cancel</a>
					</div>
				</div>
			</div>
		</div>
	</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
