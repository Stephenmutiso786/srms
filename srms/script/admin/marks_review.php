<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('marks.review', 'admin');
app_require_unlocked('exams', 'admin');

$examSubmissions = [];
$cbcSubmissions = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$stmt = $conn->prepare("SELECT s.*, e.name AS exam_name, c.name AS class_name, t.name AS term_name, sb.name AS subject_name
			FROM tbl_exam_mark_submissions s
			LEFT JOIN tbl_exams e ON e.id = s.exam_id
			LEFT JOIN tbl_classes c ON c.id = s.class_id
			LEFT JOIN tbl_terms t ON t.id = s.term_id
			LEFT JOIN tbl_subject_combinations sc ON sc.id = s.subject_combination_id
			LEFT JOIN tbl_subjects sb ON sb.id = sc.subject
			ORDER BY s.submitted_at DESC NULLS LAST, s.id DESC");
		$stmt->execute();
		$examSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
		$stmt = $conn->prepare("SELECT s.*, c.name AS class_name, t.name AS term_name, sb.name AS subject_name
			FROM tbl_cbc_mark_submissions s
			LEFT JOIN tbl_classes c ON c.id = s.class_id
			LEFT JOIN tbl_terms t ON t.id = s.term_id
			LEFT JOIN tbl_subjects sb ON sb.id = s.subject_id
			ORDER BY s.submitted_at DESC NULLS LAST, s.id DESC");
		$stmt->execute();
		$cbcSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load marks submissions."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Marks Review</title>
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
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('admin/partials/sidebar.php'); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Marks Review</h1>
<p>Review submitted mark sheets, return them for correction, and move exams toward finalization.</p>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Exam Marks Submissions</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Exam</th><th>Class</th><th>Subject</th><th>Term</th><th>Status</th><th>Submitted</th><th>Reviewed</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($examSubmissions as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['exam_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['class_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['subject_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['term_name'] ?? ''); ?></td>
<td><span class="badge bg-<?php echo htmlspecialchars(app_exam_status_badge((string)$row['status'])); ?>"><?php echo htmlspecialchars(ucfirst((string)$row['status'])); ?></span></td>
<td><?php echo htmlspecialchars($row['submitted_at'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['reviewed_at'] ?? ''); ?></td>
<td class="d-flex gap-2 flex-wrap">
  <?php if ($row['status'] === 'submitted') { ?>
    <form method="POST" action="admin/core/approve_exam_marks">
      <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
      <button class="btn btn-sm btn-success">Mark Reviewed</button>
    </form>
    <form method="POST" action="admin/core/reject_exam_marks">
      <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
      <button class="btn btn-sm btn-outline-danger">Return to Teacher</button>
    </form>
  <?php } ?>
  <?php if (in_array((string)$row['status'], ['reviewed','finalized'], true) && (int)$level === 9) { ?>
    <form method="POST" action="admin/core/unlock_exam_marks">
      <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
      <button class="btn btn-sm btn-outline-warning">Unlock to Draft</button>
    </form>
  <?php } ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile">
<h3 class="tile-title">CBC Marks Submissions</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Class</th><th>Subject</th><th>Term</th><th>Status</th><th>Submitted</th><th>Action</th>
</tr>
</thead>
<tbody>
<?php foreach ($cbcSubmissions as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['class_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['subject_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['term_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($row['status']); ?></td>
<td><?php echo htmlspecialchars($row['submitted_at'] ?? ''); ?></td>
<td class="d-flex gap-2 flex-wrap">
  <?php if ($row['status'] === 'submitted') { ?>
    <form method="POST" action="admin/core/approve_cbc_marks">
      <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
      <button class="btn btn-sm btn-success">Approve</button>
    </form>
    <form method="POST" action="admin/core/reject_cbc_marks">
      <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
      <button class="btn btn-sm btn-outline-danger">Reject</button>
    </form>
  <?php } ?>
  <?php if ($row['status'] === 'approved' && (int)$level === 9) { ?>
    <form method="POST" action="admin/core/unlock_cbc_marks">
      <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
      <button class="btn btn-sm btn-outline-warning">Unlock</button>
    </form>
  <?php } ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
