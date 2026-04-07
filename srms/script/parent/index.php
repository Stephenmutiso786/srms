<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "4") {}else{header("location:../"); exit;}

$students = [];
$notifications = [];
$classIds = [];
$summary = ['children' => 0, 'attendance_rate' => 0, 'avg_score' => 0, 'fees_balance' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException("Parent module is not installed on the server.");
	}

	$stmt = $conn->prepare("SELECT st.id, st.class AS class_id, concat_ws(' ', st.fname, st.mname, st.lname) AS name, c.name AS class_name
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE ps.parent_id = ?
		ORDER BY st.id");
	$stmt->execute([(int)$account_id]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$summary['children'] = count($students);

	foreach ($students as $st) {
		if (!empty($st['class_id'])) {
			$classIds[(int)$st['class_id']] = true;
		}
	}

	$studentIds = array_map(function ($s) { return $s['id']; }, $students);
	if (count($studentIds) > 0) {
		$placeholders = implode(',', array_fill(0, count($studentIds), '?'));

		// Attendance rate last 30 days
		if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
			$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
			$dateExpr = $driver === 'mysql' ? "DATE_SUB(CURDATE(), INTERVAL 30 DAY)" : "CURRENT_DATE - INTERVAL '30 days'";
			$stmt = $conn->prepare("SELECT SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) AS present_count,
				COUNT(*) AS total_count
				FROM tbl_attendance_records r
				JOIN tbl_attendance_sessions s ON s.id = r.session_id
				WHERE r.student_id IN ($placeholders) AND s.session_date >= $dateExpr");
			$stmt->execute($studentIds);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			if ($row && (int)$row['total_count'] > 0) {
				$summary['attendance_rate'] = ((int)$row['present_count'] / (int)$row['total_count']) * 100;
			}
		}

		// Current term average score
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
			$stmt = $conn->prepare("SELECT AVG(score) AS avg_score FROM tbl_exam_results WHERE term = ? AND student IN ($placeholders)");
			$stmt->execute(array_merge([$termId], $studentIds));
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
						WHERE i.student_id IN ($placeholders) AND i.status <> 'void'
						GROUP BY i.id
					) lines
					LEFT JOIN (
						SELECT invoice_id, SUM(amount) AS total_paid
						FROM tbl_payments
						GROUP BY invoice_id
					) paid ON paid.invoice_id = lines.id
				");
				$stmt->execute($studentIds);
				$summary['fees_balance'] = (float)$stmt->fetchColumn();
			} else {
				$stmt = $conn->prepare("
					SELECT COALESCE(SUM(l.amount), 0) AS outstanding
					FROM tbl_invoices i
					INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.student_id IN ($placeholders) AND i.status <> 'void'
				");
				$stmt->execute($studentIds);
				$summary['fees_balance'] = (float)$stmt->fetchColumn();
			}
		}
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		if (count($classIds) > 0) {
			$placeholders = implode(',', array_fill(0, count($classIds), '?'));
			$params = array_keys($classIds);
			$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
				WHERE audience IN ('all','parents') OR (audience = 'class' AND class_id IN ($placeholders))
				ORDER BY created_at DESC LIMIT 5");
			$stmt->execute($params);
		} else {
			$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
				WHERE audience IN ('all','parents')
				ORDER BY created_at DESC LIMIT 5");
			$stmt->execute();
		}
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Parent Dashboard</title>
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
<li><a class="app-menu__item active" href="parent"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="parent/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
<li><a class="app-menu__item" href="parent/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
<li><a class="app-menu__item" href="parent/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Parent Dashboard</h1>
<p>Linked students</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="row mb-3">
  <div class="col-md-6 col-lg-3">
	<div class="widget-small primary coloured-icon"><i class="icon feather icon-users fs-1"></i>
	  <div class="info">
		<h4>Children</h4>
		<p><b><?php echo number_format((int)$summary['children']); ?></b></p>
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

<div class="tile">
  <h3 class="tile-title">Students</h3>
  <?php if (count($students) < 1) { ?>
	<div class="alert alert-info mb-0">No students linked yet. Ask the school admin to link your account.</div>
  <?php } else { ?>
	<div class="table-responsive">
	  <table class="table table-hover table-striped">
		<thead>
		  <tr>
			<th>Student ID</th>
			<th>Name</th>
			<th>Class</th>
			<th>Action</th>
		  </tr>
		</thead>
		<tbody>
		<?php foreach ($students as $st) { ?>
		  <tr>
			<td><?php echo htmlspecialchars((string)$st['id']); ?></td>
			<td><?php echo htmlspecialchars((string)$st['name']); ?></td>
			<td><?php echo htmlspecialchars((string)($st['class_name'] ?? '')); ?></td>
			<td>
			  <a class="btn btn-sm btn-outline-primary" href="parent/attendance?student_id=<?php echo htmlspecialchars((string)$st['id']); ?>">Attendance</a>
			  <a class="btn btn-sm btn-outline-secondary" href="parent/report_card?student=<?php echo htmlspecialchars((string)$st['id']); ?>">Report Card</a>
			</td>
		  </tr>
		<?php } ?>
		</tbody>
	  </table>
	</div>
  <?php } ?>
</div>
<?php } ?>

<div class="tile mt-3">
  <h3 class="tile-title">Notifications</h3>
  <?php if (count($notifications) < 1) { ?>
	<div class="alert alert-info mb-0">No notifications yet.</div>
  <?php } else { ?>
	<div class="list-group">
	<?php foreach ($notifications as $note) {
		$link = trim((string)($note['link'] ?? ''));
		if ($link !== '' && strpos($link, '://') === false && strpos($link, '/') !== 0) {
			$link = 'parent/' . $link;
		}
	?>
	  <div class="list-group-item">
		<div class="d-flex justify-content-between">
		  <strong><?php echo htmlspecialchars((string)$note['title']); ?></strong>
		  <small class="text-muted"><?php echo htmlspecialchars((string)$note['created_at']); ?></small>
		</div>
		<div class="text-muted"><?php echo htmlspecialchars((string)$note['message']); ?></div>
		<?php if ($link !== '') { ?>
		  <a class="btn btn-sm btn-outline-primary mt-2" href="<?php echo htmlspecialchars($link); ?>">View</a>
		<?php } ?>
	  </div>
	<?php } ?>
	</div>
  <?php } ?>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
