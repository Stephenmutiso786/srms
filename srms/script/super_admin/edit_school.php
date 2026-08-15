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
$schoolId = (int)($_GET['id'] ?? 0);
$school = ['id' => 0, 'name' => '', 'result_system' => 1, 'allow_results' => 1, 'package_tier' => 'elimu_hub', 'support_plan' => 'basic', 'mpesa_enabled' => 1, 'term_start_date' => '', 'term_end_date' => '', 'is_locked' => 0];
if ($schoolId > 0 && app_table_exists($conn, 'tbl_school')) {
	$stmt = $conn->prepare('SELECT id, name, result_system, allow_results, package_tier, support_plan, mpesa_enabled, term_start_date, term_end_date, is_locked FROM tbl_school WHERE id = ? LIMIT 1');
	$stmt->execute([$schoolId]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	if ($row) {
		$school = [
			'id' => (int)$row['id'],
			'name' => (string)$row['name'],
			'result_system' => (int)$row['result_system'],
			'allow_results' => (int)$row['allow_results'],
			'package_tier' => (string)($row['package_tier'] ?? 'elimu_hub'),
			'support_plan' => (string)($row['support_plan'] ?? 'basic'),
			'mpesa_enabled' => (int)($row['mpesa_enabled'] ?? 1),
			'term_start_date' => (string)($row['term_start_date'] ?? ''),
			'term_end_date' => (string)($row['term_end_date'] ?? ''),
			'is_locked' => (int)($row['is_locked'] ?? 0),
		];
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit School</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body class="app sidebar-mini">
<main class="app-content">
<h1>Edit School</h1>
<form action="<?php echo htmlspecialchars(($superAdminBase !== '' ? $superAdminBase : '') . '/core/save_school.php'); ?>" method="post" class="card p-3">
  <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
  <label class="form-label">School name</label>
  <input class="form-control mb-3" name="name" value="<?php echo htmlspecialchars((string)$school['name']); ?>" required>
  <label class="form-label">Result system</label>
  <select class="form-control mb-3" name="result_system">
    <option value="1" <?php echo (int)$school['result_system'] === 1 ? 'selected' : ''; ?>>Division</option>
    <option value="0" <?php echo (int)$school['result_system'] === 0 ? 'selected' : ''; ?>>Average</option>
  </select>
  <label class="form-label">Allow results</label>
  <select class="form-control mb-3" name="allow_results">
    <option value="1" <?php echo (int)$school['allow_results'] === 1 ? 'selected' : ''; ?>>Yes</option>
    <option value="0" <?php echo (int)$school['allow_results'] === 0 ? 'selected' : ''; ?>>No</option>
  </select>
  <label class="form-label">Package tier</label>
  <select class="form-control mb-3" name="package_tier">
    <option value="elimu_hub" <?php echo (string)$school['package_tier'] === 'elimu_hub' ? 'selected' : ''; ?>>Elimu Hub</option>
    <option value="elimu_hub_pro" <?php echo (string)$school['package_tier'] === 'elimu_hub_pro' ? 'selected' : ''; ?>>Elimu Hub Pro</option>
  </select>
  <label class="form-label">Support plan</label>
  <select class="form-control mb-3" name="support_plan">
    <option value="basic" <?php echo (string)$school['support_plan'] === 'basic' ? 'selected' : ''; ?>>Basic</option>
    <option value="pro" <?php echo (string)$school['support_plan'] === 'pro' ? 'selected' : ''; ?>>Pro</option>
  </select>
  <label class="form-label">M-Pesa enabled</label>
  <select class="form-control mb-3" name="mpesa_enabled">
    <option value="1" <?php echo (int)$school['mpesa_enabled'] === 1 ? 'selected' : ''; ?>>Yes</option>
    <option value="0" <?php echo (int)$school['mpesa_enabled'] === 0 ? 'selected' : ''; ?>>No</option>
  </select>
  <label class="form-label">Term start date</label>
  <input class="form-control mb-3" type="date" name="term_start_date" value="<?php echo htmlspecialchars((string)$school['term_start_date']); ?>">
  <label class="form-label">Term end date</label>
  <input class="form-control mb-3" type="date" name="term_end_date" value="<?php echo htmlspecialchars((string)$school['term_end_date']); ?>">
  <label class="form-label">Lock school now</label>
  <select class="form-control mb-3" name="is_locked">
    <option value="0" <?php echo (int)$school['is_locked'] === 0 ? 'selected' : ''; ?>>No</option>
    <option value="1" <?php echo (int)$school['is_locked'] === 1 ? 'selected' : ''; ?>>Yes</option>
  </select>
  <button class="btn btn-primary" type="submit">Save Changes</button>
</form>
</main>
</body>
</html>
