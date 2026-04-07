<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

if (!isset($_SESSION['cbc_entry'])) {
	header("location:./marks_entry");
	exit;
}

$term = (int)$_SESSION['cbc_entry']['term'];
$class = (int)$_SESSION['cbc_entry']['class'];
$subjectComb = (int)$_SESSION['cbc_entry']['subject'];
$mode = $_SESSION['cbc_entry']['mode'] ?? 'cbc';
$mode = $mode === 'marks' ? 'marks' : 'cbc';

$termData = null;
$classData = null;
$subjectData = null;
$subjectId = 0;
$subjectName = '';
$strands = [];
$students = [];
$entries = [];
$error = '';
$isLocked = false;
$grading = [];
$levels = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
	$stmt->execute([$term]);
	$termData = $stmt->fetch(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
	$stmt->execute([$class]);
	$classData = $stmt->fetch(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations
		LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id
		WHERE tbl_subject_combinations.id = ?");
	$stmt->execute([$subjectComb]);
	$subjectData = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$subjectData) {
		throw new RuntimeException("Subject combination not found.");
	}

	if ((int)$subjectData['teacher'] !== (int)$account_id) {
		throw new RuntimeException("Not allowed to enter marks for this subject.");
	}

	$classList = app_unserialize($subjectData['class']);
	if (!in_array((string)$class, array_map('strval', $classList), true)) {
		throw new RuntimeException("Subject not assigned to selected class.");
	}

	$subjectId = (int)$subjectData['subject'];
	$subjectName = (string)$subjectData['name'];

	if (app_table_exists($conn, 'tbl_cbc_strands')) {
		$stmt = $conn->prepare("SELECT id, name FROM tbl_cbc_strands WHERE subject_id = ? AND status = 1 ORDER BY name");
		$stmt->execute([$subjectId]);
		$strands = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY id");
	$stmt->execute([$class]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (app_table_exists($conn, 'tbl_cbc_assessments')) {
		$useSubjectId = app_column_exists($conn, 'tbl_cbc_assessments', 'subject_id');
		if ($useSubjectId) {
			$stmt = $conn->prepare("SELECT student_id, strand, level, marks, points
				FROM tbl_cbc_assessments
				WHERE class_id = ? AND term_id = ? AND subject_id = ?");
			$stmt->execute([$class, $term, $subjectId]);
		} else {
			$stmt = $conn->prepare("SELECT student_id, strand, level, NULL as marks, 0 as points
				FROM tbl_cbc_assessments
				WHERE class_id = ? AND term_id = ? AND learning_area = ?");
			$stmt->execute([$class, $term, $subjectName]);
		}
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$entries[$row['student_id']][$row['strand']] = $row;
		}
	}

	if (app_table_exists($conn, 'tbl_cbc_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points, sort_order FROM tbl_cbc_grading WHERE active = 1 ORDER BY sort_order, min_mark DESC");
		$stmt->execute();
		$grading = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$isLocked = app_results_locked($conn, $class, $term);
} catch (Throwable $e) {
	$error = $e->getMessage();
}

if (count($grading) < 1) {
	$grading = [
		['level' => 'EE', 'min_mark' => 80, 'max_mark' => 100, 'points' => 4, 'sort_order' => 1],
		['level' => 'ME', 'min_mark' => 60, 'max_mark' => 79, 'points' => 3, 'sort_order' => 2],
		['level' => 'AE', 'min_mark' => 40, 'max_mark' => 59, 'points' => 2, 'sort_order' => 3],
		['level' => 'BE', 'min_mark' => 0, 'max_mark' => 39, 'points' => 1, 'sort_order' => 4],
	];
}

foreach ($grading as $row) {
	$levels[] = strtoupper((string)$row['level']);
}
$levels = array_values(array_unique($levels));
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - CBC Marks Entry</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.cbc-table select,.cbc-table input{min-width:110px;}
.cbc-badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;color:#fff;}
.cbc-ee{background:#198754;}
.cbc-me{background:#0d6efd;}
.cbc-ae{background:#fd7e14;}
.cbc-be{background:#dc3545;}
.cbc-status{font-size:12px;color:#6c757d;}
.cbc-missing{background:#fff8e1;}
</style>
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Teacher</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
<li><a class="app-menu__item" href="teacher/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview is-expanded"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Examination Results</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/exam_marks_entry"><i class="icon bi bi-circle-fill"></i> Exam Marks Entry</a></li>
<li><a class="treeview-item active" href="teacher/marks_entry"><i class="icon bi bi-circle-fill"></i> CBC Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/import_results"><i class="icon bi bi-circle-fill"></i> Import Results</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
</ul>
</li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>CBC Marks Entry</h1>
<p>Mode: <b><?php echo $mode === 'marks' ? 'Marks → CBC Auto' : 'CBC Levels'; ?></b></p>
</div>
<div class="cbc-status" id="saveStatus">Ready</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<?php if ($isLocked) { ?>
  <div class="tile"><div class="alert alert-warning mb-0">Results are locked for this class and term. Edits are disabled.</div></div>
<?php } ?>

<div class="row mb-2">
<div class="col-md-4">
<div class="tile">
<div class="tile-body">
<div><b>Class:</b> <?php echo htmlspecialchars($classData['name'] ?? ''); ?></div>
<div><b>Term:</b> <?php echo htmlspecialchars($termData['name'] ?? ''); ?></div>
<div><b>Subject:</b> <?php echo htmlspecialchars($subjectName); ?></div>
</div>
</div>
</div>
<div class="col-md-8">
<div class="tile">
<div class="tile-body d-flex align-items-center justify-content-between">
<div>Progress: <b id="progressCount">0</b>/<b id="progressTotal">0</b> entries</div>
<div>
<a class="btn btn-outline-secondary btn-sm" href="teacher/marks_entry"><i class="bi bi-arrow-left"></i> Change Selection</a>
</div>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Add Strand / Competency</h3>
<form class="app_frm" action="teacher/core/add_cbc_strand" method="POST">
<input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
<div class="mb-3">
<label class="form-label">Strand Name</label>
<input class="form-control" name="name" required placeholder="Numbers, Geometry, etc.">
</div>
<button class="btn btn-primary">Add Strand</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">Bulk Upload (CSV)</h3>
<form class="app_frm" enctype="multipart/form-data" method="POST" action="teacher/core/import_cbc_csv">
<input type="hidden" name="term_id" value="<?php echo $term; ?>">
<input type="hidden" name="class_id" value="<?php echo $class; ?>">
<input type="hidden" name="subject_id" value="<?php echo $subjectId; ?>">
<input type="hidden" name="learning_area" value="<?php echo htmlspecialchars($subjectName); ?>">
<input type="hidden" name="mode" value="<?php echo $mode; ?>">
<div class="mb-2">CSV columns: student_id, strand, <?php echo $mode === 'marks' ? 'marks' : 'level'; ?></div>
<div class="mb-3">
<input required accept=".csv" type="file" name="file" class="form-control">
</div>
<button class="btn btn-outline-primary">Import CSV</button>
</form>
</div>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Marks Entry Table</h3>
<?php if (count($strands) < 1) { ?>
  <div class="alert alert-info mb-0">Add at least one strand/competency to start entry.</div>
<?php } else { ?>
<div class="table-responsive">
<table class="table table-hover table-bordered cbc-table" id="cbcTable">
<thead>
<tr>
<th>Student</th>
<?php foreach ($strands as $st): ?>
<th><?php echo htmlspecialchars($st['name']); ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach ($students as $st): ?>
<tr>
<td><?php echo htmlspecialchars($st['id'].' — '.$st['fname'].' '.$st['mname'].' '.$st['lname']); ?></td>
<?php foreach ($strands as $strand): 
	$key = $strand['name'];
	$cell = $entries[$st['id']][$key] ?? null;
	$level = $cell['level'] ?? '';
	$marks = $cell['marks'] ?? '';
	$prefix = strtoupper(substr((string)$level, 0, 2));
	if (!in_array($prefix, ['EE','ME','AE','BE'], true)) { $prefix = 'ME'; }
?>
<td class="<?php echo $level === '' ? 'cbc-missing' : ''; ?>">
<?php if ($mode === 'marks') { ?>
  <div class="d-flex align-items-center gap-2">
	<input type="number" class="form-control form-control-sm cbc-marks" min="0" max="100" step="0.1"
	  data-student="<?php echo $st['id']; ?>" data-strand="<?php echo htmlspecialchars($key); ?>"
	  value="<?php echo htmlspecialchars((string)$marks); ?>" placeholder="0-100">
	<span class="cbc-badge <?php echo $level ? 'cbc-'.strtolower($prefix) : 'cbc-be'; ?>" data-badge>
	  <?php echo $level ?: 'BE'; ?>
	</span>
  </div>
<?php } else { ?>
  <select class="form-control form-control-sm cbc-level" data-student="<?php echo $st['id']; ?>" data-strand="<?php echo htmlspecialchars($key); ?>">
	<option value="" <?php echo $level === '' ? 'selected' : ''; ?>>--</option>
	<?php foreach ($levels as $opt): ?>
	  <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo strtoupper($level) === $opt ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
	<?php endforeach; ?>
  </select>
<?php } ?>
</td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php } ?>
</div>

<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const saveStatus = document.getElementById('saveStatus');
const mode = "<?php echo $mode; ?>";
const payloadBase = {
  term_id: <?php echo $term; ?>,
  class_id: <?php echo $class; ?>,
  subject_id: <?php echo $subjectId; ?>,
  learning_area: "<?php echo htmlspecialchars($subjectName); ?>",
  combination_id: <?php echo $subjectComb; ?>
};
const grading = <?php echo json_encode($grading); ?>;

function markStatus(text, ok=true){
  if (!saveStatus) return;
  saveStatus.textContent = text;
  saveStatus.style.color = ok ? '#198754' : '#dc3545';
}

function mapMarks(mark){
  for (const row of grading) {
    const min = parseFloat(row.min_mark);
    const max = parseFloat(row.max_mark);
    if (mark >= min && mark <= max) {
      return { level: row.level, points: parseInt(row.points || 0, 10) };
    }
  }
  return { level: 'BE', points: 0 };
}

function updateProgress(){
  const total = document.querySelectorAll('<?php echo $mode === 'marks' ? '.cbc-marks' : '.cbc-level'; ?>').length;
  let filled = 0;
  if (mode === 'marks') {
    document.querySelectorAll('.cbc-marks').forEach((el) => {
      if (el.value !== '') filled++;
    });
  } else {
    document.querySelectorAll('.cbc-level').forEach((el) => {
      if (el.value !== '') filled++;
    });
  }
  document.getElementById('progressTotal').textContent = total;
  document.getElementById('progressCount').textContent = filled;
}

async function saveEntry(studentId, strand, level, marks, points){
  markStatus('Saving...', true);
  const body = Object.assign({}, payloadBase, {
    student_id: studentId,
    strand: strand,
    level: level,
    marks: marks,
    points: points,
    mode: mode
  });
  const res = await fetch('teacher/core/save_cbc_entry', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(body)
  });
  const data = await res.json().catch(() => ({ ok: false }));
  if (data && data.ok) {
    markStatus('Saved', true);
  } else {
    markStatus(data.message || 'Save failed', false);
  }
}

function levelPrefix(level){
  const upper = (level || '').toUpperCase();
  const prefix = upper.substring(0, 2);
  if (['EE','ME','AE','BE'].includes(prefix)) return prefix;
  return 'ME';
}

function applyLevelBadge(badge, level){
  if (!badge) return;
  badge.textContent = level;
  const prefix = levelPrefix(level).toLowerCase();
  badge.classList.remove('cbc-ee','cbc-me','cbc-ae','cbc-be');
  badge.classList.add('cbc-' + prefix);
}

document.querySelectorAll('.cbc-level').forEach((el) => {
  if (<?php echo $isLocked ? 'true' : 'false'; ?>) { el.disabled = true; }
  el.addEventListener('change', async (e) => {
    const studentId = e.target.dataset.student;
    const strand = e.target.dataset.strand;
    const level = e.target.value;
    await saveEntry(studentId, strand, level, null, 0);
    updateProgress();
  });
});

document.querySelectorAll('.cbc-marks').forEach((el) => {
  if (<?php echo $isLocked ? 'true' : 'false'; ?>) { el.disabled = true; }
  el.addEventListener('change', async (e) => {
    const studentId = e.target.dataset.student;
    const strand = e.target.dataset.strand;
    const mark = parseFloat(e.target.value || '0');
    const mapped = mapMarks(mark);
    const badge = e.target.parentElement.querySelector('[data-badge]');
    applyLevelBadge(badge, mapped.level);
    await saveEntry(studentId, strand, mapped.level, mark, mapped.points);
    updateProgress();
  });
});

updateProgress();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
