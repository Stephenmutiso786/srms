<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('communication.manage', '../admin');

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$schoolRow = app_school_row($conn);
	$features = app_school_package_features((string)($schoolRow['package_tier'] ?? 'elimu_hub'));
	if (empty($features['support_24_7'])) {
		$_SESSION['reply'] = array(array('warning', '24/7 support is available on the Elimu Hub Pro package only.'));
		header('location:admin');
		exit;
	}
	$stmt = $conn->prepare("SELECT t.*, CONCAT_WS(' ', s.fname, s.lname) AS user_name
		FROM support_tickets t
		LEFT JOIN tbl_staff s ON s.id = t.user_id
		WHERE t.school_id = ?
		ORDER BY t.id DESC");
	$stmt->execute([(int)$schoolRow['id']]);
	$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$tickets = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo APP_NAME; ?> - Support Desk</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body class="app sidebar-mini">
<main class="app-content">
<div class="app-title"><div><h1>Support Desk</h1><p>24/7 ticketing is enabled for Pro schools.</p></div></div>
<div class="card mb-3"><div class="card-body">
<p class="mb-0">Package: <strong><?php echo htmlspecialchars((string)($schoolRow['package_tier'] ?? 'elimu_hub')); ?></strong></p>
</div></div>
<div class="table-responsive">
<table class="table table-striped">
<thead><tr><th>ID</th><th>Title</th><th>Priority</th><th>Status</th><th>Logged By</th></tr></thead>
<tbody>
<?php foreach ($tickets as $ticket): ?>
<tr>
<td>#<?php echo (int)$ticket['id']; ?></td>
<td><?php echo htmlspecialchars((string)$ticket['title']); ?></td>
<td><?php echo htmlspecialchars(strtoupper((string)$ticket['priority'])); ?></td>
<td><?php echo htmlspecialchars(strtoupper((string)$ticket['status'])); ?></td>
<td><?php echo htmlspecialchars((string)($ticket['user_name'] ?? '')); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</main>
</body>
</html>
