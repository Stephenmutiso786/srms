<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');
if ($res != "1") {header("location:../"); exit;}
app_require_permission('marks.enter', '../admin');
$canOverrideMarks = app_current_user_can_override_marks();

if (!isset($_SESSION['exam_entry'])) {
  header("location:./exam_marks_entry");
  exit;
}

$examId = (int)$_SESSION['exam_entry']['exam_id'];
$classId = (int)$_SESSION['exam_entry']['class_id'];
$termId = (int)$_SESSION['exam_entry']['term_id'];
$subjectComb = (int)$_SESSION['exam_entry']['subject_combination'];

$exam = null;
$classData = null;
$termData = null;
$subjectName = '';
$students = [];
$scores = [];
$isLocked = false;
$submissionStatus = 'draft';
$rejectionReason = '';
$avgScore = 0;
$error = '';
$gradingScales = [];

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_exam_grading_schema($conn);

  $stmt = $conn->prepare("SELECT e.*, c.name AS class_name, t.name AS term_name
    FROM tbl_exams e
    LEFT JOIN tbl_classes c ON c.id = e.class_id
    LEFT JOIN tbl_terms t ON t.id = e.term_id
    WHERE e.id = ? LIMIT 1");
  $stmt->execute([$examId]);
  $exam = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$exam) {
    throw new RuntimeException("Exam not found.");
  }
  if (!app_exam_teacher_can_reenter($conn, $examId, $subjectComb, (int)$account_id, (string)($exam['status'] ?? 'draft')) && !$canOverrideMarks) {
    throw new RuntimeException("Exam is not active for mark entry.");
  }

  $stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ? LIMIT 1");
  $stmt->execute([$classId]);
  $classData = $stmt->fetch(PDO::FETCH_ASSOC);

  $stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ? LIMIT 1");
  $stmt->execute([$termId]);
  $termData = $stmt->fetch(PDO::FETCH_ASSOC);

  $stmt = $conn->prepare("SELECT sc.id, s.name AS subject_name, sc.teacher, sc.class
    FROM tbl_subject_combinations sc
    LEFT JOIN tbl_subjects s ON s.id = sc.subject
    WHERE sc.id = ?");
  $stmt->execute([$subjectComb]);
  $combo = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$combo || !app_teacher_can_enter_exam_subject($conn, (int)$account_id, $examId, $subjectComb)) {
    throw new RuntimeException("Not assigned to this subject.");
  }
  $classList = app_unserialize($combo['class']);
  if (!in_array((string)$classId, array_map('strval', $classList), true)) {
    throw new RuntimeException("Subject not assigned to selected class.");
  }
  $subjectName = $combo['subject_name'] ?? '';

  $stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY id");
  $stmt->execute([$classId]);
  $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
  if ($useExamId) {
    $stmt = $conn->prepare("SELECT student, score FROM tbl_exam_results WHERE exam_id = ? AND subject_combination = ?");
    $stmt->execute([$examId, $subjectComb]);
  } else {
    $stmt = $conn->prepare("SELECT student, score FROM tbl_exam_results WHERE class = ? AND term = ? AND subject_combination = ?");
    $stmt->execute([$classId, $termId, $subjectComb]);
  }
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $scores[(string)$row['student']] = $row['score'];
  }
  if (count($scores) > 0) {
    $avgScore = array_sum(array_map('floatval', $scores)) / count($scores);
  }

  $gradingSystemId = report_exam_grading_system_id($conn, $examId);
  $gradingScales = report_grading_scales($conn, $gradingSystemId);

  $isLocked = app_results_locked($conn, $classId, $termId, $examId);
  $submissionStatus = app_exam_submission_status($conn, $examId, $subjectComb);
  
  // Load rejection reason if marks were rejected
  if ($submissionStatus === 'rejected' && app_table_exists($conn, 'tbl_exam_mark_submissions')) {
    $stmt = $conn->prepare("SELECT review_note FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
    $stmt->execute([$examId, $subjectComb]);
    $rejectionReason = (string)($stmt->fetchColumn() ?? '');
  }
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Exam Marks Entry</title>
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

<?php include("admin/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Exam Marks Entry</h1>
<p><?php echo htmlspecialchars($exam['name'] ?? ''); ?> · <?php echo htmlspecialchars($classData['name'] ?? ''); ?> · <?php echo htmlspecialchars($termData['name'] ?? ''); ?></p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<?php if ($isLocked) { ?>
  <div class="tile"><div class="alert alert-warning mb-0">Results are locked for this class and term. Edits are disabled.</div></div>
<?php } ?>
<?php if (!$isLocked && in_array($submissionStatus, ['submitted','reviewed','finalized'], true)) { ?>
  <div class="tile"><div class="alert alert-info mb-0">Marks are <?php echo htmlspecialchars($submissionStatus); ?> and read-only.</div></div>
<?php } ?>
<?php if ($submissionStatus === 'rejected') { ?>
  <div class="tile"><div class="alert alert-danger mb-0">
    <div class="fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Your marks were returned for revision</div>
    <?php if ($rejectionReason !== '') { ?>
      <div class="alert alert-light mb-0" style="border-left: 3px solid #dc3545;">
        <strong>Admin Feedback:</strong><br>
        <?php echo htmlspecialchars($rejectionReason); ?>
      </div>
    <?php } ?>
    <div class="mt-2 small text-muted">Please review the feedback above, make necessary corrections, and resubmit your marks.</div>
  </div></div>
<?php } ?>

<div class="tile">
<h3 class="tile-title">Subject: <?php echo htmlspecialchars($subjectName); ?></h3>
<p><b>Status:</b> <?php echo htmlspecialchars(ucfirst($submissionStatus)); ?> · <b>Class Average:</b> <?php echo number_format($avgScore, 2); ?></p>
<div class="small text-muted" id="autoSaveStatus">Autosave ready</div>
<div class="mb-2">
  <a class="btn btn-sm btn-outline-secondary" target="_blank" href="teacher/print_mark_sheet?exam_id=<?php echo (int)$examId; ?>&subject_combination=<?php echo (int)$subjectComb; ?>&assessment_type=<?php echo urlencode((string)($exam['name'] ?? 'Assessment')); ?>&origin_portal=admin"><i class="bi bi-printer me-1"></i>Print Mark Sheet</a>
  <a class="btn btn-sm btn-outline-secondary" href="teacher/import_results"><i class="bi bi-upload me-1"></i>Bulk Import CSV</a>
</div>
<form class="app_frm" method="POST" action="teacher/core/save_exam_marks">
<input type="hidden" name="exam_id" value="<?php echo (int)$examId; ?>">
<input type="hidden" name="class_id" value="<?php echo (int)$classId; ?>">
<input type="hidden" name="term_id" value="<?php echo (int)$termId; ?>">
<input type="hidden" name="subject_combination" value="<?php echo (int)$subjectComb; ?>">
<input type="hidden" name="origin_portal" value="admin">
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Student</th><th width="180">Marks (0-100)</th><th width="140">Grade</th></tr></thead>
<tbody>
<?php foreach ($students as $student): ?>
<?php
  $sid = (string)$student['id'];
  $fullName = trim(($student['fname'] ?? '').' '.($student['mname'] ?? '').' '.($student['lname'] ?? ''));
  $scoreVal = $scores[$sid] ?? '';
  $gradeVal = '';
  if ($scoreVal !== '' && $scoreVal !== null) {
    list($gradeVal) = report_grade_for_score($conn, (float)$scoreVal, $gradingSystemId ?? null);
  }
?>
<tr>
  <td><?php echo htmlspecialchars($fullName); ?></td>
  <td>
    <input class="form-control exam-score" type="number" min="0" max="100" step="0.01" data-student="<?php echo htmlspecialchars($sid); ?>" name="scores[<?php echo htmlspecialchars($sid); ?>]" value="<?php echo htmlspecialchars($scoreVal); ?>" <?php echo ($isLocked || in_array($submissionStatus, ['submitted','reviewed','finalized'], true)) ? 'readonly' : ''; ?>>
  </td>
  <td><span class="badge bg-secondary exam-grade" data-grade-for="<?php echo htmlspecialchars($sid); ?>"><?php echo htmlspecialchars($gradeVal !== '' ? $gradeVal : '--'); ?></span></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<button class="btn btn-primary" <?php echo ($isLocked || in_array($submissionStatus, ['submitted','reviewed','finalized'], true)) ? 'disabled' : ''; ?>>Save Marks</button>
</form>
<?php if (!$isLocked && in_array($submissionStatus, ['draft','rejected'], true)) { ?>
  <form class="mt-2" method="POST" action="teacher/core/submit_exam_marks">
    <input type="hidden" name="exam_id" value="<?php echo (int)$examId; ?>">
    <input type="hidden" name="subject_combination" value="<?php echo (int)$subjectComb; ?>">
    <input type="hidden" name="origin_portal" value="admin">
    <button class="btn btn-outline-success">Submit Marks</button>
  </form>
<?php } ?>
</div>
<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const autoStatus = document.getElementById('autoSaveStatus');
function setStatus(text, ok=true){
  if (!autoStatus) return;
  autoStatus.textContent = text;
  autoStatus.style.color = ok ? '#198754' : '#dc3545';
}

const basePayload = {
  exam_id: <?php echo (int)$examId; ?>,
  class_id: <?php echo (int)$classId; ?>,
  term_id: <?php echo (int)$termId; ?>,
  subject_combination: <?php echo (int)$subjectComb; ?>
};

const gradeBands = <?php
  $gradeBandRows = array_map(function ($row) {
    return [
      'min' => (float)($row['min'] ?? 0),
      'max' => (float)($row['max'] ?? 0),
      'grade' => (string)($row['name'] ?? '--'),
    ];
  }, $gradingScales);
  echo json_encode($gradeBandRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>;

function gradeForScore(score) {
  for (const band of gradeBands) {
    if (score >= band.min && score <= band.max) {
      return band.grade;
    }
  }
  return 'BE';
}

function updateGradeBadge(studentId, grade) {
  const badge = document.querySelector(`.exam-grade[data-grade-for="${studentId}"]`);
  if (!badge) return;
  badge.textContent = grade || '--';
  badge.classList.remove('bg-secondary', 'bg-success', 'bg-primary', 'bg-warning', 'bg-danger');
  if (!grade || grade === '--') {
    badge.classList.add('bg-secondary');
    return;
  }
  if (String(grade).startsWith('A') || grade === 'EE') badge.classList.add('bg-success');
  else if (String(grade).startsWith('B') || grade === 'ME') badge.classList.add('bg-primary');
  else if (String(grade).startsWith('C') || grade === 'AE') badge.classList.add('bg-warning');
  else badge.classList.add('bg-danger');
}

document.querySelectorAll('.exam-score').forEach((el) => {
  el.addEventListener('input', (e) => {
    const studentId = e.target.dataset.student;
    const raw = e.target.value;
    if (raw === '') {
      updateGradeBadge(studentId, '--');
      return;
    }
    let value = parseFloat(raw);
    if (!Number.isFinite(value)) {
      e.target.value = '';
      updateGradeBadge(studentId, '--');
      return;
    }
    if (value > 100) {
      value = 100;
      e.target.value = '100';
      setStatus('Marks cannot exceed 100', false);
    }
    if (value < 0) {
      value = 0;
      e.target.value = '0';
      setStatus('Marks cannot be below 0', false);
    }
    updateGradeBadge(studentId, gradeForScore(value));
  });

  el.addEventListener('change', async (e) => {
    const value = e.target.value;
    const studentId = e.target.dataset.student;
    if (value === '') {
      updateGradeBadge(studentId, '--');
      return;
    }
    const score = parseFloat(value);
    if (!Number.isFinite(score) || score < 0 || score > 100) {
      setStatus('Marks must be between 0 and 100', false);
      return;
    }
    setStatus('Saving...', true);
    const res = await fetch('teacher/core/save_exam_mark_single', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(Object.assign({}, basePayload, { student_id: studentId, score: value }))
    });
    const data = await res.json().catch(() => ({ ok: false }));
    if (data && data.ok) {
      updateGradeBadge(studentId, data.grade || gradeForScore(score));
      setStatus('Saved', true);
    } else {
      setStatus(data.message || 'Save failed', false);
    }
  });
});
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
