<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}
$notifications = [];
$studentClassId = 0;
$summary = ['attendance_rate' => 0, 'avg_score' => 0, 'fees_balance' => 0, 'subjects' => 0];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$account_id]);
	$studentClassId = (int)$stmt->fetchColumn();

	// Attendance rate last 30 days
	if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
		$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
		$dateExpr = $driver === 'mysql' ? "DATE_SUB(CURDATE(), INTERVAL 30 DAY)" : "CURRENT_DATE - INTERVAL '30 days'";
		$stmt = $conn->prepare("SELECT SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) AS present_count,
			COUNT(*) AS total_count
			FROM tbl_attendance_records r
			JOIN tbl_attendance_sessions s ON s.id = r.session_id
			WHERE r.student_id = ? AND s.session_date >= $dateExpr");
		$stmt->execute([$account_id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row && (int)$row['total_count'] > 0) {
			$summary['attendance_rate'] = ((int)$row['present_count'] / (int)$row['total_count']) * 100;
		}
	}

	// Current term avg score
	$termId = 0;
	$stmt = $conn->prepare("SELECT id FROM tbl_terms WHERE status = 1 ORDER BY id DESC LIMIT 1");
	$stmt->execute();
	$termId = (int)$stmt->fetchColumn();
	if ($termId < 1) {
		$stmt = $conn->prepare("SELECT id FROM tbl_terms ORDER BY id DESC LIMIT 1");
		$stmt->execute();
		$termId = (int)$stmt->fetchColumn();
	}
	if ($termId > 0 && app_table_exists($conn, 'tbl_exam_results')) {
		$stmt = $conn->prepare("SELECT AVG(score) FROM tbl_exam_results WHERE term = ? AND student = ?");
		$stmt->execute([$termId, $account_id]);
		$summary['avg_score'] = (float)$stmt->fetchColumn();
	}

	// Fees balance
	if (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines')) {
		if (app_table_exists($conn, 'tbl_payments')) {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(lines.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
				FROM (
					SELECT i.id, SUM(l.amount) AS total_amount
					FROM tbl_invoices i
					INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.student_id = ? AND i.status <> 'void'
					GROUP BY i.id
				) lines
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = lines.id
			");
			$stmt->execute([$account_id]);
			$summary['fees_balance'] = (float)$stmt->fetchColumn();
		} else {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(l.amount), 0) AS outstanding
				FROM tbl_invoices i
				INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
				WHERE i.student_id = ? AND i.status <> 'void'
			");
			$stmt->execute([$account_id]);
			$summary['fees_balance'] = (float)$stmt->fetchColumn();
		}
	}

	// Subjects count for class
	if ($studentClassId > 0 && app_table_exists($conn, 'tbl_subject_combinations')) {
		$stmt = $conn->prepare("SELECT id, class FROM tbl_subject_combinations");
		$stmt->execute();
		$count = 0;
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$classList = app_unserialize($row['class']);
			if (in_array((string)$studentClassId, array_map('strval', $classList), true)) {
				$count++;
			}
		}
		$summary['subjects'] = $count;
	}
	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
			WHERE audience IN ('all','students') OR (audience = 'class' AND class_id = ?)
			ORDER BY created_at DESC LIMIT 5");
		$stmt->execute([$studentClassId]);
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$notifications = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/settings"><i class="bi bi-person me-2 fs-5"></i> Change Password</a></li>
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
<p class="app-sidebar__user-designation">Student</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item active" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">My Attendance</span></a></li>
<li><a class="app-menu__item" href="student/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
<li><a class="app-menu__item" href="student/exam_timetable"><i class="app-menu__icon feather icon-calendar"></i><span class="app-menu__label">Exam Timetable</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
<li><a class="app-menu__item" href="student/ranking"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">My Ranking</span></a></li>
<li><a class="app-menu__item" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>Dashboard</h1>
</div>

</div>
<div class="row">
<h4 class="mb-3">
<?php
$h = date('G');

if($h>=5 && $h<=11)
{
echo "Good morning ".$fname."";
}
else if($h>=12 && $h<=15)
{
echo "Good afternoon ".$fname."";
}
else
{
echo "Good evening ".$fname."";
}
?>!
</h4>
</div>
<div class="row mb-3">
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-book-open fs-1"></i>
	  <div class="info">
		<h4>Subjects</h4>
		<p><b><?php echo number_format((int)$summary['subjects']); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-check-circle fs-1"></i>
	  <div class="info">
		<h4>Attendance</h4>
		<p><b><?php echo number_format((float)$summary['attendance_rate'], 1); ?>%</b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-activity fs-1"></i>
	  <div class="info">
		<h4>Avg Score</h4>
		<p><b><?php echo number_format((float)$summary['avg_score'], 2); ?></b></p>
	  </div>
	</div>
  </div>
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-credit-card fs-1"></i>
	  <div class="info">
		<h4>Fees Balance</h4>
		<p><b><?php echo number_format((float)$summary['fees_balance'], 2); ?></b></p>
	  </div>
	</div>
  </div>
</div>
<div class="row">
<div class="col-md-12">
<div class="tile">
<h4 class="tile-title">Notifications</h4>

<?php if (count($notifications) < 1) { ?>
<div class="alert alert-dismissible alert-info">
<strong>No notifications yet</strong>
</div>
<?php } else { foreach ($notifications as $note) {
	$link = trim((string)($note['link'] ?? ''));
	if ($link !== '' && strpos($link, '://') === false && strpos($link, '/') !== 0) {
		$link = 'student/' . $link;
	}
?>
<div class="col-lg-12 mb-3">
<div class="bs-component">
<div class="list-group">
<a class="list-group-item list-group-item-action active"><?php echo htmlspecialchars((string)$note['title']); ?></a>
<a class="list-group-item list-group-item-action"><?php echo htmlspecialchars((string)$note['message']); ?></a>
<a class="list-group-item list-group-item-action disabled"><?php echo htmlspecialchars((string)$note['created_at']); ?></a>
<?php if ($link !== '') { ?>
<a class="list-group-item list-group-item-action text-primary" href="<?php echo htmlspecialchars($link); ?>">View</a>
<?php } ?>
</div>
</div>
</div>
<?php } } ?>
</div>
</div>
</div>
<div class="row">
<div class="col-md-12">
<div class="tile">
<h4 class="tile-title">Announcements</h4>

<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE level = '1' OR level = '2' ORDER BY id DESC");
$stmt->execute();
$result = $stmt->fetchAll();

if (count($result) < 1) {
?>
<div class="alert alert-dismissible alert-info">
<strong>There is no any announcements at the moment</strong>
</div>
<?php
}
foreach($result as $row)
{
?>
<div class="col-lg-12 mb-3">
<div class="bs-component">
<div class="list-group">
<a class="list-group-item list-group-item-action active"><?php echo $row[1]; ?></a>
<a class="list-group-item list-group-item-action"><?php echo $row[2]; ?></a>
<a class="list-group-item list-group-item-action disabled"><?php echo $row[3]; ?></a></div>
</div>
</div>
<?php
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

?>




</div>
</div>

</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>

</body>

</html>
