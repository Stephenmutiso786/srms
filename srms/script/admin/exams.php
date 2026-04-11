<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('exams.manage', 'admin');
app_require_unlocked('exams', 'admin');

$types = [];
$exams = [];
$classes = [];
$terms = [];
$subjects = [];
$subjectClassMap = [];
$examSubjectsMap = [];
$gradingSystems = [];
$defaultGradingSystemId = 0;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_exam_types')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_exam_types ORDER BY name");
		$stmt->execute();
		$types = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
	$stmt = $conn->prepare("SELECT e.id, e.name, e.status, e.created_at, t.name AS term_name, c.name AS class_name, et.name AS type_name,
			gs.name AS grading_name, COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
			COALESCE((SELECT COUNT(*) FROM tbl_exam_mark_submissions ms WHERE ms.exam_id = e.id), 0) AS submission_count
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
<p>Create one assessment flow here for both normal exams and CBC assessments, using the subjects already selected in Class Management.</p>
</div>
</div>

<div class="tile mb-3">
<div class="tile-body">
<div class="alert alert-info mb-0">
<strong>Exam source of truth:</strong> classes, streams, class teachers, and class subjects come from <a href="admin/classes">Class Management</a>. This page only creates one assessment flow from that setup, whether the mode is normal or CBC.
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
<p class="text-muted">Create the assessment structure first, choose whether it is a normal exam or CBC assessment, then activate it for teachers, review submitted marks, finalize, and publish when ready.</p>
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
<select class="form-control" name="grading_system_id" required>
<?php foreach ($gradingSystems as $system): ?>
<option value="<?php echo (int)$system['id']; ?>" <?php echo $defaultGradingSystemId === (int)$system['id'] ? 'selected' : ''; ?>>
	<?php echo htmlspecialchars($system['name']); ?> (<?php echo htmlspecialchars(strtoupper((string)$system['type'])); ?>)
</option>
<?php endforeach; ?>
</select>
<div class="small text-muted mt-1">This controls how scores become grades for reports, analytics, and publishing.</div>
</div>
<div class="col-md-6 mb-3">
<label class="form-label">Assessment Mode</label>
<select class="form-control" name="assessment_mode" required>
<option value="normal" selected>Normal Exam</option>
<option value="cbc">CBC Assessment</option>
</select>
<div class="small text-muted mt-1">Use one exam module for both normal and CBC workflows.</div>
</div>
<div class="col-md-12 mb-3">
<label class="form-label">Subjects</label>
<select class="form-control" name="subject_ids[]" id="examSubjectIds" required multiple size="10">
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
<form id="bulkExamsForm" method="POST" action="admin/core/bulk_delete_exams" onsubmit="return confirmBulkDeleteExams('exams');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <button type="submit" class="btn btn-danger btn-sm">Delete Selected</button>
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
<tr>
<td><input class="form-check-input exam-checkbox" type="checkbox" name="exam_ids[]" value="<?php echo (int)$exam['id']; ?>"></td>
<td><?php echo htmlspecialchars($exam['name']); ?></td>
<td><?php echo htmlspecialchars($exam['type_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars(strtoupper((string)($exam['assessment_mode'] ?? 'normal'))); ?></td>
<td><?php echo htmlspecialchars($exam['class_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars(implode(', ', $examSubjectsMap[(int)$exam['id']] ?? [])); ?></td>
<td><?php echo htmlspecialchars($exam['term_name'] ?? ''); ?></td>
<td><?php echo htmlspecialchars($exam['grading_name'] ?? 'Default'); ?></td>
<td><span class="badge bg-<?php echo htmlspecialchars(app_exam_status_badge((string)($exam['status'] ?? 'draft'))); ?>"><?php echo htmlspecialchars(ucfirst((string)($exam['status'] ?? 'draft'))); ?></span></td>
<td><?php echo (int)($exam['submission_count'] ?? 0); ?></td>
<td><?php echo htmlspecialchars((string)($exam['created_at'] ?? '')); ?></td>
<td>
	<div class="d-flex flex-wrap gap-2">
	<a class="btn btn-sm btn-outline-secondary" href="admin/edit_exam?id=<?php echo (int)$exam['id']; ?>">Edit</a>
	<form class="d-inline" action="admin/core/update_exam_status" method="POST">
		<input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
		<?php if (($exam['status'] ?? '') === 'draft') { ?>
			<button class="btn btn-sm btn-outline-primary" name="status" value="active">Activate</button>
		<?php } elseif (($exam['status'] ?? '') === 'active') { ?>
			<button class="btn btn-sm btn-outline-info" name="status" value="reviewed">Mark Reviewed</button>
		<?php } elseif (($exam['status'] ?? '') === 'reviewed') { ?>
			<button class="btn btn-sm btn-outline-success" name="status" value="finalized">Finalize</button>
		<?php } elseif (($exam['status'] ?? '') === 'finalized') { ?>
			<button class="btn btn-sm btn-outline-dark" name="status" value="published">Publish</button>
		<?php } elseif (($exam['status'] ?? '') === 'published') { ?>
			<button class="btn btn-sm btn-outline-warning" name="status" value="finalized">Unpublish</button>
		<?php } ?>
	</form>
	<?php if (in_array((string)($exam['status'] ?? ''), ['active','reviewed'], true)) { ?>
	<form class="d-inline" action="admin/core/update_exam_status" method="POST">
		<input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
		<button class="btn btn-sm btn-outline-secondary" name="status" value="draft">Back to Draft</button>
	</form>
	<?php } ?>
	</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</form>
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
filterExamSubjects();
</script>
</body>
</html>
