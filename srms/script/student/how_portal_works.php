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
<title><?php echo APP_NAME; ?> - How Student Portal Works</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
  .guide-hero { background: linear-gradient(135deg, #1f7a54 0%, #176243 100%); color: #fff; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
  .guide-step { border-left: 4px solid #1f7a54; background: #fff; border-radius: 10px; padding: 18px; margin-bottom: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
  .guide-step h5 { margin-bottom: 10px; color: #176243; }
  .video-card { background: #0f172a; color: #fff; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
  .video-frame { width: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: 10px; background: #000; }
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header">
  <a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
</header>

<main class="app-content">
  <div class="guide-hero">
    <h2 class="mb-2">How Student Portal Works</h2>
    <p class="mb-0">A simple student guide to use your portal correctly from start to finish.</p>
  </div>

  <div class="video-card">
    <h4 class="mb-2"><i class="bi bi-play-circle me-2"></i>Student Portal Video</h4>
    <p class="mb-3">Upload a short student-only walkthrough video here to show how to log in, check attendance, view results, and submit work.</p>
    <video class="video-frame" controls poster="images/icon.ico">
      <source src="uploads/student_portal_works.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>
  </div>

  <div class="guide-step">
    <h5>Step 1: Sign In Safely</h5>
    <ul>
      <li>Log in using your student account.</li>
      <li>Never share your password.</li>
      <li>Log out when done, especially on shared devices.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 2: Check Dashboard</h5>
    <ul>
      <li>Read announcements and upcoming school updates.</li>
      <li>Review your summary cards for attendance, grades, and fee status.</li>
      <li>Open notifications first before starting assignments.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 3: Track Attendance</h5>
    <ul>
      <li>Open attendance records to see your recent status.</li>
      <li>Report any incorrect attendance to your class teacher quickly.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 4: View Results and Reports</h5>
    <ul>
      <li>Open result/report sections to view your marks by subject.</li>
      <li>Check trends to understand subjects that need improvement.</li>
      <li>Download report card files when available.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 5: Use Learning Resources</h5>
    <ul>
      <li>Open e-learning materials and assignment instructions.</li>
      <li>Submit work before deadlines and read teacher feedback.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 6: Follow School Communication</h5>
    <ul>
      <li>Read official notices and class updates regularly.</li>
      <li>Keep your profile information accurate where allowed.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 7: Weekly Student Checklist</h5>
    <ul>
      <li>Attendance reviewed.</li>
      <li>Assignments completed.</li>
      <li>Results checked.</li>
      <li>Any issues reported to teacher on time.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Student-Only Rules</h5>
    <ul>
      <li>Use only your own account.</li>
      <li>Do not attempt to view other learners' records.</li>
      <li>Report portal issues through your class teacher or school office.</li>
    </ul>
  </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
