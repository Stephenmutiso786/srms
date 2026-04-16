<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('exams.manage', 'admin');
app_require_unlocked('exams', 'admin');

$examId = (int)($_GET['id'] ?? 0);
if ($examId < 1) {
	$_SESSION['reply'] = array(array("danger", "Exam not found."));
	header("location:exams");
	exit;
}

$exam = null;
$types = [];
$classes = [];
$terms = [];
$subjects = [];
$subjectClassMap = [];
$selectedSubjects = [];
$selectedComponentExams = [];
$componentCandidates = [];
$gradingSystems = [];
$defaultGradingSystemId = 0;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_subjects_table($conn);
	app_ensure_exam_type($conn);
	app_ensure_exam_weights_table($conn);

	$stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$exam = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$exam) {
		throw new RuntimeException("Exam not found.");
	}
	app_ensure_exam_assessment_mode_column($conn);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_exam_types ORDER BY name");
	$stmt->execute();
	$types = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
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

	$selectedSubjects = app_exam_subject_ids($conn, $examId);
	$selectedComponentExams = app_exam_component_ids($conn, $examId);
	$examWeight = 100.0;
	if (app_table_exists($conn, 'tbl_exam_weights')) {
		$stmt = $conn->prepare("SELECT weight_percentage FROM tbl_exam_weights WHERE exam_id = ? LIMIT 1");
		$stmt->execute([$examId]);
		$examWeight = (float)($stmt->fetchColumn() ?: 100);
	}

	$stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, e.status,
		COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
		c.name AS class_name, t.name AS term_name
		FROM tbl_exams e
		LEFT JOIN tbl_classes c ON c.id = e.class_id
		LEFT JOIN tbl_terms t ON t.id = e.term_id
		WHERE e.id <> ?
		ORDER BY e.created_at DESC");
	$stmt->execute([$examId]);
	$componentCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
	header("location:exams");
	exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Edit Exam</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
	<div class="app-title">
		<div>
			<h1>Edit Exam</h1>
			<p>Update exam details and selected subjects.</p>
		</div>
	</div>
	<div class="tile">
		<div class="tile-body">
			<?php if (in_array((string)$exam['status'], ['finalized', 'published'], true)) { ?>
			<div class="alert alert-warning">This exam is already <?php echo htmlspecialchars($exam['status']); ?>. Only metadata changes that do not affect locked marks should be attempted.</div>
			<?php } ?>
			<form class="app_frm" action="admin/core/update_exam" method="POST">
				<input type="hidden" name="exam_id" value="<?php echo (int)$exam['id']; ?>">
				<div class="row">
					<div class="col-md-6 mb-3">
						<label class="form-label">Exam Name</label>
						<input class="form-control" name="name" required value="<?php echo htmlspecialchars((string)$exam['name']); ?>">
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">Exam Type</label>
						<select class="form-control" name="exam_type_id">
							<option value="">Optional</option>
							<?php foreach ($types as $type): ?>
							<option value="<?php echo (int)$type['id']; ?>" <?php echo ((int)$exam['exam_type_id'] === (int)$type['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($type['name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">Class</label>
						<select class="form-control" name="class_id" id="edit_class_id" required>
							<option value="">Select class</option>
							<?php foreach ($classes as $class): ?>
							<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$exam['class_id'] === (int)$class['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($class['name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">Term</label>
						<select class="form-control" name="term_id" required>
							<option value="">Select term</option>
							<?php foreach ($terms as $term): ?>
							<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$exam['term_id'] === (int)$term['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($term['name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">Grading System</label>
						<select class="form-control" name="grading_system_id" required>
							<?php foreach ($gradingSystems as $system): ?>
							<option value="<?php echo (int)$system['id']; ?>" <?php echo ((int)($exam['grading_system_id'] ?: $defaultGradingSystemId) === (int)$system['id']) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($system['name']); ?> (<?php echo htmlspecialchars(strtoupper((string)$system['type'])); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="col-md-6 mb-3">
						<label class="form-label">Assessment Mode</label>
						<select class="form-control" name="assessment_mode" id="edit_assessment_mode" required>
							<option value="normal" <?php echo (($exam['assessment_mode'] ?? 'normal') === 'normal') ? 'selected' : ''; ?>>Normal Exam</option>
							<option value="cbc" <?php echo (($exam['assessment_mode'] ?? 'normal') === 'cbc') ? 'selected' : ''; ?>>CBC Assessment</option>
							<option value="consolidated" <?php echo (($exam['assessment_mode'] ?? 'normal') === 'consolidated') ? 'selected' : ''; ?>>Consolidated (Average Multiple Exams)</option>
						</select>
					</div>
					<div class="col-md-12 mb-3" id="edit_component_wrap" style="display:none;">
						<label class="form-label">Source Exams To Combine</label>
						<select class="form-control" name="component_exam_ids[]" id="edit_component_exam_ids" multiple size="8">
							<?php foreach ($componentCandidates as $candidate): ?>
							<option value="<?php echo (int)$candidate['id']; ?>"
								data-class="<?php echo (int)$candidate['class_id']; ?>"
								data-term="<?php echo (int)$candidate['term_id']; ?>"
								data-mode="<?php echo htmlspecialchars((string)($candidate['assessment_mode'] ?? 'normal')); ?>"
								data-status="<?php echo htmlspecialchars((string)($candidate['status'] ?? 'draft')); ?>"
								<?php echo in_array((int)$candidate['id'], $selectedComponentExams, true) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars((string)$candidate['name'] . ' - ' . (string)($candidate['class_name'] ?? '') . ' (' . (string)($candidate['term_name'] ?? '') . ') [' . strtoupper((string)($candidate['status'] ?? 'draft')) . ']'); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<div class="small text-muted mt-1">Choose at least two finalized/published exams from the same class and term.</div>
					</div>
						<div class="col-md-6 mb-3">
							<label class="form-label">Weight Percentage</label>
							<input class="form-control" type="number" name="weight_percentage" min="0" max="100" step="0.1" value="<?php echo htmlspecialchars((string)$examWeight); ?>">
							<div class="small text-muted mt-1">Use this to build consolidated exam components like CAT 1 = 20%.</div>
						</div>
					<div class="col-md-12 mb-3">
						<label class="form-label">Subjects</label>
						<select class="form-control" name="subject_ids[]" id="edit_subject_ids" multiple size="10">
							<?php foreach ($subjects as $subject): $classesMap = $subjectClassMap[(int)$subject['id']] ?? []; ?>
							<option value="<?php echo (int)$subject['id']; ?>"
								data-classes="<?php echo htmlspecialchars(json_encode($classesMap)); ?>"
								<?php echo in_array((int)$subject['id'], $selectedSubjects, true) ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($subject['name']); ?>
							</option>
							<?php endforeach; ?>
						</select>
						<div class="small text-muted mt-1">Only subjects assigned to the selected class should be included in this exam.</div>
					</div>
				</div>
				<div class="d-flex gap-2">
					<button class="btn btn-primary">Save Changes</button>
					<a class="btn btn-light" href="admin/exams">Back</a>
				</div>
			</form>
		</div>
	</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
function filterEditSubjects() {
	const classId = parseInt(document.getElementById('edit_class_id').value || '0', 10);
	document.querySelectorAll('#edit_subject_ids option').forEach(function(option) {
		const raw = option.getAttribute('data-classes') || '[]';
		let classes = [];
		try { classes = JSON.parse(raw); } catch (e) {}
		const visible = !classes.length || !classId || classes.includes(classId);
		option.hidden = !visible;
	});
}
document.getElementById('edit_class_id').addEventListener('change', filterEditSubjects);
function filterEditComponentExams() {
	const classId = parseInt(document.getElementById('edit_class_id').value || '0', 10);
	const termId = parseInt(document.querySelector('select[name="term_id"]').value || '0', 10);
	document.querySelectorAll('#edit_component_exam_ids option').forEach(function(option) {
		const optionClass = parseInt(option.getAttribute('data-class') || '0', 10);
		const optionTerm = parseInt(option.getAttribute('data-term') || '0', 10);
		const mode = (option.getAttribute('data-mode') || 'normal').toLowerCase();
		const status = (option.getAttribute('data-status') || 'draft').toLowerCase();
		const visible = (!classId || optionClass === classId)
			&& (!termId || optionTerm === termId)
			&& mode !== 'cbc'
			&& mode !== 'consolidated'
			&& (status === 'finalized' || status === 'published');
		option.hidden = !visible;
		if (!visible) {
			option.selected = false;
		}
	});
}
function toggleEditAssessmentMode() {
	const mode = (document.getElementById('edit_assessment_mode').value || 'normal').toLowerCase();
	const consolidated = mode === 'consolidated';
	document.getElementById('edit_component_wrap').style.display = consolidated ? '' : 'none';
	document.getElementById('edit_component_exam_ids').required = consolidated;
	if (consolidated) {
		filterEditComponentExams();
	}
}
document.getElementById('edit_assessment_mode').addEventListener('change', toggleEditAssessmentMode);
document.querySelector('select[name="term_id"]').addEventListener('change', filterEditComponentExams);
document.getElementById('edit_class_id').addEventListener('change', filterEditComponentExams);
filterEditSubjects();
toggleEditAssessmentMode();
</script>
</body>
</html>
