<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "4") {}else{header("location:../"); exit;}

$studentId = trim((string)($_GET['student_id'] ?? ''));
$linked = [];
$invoices = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException("Parent module is not installed on the server.");
	}
	if (!app_table_exists($conn, 'tbl_invoices') || !app_table_exists($conn, 'tbl_invoice_lines') || !app_table_exists($conn, 'tbl_payments')) {
		throw new RuntimeException("Fees module is not installed on the server.");
	}

	$stmt = $conn->prepare("SELECT st.id, concat_ws(' ', st.fname, st.mname, st.lname) AS name, c.name AS class_name
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE ps.parent_id = ?
		ORDER BY st.id");
	$stmt->execute([(int)$account_id]);
	$linked = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (count($linked) < 1) {
		throw new RuntimeException("No students linked to your account.");
	}

	if ($studentId === '') {
		$studentId = (string)$linked[0]['id'];
	}

	$allowed = false;
	foreach ($linked as $st) {
		if ((string)$st['id'] === $studentId) { $allowed = true; break; }
	}
	if (!$allowed) {
		throw new RuntimeException("This student is not linked to your account.");
	}

	$stmt = $conn->prepare("SELECT i.id, t.name AS term_name, i.issue_date, i.due_date,
		COALESCE(SUM(l.amount),0) AS total,
		COALESCE((SELECT SUM(p.amount) FROM tbl_payments p WHERE p.invoice_id = i.id), 0) AS paid
		FROM tbl_invoices i
		JOIN tbl_terms t ON t.id = i.term_id
		LEFT JOIN tbl_invoice_lines l ON l.invoice_id = i.id
		WHERE i.student_id = ? AND i.status != 'void'
		GROUP BY i.id, t.name, i.issue_date, i.due_date
		ORDER BY i.term_id DESC, i.id DESC");
	$stmt->execute([$studentId]);
	$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Parent Fees</title>
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
<li><a class="app-menu__item" href="parent"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="parent/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="parent/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">Attendance</span></a></li>
<li><a class="app-menu__item active" href="parent/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Fees</h1>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">Select Student</h3>
  <form method="GET" action="parent/fees" class="row g-2">
	<div class="col-md-8">
	  <select class="form-control" name="student_id" required>
		<?php foreach ($linked as $st) { ?>
		  <option value="<?php echo htmlspecialchars((string)$st['id']); ?>" <?php echo ((string)$st['id'] === $studentId) ? 'selected' : ''; ?>>
			<?php echo htmlspecialchars((string)$st['id'].' — '.$st['name'].' ('.($st['class_name'] ?? '').')'); ?>
		  </option>
		<?php } ?>
	  </select>
	</div>
	<div class="col-md-4 d-grid">
	  <button class="btn btn-primary" type="submit">View</button>
	</div>
  </form>
</div>

<div class="tile">
  <h3 class="tile-title">Invoices</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>Term</th>
		  <th>Issue</th>
		  <th>Due</th>
		  <th>Total</th>
		  <th>Paid</th>
		  <th>Balance</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php if (count($invoices) < 1) { ?>
		<tr><td colspan="6" class="text-muted">No invoices yet.</td></tr>
	  <?php } else { foreach ($invoices as $inv) {
		$total = (float)$inv['total'];
		$paid = (float)$inv['paid'];
		$bal = max(0, $total - $paid);
	  ?>
		<tr>
		  <td><?php echo htmlspecialchars((string)$inv['term_name']); ?></td>
		  <td><?php echo htmlspecialchars((string)$inv['issue_date']); ?></td>
		  <td><?php echo htmlspecialchars((string)($inv['due_date'] ?? '-')); ?></td>
		  <td><?php echo number_format($total, 2); ?></td>
		  <td><?php echo number_format($paid, 2); ?></td>
		  <td><b><?php echo number_format($bal, 2); ?></b></td>
		</tr>
	  <?php } } ?>
	  </tbody>
	</table>
  </div>
  <p class="text-muted mb-0">Payments are recorded by the school admin for now (M-Pesa integration can be added in a later phase).</p>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>

