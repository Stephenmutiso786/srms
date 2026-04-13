<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res !== '1' || (int)$level === 3) {
    header('location:./');
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
<base href="./">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
  .guide-hero { background: linear-gradient(135deg, #0d64b0 0%, #0b5696 100%); color: #fff; border-radius: 12px; padding: 24px; margin-bottom: 16px; }
  .guide-step { border-left: 4px solid #0d64b0; background: #fff; border-radius: 10px; padding: 18px; margin-bottom: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
  .guide-step h5 { margin-bottom: 10px; color: #0b5696; }
  .guide-step ul { margin-bottom: 0; }
  .video-card { background: #111827; color: #fff; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
  .video-frame { width: 100%; aspect-ratio: 16 / 9; border: 0; border-radius: 10px; background: #000; }
  .chapter-list { margin-top: 12px; padding-left: 18px; }
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header">
  <a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
  <a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
</header>

<main class="app-content">
  <div class="guide-hero">
    <h2 class="mb-2">How The System Works</h2>
    <p class="mb-0">End-to-end workflow for staff, administration, BOM, and parents.</p>
  </div>

  <div class="video-card">
    <h4 class="mb-2"><i class="bi bi-play-circle me-2"></i>Walkthrough Video</h4>
    <p class="mb-3">This school guide can be paired with a detailed MP4 walkthrough. Replace the source below with your uploaded video file.</p>
    <video class="video-frame" controls poster="images/icon.ico">
      <source src="uploads/how_system_works.mp4" type="video/mp4">
      Your browser does not support the video tag.
    </video>
    <ul class="chapter-list">
      <li>00:00 - Login and role verification</li>
      <li>01:10 - Dashboard overview and navigation</li>
      <li>03:00 - Attendance, results, and finance workflow</li>
      <li>06:10 - Reports, communication, and compliance</li>
      <li>08:30 - End-of-term closure and archival</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 1: Login and Profile Check</h5>
    <ul>
      <li>Log in with your approved account.</li>
      <li>Confirm your role and portal menu.</li>
      <li>If role access is wrong, contact admin before making updates.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 2: Configure Core Data</h5>
    <ul>
      <li>Set terms, classes, subjects, and teacher allocations.</li>
      <li>Ensure timetables are published before term begins.</li>
      <li>Confirm module locks and role permissions.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 3: Daily Operations</h5>
    <ul>
      <li>Teachers capture attendance and learning progress.</li>
      <li>Accountants receive and record payments.</li>
      <li>Parents monitor learner status and fees.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 4: Assessments and Results</h5>
    <ul>
      <li>Create exams and enter marks by class and subject.</li>
      <li>Review and approve marks where workflow is enabled.</li>
      <li>Publish report cards once quality checks are complete.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 5: Finance Workflows</h5>
    <ul>
      <li>Create fee structures, invoices, and payment plans.</li>
      <li>Track class-wise, term-wise, and aging reports.</li>
      <li>Export reports for reconciliation and management review.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 6: Communication and Compliance</h5>
    <ul>
      <li>Use announcements and messaging for official updates.</li>
      <li>Maintain audit logs and compliance settings.</li>
      <li>Review policy pages and system settings regularly.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Step 7: End-Term and Archival</h5>
    <ul>
      <li>Close pending records and verify final reports.</li>
      <li>Complete promotions and certificate processes.</li>
      <li>Archive reports and prepare next term setup.</li>
    </ul>
  </div>

  <div class="guide-step">
    <h5>Implementation Notes</h5>
    <ul>
      <li>Upload the final MP4 to <code>uploads/how_system_works.mp4</code>.</li>
      <li>Use the chapter list above to match narration and screen recording.</li>
      <li>Keep the video under 10 minutes for easier training sessions.</li>
    </ul>
  </div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
