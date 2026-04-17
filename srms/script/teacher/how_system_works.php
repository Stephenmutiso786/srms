<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res !== '1' || $level !== '2') {
    header('location:../');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - How The System Works</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
  .guide-hero {
    background: linear-gradient(135deg, #0d64b0 0%, #0b5696 100%);
    color: #fff;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
  }
  .guide-step {
    border-left: 4px solid #0d64b0;
    background: #fff;
    border-radius: 10px;
    padding: 18px;
    margin-bottom: 14px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  .guide-step h5 {
    margin-bottom: 10px;
    color: #0b5696;
  }
  .guide-step ul {
    margin-bottom: 0;
  }
  .quick-links a {
    margin-right: 8px;
    margin-bottom: 8px;
  }
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header">
  <a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

  <ul class="app-nav">
    <li class="dropdown">
      <a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
      <ul class="dropdown-menu settings-menu dropdown-menu-right">
        <li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
        <li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
      </ul>
    </li>
  </ul>
</header>

<?php include("teacher/partials/sidebar.php"); ?>

<main class="app-content">
  <div class="guide-hero">
    <h2 class="mb-2">How The System Works</h2>
    <p class="mb-0">Teacher end-to-end operating guide for daily, weekly, and term workflows.</p>
  </div>

  <div class="tile">
    <h4>Quick Access</h4>
    <div class="quick-links d-flex flex-wrap">
      <a class="btn btn-outline-primary btn-sm" href="teacher">Dashboard</a>
      <a class="btn btn-outline-primary btn-sm" href="teacher/attendance">Attendance</a>
      <a class="btn btn-outline-primary btn-sm" href="teacher/manage_results">Manage Results</a>
      <a class="btn btn-outline-primary btn-sm" href="teacher/class_report">Class Report</a>
      <a class="btn btn-outline-primary btn-sm" href="teacher/report_card">Report Cards</a>
    </div>
  </div>

  <div class="guide-step">
    <h5>Step 1: Login and Confirm Session</h5>
    <ul>
      <li>Go to the login page and sign in with your teacher credentials.</li>
      <li>Confirm your name, role, and current term on the dashboard.</li>
      <li>If role details are incorrect, report to admin before entering marks.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 2: Check Dashboard Alerts</h5>
    <ul>
      <li>Review announcements, pending tasks, and notifications.</li>
      <li>Open timetable items for today and identify due classes.</li>
      <li>Prioritize urgent actions: attendance, pending marks, and student follow-up.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 3: Capture Attendance (Daily)</h5>
    <ul>
      <li>Open Attendance from the teacher menu.</li>
      <li>Select class, date, and session, then mark each student present/absent/late.</li>
      <li>Save attendance and verify totals match class list.</li>
      <li>Use attendance history for corrections before end-of-day cutoff.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 4: Manage Learning Content</h5>
    <ul>
      <li>Use E-Learning or Assignments to upload notes, tasks, and deadlines.</li>
      <li>Add clear instructions and grading criteria for each assignment.</li>
      <li>Confirm students can view content from their portal after publishing.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 5: Enter Continuous Assessment Marks</h5>
    <ul>
      <li>Open Manage Results or Marks Entry.</li>
      <li>Select class, subject, term, and exam type.</li>
      <li>Enter marks student by student or use bulk upload where available.</li>
      <li>Validate totals and grading before saving.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 6: Review and Submit Marks</h5>
    <ul>
      <li>Use preview screens to detect outliers and missing values.</li>
      <li>Submit marks for review if approval workflow is enabled.</li>
      <li>Do not publish final results until admin confirms lock/publish status.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 7: Generate Reports</h5>
    <ul>
      <li>Open the report module and choose class, term, and exam.</li>
      <li>Generate individual student reports for guidance meetings.</li>
      <li>Export PDFs where required and verify report comments are complete.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 8: Communicate with Parents and Students</h5>
    <ul>
      <li>Use communication tools for performance updates and reminders.</li>
      <li>Keep messages concise, factual, and aligned to approved school policy.</li>
      <li>Log follow-up actions for students who need intervention.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 9: End-of-Week Teacher Checklist</h5>
    <ul>
      <li>Attendance complete for all classes taught.</li>
      <li>All due marks entered and reviewed.</li>
      <li>Assignments published and feedback provided.</li>
      <li>Critical parent communications sent.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 10: End-of-Term Process</h5>
    <ul>
      <li>Finalize all marks before report generation deadline.</li>
      <li>Review class performance analytics and identify risk learners.</li>
      <li>Submit final reports and recommendations to administration.</li>
      <li>Archive class materials and prepare next-term planning notes.</li>
    </ul>
  </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
