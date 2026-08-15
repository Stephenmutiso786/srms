<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

$classStudents = [];
$selectedClassId = 0;
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1 ORDER BY class_id");
		$stmt->execute([$account_id]);
		$allowedClasses = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
	} else {
		$allowedClasses = [];
	}
} catch (Throwable $e) {
	$allowedClasses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Import Results</title>
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
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("teacher/partials/sidebar.php"); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Import Results</h1>
</div>
</div>

<div class="row">
<div class="col-md-4 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Import Results</h3>
<form class="app_frm" enctype="multipart/form-data" method="POST" autocomplete="OFF" action="teacher/core/import_results">

<div class="mb-2">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" id="termSelect" required style="width: 100%;">
<option selected disabled value="">Select Term</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
$result = app_sort_term_rows($result);

foreach($result as $row)
{
?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option>
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

<div class="mb-2">
<label class="form-label">Select Class</label>
<select onchange="fetch_subjects(this.value); loadExamOptions();" class="form-control select2" name="class" id="classSelect" required style="width: 100%;">
<option selected disabled value="">Select Class</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

if (app_table_exists($conn, 'tbl_teacher_assignments')) {
  $stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1");
  $stmt->execute([$account_id]);
  $myclasses = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
  $stmt = $conn->prepare("SELECT tbl_subject_combinations.class AS class_list
  FROM tbl_subject_combinations
  WHERE tbl_subject_combinations.teacher = ?");
  $stmt->execute([$account_id]);
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $myclasses = array();
  foreach ($result as $value) {
    $class_arr = app_unserialize((string)($value['class_list'] ?? ''));
    foreach ($class_arr as $value) {
      array_push($myclasses, $value);
    }
  }
}

if (!empty($myclasses)) {
  $matches = str_split(str_repeat("?", count($myclasses)));
  $matches = implode(",", $matches);
  $stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($matches) ORDER BY name");
  $stmt->execute($myclasses);
  $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
  $result = [];
}

foreach($result as $row)
{
?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option>
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

<div class="mb-2">
<label class="form-label">Select Subject</label>
<select class="form-control" name="subject" required id="sub_imp">
<option selected disabled value="">Select One</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Paste Results</label>
<textarea class="form-control" name="paste_results" id="pasteResultsInput" rows="10" placeholder="Examples:
12345, 84
Mary Jane 76
71
68
66"></textarea>
<div class="form-text">Use one row per student. You can paste names with scores, or just scores in the exact class list order shown in the preview below.</div>
</div>

<div class="mb-3">
<div class="card">
<div class="card-body">
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
<strong>Live Preview</strong>
<span class="text-muted small">Matches names first, then falls back to the class list order.</span>
</div>
<div id="previewWarnings" class="mb-2"></div>
<div class="table-responsive" style="max-height:320px;">
<table class="table table-sm table-bordered align-middle mb-0">
<thead><tr><th style="width:60px;">#</th><th>Student</th><th style="width:140px;">Score</th><th style="width:120px;">Match</th></tr></thead>
<tbody id="marksPreviewBody"><tr><td colspan="4" class="text-muted">Select a class and paste marks to see the mapping preview.</td></tr></tbody>
</table>
</div>
</div>
</div>
</div>

<div class="mb-2">
<label class="form-label">Select Exam</label>
<select class="form-control select2" name="exam" required id="examSelect" style="width: 100%;">
<option selected disabled value="">Select class and term first</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">CSV File</label>
<input accept=".csv" type="file" name="file" class="form-control">
</div>


<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Import Results</button>
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
$('.select2').select2();

const studentListCache = {};
let classStudents = [];
let previewHasErrors = false;

function normalizeName(value) {
	return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
}

function parsePasteLines(text) {
	return String(text || '').split(/\r\n|\r|\n/).map((line) => line.trim()).filter(Boolean);
}

function fetchClassStudents(classId) {
	const id = parseInt(classId || '0', 10);
	if (id < 1) {
		classStudents = [];
		renderMarksPreview();
		return;
	}
	if (studentListCache[id]) {
		classStudents = studentListCache[id];
		renderMarksPreview();
		return;
	}
	$.getJSON('app/ajax/fetch_students.php', { class_id: id }, function(data) {
		classStudents = Array.isArray(data.students) ? data.students : [];
		studentListCache[id] = classStudents;
		renderMarksPreview();
	});
}

function loadExamOptions() {
	const classId = parseInt($('#classSelect').val() || '0', 10);
	const termId = parseInt($('#termSelect').val() || '0', 10);
	if (classId < 1 || termId < 1) {
		$('#examSelect').html('<option selected disabled value="">Select class and term first</option>').trigger('change.select2');
		return;
	}
	$.post('app/ajax/fetch_exams.php', {id: classId, term_id: termId, submit: 1}, function(data){
		$('#examSelect').html(data).trigger('change.select2');
	});
}

$('#termSelect').on('change', loadExamOptions);
$('#classSelect').on('change', function() {
	fetchClassStudents($(this).val());
});
$('#pasteResultsInput').on('input', renderMarksPreview);

function renderMarksPreview() {
	const lines = parsePasteLines($('#pasteResultsInput').val());
	const rows = [];
	const warnings = [];
	const hasPaste = lines.length > 0;
	if (!classStudents.length && lines.length === 0) {
		$('#marksPreviewBody').html('<tr><td colspan="4" class="text-muted">Select a class and paste marks to see the mapping preview.</td></tr>');
		$('#previewWarnings').html('');
		previewHasErrors = false;
		toggleImportButton();
		return;
	}
	let sequentialIndex = 0;
	const usedIds = new Set();
	const seenNames = new Map();
	lines.forEach((line, idx) => {
		let match = 'No match';
		let studentName = '';
		let score = '';
		const namedMatch = line.match(/^(.+?)\s*(?:,|\||\t|\s{2,})\s*(-?\d+(?:\.\d+)?)$/);
		const scoreOnly = line.match(/^(-?\d+(?:\.\d+)?)$/);
		if (namedMatch) {
			studentName = namedMatch[1].trim();
			score = namedMatch[2];
			const scoreNum = parseFloat(score);
			if (scoreNum < 0 || scoreNum > 100) {
				warnings.push('Score out of range on row ' + (idx + 1) + ': ' + score + ' (must be 0-100).');
			}
			const normalized = normalizeName(studentName);
			const found = classStudents.find((student) => normalizeName(student.name) === normalized || normalizeName(student.name).includes(normalized));
			if (found) {
				match = found.name;
				if (seenNames.has(found.id)) {
					warnings.push('Duplicate student matched more than once: ' + found.name + '.');
				}
				seenNames.set(found.id, (seenNames.get(found.id) || 0) + 1);
				usedIds.add(found.id);
			} else {
				warnings.push('Unmatched name on row ' + (idx + 1) + ': ' + studentName + '.');
			}
		} else if (scoreOnly) {
			score = scoreOnly[1];
			const scoreNum = parseFloat(score);
			if (scoreNum < 0 || scoreNum > 100) {
				warnings.push('Score out of range on row ' + (idx + 1) + ': ' + score + ' (must be 0-100).');
			}
			const nextStudent = classStudents[sequentialIndex] || null;
			if (nextStudent) {
				match = nextStudent.name;
				studentName = nextStudent.name;
				if (seenNames.has(nextStudent.id)) {
					warnings.push('Class list student reused more than once: ' + nextStudent.name + '.');
				}
				seenNames.set(nextStudent.id, (seenNames.get(nextStudent.id) || 0) + 1);
				usedIds.add(nextStudent.id);
			} else {
				warnings.push('More score-only rows were pasted than students available in the class list.');
			}
			sequentialIndex++;
		} else {
			warnings.push('Unrecognized format on row ' + (idx + 1) + '. Use "Name, Score" or just a score.');
		}
		rows.push('<tr><td>' + (idx + 1) + '</td><td>' + escapeHtml(studentName || line) + '</td><td>' + escapeHtml(score || '-') + '</td><td>' + escapeHtml(match) + '</td></tr>');
	});
	if (!rows.length) {
		$('#marksPreviewBody').html('<tr><td colspan="4" class="text-muted">No pasted marks yet.</td></tr>');
		$('#previewWarnings').html('');
		previewHasErrors = false;
		toggleImportButton();
		return;
	}
	$('#marksPreviewBody').html(rows.join(''));
	const missingStudents = classStudents.filter((student) => !usedIds.has(student.id)).slice(0, 8);
	if (missingStudents.length) {
		warnings.push('Students not covered by pasted rows: ' + missingStudents.map((student) => student.name).join(', ') + '.');
	}
	if (hasPaste && classStudents.length > 0 && lines.length !== classStudents.length) {
		warnings.push('Pasted row count must match the class list exactly. Class list has ' + classStudents.length + ' students and you pasted ' + lines.length + ' rows.');
	}
	const invalidScores = lines.filter((line) => {
		const namedMatch = line.match(/^(.+?)\s*(?:,|\||\t|\s{2,})\s*(-?\d+(?:\.\d+)?)$/);
		const scoreOnly = line.match(/^(-?\d+(?:\.\d+)?)$/);
		const score = namedMatch ? parseFloat(namedMatch[2]) : (scoreOnly ? parseFloat(scoreOnly[1]) : null);
		return score !== null && (score < 0 || score > 100);
	});
	if (invalidScores.length) {
		warnings.push('All scores must be between 0 and 100.');
	}
	previewHasErrors = warnings.length > 0;
	const warningHtml = warnings.length
		? '<div class="alert alert-warning mb-0"><div class="fw-semibold mb-1">Preview warnings</div><ul class="mb-0 ps-3">' + warnings.slice(0, 8).map((w) => '<li>' + escapeHtml(w) + '</li>').join('') + '</ul></div>'
		: '<div class="alert alert-success mb-0">Preview looks good. Every pasted row has a target student.</div>';
	$('#previewWarnings').html(warningHtml);
	toggleImportButton();
}

function toggleImportButton() {
	const btn = document.querySelector('button[name="submit"]');
	if (!btn) return;
	btn.disabled = previewHasErrors;
	btn.title = previewHasErrors ? 'Fix the preview warnings before importing.' : '';
}

document.querySelector('form[action="teacher/core/import_results"]')?.addEventListener('submit', function (event) {
	if (previewHasErrors) {
		event.preventDefault();
		if (typeof Swal !== 'undefined' && Swal.fire) {
			Swal.fire({
			icon: 'warning',
			title: 'Fix preview warnings',
			text: 'Please resolve the unmatched, duplicate, or missing student warnings before importing.',
		});
		} else {
			alert('Please resolve the preview warnings before importing.');
		}
	}
});

function escapeHtml(value) {
	return String(value || '').replace(/[&<>"']/g, function(ch) {
		return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]);
	});
}
</script>
</body>

</html>
