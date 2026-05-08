<?php
chdir(__DIR__ . '/..');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "5") {}else{header("location:../"); exit;}
$students = [];
$entries = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_finance_tables($conn);
	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, mname, lname) AS student_name FROM tbl_students ORDER BY fname, lname LIMIT 200");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$studentId = trim((string)($_POST['student_id'] ?? ''));
		$sponsorName = trim((string)($_POST['sponsor_name'] ?? ''));
		$type = trim((string)($_POST['sponsorship_type'] ?? 'bursary'));
		$amount = (float)($_POST['amount'] ?? 0);
		$notes = trim((string)($_POST['notes'] ?? ''));
		if ($studentId !== '' && $sponsorName !== '' && $amount > 0) {
			$stmt = $conn->prepare("INSERT INTO tbl_finance_scholarships (student_id, sponsor_name, sponsorship_type, amount, notes, created_by) VALUES (?,?,?,?,?,?)");
			$stmt->execute([$studentId, $sponsorName, $type, $amount, $notes !== '' ? $notes : null, (int)$account_id]);
			$_SESSION['reply'] = [[ 'success', 'Bursary or sponsorship saved successfully.' ]];
			header('location:bursaries');
			exit;
		}
	}
	$stmt = $conn->prepare("SELECT fs.student_id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name, fs.sponsor_name, fs.sponsorship_type, fs.amount, fs.status
		FROM tbl_finance_scholarships fs
		LEFT JOIN tbl_students st ON st.id = fs.student_id
		ORDER BY fs.id DESC
		LIMIT 20");
	$stmt->execute();
	$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$students = [];
	$entries = [];
}
?>
<!DOCTYPE html>
<html lang="en"><head><title><?php echo APP_NAME; ?> - Bursaries</title><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=edge"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../"><link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico"><link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css"></head>
<body class="app sidebar-mini"><header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header><?php include('accountant/partials/sidebar.php'); ?><main class="app-content">
<div class="app-title"><div><h1>Bursaries & Scholarships</h1><p>Record sponsor support, bursaries, and student discounts.</p></div></div>
<div class="tile"><h3 class="tile-title">Add Support Record</h3><form class="row g-3" method="POST" action="accountant/bursaries"><div class="col-md-4"><label class="form-label">Student</label><select class="form-control" name="student_id" required><option value="" selected disabled>Select student</option><?php foreach ($students as $student): ?><option value="<?php echo htmlspecialchars((string)$student['id']); ?>"><?php echo htmlspecialchars((string)$student['id'].' - '.$student['student_name']); ?></option><?php endforeach; ?></select></div><div class="col-md-4"><label class="form-label">Sponsor</label><input class="form-control" name="sponsor_name" placeholder="Sponsor or bursary fund" required></div><div class="col-md-4"><label class="form-label">Type</label><select class="form-control" name="sponsorship_type"><option value="bursary">Bursary</option><option value="scholarship">Scholarship</option><option value="discount">Discount</option></select></div><div class="col-md-4"><label class="form-label">Amount</label><input class="form-control" type="number" step="0.01" min="0.01" name="amount" required></div><div class="col-md-8"><label class="form-label">Notes</label><input class="form-control" name="notes" placeholder="Optional notes"></div><div class="col-md-12 d-grid"><button class="btn btn-primary" type="submit">Save Support Record</button></div></form></div>
<div class="tile mt-3"><h3 class="tile-title">Recent Support Records</h3><div class="table-responsive"><table class="table table-hover table-striped"><thead><tr><th>Student</th><th>Sponsor</th><th>Type</th><th>Amount</th><th>Status</th></tr></thead><tbody><?php if (!$entries): ?><tr><td colspan="5" class="text-muted">No bursary or scholarship records found.</td></tr><?php endif; ?><?php foreach ($entries as $entry): ?><tr><td><?php echo htmlspecialchars((string)$entry['student_id'].' - '.$entry['student_name']); ?></td><td><?php echo htmlspecialchars((string)$entry['sponsor_name']); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$entry['sponsorship_type'])); ?></td><td><?php echo number_format((float)$entry['amount'], 2); ?></td><td><?php echo htmlspecialchars(ucfirst((string)$entry['status'])); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main><script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script></body></html>
