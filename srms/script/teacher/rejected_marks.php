<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

$rejectedMarks = [];
$error = '';

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // Get rejected exam submissions for this teacher
  if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
    $stmt = $conn->prepare("
      SELECT 
        s.id, s.exam_id, s.subject_combination_id, s.status, s.reviewed_at, s.review_note,
        e.name AS exam_name,
        c.name AS class_name,
        t.name AS term_name,
        sb.name AS subject_name
      FROM tbl_exam_mark_submissions s
      LEFT JOIN tbl_exams e ON e.id = s.exam_id
      LEFT JOIN tbl_classes c ON c.id = s.class_id
      LEFT JOIN tbl_terms t ON t.id = s.term_id
      LEFT JOIN tbl_subject_combinations sc ON sc.id = s.subject_combination_id
      LEFT JOIN tbl_subjects sb ON sb.id = sc.subject
      WHERE s.teacher_id = ? AND s.status = 'rejected'
      ORDER BY s.reviewed_at DESC
    ");
    $stmt->execute([(int)$account_id]);
    $rejectedMarks = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  error_log("[" . __FILE__ . ":" . __LINE__ . " Throwable] " . $e->getMessage());
  $error = "Failed to load rejected submissions.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Rejected Marks</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("teacher/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Rejected Marks</h1>
<p>Review marks that were returned for correction and resubmit after making changes.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<?php if (count($rejectedMarks) === 0) { ?>
  <div class="tile"><div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>No rejected mark submissions at this time.</div></div>
<?php } else { ?>

<div class="tile">
<h3 class="tile-title"><i class="bi bi-exclamation-triangle me-2"></i>Your Rejected Submissions (<?php echo count($rejectedMarks); ?>)</h3>
<p class="text-muted">The following mark submissions were returned for revision. Click on each to review feedback and make corrections.</p>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Exam</th>
<th>Class</th>
<th>Subject</th>
<th>Term</th>
<th>Rejected Date</th>
<th>Admin Feedback</th>
<th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($rejectedMarks as $row): ?>
<tr>
<td><strong><?php echo htmlspecialchars($row['exam_name'] ?? ''); ?></strong></td>
<td><?php echo htmlspecialchars($row['class_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['subject_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['term_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($row['reviewed_at'] ?? 'now'))); ?></td>
<td>
  <?php if ($row['review_note'] && trim($row['review_note']) !== '') { ?>
    <span class="badge bg-warning text-dark">Has Feedback</span>
  <?php } else { ?>
    <span class="badge bg-secondary">No specific feedback</span>
  <?php } ?>
</td>
<td>
  <form method="POST" action="teacher/core/start_exam_entry" style="display:inline;margin:0;">
    <input type="hidden" name="exam_id" value="<?php echo (int)$row['exam_id']; ?>">
    <input type="hidden" name="subject_combination" value="<?php echo (int)$row['subject_combination_id']; ?>">
    <button class="btn btn-sm btn-primary">Review & Edit</button>
  </form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<?php } ?>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>

</body>
</html>
