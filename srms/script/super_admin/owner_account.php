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
$owner = ['id' => 0, 'fname' => '', 'lname' => '', 'email' => '', 'status' => 1];
$ownerId = (int)($_GET['id'] ?? 0);
if ($ownerId > 0 && app_table_exists($conn, 'tbl_staff')) {
	$stmt = $conn->prepare('SELECT id, fname, lname, email, status FROM tbl_staff WHERE id = ? AND level = 9 LIMIT 1');
	$stmt->execute([$ownerId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		$owner = $row;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Owner Account</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body class="app sidebar-mini">
<main class="app-content">
<h1>Owner Account</h1>
<form action="<?php echo htmlspecialchars(($superAdminBase !== '' ? $superAdminBase : '') . '/core/save_owner.php'); ?>" method="post" class="card p-3">
  <input type="hidden" name="owner_id" value="<?php echo (int)($owner['id'] ?? 0); ?>">
  <label class="form-label">First name</label>
  <input class="form-control mb-3" name="fname" value="<?php echo htmlspecialchars((string)($owner['fname'] ?? '')); ?>" required>
  <label class="form-label">Last name</label>
  <input class="form-control mb-3" name="lname" value="<?php echo htmlspecialchars((string)($owner['lname'] ?? '')); ?>" required>
  <label class="form-label">Email</label>
  <input class="form-control mb-3" name="email" type="email" value="<?php echo htmlspecialchars((string)($owner['email'] ?? '')); ?>" required>
  <label class="form-label">Password <?php echo (int)($owner['id'] ?? 0) > 0 ? '(leave blank to keep unchanged)' : ''; ?></label>
  <input class="form-control mb-3" name="password" type="password">
  <label class="form-label">Status</label>
  <select class="form-control mb-3" name="status">
    <option value="1" <?php echo (int)($owner['status'] ?? 1) === 1 ? 'selected' : ''; ?>>Active</option>
    <option value="0" <?php echo (int)($owner['status'] ?? 1) === 0 ? 'selected' : ''; ?>>Blocked</option>
  </select>
  <button class="btn btn-primary" type="submit">Save Owner</button>
</form>
</main>
</body>
</html>
