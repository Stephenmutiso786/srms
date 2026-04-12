<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}
app_require_permission('report.generate', 'admin');
app_require_unlocked('reports', 'admin');

$classes = [];
$terms = [];
$generatedCards = [];
$listClassId = (int)($_GET['list_class_id'] ?? 0);
$listTermId = (int)($_GET['list_term_id'] ?? 0);
$hasStudentEmail = false;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$hasStudentEmail = app_column_exists($conn, 'tbl_students', 'email');

	if (app_table_exists($conn, 'tbl_report_cards')) {
		$where = [];
		$params = [];
		if ($listClassId > 0) {
			$where[] = 'rc.class_id = ?';
			$params[] = $listClassId;
		}
		if ($listTermId > 0) {
			$where[] = 'rc.term_id = ?';
			$params[] = $listTermId;
		}

		$sql = "SELECT rc.id, rc.student_id, rc.class_id, rc.term_id, rc.mean, rc.grade, rc.position, rc.total_students,
			rc.verification_code, rc.generated_at, COALESCE(rc.downloads, 0) AS downloads,
			st.school_id, st.fname, st.mname, st.lname" . ($hasStudentEmail ? ', st.email AS student_email' : '') . ", c.name AS class_name, t.name AS term_name
			FROM tbl_report_cards rc
			LEFT JOIN tbl_students st ON st.id = rc.student_id
			LEFT JOIN tbl_classes c ON c.id = rc.class_id
			LEFT JOIN tbl_terms t ON t.id = rc.term_id";
		if (!empty($where)) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}
		$sql .= " ORDER BY rc.generated_at DESC, rc.id DESC LIMIT 250";

		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		$generatedCards = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Report Tool</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
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
<h1>Report Tool</h1>
</div>

</div>
<div class="row">
<div class="col-md-5 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Generate Report Cards</h3>
<p class="text-muted mb-3">Lock results first, then generate the full class report set. The system computes every learner's report card, stores the ranked merit list, and prepares the published documents for student, parent, and teacher access.</p>
<form enctype="multipart/form-data" action="admin/core/process_results" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="class_id" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term_id" required style="width: 100%;">
<option selected disabled value="">Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="">
<button class="btn btn-primary app_btn" type="submit">Generate Report Cards</button>
</div>
</form>
</div>

</div>
</div>
</div>

<div class="col-md-5 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Performance Summary</h3>
<p class="text-muted mb-3">Generate a class-level performance summary PDF.</p>
<form enctype="multipart/form-data" action="admin/core/start_report" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="student" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" required style="width: 100%;">
<option selected disabled value="">Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="">
<button class="btn btn-outline-primary app_btn" type="submit">Generate Summary Report</button>
</div>
</form>
</div>

</div>
</div>
</div>

<div class="col-12 mt-3">
<div class="tile">
<div class="tile-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<div>
<h3 class="tile-title mb-1">Merit List</h3>
<p class="text-muted mb-0">Generate a ranked class merit list and export it as a printable PDF.</p>
</div>
<a class="btn btn-primary" href="admin/merit_list"><i class="bi bi-trophy me-2"></i>Open Merit List</a>
</div>
</div>
</div>

<div class="col-12 mt-3">
<div class="tile">
<div class="tile-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<div>
<h3 class="tile-title mb-1">Result Delivery</h3>
<p class="text-muted mb-0">Publish exam results and send SMS or email notifications from one place.</p>
</div>
<a class="btn btn-success" href="admin/publish_results"><i class="bi bi-broadcast me-2"></i>Open Publish Results</a>
</div>
</div>
</div>
</div>

<div class="col-12 mt-3">
<div class="tile">
<div class="tile-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
<div>
<h3 class="tile-title mb-1">Generated Report Cards</h3>
<p class="text-muted mb-0">View generated report cards and download PDFs directly from this report tool.</p>
</div>
</div>

