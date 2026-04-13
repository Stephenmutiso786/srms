<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res !== '1' || (int)$level !== 3) {
    header('location:../');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Student Terms</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
  .policy-wrap { max-width: 960px; margin: 24px auto; padding: 0 16px 48px; }
  .policy-card { background: #fff; border-radius: 14px; box-shadow: 0 12px 30px rgba(0,0,0,0.08); overflow: hidden; }
  .policy-hero { background: linear-gradient(135deg, #0d64b0 0%, #0b5696 100%); color: #fff; padding: 28px; }
  .policy-body { padding: 28px; line-height: 1.8; }
  .policy-body h2 { color: #0b5696; margin-top: 28px; }
  .policy-note { background: #f4f8fc; border-left: 4px solid #0d64b0; padding: 14px 16px; border-radius: 8px; }
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<div class="policy-wrap">
  <div class="policy-card">
    <div class="policy-hero">
      <h1 class="mb-2">Student Terms</h1>
      <p class="mb-0">These terms explain how students should use the portal responsibly.</p>
    </div>
    <div class="policy-body">
      <div class="policy-note mb-4">
        This version applies to students only and covers student portal usage, account security, learning content, and acceptable use.
      </div>
      <h2>1. Portal Use</h2>
      <p>Students may use the portal to view attendance, results, assignments, notices, and learning resources provided by the school.</p>
      <h2>2. Account Security</h2>
      <p>You must protect your login details, use your account responsibly, and log out after each session.</p>
      <h2>3. Acceptable Use</h2>
      <p>Do not attempt to access other users' accounts, modify data, or misuse portal features.</p>
      <h2>4. School Content</h2>
      <p>Learning materials, notices, and results remain controlled by the school and authorized staff.</p>
      <h2>5. Support</h2>
      <p>Any portal issue should be reported to the class teacher or school ICT/admin team.</p>
      <p class="mt-4"><a href="student/privacy">View Student Privacy Policy</a></p>
    </div>
  </div>
</div>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body>
</html>
