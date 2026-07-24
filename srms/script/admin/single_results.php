<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}

if (isset($_SESSION['student_result'])) {
$std = $_SESSION['student_result']['student'];
$term = $_SESSION['student_result']['term'];
$examId = (int)($_SESSION['student_result']['exam'] ?? 0);
$studentData = null;
$termData = null;
$classData = null;
$subjectRows = [];
$selectedExamName = '';
$useExamId = false;
$lockedSubjectIds = [];
$hasLockedSubjects = false;
$selectedExamStatus = 'draft';
$allowAdminEdit = true;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, fname, mname, lname, class FROM tbl_students WHERE id = ?");
$stmt->execute([$std]);
$studentData = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id = ?");
$stmt->execute([$term]);
$termData = $stmt->fetch(PDO::FETCH_ASSOC);

$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
if ($examId > 0 && app_table_exists($conn, 'tbl_exams')) {
	$stmt = $conn->prepare("SELECT id, name, COALESCE(status, 'draft') AS status FROM tbl_exams WHERE id = ?");
	$stmt->execute([$examId]);
	$examData = $stmt->fetch(PDO::FETCH_ASSOC);
	$selectedExamName = (string)($examData['name'] ?? '');
	$selectedExamStatus = strtolower(trim((string)($examData['status'] ?? 'draft')));
	$allowAdminEdit = $selectedExamStatus !== 'published';
}

$studentClassId = (int)($studentData['class'] ?? 0);
$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
$stmt->execute([$studentClassId]);
$classData = $stmt->fetch(PDO::FETCH_ASSOC);

$subjectRows = report_fetch_subjects_for_class($conn, $studentClassId, (int)$term, $examId);

if ($useExamId && $examId > 0) {
	foreach ($subjectRows as $subjectRow) {
		$subjectId = (int)($subjectRow['combination_id'] ?? 0);
		if ($subjectId < 1) {
			continue;
		}
		$status = app_exam_submission_status($conn, $examId, $subjectId);
		if (in_array($status, ['submitted', 'reviewed', 'finalized'], true)) {
			$lockedSubjectIds[$subjectId] = $status;
			$hasLockedSubjects = true;
		}
	}
}

$tit = trim((string)($studentData['fname'] ?? '').' '.(string)($studentData['mname'] ?? '').' '.(string)($studentData['lname'] ?? '')).' ('.(string)($termData['name'] ?? 'Term').' Results'.($selectedExamName !== '' ? ' - '.$selectedExamName : '').')';
}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
header("location:./");
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - <?php echo $tit ?></title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
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
<h1><?php echo $tit ?></h1>
</div>
</div>


<div class="row">
<div class="col-md-12 ">
<div class="tile">
<div class="tile-body">
<div class="alert alert-info">This page opens in safe review mode. Click <strong>Edit</strong> on one subject to unlock only that score, then save.</div>

<form enctype="multipart/form-data" action="admin/core/update_results" class="app_frm row" method="POST" autocomplete="OFF">

<?php if ($useExamId && $examId < 1) { ?>
<div class="col-12">
<div class="alert alert-warning">Select a specific exam first before editing individual results.</div>
</div>
<?php } ?>
<?php if ($hasLockedSubjects) { ?>
<div class="col-12">
<div class="alert alert-info">Admin override is available for targeted corrections on finalized results. Published exams stay view-only.</div>
</div>
<?php } ?>
<?php if (!$allowAdminEdit) { ?>
<div class="col-12">
<div class="alert alert-warning">This exam is published. Marks can no longer be edited from here to avoid changing already sent results.</div>
</div>
<?php } ?>

<?php
foreach ($subjectRows as $row) {
$score = 0;
$subjectId = (int)($row['combination_id'] ?? 0);
$subjectLockedStatus = $lockedSubjectIds[$subjectId] ?? '';

if ($useExamId && $examId > 0) {
	$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? AND exam_id = ? LIMIT 1");
	$stmt->execute([(int)($studentData['class'] ?? 0), $subjectId, $term, $std, $examId]);
} else {
	$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? LIMIT 1");
	$stmt->execute([(int)($studentData['class'] ?? 0), $subjectId, $term, $std]);
}
$scoreValue = $stmt->fetchColumn();

if ($scoreValue !== false && $scoreValue !== null && $scoreValue !== '') {
$score = (float)$scoreValue;
}

?>

<div class="mb-3 col-md-2">
<label class="form-label"><?php echo htmlspecialchars((string)($row['subject_name'] ?? '')); ?></label>
<div class="d-flex gap-2 align-items-start">
<input value="<?php echo htmlspecialchars((string)$score); ?>" name="<?php echo $subjectId; ?>" class="form-control merit-subject-input" required type="number" placeholder="Enter score" readonly data-subject-input="<?php echo $subjectId; ?>">
<button class="btn btn-outline-primary btn-sm subject-edit-btn" type="button" data-target-subject="<?php echo $subjectId; ?>" <?php echo !$allowAdminEdit ? 'disabled' : ''; ?>>Edit</button>
</div>
<?php if ($subjectLockedStatus !== '') { ?>
<div class="small text-muted mt-1">Current sheet status: <?php echo htmlspecialchars(ucfirst($subjectLockedStatus)); ?></div>
<?php } ?>
</div>

<?php
}

?>
<input type="hidden" name="student" value="<?php echo $std; ?>">
<input type="hidden" name="term" value="<?php echo $term; ?>">
<input type="hidden" name="exam" value="<?php echo $examId; ?>">
<input type="hidden" name="class" value="<?php echo (int)($studentData['class'] ?? 0); ?>">
<input type="hidden" name="edit_mode" id="singleResultEditMode" value="0">
<div class="">
<button class="btn btn-primary app_btn" id="singleResultSaveBtn" type="submit" disabled>Save Results</button>
</div>
</form>


</div>
</div>
</div>
</div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script type="text/javascript">$('#srmsTable').DataTable({"sort" : false});</script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.select2').select2()
document.querySelectorAll('.subject-edit-btn').forEach((btn) => {
	btn.addEventListener('click', function () {
		if (this.hasAttribute('disabled')) {
			return;
		}
		const target = this.getAttribute('data-target-subject');
		document.querySelectorAll('.merit-subject-input').forEach((input) => {
			input.setAttribute('readonly', 'readonly');
		});
		document.querySelectorAll('.subject-edit-btn').forEach((otherBtn) => {
			otherBtn.classList.remove('btn-primary');
			otherBtn.classList.add('btn-outline-primary');
			otherBtn.textContent = 'Edit';
		});
		const input = document.querySelector('[data-subject-input="' + target + '"]');
		if (input) {
			input.removeAttribute('readonly');
			input.focus();
			input.select();
		}
		this.classList.remove('btn-outline-primary');
		this.classList.add('btn-primary');
		this.textContent = 'Editing';
		const mode = document.getElementById('singleResultEditMode');
		const saveBtn = document.getElementById('singleResultSaveBtn');
		if (mode) mode.value = target;
		if (saveBtn) saveBtn.disabled = false;
	});
});
</script>
</body>

</html>