<form class="row g-2 align-items-end mb-3" method="GET" action="admin/report">
<div class="col-md-4">
<label class="form-label">Filter by Class</label>
<select class="form-control" name="list_class_id">
<option value="">All classes</option>
<?php foreach ($classes as $classOpt): ?>
<option value="<?php echo (int)$classOpt['id']; ?>" <?php echo ((int)$classOpt['id'] === $listClassId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$classOpt['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Filter by Term</label>
<select class="form-control" name="list_term_id">
<option value="">All terms</option>
<?php foreach ($terms as $termOpt): ?>
<option value="<?php echo (int)$termOpt['id']; ?>" <?php echo ((int)$termOpt['id'] === $listTermId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$termOpt['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4 d-flex gap-2">
<button class="btn btn-primary" type="submit">Apply Filter</button>
<a class="btn btn-outline-secondary" href="admin/report">Reset</a>
</div>
</form>

<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Student</th>
<th>Class</th>
<th>Term</th>
<th>Mean</th>
<th>Grade</th>
<th>Position</th>
<th>Generated</th>
<th>Downloads</th>
<th>Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($generatedCards as $cardRow): ?>
<tr>
<td>
<?php
	$studentName = trim((string)($cardRow['fname'] ?? '') . ' ' . (string)($cardRow['mname'] ?? '') . ' ' . (string)($cardRow['lname'] ?? ''));
	echo htmlspecialchars($studentName !== '' ? $studentName : (string)$cardRow['student_id']);
?>
<br><small class="text-muted"><?php echo htmlspecialchars((string)($cardRow['school_id'] !== '' ? $cardRow['school_id'] : $cardRow['student_id'])); ?></small>
</td>
<td><?php echo htmlspecialchars((string)($cardRow['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)($cardRow['term_name'] ?? '')); ?></td>
<td><?php echo number_format((float)$cardRow['mean'], 2); ?>%</td>
<td><span class="badge bg-primary"><?php echo htmlspecialchars((string)$cardRow['grade']); ?></span></td>
<td><?php echo (int)$cardRow['position']; ?> / <?php echo (int)$cardRow['total_students']; ?></td>
<td><?php echo htmlspecialchars((string)$cardRow['generated_at']); ?></td>
<td><?php echo (int)$cardRow['downloads']; ?></td>
<td>
<a class="btn btn-sm btn-primary" target="_blank" href="admin/save_pdf?std=<?php echo urlencode((string)$cardRow['student_id']); ?>&term=<?php echo (int)$cardRow['term_id']; ?>"><i class="bi bi-download me-1"></i>PDF</a>
<button class="btn btn-sm btn-info" type="button" onclick="openEmailModal('report_card', <?php echo (int)$cardRow['id']; ?>, '<?php echo htmlspecialchars(addslashes($studentName)); ?>', '<?php echo htmlspecialchars(addslashes((string)($cardRow['student_email'] ?? ''))); ?>')" title="Send via Email"><i class="bi bi-envelope me-1"></i>Email</button>
<a class="btn btn-sm btn-outline-secondary" target="_blank" href="verify_report?code=<?php echo urlencode((string)$cardRow['verification_code']); ?>"><i class="bi bi-shield-check me-1"></i>Verify</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$generatedCards): ?>
<tr><td colspan="9" class="text-muted text-center">No generated report cards found for the selected filter.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

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
<?php require_once('const/check-reply.php'); ?>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.select2').select2()

function openEmailModal(resultType, resultId, studentName, studentEmail) {
		document.getElementById('emailResultType').value = resultType;
		document.getElementById('emailResultId').value = resultId;
		document.getElementById('emailStudentName').textContent = studentName;
		document.getElementById('emailAddress').value = studentEmail || '';
		document.getElementById('emailModalLabel').textContent = resultType === 'certificate' ? 'Send Certificate via Email' : 'Send Report Card via Email';

		const modal = new bootstrap.Modal(document.getElementById('emailModal'));
		modal.show();
}

function sendEmailResult() {
		const resultType = document.getElementById('emailResultType').value;
		const resultId = document.getElementById('emailResultId').value;
		const email = document.getElementById('emailAddress').value.trim();
		const message = document.getElementById('emailMessage').value.trim();

		if (!email || !email.includes('@')) {
				alert('Please enter a valid email address');
				return;
		}

		const formData = new FormData();
		formData.append('result_type', resultType);
		formData.append('result_id', resultId);
		formData.append('recipient_email', email);
		formData.append('message', message);

		fetch('admin/core/email_result', {
				method: 'POST',
				body: formData
		}).then(response => {
				if (response.ok) {
						const modal = bootstrap.Modal.getInstance(document.getElementById('emailModal'));
						modal.hide();
						location.reload();
				} else {
						throw new Error('Failed to send email');
				}
		}).catch(error => {
				alert('Error: ' + error.message);
		});
}
</script>

<!-- Email Modal -->
<div class="modal fade" id="emailModal" tabindex="-1" aria-labelledby="emailModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title" id="emailModalLabel">Send Report Card via Email</h5>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body">
				<form id="emailForm">
					<input type="hidden" id="emailResultType">
					<input type="hidden" id="emailResultId">
					<div class="mb-3">
						<label class="form-label">Student:</label>
						<p class="form-control-plaintext" id="emailStudentName"></p>
					</div>
					<div class="mb-3">
						<label for="emailAddress" class="form-label">Recipient Email *</label>
						<input type="email" class="form-control" id="emailAddress" placeholder="Enter recipient email address" required>
						<small class="text-muted">Send to parent, guardian, or student email</small>
					</div>
					<div class="mb-3">
						<label for="emailMessage" class="form-label">Message (Optional)</label>
						<textarea class="form-control" id="emailMessage" rows="3" placeholder="Add a personal message to include in the email..."></textarea>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
				<button type="button" class="btn btn-primary" onclick="sendEmailResult()">
					<i class="bi bi-send"></i> Send Email
				</button>
			</div>
		</div>
	</div>
</div>
</body>

</html>
