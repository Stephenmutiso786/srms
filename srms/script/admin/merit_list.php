<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('report.generate', 'admin');
app_require_unlocked('reports', 'admin');

$classes = [];
$terms = [];
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$examId = (int)($_GET['exam_id'] ?? 0);
$rows = [];
$subjects = [];
$className = '';
$termName = '';
$examName = '';
$locked = false;
$summary = ['students' => 0, 'avg' => 0, 'best' => 0, 'worst' => 0];
$termExamMap = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($classes as $classRow) {
		$classKey = (int)($classRow['id'] ?? 0);
		if ($classKey < 1) {
			continue;
		}
		$termExamMap[$classKey] = [];
		foreach ($terms as $termRow) {
			$termKey = (int)($termRow['id'] ?? 0);
			if ($termKey < 1) {
				continue;
			}
			$termExamMap[$classKey][$termKey] = report_term_exam_options($conn, $classKey, $termKey);
		}
	}

	if ($classId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$classId]);
		$className = (string)$stmt->fetchColumn();
	}
	if ($termId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();
	}
	if ($examId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_exams WHERE id = ? LIMIT 1");
		$stmt->execute([$examId]);
		$examName = (string)$stmt->fetchColumn();
	}

	if ($classId > 0 && $termId > 0) {
		$locked = app_results_locked($conn, $classId, $termId);
		$list = report_class_merit_list($conn, $classId, $termId, (int)$account_id, $examId);
		$rows = $list['rows'];
		$subjects = is_array($list['subjects'] ?? null) ? $list['subjects'] : [];
		$summary['students'] = (int)$list['total_students'];
		if (!empty($rows)) {
			$summary['best'] = (float)($rows[0]['mean_points'] ?? 0);
			$summary['worst'] = (float)($rows[count($rows) - 1]['mean_points'] ?? 0);
			$sum = 0;
			foreach ($rows as $row) {
				$sum += (float)($row['mean_points'] ?? 0);
			}
			$summary['avg'] = round($sum / max(1, count($rows)), 2);
		}
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Merit List</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.merit-hero{background:linear-gradient(135deg,#0d3b66,#0d64b0 55%,#1ca874);color:#fff;border-radius:24px;padding:22px;box-shadow:0 18px 50px rgba(13,59,102,.14)}
.merit-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:18px 0}
.merit-stat{background:#fff;border-radius:18px;padding:16px;box-shadow:0 12px 32px rgba(9,30,66,.08)}
.merit-stat .label{font-size:.75rem;text-transform:uppercase;color:#6b7280}
.merit-stat .value{font-size:1.6rem;font-weight:800;color:#123}
.merit-card{background:#fff;border-radius:20px;box-shadow:0 12px 32px rgba(9,30,66,.08);overflow:hidden}
.sheet-wrap{overflow-x:auto;overflow-y:visible;-webkit-overflow-scrolling:touch}
.sheet-table{min-width:max-content;white-space:nowrap}
.sheet-table thead th{position:sticky;top:0;z-index:5;background:#f8fbfd}
.sheet-table th,.sheet-table td{vertical-align:middle}
.sheet-table .sticky-col{position:sticky;background:#fff;z-index:4}
.sheet-table thead .sticky-col{background:#f8fbfd;z-index:6}
.sheet-table .sticky-1{left:0;min-width:78px}
.sheet-table .sticky-2{left:78px;min-width:110px}
.sheet-table .sticky-3{left:188px;min-width:220px}
.sheet-table .sticky-4{left:408px;min-width:90px}
@media (max-width: 991px){
.sheet-table .sticky-1{left:0;min-width:70px}
.sheet-table .sticky-2{left:70px;min-width:100px}
.sheet-table .sticky-3{left:170px;min-width:180px}
.sheet-table .sticky-4{left:350px;min-width:80px}
}
@media (max-width: 991px){.merit-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 576px){.merit-grid{grid-template-columns:1fr}}
@media print{.app-header,.app-sidebar,.app-title,.toolbar,.filter-card{display:none!important}.app-content{margin-left:0;padding:0}.merit-card,.merit-hero{box-shadow:none}}
</style>
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
<h1>Class Merit List</h1>
<p class="mb-0 text-muted">Ranked learner performance for the selected class and term.</p>
</div>
</div>

<div class="merit-hero">
  <div class="d-flex justify-content-between flex-wrap gap-2 align-items-start">
    <div>
      <div class="small opacity-75">Class Merit</div>
      <h3 class="mb-1"><?php echo htmlspecialchars($className !== '' ? $className : 'Select class'); ?></h3>
      <div class="small opacity-75"><?php echo htmlspecialchars(trim($termName . ($examName !== '' ? ' | ' . $examName : '')) !== '' ? trim($termName . ($examName !== '' ? ' | ' . $examName : '')) : 'Select term'); ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <button class="btn btn-light" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print</button>
      <?php if ($classId > 0 && $termId > 0): ?>
      <a class="btn btn-outline-light" href="admin/merit_list_pdf?class_id=<?php echo $classId; ?>&term_id=<?php echo $termId; ?>&exam_id=<?php echo $examId; ?>" target="_blank"><i class="bi bi-download me-2"></i>PDF</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="merit-grid">
  <div class="merit-stat"><div class="label">Learners</div><div class="value"><?php echo (int)$summary['students']; ?></div></div>
  <div class="merit-stat"><div class="label">Average Points</div><div class="value"><?php echo number_format((float)$summary['avg'], 2); ?></div></div>
  <div class="merit-stat"><div class="label">Best Mean</div><div class="value"><?php echo number_format((float)$summary['best'], 2); ?></div></div>
  <div class="merit-stat"><div class="label">Lowest Mean</div><div class="value"><?php echo number_format((float)$summary['worst'], 2); ?></div></div>
</div>

<div class="tile filter-card mb-3">
<div class="tile-body">
<form class="d-flex flex-wrap gap-2 align-items-end" method="get">
<div>
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select class</option>
<?php foreach ($classes as $class): ?>
<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$class['id'] === $classId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$class['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Term</label>
<select class="form-control" name="term_id" required>
<option value="">Select term</option>
<?php foreach ($terms as $term): ?>
<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$term['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Exam</label>
<select class="form-control select2" name="exam_id" id="meritExamSelect" style="width: 100%;">
<option value="">All Published Exams / Term</option>
</select>
</div>
<div>
<button class="btn btn-primary" type="submit">Show Class Results</button>
</div>
</form>
</div>
</div>

<?php if ($classId > 0 && $termId > 0 && !$locked): ?>
<div class="alert alert-warning">Results are not locked yet. The merit list is shown for review, but you should lock results before printing or sharing it.</div>
<?php endif; ?>

<div class="merit-card">
  <div class="sheet-wrap">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 sheet-table">
      <thead>
        <tr>
          <th class="sticky-col sticky-1">Pos</th>
          <th class="sticky-col sticky-2">School ID</th>
          <th class="sticky-col sticky-3">Student</th>
          <th class="sticky-col sticky-4">Gender</th>
          <?php foreach ($subjects as $subject): ?>
          <th><?php echo htmlspecialchars((string)($subject['subject_name'] ?? 'Subject')); ?></th>
          <?php endforeach; ?>
          <th>Total Points</th>
          <th>Mean Points</th>
          <th>Grade</th>
          <th>Trend</th>
          <th>Remark</th>
          <th>Verification</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows) { ?>
        <tr><td colspan="<?php echo 9 + count($subjects); ?>" class="text-muted">Select a class and term to show class results.</td></tr>
      <?php } ?>
      <?php foreach ($rows as $row): ?>
        <tr>
          <td class="sticky-col sticky-1"><?php echo htmlspecialchars((string)($row['position_text'] ?? $row['position'] ?? '-')); ?></td>
          <td class="sticky-col sticky-2"><?php echo htmlspecialchars((string)($row['school_id'] !== '' ? $row['school_id'] : $row['student_id'])); ?></td>
          <td class="sticky-col sticky-3"><?php echo htmlspecialchars((string)$row['student_name']); ?></td>
          <td class="sticky-col sticky-4"><?php echo htmlspecialchars((string)($row['gender'] ?? '')); ?></td>
          <?php foreach ($subjects as $subject): ?>
          <?php $subjectId = (int)($subject['subject'] ?? 0); $subjectScore = $row['subject_scores'][$subjectId] ?? null; ?>
          <td><?php echo $subjectScore === null ? '-' : htmlspecialchars(number_format((float)$subjectScore, ((float)$subjectScore === floor((float)$subjectScore)) ? 0 : 1)); ?></td>
          <?php endforeach; ?>
          <td><?php echo number_format((float)($row['total_points'] ?? 0), 1); ?></td>
          <td><?php echo number_format((float)($row['mean_points'] ?? 0), 2); ?></td>
          <td><span class="badge bg-primary"><?php echo htmlspecialchars((string)$row['grade']); ?></span></td>
          <td><?php echo htmlspecialchars((string)$row['trend']); ?></td>
          <td><?php echo htmlspecialchars((string)($row['remark'] ?? '')); ?></td>
          <td class="text-muted small"><?php echo htmlspecialchars((string)$row['verification_code']); ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const meritExamMap = <?php echo json_encode($termExamMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
const meritSelectedExamId = <?php echo (int)$examId; ?>;
function loadMeritExams() {
  const classId = document.querySelector('select[name="class_id"]')?.value || '';
  const termId = document.querySelector('select[name="term_id"]')?.value || '';
  const select = document.getElementById('meritExamSelect');
  if (!select) return;
  const exams = (((meritExamMap[classId] || {})[termId]) || []);
  let html = '<option value="">All Published Exams / Term</option>';
  exams.forEach((exam) => {
    const selected = Number(exam.id) === Number(meritSelectedExamId) ? ' selected' : '';
    html += `<option value="${exam.id}"${selected}>${String(exam.name || 'Exam')}</option>`;
  });
  select.innerHTML = html;
  if (window.jQuery && jQuery.fn.select2) {
    jQuery(select).trigger('change.select2');
  }
}
document.addEventListener('DOMContentLoaded', function () {
  loadMeritExams();
  const classSelect = document.querySelector('select[name="class_id"]');
  const termSelect = document.querySelector('select[name="term_id"]');
  if (classSelect) classSelect.addEventListener('change', loadMeritExams);
  if (termSelect) termSelect.addEventListener('change', loadMeritExams);
});
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
