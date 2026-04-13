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
<title><?php echo APP_NAME; ?> - Student Privacy Policy</title>
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
  .policy-hero { background: linear-gradient(135deg, #1f7a54 0%, #176243 100%); color: #fff; padding: 28px; }
  .policy-body { padding: 28px; line-height: 1.8; }
  .policy-body h2 { color: #176243; margin-top: 28px; }
  .policy-note { background: #f5fbf8; border-left: 4px solid #1f7a54; padding: 14px 16px; border-radius: 8px; }
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<div class="policy-wrap">
  <div class="policy-card">
    <div class="policy-hero">
      <h1 class="mb-2">Student Privacy Policy</h1>
      <p class="mb-0">This page explains how the student portal handles student account data, academic records, attendance, and learning content.</p>
    </div>
    <div class="policy-body">
      <div class="policy-note mb-4">
        This version applies to the student portal only. It does not include staff, accountant, BOM, or admin processing details.
      </div>
      <h2>1. What We Collect</h2>
      <p>We collect the minimum information needed to operate the student portal, including your name, class, attendance records, results, assignments, and portal activity.</p>
      <h2>2. How We Use Student Data</h2>
      <p>Student data is used to show attendance, results, assignments, notices, profile information, and school communications related to your learning.</p>
      <h2>3. Access Control</h2>
      <p>Only authorized school staff can view or update student records. Students can only access their own portal information.</p>
      <h2>4. Sharing and Security</h2>
      <p>We do not sell student data. Access is restricted by role-based permissions, authentication, and audit logs.</p>
      <h2>5. Student Rights</h2>
      <p>You may ask the school to correct inaccurate profile information or raise a concern through the school administration.</p>
      <h2>6. Contact</h2>
      <p>For student privacy concerns, contact your school administrator or the system provider listed in the main policy pages.</p>
      <p class="mt-4"><a href="student/terms">View Student Terms</a></p>
    </div>
  </div>
</div>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body>
</html>
