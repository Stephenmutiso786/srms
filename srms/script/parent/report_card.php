<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($res !== "1" || $level !== "4") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
$card = null;
$attendance = ['days_open' => 0, 'present' => 0, 'absent' => 0];
$feesBalance = 0;
$blockReport = false;
$termName = '';
$className = '';
$studentName = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT ps.student_id, s.fname, s.lname FROM tbl_parent_students ps JOIN tbl_students s ON s.id = ps.student_id WHERE ps.parent_id = ? ORDER BY s.fname");
	$stmt->execute([$account_id]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($studentId === '' && !empty($students)) {
		$studentId = (string)$students[0]['student_id'];
	}

	if ($termId < 1 && !empty($terms)) {
		$termId = (int)$terms[count($terms)-1]['id'];
	}

	if ($termId > 0 && $studentId !== '') {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();

		$stmt = $conn->prepare("SELECT class, fname, lname FROM tbl_students WHERE id = ? LIMIT 1");
		$stmt->execute([$studentId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$classId = (int)$row['class'];
			$studentName = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
			$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
			$stmt->execute([$classId]);
			$className = (string)$stmt->fetchColumn();

			if (app_table_exists($conn, 'tbl_results_locks') && !app_results_locked($conn, $classId, $termId)) {
				$card = null;
			} else {
				$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
				$stmt->execute([$studentId, $termId]);
				$reportId = (int)$stmt->fetchColumn();
				if ($reportId > 0) {
					$card = report_load_card($conn, $reportId);
					$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
					$feesBalance = report_fees_balance($conn, $studentId, $termId);
					$settings = report_get_settings($conn);
					$blockReport = ((int)$settings['require_fees_clear'] === 1 && $feesBalance > 0);
				}
			}
		}
	}
} catch (Throwable $e) {
	$card = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Report Card</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);\"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Parent</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="parent"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="parent/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item active" href="parent/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
<li><a class="app-menu__item" href="parent/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
<li><a class="app-menu__item" href="parent/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Report Card</h1>
</div>
</div>

<div class="tile mb-3">
<div class="tile-body">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end">
<div>
<label class="form-label">Student</label>
<select class="form-control" name="student" required>
<option value="">Select student</option>
<?php foreach (($students ?? []) as $std): ?>
<option value="<?php echo $std['student_id']; ?>" <?php echo ($std['student_id'] === $studentId) ? 'selected' : ''; ?>><?php echo $std['fname'].' '.$std['lname']; ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Term</label>
<select class="form-control" name="term" required>
<option value="">Select term</option>
<?php foreach (($terms ?? []) as $term): ?>
<option value="<?php echo $term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo $term['name']; ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<button class="btn btn-primary">View Report</button>
</div>
</form>
</div>
</div>

<?php if (!$card): ?>
<div class="tile">
<div class="tile-body">
<p class="text-muted mb-0">No report card generated for this term yet. Please check back after results are approved.</p>
</div>
</div>
<?php else: ?>
<div class="report-card">
<?php if ($blockReport): ?>
<div class="alert alert-warning">Report card is temporarily unavailable until the fees balance is cleared.</div>
<?php endif; ?>
<div class="report-header">
<div>
<h2><?php echo WBName; ?></h2>
<div class="report-meta">
<span><?php echo WBAddress; ?></span>
<span><?php echo WBEmail; ?></span>
</div>
</div>
<div class="text-end">
<span class="report-badge"><i class="bi bi-shield-check"></i> VERIFIED</span>
<div class="report-meta">
<span>Term: <?php echo $termName; ?></span>
<span>Class: <?php echo $className; ?></span>
</div>
</div>
</div>

<div class="report-grid">
<div class="report-stat"><div class="label">Student Name</div><div class="value"><?php echo $studentName; ?></div></div>
<div class="report-stat"><div class="label">Admission No</div><div class="value"><?php echo $studentId; ?></div></div>
<div class="report-stat"><div class="label">Total Marks</div><div class="value"><?php echo $card['total']; ?></div></div>
<div class="report-stat"><div class="label">Mean Score</div><div class="value"><?php echo $card['mean']; ?>%</div></div>
<div class="report-stat"><div class="label">Grade</div><div class="value"><?php echo $card['grade']; ?></div></div>
<div class="report-stat"><div class="label">Position</div><div class="value"><?php echo $card['position'].' / '.$card['total_students']; ?></div></div>
</div>

<table class="report-table">
<thead>
<tr>
<th>Subject</th>
<th>Score</th>
<th>Grade</th>
<th>Teacher</th>
</tr>
</thead>
<tbody>
<?php foreach ($card['subjects'] as $subject): ?>
<tr>
<td><?php echo $subject['subject_name']; ?></td>
<td><?php echo $subject['score']; ?></td>
<td><?php echo $subject['grade']; ?></td>
<td><?php echo $subject['teacher_name']; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="report-grid">
<div class="report-stat">
<div class="label">Performance Trend</div>
<div class="value"><?php echo $card['trend']; ?></div>
</div>
<div class="report-stat">
<div class="label">Attendance</div>
<div class="value"><?php echo $attendance['present'].' / '.$attendance['days_open']; ?> Present</div>
</div>
<div class="report-stat">
<div class="label">Fees Balance</div>
<div class="value">KES <?php echo number_format((float)$feesBalance, 0); ?></div>
</div>
</div>

<div class="report-progress mb-3"><span style="width: <?php echo min(100, (float)$card['mean']); ?>%;"></span></div>

<div class="report-comments">
<strong>Teacher Remarks:</strong>
<p class="mb-1"><?php echo $card['remark']; ?></p>
<strong>Verification Code:</strong>
<p class="mb-0"><?php echo $card['verification_code']; ?></p>
</div>

<?php if (!$blockReport): ?>
<div class="report-actions">
<a class="btn btn-primary" href="parent/report_card_pdf?term=<?php echo $termId; ?>&student=<?php echo $studentId; ?>" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
<a class="btn btn-outline-secondary" href="verify_report?code=<?php echo $card['verification_code']; ?>" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Verify</a>
</div>
<?php endif; ?>
</div>
<?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
