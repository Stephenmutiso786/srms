<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
if ($res !== "1") { header("location:../"); exit; }
$portalHome = ((string)$level === '1') ? 'academic' : 'admin';
app_require_permission('exams.manage', $portalHome);
app_require_unlocked('exams', $portalHome);

$types = [];
$exams = [];
$classes = [];
$terms = [];
$subjects = [];
$subjectClassMap = [];
$examSubjectsMap = [];
$examSubmissionGapMap = [];
$componentCandidates = [];
$gradingSystems = [];
$defaultGradingSystemId = 0;
$classGradingMap = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_grading_schema($conn);
	app_ensure_exam_type($conn);
	app_ensure_exam_weights_table($conn);

	if (app_table_exists($conn, 'tbl_exam_types')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_exam_types ORDER BY name");
		$stmt->execute();
		$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT c.id, c.name, c.grading_system_id, gs.name AS grading_name, gs.type AS grading_type
		FROM tbl_classes c
		LEFT JOIN tbl_grading_systems gs ON gs.id = c.grading_system_id
		ORDER BY c.id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($classes as $classRow) {
		$classGradingMap[(int)$classRow['id']] = [
			'grading_system_id' => (int)($classRow['grading_system_id'] ?? 0),
			'grading_name' => (string)($classRow['grading_name'] ?? ''),
			'grading_type' => (string)($classRow['grading_type'] ?? ''),
		];
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$gradingSystems = report_grading_systems($conn);
	foreach ($gradingSystems as $gradingSystem) {
		if ((int)($gradingSystem['is_default'] ?? 0) === 1) {
			$defaultGradingSystemId = (int)$gradingSystem['id'];
			break;
		}
	}
	if ($defaultGradingSystemId < 1 && !empty($gradingSystems)) {
		$defaultGradingSystemId = (int)$gradingSystems[0]['id'];
	}

	if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
		$stmt = $conn->prepare("SELECT subject_id, class_id FROM tbl_subject_class_assignments");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$subjectClassMap[(int)$row['subject_id']][] = (int)$row['class_id'];
		}
	}
	app_ensure_exam_subjects_table($conn);

	if (app_table_exists($conn, 'tbl_exams')) {
	$stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id,
			CASE WHEN COALESCE(e.status, 'draft') = 'open' THEN 'active' ELSE COALESCE(e.status, 'draft') END AS status,
			e.created_at, t.name AS term_name, c.name AS class_name, et.name AS type_name,
			gs.name AS grading_name, COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
			CASE
				WHEN COALESCE(e.assessment_mode, 'normal') = 'cbe' THEN
					COALESCE((SELECT COUNT(*) FROM tbl_cbe_mark_submissions cms WHERE cms.class_id = e.class_id AND cms.term_id = e.term_id), 0)
				ELSE
					COALESCE((SELECT COUNT(*) FROM tbl_exam_mark_submissions ms WHERE ms.exam_id = e.id), 0)
			END AS submission_count
			FROM tbl_exams e
			LEFT JOIN tbl_terms t ON t.id = e.term_id
			LEFT JOIN tbl_classes c ON c.id = e.class_id
			LEFT JOIN tbl_exam_types et ON et.id = e.exam_type_id
			LEFT JOIN tbl_grading_systems gs ON gs.id = e.grading_system_id
			ORDER BY e.created_at DESC");
		$stmt->execute();
		$exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $conn->prepare("SELECT es.exam_id, s.name
			FROM tbl_exam_subjects es
			JOIN tbl_subjects s ON s.id = es.subject_id
			ORDER BY s.name");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$examSubjectsMap[(int)$row['exam_id']][] = (string)$row['name'];
		}

		$stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, e.status,
			COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
			c.name AS class_name, t.name AS term_name
			FROM tbl_exams e
			LEFT JOIN tbl_classes c ON c.id = e.class_id
			LEFT JOIN tbl_terms t ON t.id = e.term_id
			ORDER BY e.created_at DESC");
		$stmt->execute();
		$componentCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

		foreach ($exams as $examRow) {
			$gapSummary = report_exam_submission_gap_summary($conn, (int)($examRow['id'] ?? 0));
			$examSubmissionGapMap[(int)($examRow['id'] ?? 0)] = $gapSummary;
		}
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load exam data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Exams</title>
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
<h1>Exam Management</h1>
<p>Create one assessment flow here for both normal exams and CBE assessments, using the subjects already selected in Class Management.</p>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><a class="btn btn-outline-primary btn-sm" href="admin/grading_system">Grading Management</a></li>
</ul>
</div>

<div class="tile mb-3">
<div class="tile-body">
<div class="alert alert-info mb-0">
<strong>Exam source of truth:</strong> classes, streams, class teachers, and class subjects come from <a href="admin/classes">Class Management</a>. This page only creates one assessment flow from that setup, whether the mode is normal or CBE.
<hr class="my-2">
<span class="small">Need to create or change the grading rules first? Open <a href="admin/grading_system">Grading Management</a>, then assign the grading system to each class in <a href="admin/classes">Class Management</a>.</span>
</div>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Create Exam Type</h3>
<form class="app_frm" action="admin/core/new_exam_type" method="POST">
<div class="mb-3">
<label class="form-label">Type Name</label>
<input class="form-control" name="name" required placeholder="CAT, Midterm, End Term">
</div>
<button class="btn btn-primary">Save Type</button>
</form>

<hr>
<h3 class="tile-title">Exam Types</h3>
<div class="table-responsive">
<form id="bulkExamTypesForm" method="POST" action="admin/core/bulk_delete_exam_types" onsubmit="return confirmBulkDeleteExams('types');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
  <div class="form-check ms-2">
	<input class="form-check-input" type="checkbox" id="selectAllExamTypes">
	<label class="form-check-label" for="selectAllExamTypes">Select all</label>
  </div>
</div>
<table class="table table-hover">
<thead><tr><th width="40"><input class="form-check-input" type="checkbox" id="selectAllExamTypesHead"></th><th>Name</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($types as $type): ?>
<tr>
<td><input class="form-check-input examtype-checkbox" type="checkbox" name="type_ids[]" value="<?php echo (int)$type['id']; ?>"></td>
<td><?php echo htmlspecialchars($type['name']); ?></td>
<td><?php echo ((int)$type['status'] === 1) ? 'Active' : 'Inactive'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>
</div>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Create Exam</h3>
<p class="text-muted">Create the assessment structure first, choose whether it is a normal exam, CBE assessment, or a consolidated average. Consolidated exams are auto-computed from selected exams, so they skip manual review and go straight to finalization and publishing. Normal exams can be activated, returned to draft, and reviewed again after reactivation; submitted marks will be auto-promoted so finalization is not blocked by review status.</p>
<div class="d-flex flex-wrap gap-2 mb-3">
	<a class="btn btn-outline-primary btn-sm" href="admin/exam_timetable"><i class="bi bi-calendar-event me-1"></i>Manage Timetable</a>
	<a class="btn btn-outline-secondary btn-sm" href="admin/results_locks"><i class="bi bi-lock me-1"></i>Results Locks</a>
	<a class="btn btn-outline-dark btn-sm" href="admin/marks_review"><i class="bi bi-clipboard-check me-1"></i>Marks Review</a>
	<a class="btn btn-outline-success btn-sm" href="admin/publish_results"><i class="bi bi-broadcast me-1"></i>Publish Results</a>
</div>
<form class="app_frm" action="admin/core/new_exam" method="POST">
<div class="row">
<div class="col-md-6 mb-3">
<label class="form-label">Exam Name</label>
<input class="form-control" name="name" required placeholder="Term 1 End Term">
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Exam Type</label>
<select class="form-control" name="exam_type_id">
<option value="">Optional</option>
<?php foreach ($types as $type): ?>
<option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Classes</label>
<select class="form-control" name="class_ids[]" id="examClassIds" required multiple size="6">
<?php foreach ($classes as $class): ?>
<option value="<?php echo $class['id']; ?>"><?php echo htmlspecialchars($class['name']); ?></option>
<?php endforeach; ?>
</select>
<div class="small text-muted mt-1">Hold Ctrl / Cmd to select more than one class.</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select</option>
<?php foreach ($terms as $term): ?>
<option value="<?php echo $term['id']; ?>"><?php echo htmlspecialchars($term['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Grading System</label>
<input class="form-control" id="examGradingSystemDisplay" type="text" value="Auto from selected class" readonly>
<div class="small text-muted mt-1">This is assigned automatically from Class Management for each selected class. Change it there, not here.</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Assessment Mode</label>
<select class="form-control" name="assessment_mode" id="assessmentModeSelect" required>
<option value="normal" selected>Normal Exam</option>
<option value="cbe">CBE Assessment</option>
<option value="KPSEA">KPSEA (Grade 6 National Assessment)</option>
<option value="KJSEA">KJSEA (Grade 9 National Assessment)</option>
<option value="consolidated">Consolidated (Average Multiple Exams)</option>
</select>
<div class="small text-muted mt-1">Use one exam module for both normal and CBE workflows. National assessments have fixed structures and prerequisites.</div>
</div>
<div class="col-md-12 mb-3" id="kjseaWarning" style="display:none;">
<div class="alert alert-warning" role="alert">
  <i class="bi bi-exclamation-triangle me-2"></i>
  <strong>KJSEA Prerequisite Check Required</strong>
  <p class="mb-0 mt-2">KJSEA (Grade 9) requires:</p>
  <ul class="mb-0 mt-2">
    <li>✅ All Grade 9 students must have SBA scores from Grade 7 & 8</li>
    <li>✅ All Grade 9 students must have KPSEA results</li>
  </ul>
  <p class="mb-0 mt-2 small">The system will validate these prerequisites when the exam is created. If any student is missing scores, the exam creation will be blocked.</p>
</div>
</div>
<div class="col-md-12 mb-3" id="componentExamWrap" style="display:none;">
<label class="form-label">Source Exams To Combine</label>
<select class="form-control" name="component_exam_ids[]" id="componentExamIds" multiple size="8">
<?php foreach ($componentCandidates as $candidate): ?>
<option value="<?php echo (int)$candidate['id']; ?>"
	data-class="<?php echo (int)$candidate['class_id']; ?>"
	data-term="<?php echo (int)$candidate['term_id']; ?>"
	data-mode="<?php echo htmlspecialchars((string)($candidate['assessment_mode'] ?? 'normal')); ?>"
	data-status="<?php echo htmlspecialchars((string)($candidate['status'] ?? 'draft')); ?>">
	<?php echo htmlspecialchars((string)$candidate['name'] . ' - ' . (string)($candidate['class_name'] ?? '') . ' (' . (string)($candidate['term_name'] ?? '') . ') [' . strtoupper((string)($candidate['status'] ?? 'draft')) . ']'); ?>
</option>
<?php endforeach; ?>
</select>
<div class="small text-muted mt-1">When consolidated mode is selected, choose at least two finalized or published exams in the same class and term.</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Weight Percentage</label>
<input class="form-control" type="number" name="weight_percentage" min="0" max="100" step="0.1" value="100">
<div class="small text-muted mt-1">Use this for consolidated/complex exam components, for example 20, 20, 10, 50.</div>
</div>
<div class="col-md-12 mb-3">
<div id="consolidatedSubjectNotice" class="alert alert-info" style="display:none;">
Subjects are pulled automatically from the selected source exams in consolidated mode. No manual subject selection or mark entry is needed.
</div>
<label class="form-label">Subjects</label>
<select class="form-control" name="subject_ids[]" id="examSubjectIds" multiple size="10">
<?php foreach ($subjects as $subject): $classesMap = $subjectClassMap[(int)$subject['id']] ?? []; ?>
<option value="<?php echo (int)$subject['id']; ?>" data-classes="<?php echo htmlspecialchars(json_encode($classesMap)); ?>">
	<?php echo htmlspecialchars($subject['name']); ?>
</option>
<?php endforeach; ?>
</select>
<div class="small text-muted mt-1">Choose the subjects that should appear in the exam for the selected classes.</div>
</div>
</div>
<button class="btn btn-primary">Create Exam</button>
</form>

<hr>
<h3 class="tile-title">Recent Exams</h3>
<div class="table-responsive">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <form id="bulkExamsForm" method="POST" action="admin/core/bulk_delete_exams" onsubmit="return confirmBulkDeleteExams('exams');" class="d-inline">
    <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
  </form>
  <div class="form-check ms-2">
	<input class="form-check-input" type="checkbox" id="selectAllExams">
	<label class="form-check-label" for="selectAllExams">Select all</label>
  </div>
</div>
<table class="table table-hover">
<thead>
<tr><th width="40"><input class="form-check-input" type="checkbox" id="selectAllExamsHead"></th><th>Name</th><th>Type</th><th>Mode</th><th>Class</th><th>Subjects</th><th>Term</th><th>Grading</th><th>Status</th><th>Submissions</th><th>Created</th><th>Action</th></tr>
</thead>
<tbody>
<?php foreach ($exams as $exam): ?>
<?php
$examSubjectTotal = count($examSubjectsMap[(int)$exam['id']] ?? []);
$submissionCount = (int)($exam['submission_count'] ?? 0);
$missingSubmissions = $examSubjectTotal > 0 && $submissionCount < $examSubjectTotal;
$gapSummary = $examSubmissionGapMap[(int)$exam['id']] ?? [];
$missingSubjectNames = array_map(static function ($row) {
	return (string)($row['subject_name'] ?? '');
}, (array)($gapSummary['missing_subjects'] ?? []));
?>
<tr>
<td><input class="form-check-input exam-checkbox" type="checkbox" name="exam_ids[]" value="<?php echo (int)$exam['id']; ?>" form="bulkExamsForm"></td>
<td><?php echo htmlspecialchars($exam['name']); ?></td>
<td><?php echo htmlspecialchars($exam['type_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars(strtoupper((string)($exam['assessment_mode'] ?? 'normal'))); ?></td>
<td><?php echo htmlspecialchars($exam['class_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars(implode(', ', $examSubjectsMap[(int)$exam['id']] ?? [])); ?></td>
<td><?php echo htmlspecialchars($exam['term_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($exam['grading_name'] ?? 'Default'); ?></td>
<td><span class="badge bg-<?php echo htmlspecialchars(app_exam_status_badge((string)($exam['status'] ?? 'draft'))); ?>"><?php echo htmlspecialchars(ucfirst((string)($exam['status'] ?? 'draft'))); ?></span></td>
<td title="Submitted/finalized subject sheets out of total exam subjects">
<?php echo $submissionCount; ?><?php echo $examSubjectTotal > 0 ? ' / ' . $examSubjectTotal : ''; ?>
<?php if ($missingSubmissions) { ?><div class="small text-danger">Missing</div><?php } ?>
<?php if ($missingSubmissions && !empty($missingSubjectNames)) { ?><div class="small text-muted"><?php echo htmlspecialchars(implode(', ', $missingSubjectNames)); ?></div><?php } ?>
</td>
<td><?php echo htmlspecialchars((string)($exam['created_at'] ?? '')); ?></td>
<td>
	<div class="d-flex flex-wrap gap-2">
	<?php
		$examClassId = (int)($exam['class_id'] ?? 0);
		$examTermId = (int)($exam['term_id'] ?? 0);
		$examIdValue = (int)($exam['id'] ?? 0);
		$examStatusValue = strtolower(trim((string)($exam['status'] ?? 'draft')));
		$canDownloadExamPdf = $examClassId > 0 && $examTermId > 0 && $examIdValue > 0
			&& in_array($examStatusValue, ['finalized', 'published'], true);
	?>
	<a class="btn btn-sm btn-outline-secondary" href="admin/edit_exam?id=<?php echo (int)$exam['id']; ?>">Edit</a>
	<?php if ($canDownloadExamPdf): ?>
	<a class="btn btn-sm btn-outline-primary" href="admin/merit_list_pdf?class_id=<?php echo $examClassId; ?>&term_id=<?php echo $examTermId; ?>&exam_id=<?php echo $examIdValue; ?>" target="_blank" title="Download class merit list PDF for this exam">Merit PDF</a>
	<a class="btn btn-sm btn-outline-success" href="admin/class_report_cards_pdf?class_id=<?php echo $examClassId; ?>&term_id=<?php echo $examTermId; ?>&exam=<?php echo $examIdValue; ?>&download=1" target="_blank" title="Download whole-class report cards PDF for this exam">Class PDF</a>
	<?php endif; ?>
	<form class="d-inline" action="admin/core/update_exam_status" method="POST">
		<input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
		<?php if (($exam['status'] ?? '') === 'draft') { ?>
			<button type="submit" class="btn btn-sm btn-outline-primary" name="status" value="active">Activate</button>
		<?php } elseif (($exam['status'] ?? '') === 'active' && ($exam['assessment_mode'] ?? 'normal') === 'consolidated') { ?>
			<button type="submit" class="btn btn-sm btn-outline-success" name="status" value="finalized">Finalize</button>
		<?php } elseif (($exam['status'] ?? '') === 'active') { ?>
			<button type="submit" class="btn btn-sm btn-outline-info" name="status" value="reviewed">Mark Reviewed</button>
		<?php } elseif (($exam['status'] ?? '') === 'reviewed' && !$missingSubmissions) { ?>
			<button type="submit" class="btn btn-sm btn-outline-success" name="status" value="finalized">Finalize</button>
		<?php } elseif (($exam['status'] ?? '') === 'reviewed' && $missingSubmissions) { ?>
			<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="All exam subjects must submit marks before finalizing">Finalize</button>
		<?php } elseif (($exam['status'] ?? '') === 'finalized' && !$missingSubmissions) { ?>
			<button type="submit" class="btn btn-sm btn-outline-dark" name="status" value="published">Publish</button>
		<?php } elseif (($exam['status'] ?? '') === 'finalized' && $missingSubmissions) { ?>
			<button type="button" class="btn btn-sm btn-outline-secondary" disabled title="All exam subjects must submit marks before publishing">Publish</button>
		<?php } elseif (($exam['status'] ?? '') === 'published') { ?>
			<button type="submit" class="btn btn-sm btn-outline-warning" name="status" value="finalized">Unpublish</button>
		<?php } ?>
	</form>
	<?php if (in_array((string)($exam['status'] ?? ''), ['active','reviewed'], true)) { ?>
	<form class="d-inline" action="admin/core/update_exam_status" method="POST">
		<input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
		<button type="submit" class="btn btn-sm btn-outline-secondary" name="status" value="draft">Back to Draft and Reopen Marks</button>
	</form>
	<?php } ?>
	</div>
</td>
</tr>
<?php endforeach; ?>
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
<?php require_once('const/check-reply.php'); ?>
<script>
function confirmBulkDeleteExams(label){
  var selector = label === 'types' ? '.examtype-checkbox:checked' : '.exam-checkbox:checked';
  if (!document.querySelectorAll(selector).length) {
    alert('Please select at least one ' + label + ' record to delete.');
    return false;
  }
  return confirm('Delete selected ' + label + '? This action cannot be undone.');
}
function bindSelectAll(sourceId, targetClass) {
  var source = document.getElementById(sourceId);
  if (!source) return;
  source.addEventListener('change', function(){
    document.querySelectorAll(targetClass).forEach(function(cb){
      cb.checked = source.checked;
    });
  });
}
bindSelectAll('selectAllExamTypes', '.examtype-checkbox');
bindSelectAll('selectAllExamTypesHead', '.examtype-checkbox');
bindSelectAll('selectAllExams', '.exam-checkbox');
bindSelectAll('selectAllExamsHead', '.exam-checkbox');

const classGradingMap = <?php echo json_encode($classGradingMap); ?>;

function updateExamGradingSystemDisplay() {
  var classSelect = document.getElementById('examClassIds');
  var display = document.getElementById('examGradingSystemDisplay');
  if (!classSelect || !display) return;
  var selectedClasses = Array.from(classSelect.selectedOptions).map(function(option) {
    return parseInt(option.value || '0', 10);
  }).filter(Boolean);
  if (!selectedClasses.length) {
    display.value = 'Auto from selected class';
    return;
  }

  var labels = selectedClasses.map(function(classId) {
    var meta = classGradingMap[classId] || {};
    if (meta.grading_name) {
      return meta.grading_name + (meta.grading_type ? ' (' + String(meta.grading_type).toUpperCase() + ')' : '');
    }
    var option = classSelect.querySelector('option[value="' + classId + '"]');
    var classLabel = option ? option.textContent.trim() : ('Class #' + classId);
    return classLabel + ': No grading system assigned';
  });
  var uniqueLabels = Array.from(new Set(labels));
  display.value = uniqueLabels.length === 1 ? uniqueLabels[0] : 'Multiple class grading systems will be applied automatically';
}

function filterExamSubjects() {
  var selectedClasses = Array.from(document.getElementById('examClassIds').selectedOptions).map(function(opt){ return parseInt(opt.value, 10); });
  document.querySelectorAll('#examSubjectIds option').forEach(function(option){
    var raw = option.getAttribute('data-classes') || '[]';
    var classes = [];
    try { classes = JSON.parse(raw); } catch (e) {}
    var visible = !classes.length || !selectedClasses.length || selectedClasses.some(function(classId){ return classes.includes(classId); });
    option.hidden = !visible;
    if (!visible) {
      option.selected = false;
    }
  });
}
document.getElementById('examClassIds').addEventListener('change', filterExamSubjects);
document.getElementById('examClassIds').addEventListener('change', updateExamGradingSystemDisplay);
const assessmentModeSelect = document.getElementById('assessmentModeSelect');
const componentExamWrap = document.getElementById('componentExamWrap');
const componentExamIds = document.getElementById('componentExamIds');
const termSelect = document.querySelector('select[name="term_id"]');
const consolidatedSubjectNotice = document.getElementById('consolidatedSubjectNotice');

function filterComponentExams() {
	const selectedClasses = Array.from(document.getElementById('examClassIds').selectedOptions).map(option => parseInt(option.value || '0', 10)).filter(Boolean);
	const selectedTerm = parseInt(termSelect.value || '0', 10);
	Array.from(componentExamIds.options).forEach(function(option) {
		const classId = parseInt(option.getAttribute('data-class') || '0', 10);
		const termId = parseInt(option.getAttribute('data-term') || '0', 10);
		const mode = (option.getAttribute('data-mode') || 'normal').toLowerCase();
		const status = (option.getAttribute('data-status') || 'draft').toLowerCase();
		const classMatches = selectedClasses.length === 0 || selectedClasses.includes(classId);
		const termMatches = !selectedTerm || termId === selectedTerm;
		const modeAllowed = mode !== 'cbe' && mode !== 'consolidated';
		const statusAllowed = status === 'finalized' || status === 'published';
		option.hidden = !(classMatches && termMatches && modeAllowed && statusAllowed);
		if (option.hidden) {
			option.selected = false;
		}
	});
}

function toggleAssessmentModeFields() {
	const mode = (assessmentModeSelect.value || 'normal').toLowerCase();
	const consolidated = mode === 'consolidated';
	const isKPSEA = mode === 'kpsea';
	const isKJSEA = mode === 'kjsea';
	const isNational = isKPSEA || isKJSEA;

	// Handle consolidated mode
	componentExamWrap.style.display = consolidated ? '' : 'none';
	componentExamIds.required = consolidated;
	consolidatedSubjectNotice.style.display = consolidated ? '' : 'none';
	document.getElementById('examSubjectIds').disabled = consolidated || isNational;

	// Handle national assessment modes (KPSEA/KJSEA)
	const examNameInput = document.querySelector('input[name="name"]');
	const examClassSelect = document.getElementById('examClassIds');
	const kjseaWarning = document.getElementById('kjseaWarning');

	if (isKPSEA) {
		// KPSEA: Grade 6 only
		examNameInput.value = 'KPSEA ' + new Date().getFullYear();
		examNameInput.disabled = false; // Allow override
		examClassSelect.disabled = false;
		// Auto-select Grade 6
		Array.from(examClassSelect.options).forEach(function(option) {
			if (option.text.includes('Grade 6') || option.text.includes('Form 1')) {
				option.selected = true;
			} else {
				option.selected = false;
			}
		});
		kjseaWarning.style.display = 'none';
		Array.from(document.getElementById('examSubjectIds').options).forEach(function(option) {
			option.selected = false;
		});
	} else if (isKJSEA) {
		// KJSEA: Grade 9 only
		examNameInput.value = 'KJSEA ' + new Date().getFullYear();
		examNameInput.disabled = false; // Allow override
		examClassSelect.disabled = false;
		// Auto-select Grade 9
		Array.from(examClassSelect.options).forEach(function(option) {
			if (option.text.includes('Grade 9') || option.text.includes('Form 3')) {
				option.selected = true;
			} else {
				option.selected = false;
			}
		});
		kjseaWarning.style.display = '';
		Array.from(document.getElementById('examSubjectIds').options).forEach(function(option) {
			option.selected = false;
		});
	} else {
		// Normal or CBE mode
		examNameInput.disabled = false;
		examClassSelect.disabled = false;
		kjseaWarning.style.display = 'none';
		document.getElementById('examSubjectIds').disabled = false;
	}

	if (consolidated) {
		Array.from(document.getElementById('examSubjectIds').options).forEach(function(option) {
			option.selected = false;
		});
		filterComponentExams();
	}
}

assessmentModeSelect.addEventListener('change', toggleAssessmentModeFields);
document.getElementById('examClassIds').addEventListener('change', filterComponentExams);
termSelect.addEventListener('change', filterComponentExams);
filterExamSubjects();
updateExamGradingSystemDisplay();
toggleAssessmentModeFields();
</script>
</body>
</html>
