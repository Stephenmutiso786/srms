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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register School</title>
<link rel="stylesheet" href="../css/main.css">
</head>
<body class="app sidebar-mini">
<main class="app-content">
<h1>Register School</h1>
<form action="<?php echo htmlspecialchars(($superAdminBase !== '' ? $superAdminBase : '') . '/core/save_school.php'); ?>" method="post" class="card p-3">
  <input type="hidden" name="school_id" value="">
  <label class="form-label">School name</label>
  <input class="form-control mb-3" name="name" required>
  <label class="form-label">School code</label>
  <input class="form-control mb-3" name="school_code" placeholder="Optional internal code">
  <label class="form-label">School admin email</label>
  <input class="form-control mb-3" type="email" name="admin_email" placeholder="admin@school.local">
  <label class="form-label">Temporary admin password</label>
  <input class="form-control mb-3" type="text" name="admin_password" placeholder="Leave blank to auto-generate">
  <label class="form-label">Result system</label>
  <select class="form-control mb-3" name="result_system">
    <option value="1">Division</option>
    <option value="0">Average</option>
  </select>
  <label class="form-label">Allow results</label>
  <select class="form-control mb-3" name="allow_results">
    <option value="1">Yes</option>
    <option value="0">No</option>
  </select>
  <label class="form-label">Package tier</label>
  <select class="form-control mb-3" name="package_tier">
    <option value="elimu_hub">Elimu Hub</option>
    <option value="elimu_hub_pro">Elimu Hub Pro</option>
  </select>
  <label class="form-label">Support plan</label>
  <select class="form-control mb-3" name="support_plan">
    <option value="basic">Basic</option>
    <option value="pro">Pro</option>
  </select>
  <label class="form-label">M-Pesa enabled</label>
  <select class="form-control mb-3" name="mpesa_enabled">
    <option value="1">Yes</option>
    <option value="0">No</option>
  </select>
  <label class="form-label">Term start date</label>
  <input class="form-control mb-3" type="date" name="term_start_date">
  <label class="form-label">Term end date</label>
  <input class="form-control mb-3" type="date" name="term_end_date">
  <label class="form-label">Lock school now</label>
  <select class="form-control mb-3" name="is_locked">
    <option value="0">No</option>
    <option value="1">Yes</option>
  </select>
  <button class="btn btn-primary" type="submit">Save School</button>
</form>
</main>
</body>
</html>
