<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '2') {
  header('location:../');
  exit;
}

$exams = [];
$classSubjects = [];
$useTeacherAssignments = false;

$selectedExamId = (int)($_GET['exam_id'] ?? 0);
$selectedSubjectComb = (int)($_GET['subject_combination'] ?? 0);
$assessmentType = trim((string)($_GET['assessment_type'] ?? 'CAT 1'));
$customAssessment = trim((string)($_GET['assessment_custom'] ?? ''));
$assessmentLabel = $assessmentType === 'Custom' ? $customAssessment : $assessmentType;
$columns = (int)($_GET['columns'] ?? 1);
$columns = max(1, min(6, $columns));
$maxScore = (int)($_GET['max_score'] ?? 100);
$maxScore = max(1, min(500, $maxScore));
$sortBy = (string)($_GET['sort'] ?? 'admission');
if (!in_array($sortBy, ['admission', 'name'], true)) {
  $sortBy = 'admission';
}
$columnPrefix = trim((string)($_GET['column_prefix'] ?? ''));
$showCodes = isset($_GET['show_codes']) && (string)$_GET['show_codes'] === '1';
$downloadPdf = isset($_GET['download']) && (string)$_GET['download'] === '1';

$students = [];
$examMeta = null;
$subjectMeta = null;
$classMeta = null;
$formError = '';

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_exam_subjects_table($conn);
  app_ensure_exam_assessment_mode_column($conn);

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
      $stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
        c.name AS class_name, t.name AS term_name
        FROM tbl_exams e
        LEFT JOIN tbl_classes c ON c.id = e.class_id
        LEFT JOIN tbl_terms t ON t.id = e.term_id
        WHERE e.class_id IN ($placeholders) AND COALESCE(e.assessment_mode, 'normal') <> 'consolidated'
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
      }
    }
    $classIds = array_values(array_unique(array_filter($classIds)));

    if (!empty($classIds)) {
      $placeholders = implode(',', array_fill(0, count($classIds), '?'));
      $stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, COALESCE(e.assessment_mode, 'normal') AS assessment_mode,
        c.name AS class_name, t.name AS term_name
        FROM tbl_exams e
        LEFT JOIN tbl_classes c ON c.id = e.class_id
        LEFT JOIN tbl_terms t ON t.id = e.term_id
        WHERE e.class_id IN ($placeholders) AND COALESCE(e.assessment_mode, 'normal') <> 'consolidated'
        ORDER BY e.created_at DESC");
      $stmt->execute($classIds);
      $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($exams as $exam) {
      foreach ($combos as $combo) {
        $classes = app_unserialize($combo['class']);
        if (!in_array((string)$exam['class_id'], array_map('strval', $classes), true)) {
          continue;
        }
        if (!app_exam_has_subject($conn, (int)$exam['id'], (int)$combo['subject'])) {
          continue;
        }
        $classSubjects[(int)$exam['id']][(int)$combo['id']] = [
          'id' => (int)$combo['id'],
          'name' => (string)($combo['subject_name'] ?? 'Subject')
        ];
      }
    }
  }

  if ($selectedExamId > 0 && $selectedSubjectComb > 0) {
    $stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, c.name AS class_name, t.name AS term_name
      FROM tbl_exams e
      LEFT JOIN tbl_classes c ON c.id = e.class_id
      LEFT JOIN tbl_terms t ON t.id = e.term_id
      WHERE e.id = ?");
    $stmt->execute([$selectedExamId]);
    $examMeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$examMeta) {
      throw new RuntimeException('Exam not found.');
    }

    if (!isset($classSubjects[$selectedExamId][$selectedSubjectComb])) {
      throw new RuntimeException('You are not assigned to this exam subject.');
    }

    $stmt = $conn->prepare("SELECT sc.id, sc.subject, s.name AS subject_name
      FROM tbl_subject_combinations sc
      LEFT JOIN tbl_subjects s ON s.id = sc.subject
      WHERE sc.id = ? AND sc.teacher = ?");
    $stmt->execute([$selectedSubjectComb, (int)$account_id]);
    $subjectMeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subjectMeta) {
      throw new RuntimeException('Subject assignment not found.');
    }

    $classMeta = [
      'id' => (int)$examMeta['class_id'],
      'name' => (string)($examMeta['class_name'] ?? ''),
    ];

    $hasSchoolId = app_column_exists($conn, 'tbl_students', 'school_id');

    if ($hasSchoolId) {
      $admExpr = "COALESCE(NULLIF(TRIM(school_id), ''), id)";
    } else {
      $admExpr = 'id';
    }

    $orderBy = $sortBy === 'name'
      ? 'fname ASC, mname ASC, lname ASC, id ASC'
      : $admExpr . ' ASC, fname ASC, mname ASC, lname ASC';

    $sql = "SELECT id, fname, mname, lname, " . $admExpr . " AS admission_no
      FROM tbl_students
      WHERE class = ? AND status = 1
      ORDER BY " . $orderBy;
    $stmt = $conn->prepare($sql);
    $stmt->execute([(int)$examMeta['class_id']]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $formError = 'Failed to load mark sheet data.';
  error_log('[teacher/print_mark_sheet] ' . $e->getMessage());
}

