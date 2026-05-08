<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}
app_require_permission('report.generate', 'admin');
app_require_unlocked('reports', 'admin');

$classes = [];
$terms = [];
$generatedCards = [];
$reportGroups = [];
$listClassId = (int)($_GET['list_class_id'] ?? 0);
$listTermId = (int)($_GET['list_term_id'] ?? 0);
$listExamId = (int)($_GET['list_exam_id'] ?? 0);
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
	report_ensure_exam_batch_schema($conn);

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
		if ($listExamId > 0 && app_column_exists($conn, 'tbl_report_cards', 'exam_id')) {
			$where[] = 'rc.exam_id = ?';
			$params[] = $listExamId;
		}

		$sql = "SELECT rc.id, rc.student_id, rc.class_id, rc.term_id, rc.mean, rc.grade, rc.position, rc.total_students,
			rc.verification_code, rc.generated_at, COALESCE(rc.downloads, 0) AS downloads, " . (app_column_exists($conn, 'tbl_report_cards', 'exam_id') ? 'COALESCE(rc.exam_id, 0) AS exam_id,' : '0 AS exam_id,') . "
			COALESCE(ex.name, 'Unclassified') AS exam_name, COALESCE(et.name, '') AS exam_type,
			st.school_id, st.fname, st.mname, st.lname" . ($hasStudentEmail ? ', st.email AS student_email' : '') . ", c.name AS class_name, t.name AS term_name
			FROM tbl_report_cards rc
			LEFT JOIN tbl_students st ON st.id = rc.student_id
			LEFT JOIN tbl_classes c ON c.id = rc.class_id
			LEFT JOIN tbl_terms t ON t.id = rc.term_id
			LEFT JOIN tbl_exams ex ON ex.id = rc.exam_id
			LEFT JOIN tbl_exam_types et ON et.id = ex.exam_type_id";
		if (!empty($where)) {
			$sql .= " WHERE " . implode(' AND ', $where);
		}
		$sql .= " ORDER BY rc.generated_at DESC, rc.id DESC LIMIT 250";

		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		$generatedCards = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($generatedCards as $cardRow) {
			$groupKey = implode('|', [
				(int)($cardRow['term_id'] ?? 0),
				(string)($cardRow['exam_name'] ?? 'Unclassified'),
				(string)($cardRow['exam_type'] ?? ''),
			]);
			if (!isset($reportGroups[$groupKey])) {
				$reportGroups[$groupKey] = [
					'term_id' => (int)($cardRow['term_id'] ?? 0),
					'term_name' => (string)($cardRow['term_name'] ?? ''),
					'exam_name' => (string)($cardRow['exam_name'] ?? 'Unclassified'),
					'exam_type' => (string)($cardRow['exam_type'] ?? ''),
					'classes' => [],
					'rows' => [],
					'downloads' => 0,
				];
			}
			$classId = (int)($cardRow['class_id'] ?? 0);
			if ($classId > 0 && !isset($reportGroups[$groupKey]['classes'][$classId])) {
				$reportGroups[$groupKey]['classes'][$classId] = (string)($cardRow['class_name'] ?? ('Class ' . $classId));
			}
			$reportGroups[$groupKey]['rows'][] = $cardRow;
			$reportGroups[$groupKey]['downloads'] += (int)($cardRow['downloads'] ?? 0);
		}
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
<div>
<a href="admin/downloads_center" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i> Downloads Hub</a>
</div>
</div>
<div class="row">
<div class="col-md-6 mb-4">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Generate Report Cards</h3>
<p class="text-muted mb-3">Lock results first, then generate the full class report set. The system computes every learner's report card, stores the ranked merit list, and prepares the published documents for student, parent, and teacher access.</p>
<form enctype="multipart/form-data" action="admin/core/process_results" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="class_id" id="genClassSelect" required style="width: 100%;" onchange="loadPublishedExams('genClassSelect','genTermSelect','genExamSelect');">
<option value="" selected disabled> Select One</option>
<?php foreach ($classes as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term_id" id="genTermSelect" required style="width: 100%;" onchange="loadPublishedExams('genClassSelect','genTermSelect','genExamSelect');">
<option selected disabled value="">Select One</option>
<?php foreach ($terms as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Exam</label>
<select class="form-control select2" name="exam_id" id="genExamSelect" required style="width: 100%;">
<option selected disabled value="">Select class and term first</option>
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

<div class="col-md-6 mb-4">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Performance Summary</h3>
<p class="text-muted mb-3">Generate a class-level performance summary PDF.</p>
<form enctype="multipart/form-data" action="admin/core/start_report" class="app_frm" method="POST" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="student" id="sumClassSelect" required style="width: 100%;" onchange="loadPublishedExams('sumClassSelect','sumTermSelect','sumExamSelect');">
<option value="" selected disabled> Select One</option>
<?php foreach ($classes as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" id="sumTermSelect" required style="width: 100%;" onchange="loadPublishedExams('sumClassSelect','sumTermSelect','sumExamSelect');">
<option selected disabled value="">Select One</option>
<?php foreach ($terms as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Exam</label>
<select class="form-control select2" name="exam" id="sumExamSelect" required style="width: 100%;">
<option selected disabled value="">Select class and term first</option>
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
<div class="tile-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
<div>
<h3 class="tile-title mb-1">Merit List</h3>
<p class="text-muted mb-0">Generate and open a ranked class merit list directly from the report tool.</p>
</div>
<a class="btn btn-outline-primary" href="admin/merit_list"><i class="bi bi-trophy me-2"></i>Open Merit List Page</a>
</div>
<form class="row g-2 align-items-end" method="GET" action="admin/merit_list">
<div class="col-md-3">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="class_id" id="meritToolClassSelect" required style="width: 100%;" onchange="loadPublishedExams('meritToolClassSelect','meritToolTermSelect','meritToolExamSelect');">
<option value="" selected disabled>Select One</option>
<?php foreach ($classes as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term_id" id="meritToolTermSelect" required style="width: 100%;" onchange="loadPublishedExams('meritToolClassSelect','meritToolTermSelect','meritToolExamSelect');">
<option value="" selected disabled>Select One</option>
<?php foreach ($terms as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>
<div class="col-md-3">
<label class="form-label">Select Exam</label>
<select class="form-control select2" name="exam_id" id="meritToolExamSelect" style="width: 100%;">
<option value="">All Published Exams / Term</option>
</select>
</div>
<div class="col-md-3 d-flex gap-2">
<button class="btn btn-primary flex-fill" type="submit"><i class="bi bi-trophy me-2"></i>Generate Merit List</button>
<button class="btn btn-outline-primary" type="submit" formaction="admin/merit_list_pdf" formtarget="_blank"><i class="bi bi-download me-2"></i>PDF</button>
</div>
</form>
</div>
</div>
</div>

<div class="col-12 mt-3">
<div class="tile">
<div class="tile-body d-flex justify-content-between align-items-center flex-wrap gap-2">
<div>
<h3 class="tile-title mb-1">Bulk Results</h3>
<p class="text-muted mb-0">Open the class results view, print all results, or export the bulk sheet for a selected class and term.</p>
</div>
<a class="btn btn-danger" href="admin/bulk_results"><i class="bi bi-printer me-2"></i>Open Bulk Results</a>
</div>
</div>
</div>

<div class="col-12 mt-3">
<div class="tile">
<div class="tile-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
<div>
<h3 class="tile-title mb-1">Bulk Report Card Downloads</h3>
<p class="text-muted mb-0">Visible bulk actions for report cards. Select the class, term, and exam batch you want, then print or download directly from the report tool.</p>
</div>
</div>

<form class="row g-2 align-items-end" method="GET" action="admin/class_report_pdf">
<div class="col-md-3">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="class_id" id="bulkClassSelect" required style="width: 100%;" onchange="loadPublishedExams('bulkClassSelect','bulkTermSelect','bulkExamSelect');">
<option value="" selected disabled>Select One</option>
<?php foreach ($classes as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term_id" id="bulkTermSelect" required style="width: 100%;" onchange="loadPublishedExams('bulkClassSelect','bulkTermSelect','bulkExamSelect');">
<option selected disabled value="">Select One</option>
<?php foreach ($terms as $row) { ?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?></option>
<?php } ?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Select Exam</label>
<select class="form-control select2" name="exam_id" id="bulkExamSelect" required style="width: 100%;">
<option selected disabled value="">Select class and term first</option>
</select>
</div>

<div class="col-md-3 d-flex gap-2">
<button class="btn btn-outline-primary" type="submit" formaction="admin/class_report_cards_pdf" formtarget="_blank"><i class="bi bi-printer me-1"></i>Print Class Batch</button>
<button class="btn btn-primary" type="submit" formaction="admin/class_report_cards_pdf" name="download" value="1" formtarget="_blank"><i class="bi bi-download me-1"></i>Download Class PDF</button>
</div>
</form>
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
<p class="text-muted mb-0">Generated batches are now separated by exam name and exam type so new report cards do not mix with old ones.</p>
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

<?php if ($reportGroups): ?>
<div class="row g-3 mb-4">
<?php foreach ($reportGroups as $group): ?>
<div class="col-12">
<div class="border rounded p-3 bg-light">
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
<div>
<h5 class="mb-1"><?php echo htmlspecialchars($group['exam_name']); ?></h5>
<p class="text-muted mb-1"><?php echo htmlspecialchars($group['term_name']); ?><?php echo $group['exam_type'] !== '' ? ' • ' . htmlspecialchars($group['exam_type']) : ''; ?></p>
<small class="text-muted"><?php echo count($group['rows']); ?> report cards across <?php echo count($group['classes']); ?> class(es)</small>
</div>
<div class="d-flex flex-wrap gap-2">
<a class="btn btn-primary btn-sm" target="_blank" href="admin/core/download_all_reports?batch_term_id=<?php echo (int)$group['term_id']; ?>&batch_exam_name=<?php echo urlencode($group['exam_name']); ?>&batch_exam_type=<?php echo urlencode($group['exam_type']); ?>&view=1"><i class="bi bi-printer me-1"></i>Print/View All</a>
<a class="btn btn-outline-primary btn-sm" href="admin/core/download_all_reports?batch_term_id=<?php echo (int)$group['term_id']; ?>&batch_exam_name=<?php echo urlencode($group['exam_name']); ?>&batch_exam_type=<?php echo urlencode($group['exam_type']); ?>&download=1"><i class="bi bi-download me-1"></i>Download All PDF</a>
</div>
</div>

<form class="row g-2 align-items-end" method="GET" action="admin/core/download_all_reports">
<input type="hidden" name="batch_term_id" value="<?php echo (int)$group['term_id']; ?>">
<input type="hidden" name="batch_exam_name" value="<?php echo htmlspecialchars($group['exam_name']); ?>">
<input type="hidden" name="batch_exam_type" value="<?php echo htmlspecialchars($group['exam_type']); ?>">
<input type="hidden" name="download" value="1">
<div class="col-md-8">
<label class="form-label">Download Selected Classes Only</label>
<select class="form-control select2" name="class_ids[]" multiple data-placeholder="Choose one or more classes">
<?php foreach ($group['classes'] as $classId => $className): ?>
<option value="<?php echo (int)$classId; ?>"><?php echo htmlspecialchars($className); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4 d-flex gap-2">
<button class="btn btn-success" type="submit"><i class="bi bi-file-earmark-pdf me-1"></i>Download Selected Bulk PDF</button>
</div>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-hover">
<thead>
<tr>
<th>Student</th>
<th>Class</th>
<th>Term</th>
<th>Exam</th>
<th>Mean Band</th>
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
<td><?php echo htmlspecialchars((string)($cardRow['exam_name'] ?? 'Unclassified')); ?><?php echo !empty($cardRow['exam_type']) ? '<br><small class="text-muted">' . htmlspecialchars((string)$cardRow['exam_type']) . '</small>' : ''; ?></td>
<td><?php echo htmlspecialchars((string)$cardRow['grade']); ?></td>
<td><span class="badge bg-primary"><?php echo htmlspecialchars((string)$cardRow['grade']); ?></span></td>
<td><?php echo (int)$cardRow['position']; ?> / <?php echo (int)$cardRow['total_students']; ?></td>
<td><?php echo htmlspecialchars((string)$cardRow['generated_at']); ?></td>
<td><?php echo (int)$cardRow['downloads']; ?></td>
<td>
<a class="btn btn-sm btn-primary" target="_blank" href="admin/save_pdf?std=<?php echo urlencode((string)$cardRow['student_id']); ?>&term=<?php echo (int)$cardRow['term_id']; ?><?php echo (int)($cardRow['exam_id'] ?? 0) > 0 ? '&exam=' . (int)$cardRow['exam_id'] : ''; ?>&download=1"><i class="bi bi-download me-1"></i>PDF</a>
<button class="btn btn-sm btn-info" type="button" onclick="openEmailModal('report_card', <?php echo (int)$cardRow['id']; ?>, '<?php echo htmlspecialchars(addslashes($studentName)); ?>', '<?php echo htmlspecialchars(addslashes((string)($cardRow['student_email'] ?? ''))); ?>')" title="Send via Email"><i class="bi bi-envelope me-1"></i>Email</button>
<!-- Verify button removed as requested -->
<form method="POST" action="admin/core/delete_report_card" class="d-inline" onsubmit="return confirm('Delete this generated report card? This cannot be undone.');">
<input type="hidden" name="report_id" value="<?php echo (int)$cardRow['id']; ?>">
<input type="hidden" name="list_class_id" value="<?php echo (int)$listClassId; ?>">
<input type="hidden" name="list_term_id" value="<?php echo (int)$listTermId; ?>">
<input type="hidden" name="list_exam_id" value="<?php echo (int)$listExamId; ?>">
<button class="btn btn-sm btn-danger" type="submit"><i class="bi bi-trash me-1"></i>Delete</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$generatedCards): ?>
<tr><td colspan="10" class="text-muted text-center">No generated report cards found for the selected filter.</td></tr>
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

function loadPublishedExams(classSelectId, termSelectId, examSelectId) {
	var classId = $('#' + classSelectId).val() || '';
	var termId = $('#' + termSelectId).val() || '';
	var examSelect = $('#' + examSelectId);
	if (!examSelect.length) {
		return;
	}
	examSelect.empty();
	if (classId === '' || termId === '') {
		examSelect.append('<option selected disabled value="">Select class and term first</option>');
		return;
	}
	$.post('app/ajax/fetch_exams.php', {id: classId, term_id: termId, include_unpublished: 1, submit: 1}, function(data){
		examSelect.html(data);
		examSelect.trigger('change.select2');
	});
}

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
		formData.append('return_to', '../report');

		fetch('admin/core/email_result', {
				method: 'POST',
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest'
				},
				credentials: 'same-origin',
				body: formData
		}).then(response => response.json())
		  .then(data => {
				if (data && data.ok) {
						const modal = bootstrap.Modal.getInstance(document.getElementById('emailModal'));
						modal.hide();
						if (window.Swal) {
							Swal.fire('Success', data.message || 'Email sent successfully.', 'success').then(() => location.reload());
							return;
						}
						location.reload();
				} else {
						throw new Error((data && data.message) ? data.message : 'Failed to send email');
				}
		}).catch(error => {
				if (window.Swal) {
					Swal.fire('Failed', error.message, 'error');
					return;
				}
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
