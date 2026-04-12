<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($res !== "1" || $level !== "2") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
$card = null;
$student = null;
$attendance = ['days_open' => 0, 'present' => 0, 'absent' => 0];
$feesBalance = 0;
$termName = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$student = report_get_student_identity($conn, $studentId);
	if (!$student || $termId < 1 || !report_teacher_has_class_access($conn, (int)$account_id, (int)$student['class_id'], $termId)) {
		header("location:manage_results");
		exit;
	}

	$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$termId]);
	$termName = (string)$stmt->fetchColumn();

	if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, (int)$student['class_id'], $termId)) {
		$card = null;
	} else {
		$card = report_ensure_card_generated($conn, $studentId, (int)$student['class_id'], $termId, (int)$account_id);
		if ($card) {
			$attendance = report_attendance_summary($conn, $studentId, (int)$student['class_id'], $termId);
			$feesBalance = report_fees_balance($conn, $studentId, $termId);
		}
	}
} catch (Throwable $e) {
	$card = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Student Report</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
@media print{
	.app-header,.app-sidebar,.app-title,.report-actions,.app-nav{display:none!important}
	.app-content{margin-left:0;padding:0}
	.report-card{box-shadow:none}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar"><div class="app-sidebar__user"><div><p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p><p class="app-sidebar__user-designation">Teacher</p></div></div><ul class="app-menu"><li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li><li class="treeview is-expanded"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span><i class="treeview-indicator bi bi-chevron-right"></i></a><ul class="treeview-menu"><li><a class="treeview-item active" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li></ul></li></ul></aside>
<main class="app-content">
<div class="app-title"><div><h1>Student Report Card</h1><p class="mb-0 text-muted">Teacher access is limited to assigned classes only.</p></div></div>
<?php if (!$card || !$student): ?>
<div class="tile"><div class="tile-body"><p class="mb-0 text-muted">This report card is not available yet. Process and lock results first.</p></div></div>
<?php else: ?>
<div class="report-card">
<div class="report-header">
<div><h2><?php echo WBName; ?></h2><div class="report-meta"><span><?php echo htmlspecialchars($student['name']); ?></span><span><?php echo htmlspecialchars($student['school_id'] ?: $student['id']); ?></span></div></div>
<div class="text-end"><span class="report-badge"><i class="bi bi-shield-check"></i> TEACHER VIEW</span><div class="report-meta"><span>Term: <?php echo htmlspecialchars($termName); ?></span><span>Class: <?php echo htmlspecialchars($student['class_name']); ?></span></div></div>
</div>
<div class="report-grid">
<div class="report-stat"><div class="label">Student</div><div class="value"><?php echo htmlspecialchars($student['name']); ?></div></div>
<div class="report-stat"><div class="label">School ID</div><div class="value"><?php echo htmlspecialchars($student['school_id'] ?: $student['id']); ?></div></div>
<div class="report-stat"><div class="label">Total Marks</div><div class="value"><?php echo $card['total']; ?></div></div>
<div class="report-stat"><div class="label">Mean Score</div><div class="value"><?php echo $card['mean']; ?>%</div></div>
<div class="report-stat"><div class="label">Grade</div><div class="value"><?php echo htmlspecialchars($card['grade']); ?></div></div>
<div class="report-stat"><div class="label">Position</div><div class="value"><?php echo $card['position'].' / '.$card['total_students']; ?></div></div>
</div>
<table class="report-table"><thead><tr><th>Subject</th><th>Score</th><th>Grade</th><th>Teacher</th></tr></thead><tbody><?php foreach ($card['subjects'] as $subject): ?><tr><td><?php echo htmlspecialchars($subject['subject_name']); ?></td><td><?php echo $subject['score']; ?></td><td><?php echo htmlspecialchars($subject['grade']); ?></td><td><?php echo htmlspecialchars($subject['teacher_name']); ?></td></tr><?php endforeach; ?></tbody></table>
<div class="report-grid">
<div class="report-stat"><div class="label">Trend</div><div class="value"><?php echo htmlspecialchars($card['trend']); ?></div></div>
<div class="report-stat"><div class="label">Attendance</div><div class="value"><?php echo $attendance['present'].' / '.$attendance['days_open']; ?> Present</div></div>
<div class="report-stat"><div class="label">Fees Balance</div><div class="value">KES <?php echo number_format((float)$feesBalance, 0); ?></div></div>
</div>
<div class="report-comments">
<strong>AI Summary:</strong><p class="mb-1"><?php echo htmlspecialchars($card['ai_summary'] ?? ''); ?></p>
<strong>Teacher Remarks:</strong><p class="mb-1"><?php echo htmlspecialchars($card['teacher_comment'] ?? $card['remark']); ?></p>
<strong>Headteacher Remarks:</strong><p class="mb-1"><?php echo htmlspecialchars($card['headteacher_comment'] ?? $card['remark']); ?></p>
<strong>Verification Code:</strong><p class="mb-0"><?php echo htmlspecialchars($card['verification_code']); ?></p>
</div>
<div class="report-actions">
<button class="btn btn-outline-secondary" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print</button>
<a class="btn btn-primary" href="teacher/report_card_pdf?term=<?php echo $termId; ?>&student=<?php echo urlencode($studentId); ?>" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
</div>
</div>
<?php endif; ?>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