if ($assessmentLabel === '') {
  $assessmentLabel = 'Assessment';
}

$columnTitles = [];
for ($i = 1; $i <= $columns; $i++) {
  if ($columns === 1) {
    $columnTitles[] = $assessmentLabel;
    continue;
  }
  if ($columnPrefix !== '') {
    $columnTitles[] = $columnPrefix . ' ' . $i;
  } else {
    $columnTitles[] = 'Marks ' . $i;
  }
}

function app_sheet_h(?string $value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($downloadPdf && !empty($students) && $examMeta && $subjectMeta) {
  require_once('tcpdf/tcpdf.php');

  $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
  $pdf->SetCreator((string)APP_NAME);
  $pdf->SetAuthor(trim((string)$fname . ' ' . (string)$lname));
  $pdf->SetTitle('Mark Sheet - ' . (string)($classMeta['name'] ?? 'Class'));
  $pdf->SetSubject('Teacher Printable Mark Sheet');
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->SetMargins(8, 8, 8);
  $pdf->SetAutoPageBreak(true, 8);
  $pdf->AddPage();

  $schoolName = (string)(WBName !== '' ? WBName : APP_NAME);
  $className = (string)($classMeta['name'] ?? '');
  $subjectName = (string)($subjectMeta['subject_name'] ?? '');
  $termName = (string)($examMeta['term_name'] ?? '');
  $examName = (string)($examMeta['name'] ?? '');
  $motto = (string)WBMotto;
  $contacts = [];
  if ((string)WBPhone !== '') { $contacts[] = 'Phone: ' . (string)WBPhone; }
  if ((string)WBEmail !== '') { $contacts[] = 'Email: ' . (string)WBEmail; }
  $contactsLine = implode(' | ', $contacts);

  $nameWidth = $showCodes ? 48 : 58;
  $admWidth = 24;
  $codeWidth = $showCodes ? 24 : 0;
  $fixedWidth = 8 + $nameWidth + $admWidth + $codeWidth;
  $marksWidth = max(16, (190 - $fixedWidth) / max(1, count($columnTitles)));

  $thead = '<tr>'
    . '<th width="8%"><b>No</b></th>'
    . '<th width="' . $nameWidth . '%"><b>Student Name</b></th>'
    . '<th width="' . $admWidth . '%"><b>Adm No</b></th>';
  foreach ($columnTitles as $title) {
    $thead .= '<th width="' . $marksWidth . '%"><b>' . app_sheet_h($title) . ' (/' . (int)$maxScore . ')</b></th>';
  }
  if ($showCodes) {
    $thead .= '<th width="' . $codeWidth . '%"><b>Code (EE/ME/AE/BE)</b></th>';
  }
  $thead .= '</tr>';

  $tbody = '';
  foreach ($students as $index => $student) {
    $name = trim((string)$student['fname'] . ' ' . (string)$student['mname'] . ' ' . (string)$student['lname']);
    $admNo = (string)($student['admission_no'] ?? $student['id']);
    $tbody .= '<tr>'
      . '<td style="text-align:center;">' . (int)($index + 1) . '</td>'
      . '<td>' . app_sheet_h($name) . '</td>'
      . '<td style="text-align:center;">' . app_sheet_h($admNo) . '</td>';
    foreach ($columnTitles as $title) {
      $tbody .= '<td>&nbsp;</td>';
    }
    if ($showCodes) {
      $tbody .= '<td>&nbsp;</td>';
    }
    $tbody .= '</tr>';
  }

  $html = ''
    . '<h2 style="text-align:center; margin:0;">' . app_sheet_h($schoolName) . '</h2>'
    . ($motto !== '' ? '<div style="text-align:center; font-size:10pt; color:#4b5f56; margin-top:2px;">' . app_sheet_h($motto) . '</div>' : '')
    . ($contactsLine !== '' ? '<div style="text-align:center; font-size:9pt; color:#4b5f56; margin-top:2px;">' . app_sheet_h($contactsLine) . '</div>' : '')
    . '<h3 style="text-align:center; margin:8px 0 6px 0;">Printable Mark Sheet</h3>'
    . '<table cellpadding="4" cellspacing="0" border="1" style="font-size:10pt;">'
    . '<tr><td width="50%"><b>Class:</b> ' . app_sheet_h($className) . '</td><td width="50%"><b>Subject:</b> ' . app_sheet_h($subjectName) . '</td></tr>'
    . '<tr><td width="50%"><b>Assessment:</b> ' . app_sheet_h($assessmentLabel) . '</td><td width="50%"><b>Term:</b> ' . app_sheet_h($termName) . '</td></tr>'
    . '<tr><td width="50%"><b>Exam Session:</b> ' . app_sheet_h($examName) . '</td><td width="50%"><b>Purpose:</b> Raw mark capture for later system entry.</td></tr>'
    . '</table>'
    . '<br>'
    . '<table cellpadding="4" cellspacing="0" border="1" style="font-size:9pt;">'
    . '<thead>' . $thead . '</thead>'
    . '<tbody>' . $tbody . '</tbody>'
    . '</table>'
    . ($showCodes ? '<div style="font-size:8pt; color:#5f6b65; margin-top:5px;">Suggested coding key: EE = Exceeding Expectation, ME = Meeting Expectation, AE = Approaching Expectation, BE = Below Expectation.</div>' : '')
    . '<br><br>'
    . '<table cellpadding="2" cellspacing="0" border="0" style="font-size:10pt;">'
    . '<tr><td width="55%">Teacher Signature: __________________________</td><td width="45%">Date: __________________</td></tr>'
    . '</table>';

  $pdf->writeHTML($html, true, false, true, false, '');
  $filename = 'mark_sheet_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower($className . '_' . $subjectName)) . '.pdf';
  $pdf->Output($filename, 'D');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Printable Mark Sheet</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
<style>
body.print-sheet-page{background:linear-gradient(180deg,#eef5f2 0%,#f7fbf9 42%,#edf3ef 100%)}
.sheet-page-shell{display:grid;gap:18px}
.sheet-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#07463d 0%,#0b7b70 56%,#0e8eb1 100%);color:#fff;border-radius:24px;padding:24px 26px;box-shadow:0 22px 50px rgba(6,60,52,.18);display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:18px;align-items:stretch}
.sheet-hero:before,.sheet-hero:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.1);pointer-events:none}
.sheet-hero:before{width:210px;height:210px;right:-80px;top:-84px}
.sheet-hero:after{width:150px;height:150px;right:120px;bottom:-80px}
.sheet-hero-copy{position:relative;z-index:1}
.sheet-hero h2{margin:0 0 8px;font-size:clamp(1.2rem,2.5vw,1.8rem);font-weight:900;letter-spacing:-.03em}
.sheet-hero p{margin:0;opacity:.95;line-height:1.65}
.sheet-hero-badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.sheet-badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:rgba(255,255,255,.12);padding:7px 12px;font-size:.82rem;font-weight:700}
.sheet-hero-stats{position:relative;z-index:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.sheet-stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);backdrop-filter:blur(10px);border-radius:18px;padding:14px}
.sheet-stat .label{text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;opacity:.8}
.sheet-stat .value{font-size:1.2rem;font-weight:800;margin-top:4px}
.sheet-wrap { background:#fff; border:1px solid #d6e2dd; border-radius:16px; padding:18px; box-shadow:0 12px 30px rgba(14,53,47,.08); }
.sheet-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:12px; }
.sheet-school { font-size:1.1rem; font-weight:800; color:#164e3a; line-height:1.2; }
.sheet-sub { font-size:12px; color:#4b5f56; margin-top:4px; }
.sheet-meta { width:100%; border-collapse:collapse; margin-top:8px; margin-bottom:10px; }
.sheet-meta td { border:1px solid #d5e0da; padding:6px 8px; font-size:12px; }
.sheet-table { width:100%; border-collapse:collapse; }
.sheet-table th, .sheet-table td { border:1px solid #333; padding:6px 8px; font-size:12px; }
.sheet-table th { background:#f4f6f5; text-align:center; }
.sheet-table .name-cell { text-align:left; }
.sheet-sign { margin-top:18px; display:flex; justify-content:space-between; gap:12px; font-size:12px; flex-wrap:wrap; }
.sheet-sign .line { min-width:240px; border-bottom:1px solid #333; height:26px; display:inline-block; }
.sheet-code-help { font-size:10px; color:#5f6b65; margin-top:8px; }
.logo-box { width:72px; height:72px; border:1px solid #d5dfda; border-radius:12px; background:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; box-shadow:0 8px 18px rgba(13,61,43,.08); }
.logo-box img { max-width:100%; max-height:100%; object-fit:contain; }
@media (max-width: 991px){.sheet-hero{grid-template-columns:1fr}.sheet-hero-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 600px){.sheet-hero-stats{grid-template-columns:1fr}.sheet-sign .line{min-width:180px}}
@media print {
  .app-header, .app-sidebar, .app-title, .print-controls, .app-footer, .no-print, .app-nav { display:none !important; }
  .app-content { margin:0 !important; padding:0 !important; }
  .sheet-wrap { border:none; border-radius:0; padding:0; }
}
</style>
</head>
<body class="app sidebar-mini print-sheet-page">

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
<h1>Printable Mark Sheet Generator</h1>
<p>Generate paper mark sheets for class marking, then enter marks later in the system.</p>
</div>
</div>

<section class="sheet-hero mb-3">
  <div class="sheet-hero-copy">
    <p class="text-uppercase fw-bold mb-2" style="letter-spacing:.1em;opacity:.8;">Paper workflow</p>
    <h2>Generate a clean mark sheet for manual capture and classroom marking.</h2>
    <p>The screen preview mirrors a print-ready sheet so you can hand it out, fill it in, and later enter the scores back into the system.</p>
    <div class="sheet-hero-badges">
      <span class="sheet-badge"><i class="bi bi-printer"></i>Print-ready</span>
      <span class="sheet-badge"><i class="bi bi-file-earmark-pdf"></i>PDF download</span>
      <span class="sheet-badge"><i class="bi bi-clipboard-check"></i>Paper entry workflow</span>
    </div>
  </div>
  <div class="sheet-hero-stats">
    <div class="sheet-stat"><div class="label">Students loaded</div><div class="value"><?php echo !empty($students) ? count($students) : 0; ?></div></div>
    <div class="sheet-stat"><div class="label">Columns</div><div class="value"><?php echo (int)$columns; ?></div></div>
    <div class="sheet-stat"><div class="label">Max score</div><div class="value"><?php echo (int)$maxScore; ?></div></div>
    <div class="sheet-stat"><div class="label">Coding key</div><div class="value"><?php echo $showCodes ? 'Enabled' : 'Hidden'; ?></div></div>
  </div>
</section>

<?php if ($formError !== ''): ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($formError); ?></div></div>
<?php endif; ?>

<div class="tile no-print" style="border-radius:18px;box-shadow:0 12px 28px rgba(14,53,47,.08);">
<form method="GET" action="teacher/print_mark_sheet" class="row g-3" id="markSheetForm">
  <div class="col-md-4">
    <label class="form-label">Exam / Assessment Session</label>
    <select class="form-control select2" name="exam_id" id="examSelect" required>
      <option value="" selected disabled>Select exam</option>
      <?php foreach ($exams as $exam): ?>
      <option value="<?php echo (int)$exam['id']; ?>" <?php echo (int)$exam['id'] === $selectedExamId ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars((string)$exam['name'] . ' - ' . (string)$exam['class_name'] . ' (' . (string)$exam['term_name'] . ')'); ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Subject</label>
    <select class="form-control select2" name="subject_combination" id="subjectSelect" required>
      <option value="" selected disabled>Select subject</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Assessment Label</label>
    <select class="form-control" name="assessment_type" id="assessmentType">
      <?php $types = ['CAT 1','CAT 2','Midterm','Exam','Custom']; foreach ($types as $type): ?>
      <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $assessmentType === $type ? 'selected' : ''; ?>><?php echo htmlspecialchars($type); ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Custom Assessment Text</label>
    <input class="form-control" type="text" name="assessment_custom" id="assessmentCustom" value="<?php echo htmlspecialchars($customAssessment); ?>" placeholder="e.g. End of Month Test">
  </div>
  <div class="col-md-2">
    <label class="form-label">Columns</label>
    <select class="form-control" name="columns">
      <?php for ($i = 1; $i <= 6; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $columns === $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label">Max Score</label>
    <input class="form-control" type="number" name="max_score" min="1" max="500" value="<?php echo (int)$maxScore; ?>">
  </div>
  <div class="col-md-4">
    <label class="form-label">Sort Students By</label>
    <select class="form-control" name="sort">
      <option value="admission" <?php echo $sortBy === 'admission' ? 'selected' : ''; ?>>Admission Number</option>
      <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Alphabetical Name</option>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Column Prefix (optional)</label>
    <input class="form-control" type="text" name="column_prefix" value="<?php echo htmlspecialchars($columnPrefix); ?>" placeholder="e.g. CAT">
  </div>
  <div class="col-md-4 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="1" name="show_codes" id="showCodes" <?php echo $showCodes ? 'checked' : ''; ?>>
      <label class="form-check-label" for="showCodes">Include CBC code column (EE/ME/AE/BE)</label>
    </div>
  </div>
  <div class="col-12 d-flex gap-2">
    <button class="btn btn-primary" type="submit"><i class="bi bi-layout-text-window-reverse me-1"></i>Generate Mark Sheet</button>
    <?php if (!empty($students)): ?>
    <button class="btn btn-outline-secondary" type="button" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print</button>
    <a class="btn btn-outline-primary" href="<?php echo app_sheet_h('teacher/print_mark_sheet?' . http_build_query(array_merge($_GET, ['download' => '1']))); ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
    <?php endif; ?>
  </div>
</form>
</div>

<?php if (!empty($students) && $examMeta && $subjectMeta): ?>
<div class="tile print-controls no-print" style="border-radius:18px;box-shadow:0 12px 28px rgba(14,53,47,.08);">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted small">Loaded <?php echo count($students); ?> students</div>
    <div class="d-flex gap-2">
      <button class="btn btn-success" type="button" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print Mark Sheet</button>
      <a class="btn btn-outline-primary" href="<?php echo app_sheet_h('teacher/print_mark_sheet?' . http_build_query(array_merge($_GET, ['download' => '1']))); ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
    </div>
  </div>
</div>

<div class="tile" style="border-radius:18px;box-shadow:0 12px 28px rgba(14,53,47,.08);">
  <div class="sheet-wrap">
    <div class="sheet-header">
      <div>
        <div class="sheet-school"><?php echo htmlspecialchars((string)(WBName !== '' ? WBName : APP_NAME)); ?></div>
        <div class="sheet-sub"><?php echo htmlspecialchars((string)WBMotto); ?></div>
        <div class="sheet-sub">
          <?php if ((string)WBPhone !== ''): ?>Phone: <?php echo htmlspecialchars((string)WBPhone); ?><?php endif; ?>
          <?php if ((string)WBPhone !== '' && (string)WBEmail !== ''): ?> | <?php endif; ?>
          <?php if ((string)WBEmail !== ''): ?>Email: <?php echo htmlspecialchars((string)WBEmail); ?><?php endif; ?>
        </div>
      </div>
      <div class="logo-box">
        <?php if ((string)WBLogo !== '' && is_file('images/logo/' . (string)WBLogo)): ?>
        <img src="images/logo/<?php echo htmlspecialchars((string)WBLogo); ?>" alt="School Logo">
        <?php endif; ?>
      </div>
    </div>

    <table class="sheet-meta">
      <tr>
        <td><strong>Class:</strong> <?php echo htmlspecialchars((string)($classMeta['name'] ?? '')); ?></td>
        <td><strong>Subject:</strong> <?php echo htmlspecialchars((string)($subjectMeta['subject_name'] ?? '')); ?></td>
      </tr>
      <tr>
        <td><strong>Assessment:</strong> <?php echo htmlspecialchars($assessmentLabel); ?></td>
        <td><strong>Term:</strong> <?php echo htmlspecialchars((string)($examMeta['term_name'] ?? '')); ?></td>
      </tr>
      <tr>
        <td><strong>Exam Session:</strong> <?php echo htmlspecialchars((string)($examMeta['name'] ?? '')); ?></td>
        <td><strong>Purpose:</strong> Raw mark capture for later system entry.</td>
      </tr>
    </table>

    <table class="sheet-table">
      <thead>
        <tr>
          <th style="width:40px;">No</th>
          <th class="name-cell">Student Name</th>
          <th style="width:110px;">Adm No</th>
          <?php foreach ($columnTitles as $title): ?>
          <th style="width:105px;"><?php echo htmlspecialchars($title); ?> (/<?php echo (int)$maxScore; ?>)</th>
          <?php endforeach; ?>
          <?php if ($showCodes): ?>
          <th style="width:120px;">Code (EE/ME/AE/BE)</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $index => $student):
          $name = trim((string)$student['fname'] . ' ' . (string)$student['mname'] . ' ' . (string)$student['lname']);
          $admNo = (string)($student['admission_no'] ?? $student['id']);
        ?>
        <tr>
          <td style="text-align:center;"><?php echo (int)($index + 1); ?></td>
          <td class="name-cell"><?php echo htmlspecialchars($name); ?></td>
          <td style="text-align:center;"><?php echo htmlspecialchars($admNo); ?></td>
          <?php foreach ($columnTitles as $title): ?>
          <td>&nbsp;</td>
          <?php endforeach; ?>
          <?php if ($showCodes): ?>
          <td>&nbsp;</td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <?php if ($showCodes): ?>
    <div class="sheet-code-help">Suggested coding key: EE = Exceeding Expectation, ME = Meeting Expectation, AE = Approaching Expectation, BE = Below Expectation.</div>
    <?php endif; ?>

    <div class="sheet-sign">
      <div>Teacher Signature: <span class="line"></span></div>
      <div>Date: <span class="line" style="min-width:160px;"></span></div>
    </div>
  </div>
</div>
<?php endif; ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<script>
$('.select2').select2();
const classSubjects = <?php echo json_encode($classSubjects); ?>;
const selectedSubjectCombination = <?php echo (int)$selectedSubjectComb; ?>;

function fillSubjects() {
  const examId = $('#examSelect').val();
  const subjects = Object.values((classSubjects[examId] || {}));
  const $subject = $('#subjectSelect');
  $subject.empty().append('<option value="" selected disabled>Select subject</option>');

  subjects.forEach(item => {
    const isSel = String(item.id) === String(selectedSubjectCombination);
    $subject.append(`<option value="${item.id}" ${isSel ? 'selected' : ''}>${item.name}</option>`);
  });

  if (!subjects.length) {
    $subject.append('<option value="" disabled>No assigned subjects for this exam</option>');
  }

  $subject.trigger('change.select2');
}

$('#examSelect').on('change', function() {
  fillSubjects();
});

$('#assessmentType').on('change', function() {
  const custom = document.getElementById('assessmentCustom');
  if (!custom) return;
  custom.disabled = this.value !== 'Custom';
  if (this.value !== 'Custom') {
    custom.value = '';
  }
});

fillSubjects();
$('#assessmentType').trigger('change');
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
