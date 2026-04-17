<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

$exams = [];
$classSubjects = [];
$useTeacherAssignments = false;
$examModeMap = [];

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_exam_subjects_table($conn);
  app_ensure_exam_assessment_mode_column($conn);

  $combos = [];
  $useTeacherAssignments = app_table_exists($conn, 'tbl_teacher_assignments');
  $stmt = $conn->prepare("SELECT sc.id, sc.class, sc.subject, s.name AS subject_name
    FROM tbl_subject_combinations sc
    LEFT JOIN tbl_subjects s ON sc.subject = s.id
    WHERE sc.teacher = ?");
  $stmt->execute([$account_id]);
  $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $classIds = [];

  if ($useTeacherAssignments) {
    $stmt = $conn->prepare("SELECT ta.class_id, ta.subject_id, ta.term_id, s.name AS subject_name
      FROM tbl_teacher_assignments ta
      JOIN tbl_subjects s ON s.id = ta.subject_id
      WHERE ta.teacher_id = ? AND ta.status = 1
      ORDER BY ta.year DESC, ta.id DESC");
    $stmt->execute([$account_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as $assignment) {
      $classIds[] = (int)$assignment['class_id'];
    }
    $classIds = array_values(array_unique(array_filter($classIds)));

    if (!empty($classIds)) {
      $placeholders = implode(',', array_fill(0, count($classIds), '?'));
  		$stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, COALESCE(e.assessment_mode, 'normal') AS assessment_mode, c.name AS class_name, t.name AS term_name
        FROM tbl_exams e
        LEFT JOIN tbl_classes c ON c.id = e.class_id
        LEFT JOIN tbl_terms t ON t.id = e.term_id
  		WHERE e.status IN ('active', 'open') AND e.class_id IN ($placeholders) AND COALESCE(e.assessment_mode, 'normal') <> 'consolidated'
        ORDER BY e.created_at DESC");
      $stmt->execute($classIds);
      $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($exams as $exam) {
      $allowedSubjectIds = app_exam_subject_ids($conn, (int)$exam['id']);
      foreach ($assignments as $assignment) {
        if ((int)$assignment['class_id'] !== (int)$exam['class_id']) {
          continue;
        }
        if ((int)$assignment['term_id'] > 0 && (int)$assignment['term_id'] !== (int)$exam['term_id']) {
          continue;
        }
        if (!empty($allowedSubjectIds) && !in_array((int)$assignment['subject_id'], $allowedSubjectIds, true)) {
          continue;
        }
        $comboId = app_get_teacher_subject_combination_id($conn, (int)$account_id, (int)$assignment['subject_id'], (int)$exam['class_id'], true);
        if ($comboId > 0) {
          $classSubjects[(int)$exam['id']][$comboId] = [
            'id' => $comboId,
            'name' => $assignment['subject_name']
          ];
        }
      }
    }
  } else {
    foreach ($combos as $combo) {
      $classes = app_unserialize($combo['class']);
      foreach ($classes as $cid) {
        $classIds[] = (int)$cid;
        $classSubjects[(int)$cid][] = [
          'id' => (int)$combo['id'],
          'name' => $combo['subject_name']
        ];
      }
    }
    $classIds = array_values(array_unique(array_filter($classIds)));

    if (!empty($classIds)) {
      $placeholders = implode(',', array_fill(0, count($classIds), '?'));
  		$stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, COALESCE(e.assessment_mode, 'normal') AS assessment_mode, c.name AS class_name, t.name AS term_name
        FROM tbl_exams e
        LEFT JOIN tbl_classes c ON c.id = e.class_id
        LEFT JOIN tbl_terms t ON t.id = e.term_id
  		WHERE e.status IN ('active', 'open') AND e.class_id IN ($placeholders) AND COALESCE(e.assessment_mode, 'normal') <> 'consolidated'
        ORDER BY e.created_at DESC");
      $stmt->execute($classIds);
      $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  }
  foreach ($exams as $exam) {
    $examModeMap[(int)$exam['id']] = (($exam['assessment_mode'] ?? 'normal') === 'cbc') ? 'cbc' : 'normal';
  }
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", "Failed to load exams."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Assessment Marks Entry</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
<style>
body.exam-entry-page{background:linear-gradient(180deg,#eef5f2 0%,#f7fbf9 45%,#edf3f0 100%)}
.exam-entry-shell{display:grid;gap:18px}
.exam-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#08463d 0%,#0c7a6f 56%,#0e8db0 100%);color:#fff;border-radius:24px;padding:24px 26px;box-shadow:0 22px 50px rgba(6,60,52,.18);display:grid;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);gap:18px;align-items:stretch}
.exam-hero:before,.exam-hero:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.1);pointer-events:none}
.exam-hero:before{width:200px;height:200px;right:-80px;top:-80px}
.exam-hero:after{width:140px;height:140px;right:110px;bottom:-70px}
.exam-hero-copy{position:relative;z-index:1}
.exam-hero h2{margin:0 0 8px;font-size:clamp(1.25rem,2.6vw,1.8rem);font-weight:900;letter-spacing:-.03em}
.exam-hero p{margin:0;opacity:.94;line-height:1.6}
.exam-workflow{position:relative;z-index:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.workflow-card{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);backdrop-filter:blur(10px);border-radius:18px;padding:14px}
.workflow-card .label{text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;opacity:.82}
.workflow-card .value{font-size:1.2rem;font-weight:800;margin-top:4px}
.workflow-note{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap}
.workflow-pill{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:rgba(255,255,255,.1);padding:7px 12px;font-size:.82rem;font-weight:700}
.exam-card{background:#fff;border-radius:22px;border:1px solid #e6edf5;box-shadow:0 14px 34px rgba(14,53,47,.08);overflow:hidden}
.exam-card .tile-body{padding:20px}
.exam-card .tile-title{display:flex;align-items:center;font-weight:900;letter-spacing:-.02em;color:#164f44}
.exam-card .form-label{font-weight:800;color:#405463}
.exam-card .form-control,.exam-card .select2-container .select2-selection{border-radius:12px;min-height:44px}
.exam-card .btn{border-radius:12px;font-weight:800}
.exam-help-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:18px}
.exam-help-card{border-radius:16px;padding:14px;background:linear-gradient(180deg,#f8fbfa,#ffffff);border:1px solid #e1ece7}
.exam-help-card .label{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:#6d7f88}
.exam-help-card .value{font-size:1rem;font-weight:800;color:#183a33;margin-top:4px}
.exam-side-note{border-radius:16px;padding:14px 16px;background:#f7fcfb;border:1px dashed #cfe0da;color:#5a6a73}
@media (max-width:991px){.exam-hero{grid-template-columns:1fr}.exam-help-grid{grid-template-columns:1fr 1fr}.exam-workflow{grid-template-columns:1fr 1fr}}
@media (max-width:600px){.exam-help-grid,.exam-workflow{grid-template-columns:1fr}}
</style>
</head>
<body class="app sidebar-mini exam-entry-page">

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
<h1>Assessment Marks Entry</h1>
<p>Select one exam. The system will automatically use normal marks entry or CBC entry based on the exam mode chosen by admin.</p>
</div>
</div>

<section class="exam-hero mb-3">
  <div class="exam-hero-copy">
    <p class="text-uppercase fw-bold mb-2" style="letter-spacing:.1em;opacity:.8;">Teaching workflow</p>
    <h2>Choose an exam, choose a subject, then enter marks in one clean flow.</h2>
    <p>The page adapts to the selected exam mode so you can move through normal, CBC, or consolidated workflows without changing screens.</p>
    <div class="workflow-note">
      <span class="workflow-pill"><i class="bi bi-1-circle"></i>Pick exam</span>
      <span class="workflow-pill"><i class="bi bi-2-circle"></i>Pick subject</span>
      <span class="workflow-pill"><i class="bi bi-3-circle"></i>Enter marks</span>
    </div>
  </div>
  <div class="exam-workflow">
    <div class="workflow-card"><div class="label">Available exams</div><div class="value"><?php echo (int)count($exams); ?></div></div>
    <div class="workflow-card"><div class="label">Subjects mapped</div><div class="value"><?php echo (int)count($classSubjects); ?></div></div>
    <div class="workflow-card"><div class="label">Mode</div><div class="value">Auto-detected</div></div>
    <div class="workflow-card"><div class="label">Print sheet</div><div class="value">One click</div></div>
  </div>
</section>

<div class="row">
<div class="col-md-6 center_form">
<div class="exam-card">
<div class="tile-body">
<h3 class="tile-title">Start Exam Entry</h3>
<form class="app_frm" method="POST" action="teacher/core/start_exam_entry">
<div class="mb-3">
<label class="form-label">Active Exam</label>
<select class="form-control select2" name="exam_id" id="examSelect" required>
<option value="" selected disabled>Select exam</option>
<?php foreach ($exams as $exam): ?>
<option value="<?php echo (int)$exam['id']; ?>" data-class="<?php echo (int)$exam['class_id']; ?>">
<?php echo htmlspecialchars($exam['name'].' - '.$exam['class_name'].' ('.$exam['term_name'].')'); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<input type="hidden" name="assessment_mode" id="assessmentMode" value="normal">
<div class="mb-3">
<label class="form-label">Subject</label>
<select class="form-control select2" name="subject_combination" id="subjectSelect" required>
<option value="" selected disabled>Select subject</option>
</select>
</div>
<div class="d-flex gap-2 flex-wrap">
<button class="btn btn-primary app_btn" type="submit">Start Entry</button>
<button class="btn btn-outline-secondary" id="printMarkSheetBtn" type="button"><i class="bi bi-printer me-1"></i>Print Mark Sheet</button>
</div>
</form>
</div>
</div>
</div>

<div class="col-md-6">
  <div class="exam-side-note">
    <div class="fw-bold mb-2">What this page does</div>
    <div class="small text-muted">The selected exam controls whether you enter normal marks, CBC marks, or get redirected to the consolidated workflow. The print mark sheet button opens the same exam/subject in a paper-friendly layout.</div>
  </div>
  <div class="exam-help-grid">
    <div class="exam-help-card"><div class="label">Normal</div><div class="value">Class tests and end-term assessments</div></div>
    <div class="exam-help-card"><div class="label">CBC</div><div class="value">Competency entry and coded grading</div></div>
    <div class="exam-help-card"><div class="label">Consolidated</div><div class="value">Auto averages from component exams</div></div>
  </div>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
  $('.select2').select2();
  const classSubjects = <?php echo json_encode($classSubjects); ?>;
  const mapByExam = <?php echo $useTeacherAssignments ? 'true' : 'false'; ?>;
  const examModes = <?php echo json_encode($examModeMap); ?>;
  $('#examSelect').on('change', function () {
    const classId = $(this).find(':selected').data('class');
    const examId = $(this).val();
    $('#assessmentMode').val(examModes[examId] || 'normal');
    const key = mapByExam ? examId : classId;
    const subjects = Object.values(classSubjects[key] || {});
    const $subject = $('#subjectSelect');
    $subject.empty().append('<option value="" selected disabled>Select subject</option>');
    subjects.forEach(item => {
      $subject.append(`<option value="${item.id}">${item.name}</option>`);
    });
    if (!subjects.length) {
      $subject.append('<option value="" disabled>No assigned subjects found for this exam</option>');
    }
    $subject.trigger('change');
  });

  $('#printMarkSheetBtn').on('click', function () {
    const examId = $('#examSelect').val();
    const subjectId = $('#subjectSelect').val();
    if (!examId || !subjectId) {
      alert('Please select exam and subject first.');
      return;
    }
    const url = new URL('teacher/print_mark_sheet', document.baseURI || window.location.href);
    url.searchParams.set('exam_id', examId);
    url.searchParams.set('subject_combination', subjectId);
    window.open(url.toString(), '_blank');
  });
</script>
</body>
</html>
