<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/pdf_branding.php');

if ($res !== '1' || !in_array((string)$level, ['0', '1', '2', '9'], true)) {
  header('location:../');
  exit;
}

$originPortal = strtolower(trim((string)($_GET['origin_portal'] ?? '')));
if (!in_array($originPortal, ['teacher', 'academic', 'admin'], true)) {
  if (app_is_attendance_admin_level((string)$level)) {
    $originPortal = 'admin';
  } elseif ((string)$level === '1') {
    $originPortal = 'academic';
  } else {
    $originPortal = 'teacher';
  }
}

$selectedClassId = (int)($_GET['class_id'] ?? 0);
$columns = (int)($_GET['columns'] ?? 6);
$columns = max(6, min(12, $columns));
$columnPrefix = trim((string)($_GET['column_prefix'] ?? 'Entry'));
$sheetTitle = trim((string)($_GET['sheet_title'] ?? 'Printable Class List'));
$sheetUse = trim((string)($_GET['sheet_use'] ?? 'Attendance, marks, and classroom notes'));
$sortBy = (string)($_GET['sort'] ?? 'admission');
if (!in_array($sortBy, ['admission', 'name'], true)) {
  $sortBy = 'admission';
}
$downloadPdf = isset($_GET['download']) && (string)$_GET['download'] === '1';

$classes = [];
$students = [];
$allowedClassIds = [];
$classMeta = null;
$formError = '';
$isAttendanceAdmin = false;

function app_class_list_h(?string $value): string
{
  return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function app_class_list_layout(int $columnCount): array
{
  $columnCount = max(6, $columnCount);
  $numberWidth = 6;
  $admissionWidth = 14;
  $nameWidth = max(22, 38 - (($columnCount - 6) * 4));
  $workTotalWidth = max(24, 100 - $numberWidth - $admissionWidth - $nameWidth);
  $workWidth = $workTotalWidth / $columnCount;

  return [
    'number' => $numberWidth,
    'name' => $nameWidth,
    'admission' => $admissionWidth,
    'work' => $workWidth,
  ];
}

function app_class_list_render_profile(int $columnCount): array
{
  $columnCount = max(6, $columnCount);
  $orientation = $columnCount >= 8 ? 'L' : 'P';
  $tableFontSize = 8.8;
  $metaFontSize = 10;
  $dense = $columnCount >= 8;

  if ($columnCount >= 11) {
    $tableFontSize = 7.1;
    $metaFontSize = 9;
  } elseif ($columnCount >= 9) {
    $tableFontSize = 7.8;
    $metaFontSize = 9.3;
  } elseif ($columnCount >= 8) {
    $tableFontSize = 8.2;
    $metaFontSize = 9.6;
  }

  return [
    'orientation' => $orientation,
    'table_font_size' => $tableFontSize,
    'meta_font_size' => $metaFontSize,
    'is_dense' => $dense,
  ];
}

$columnTitles = [];
for ($i = 1; $i <= $columns; $i++) {
  $columnTitles[] = ($columnPrefix !== '' ? $columnPrefix : 'Entry') . ' ' . $i;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_class_teachers_table($conn);
  $isAttendanceAdmin = app_is_attendance_admin_level((string)$level);

  if (!$isAttendanceAdmin) {
    $allowedClassIds = app_staff_class_teacher_ids($conn, (int)$account_id);
    if (count($allowedClassIds) < 1) {
      app_render_access_error_page(
        'Class list access restricted',
        'Printable class lists are available to admins and teachers who are currently assigned as class teachers.',
        403,
        [
          'portal' => $originPortal,
          'account_id' => (string)$account_id,
        ]
      );
    }
  }

  if ($isAttendanceAdmin) {
    $stmt = $conn->prepare("SELECT c.id, c.name, st.fname AS teacher_fname, st.lname AS teacher_lname
      FROM tbl_classes c
      LEFT JOIN tbl_class_teachers ct ON ct.class_id = c.id AND ct.active = 1
      LEFT JOIN tbl_staff st ON st.id = ct.teacher_id
      ORDER BY c.name ASC");
    $stmt->execute();
  } else {
    $matches = implode(',', array_fill(0, count($allowedClassIds), '?'));
    $stmt = $conn->prepare("SELECT c.id, c.name, st.fname AS teacher_fname, st.lname AS teacher_lname
      FROM tbl_classes c
      LEFT JOIN tbl_class_teachers ct ON ct.class_id = c.id AND ct.active = 1
      LEFT JOIN tbl_staff st ON st.id = ct.teacher_id
      WHERE c.id IN ($matches)
      ORDER BY c.name ASC");
    $stmt->execute($allowedClassIds);
  }
  $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($selectedClassId > 0) {
    if (!$isAttendanceAdmin && !in_array($selectedClassId, $allowedClassIds, true)) {
      throw new RuntimeException('You are not assigned to this class.');
    }

    $stmt = $conn->prepare("SELECT c.id, c.name, st.fname AS teacher_fname, st.lname AS teacher_lname
      FROM tbl_classes c
      LEFT JOIN tbl_class_teachers ct ON ct.class_id = c.id AND ct.active = 1
      LEFT JOIN tbl_staff st ON st.id = ct.teacher_id
      WHERE c.id = ?
      LIMIT 1");
    $stmt->execute([$selectedClassId]);
    $classMeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$classMeta) {
      throw new RuntimeException('Class not found.');
    }

    $studentClassColumn = app_column_exists($conn, 'tbl_students', 'class_id') ? 'class_id' : 'class';
    $admissionExpr = app_column_exists($conn, 'tbl_students', 'school_id')
      ? "COALESCE(NULLIF(TRIM(school_id), ''), id)"
      : 'id';

    $studentFilter = $studentClassColumn . ' = ?';
    if (app_column_exists($conn, 'tbl_students', 'status')) {
      $studentFilter .= ' AND (status = 1 OR status IS NULL OR status = 0)';
    }

    $orderBy = $sortBy === 'name'
      ? 'fname ASC, mname ASC, lname ASC, id ASC'
      : $admissionExpr . ' ASC, fname ASC, mname ASC, lname ASC';

    $stmt = $conn->prepare("SELECT id, fname, mname, lname, " . $admissionExpr . " AS admission_no
      FROM tbl_students
      WHERE " . $studentFilter . "
      ORDER BY " . $orderBy);
    $stmt->execute([$selectedClassId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $formError = 'Failed to load class list data.';
  error_log('[teacher/print_class_list] ' . $e->getMessage());
}

if ($sheetTitle === '') {
  $sheetTitle = 'Printable Class List';
}

if ($sheetUse === '') {
  $sheetUse = 'Attendance, marks, and classroom notes';
}

if ($downloadPdf && !empty($students) && $classMeta) {
  require_once('tcpdf/tcpdf.php');

  $renderProfile = app_class_list_render_profile(count($columnTitles));
  $layout = app_class_list_layout(count($columnTitles));
  $pdf = new TCPDF($renderProfile['orientation'], 'mm', 'A4', true, 'UTF-8', false);
  $pdf->SetCreator((string)APP_NAME);
  $pdf->SetAuthor(trim((string)$fname . ' ' . (string)$lname));
  $pdf->SetTitle('Class List - ' . (string)($classMeta['name'] ?? 'Class'));
  $pdf->SetSubject('Printable Class List');
  $pdf->setPrintHeader(false);
  $pdf->setPrintFooter(false);
  $pdf->SetMargins(8, 8, 8);
  $pdf->SetAutoPageBreak(true, 8);
  $pdf->AddPage();

  $schoolName = (string)(WBName !== '' ? WBName : APP_NAME);
  $motto = (string)WBMotto;
  $contacts = [];
  if ((string)WBPhone !== '') { $contacts[] = 'Phone: ' . (string)WBPhone; }
  if ((string)WBEmail !== '') { $contacts[] = 'Email: ' . (string)WBEmail; }
  $contactsLine = implode(' | ', $contacts);
  $classTeacherName = trim((string)($classMeta['teacher_fname'] ?? '') . ' ' . (string)($classMeta['teacher_lname'] ?? ''));

  $thead = '<tr>'
    . '<th width="' . $layout['number'] . '%"><b>No</b></th>'
    . '<th width="' . $layout['name'] . '%"><b>Student Name</b></th>'
    . '<th width="' . $layout['admission'] . '%"><b>Adm No</b></th>';
  foreach ($columnTitles as $title) {
    $thead .= '<th width="' . $layout['work'] . '%"><b>' . app_class_list_h($title) . '</b></th>';
  }
  $thead .= '</tr>';

  $tbody = '';
  foreach ($students as $index => $student) {
    $name = trim((string)$student['fname'] . ' ' . (string)$student['mname'] . ' ' . (string)$student['lname']);
    $admNo = (string)($student['admission_no'] ?? $student['id']);
    $tbody .= '<tr>'
      . '<td style="text-align:center;">' . (int)($index + 1) . '</td>'
      . '<td>' . app_class_list_h($name) . '</td>'
      . '<td style="text-align:center;">' . app_class_list_h($admNo) . '</td>';
    foreach ($columnTitles as $title) {
      $tbody .= '<td>&nbsp;</td>';
    }
    $tbody .= '</tr>';
  }

  $metaFont = number_format((float)$renderProfile['meta_font_size'], 1, '.', '');
  $tableFont = number_format((float)$renderProfile['table_font_size'], 1, '.', '');
  $html = ''
    . '<h2 style="text-align:center; margin:0;">' . app_class_list_h($schoolName) . '</h2>'
    . ($motto !== '' ? '<div style="text-align:center; font-size:10pt; color:#4b5f56; margin-top:2px;">' . app_class_list_h($motto) . '</div>' : '')
    . ($contactsLine !== '' ? '<div style="text-align:center; font-size:9pt; color:#4b5f56; margin-top:2px;">' . app_class_list_h($contactsLine) . '</div>' : '')
    . '<h3 style="text-align:center; margin:8px 0 6px 0;">' . app_class_list_h($sheetTitle) . '</h3>'
    . '<table cellpadding="4" cellspacing="0" border="1" style="font-size:' . $metaFont . 'pt;">'
    . '<tr><td width="50%"><b>Class:</b> ' . app_class_list_h((string)($classMeta['name'] ?? '')) . '</td><td width="50%"><b>Use:</b> ' . app_class_list_h($sheetUse) . '</td></tr>'
    . '<tr><td width="50%"><b>Class Teacher:</b> ' . app_class_list_h($classTeacherName !== '' ? $classTeacherName : 'Not assigned') . '</td><td width="50%"><b>Students:</b> ' . count($students) . '</td></tr>'
    . '<tr><td width="50%"><b>Generated By:</b> ' . app_class_list_h(trim((string)$fname . ' ' . (string)$lname)) . '</td><td width="50%"><b>Date:</b> ' . app_class_list_h(date('Y-m-d')) . '</td></tr>'
    . '</table>'
    . '<br>'
    . '<table cellpadding="4" cellspacing="0" border="1" style="font-size:' . $tableFont . 'pt; table-layout:fixed; width:100%;">'
    . '<thead>' . $thead . '</thead>'
    . '<tbody>' . $tbody . '</tbody>'
    . '</table>'
    . '<br><br>'
    . '<table cellpadding="2" cellspacing="0" border="0" style="font-size:' . $metaFont . 'pt;">'
    . '<tr><td width="55%">Class Teacher Signature: __________________________</td><td width="45%">Date: __________________</td></tr>'
    . '</table>';

  $pdf->writeHTML($html, true, false, true, false, '');
  app_pdf_draw_official_footer($pdf, [
    'base_y' => $pdf->getPageHeight() - 18,
    'date_value' => date('Y-m-d'),
    'title' => 'Headteacher',
    'stamp_x' => $pdf->getPageWidth() - 24,
    'stamp_y' => $pdf->getPageHeight() - 22,
    'stamp_size' => 14,
    'signature_width' => 24,
    'signature_height' => 10,
    'line_width' => 44,
  ]);
  $filename = 'class_list_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', strtolower((string)($classMeta['name'] ?? 'class'))) . '.pdf';
  $pdf->Output($filename, 'D');
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Printable Class List</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
<style>
body.print-class-list-page{background:linear-gradient(180deg,#edf5f1 0%,#f7fbf9 42%,#ecf3ef 100%)}
.class-list-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#0c4b41 0%,#11796f 52%,#1693a2 100%);color:#fff;border-radius:24px;padding:24px 26px;box-shadow:0 22px 50px rgba(6,60,52,.18);display:grid;grid-template-columns:minmax(0,1.2fr) minmax(280px,.8fr);gap:18px;align-items:stretch}
.class-list-hero:before,.class-list-hero:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.1);pointer-events:none}
.class-list-hero:before{width:210px;height:210px;right:-80px;top:-84px}
.class-list-hero:after{width:150px;height:150px;right:120px;bottom:-80px}
.class-list-copy,.class-list-stats{position:relative;z-index:1}
.class-list-copy h2{margin:0 0 8px;font-size:clamp(1.2rem,2.5vw,1.8rem);font-weight:900;letter-spacing:-.03em}
.class-list-copy p{margin:0;opacity:.95;line-height:1.65}
.class-list-badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}
.class-list-badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;background:rgba(255,255,255,.12);padding:7px 12px;font-size:.82rem;font-weight:700}
.class-list-stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.class-list-stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);backdrop-filter:blur(10px);border-radius:18px;padding:14px}
.class-list-stat .label{text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;opacity:.8}
.class-list-stat .value{font-size:1.2rem;font-weight:800;margin-top:4px}
.class-list-wrap{background:#fff;border:1px solid #d6e2dd;border-radius:16px;padding:18px;box-shadow:0 12px 30px rgba(14,53,47,.08)}
.class-list-wrap.class-list-dense{padding:16px 14px}
.class-list-header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
.class-list-school{font-size:1.1rem;font-weight:800;color:#164e3a;line-height:1.2}
.class-list-sub{font-size:12px;color:#4b5f56;margin-top:4px}
.class-list-meta{width:100%;border-collapse:collapse;table-layout:fixed;margin-top:8px;margin-bottom:10px}
.class-list-meta td{border:1px solid #d5e0da;padding:6px 8px;font-size:12px}
.class-list-table{width:100%;border-collapse:collapse;table-layout:fixed}
.class-list-table th,.class-list-table td{border:1px solid #333;padding:6px 8px;font-size:12px;vertical-align:middle}
.class-list-table th{background:#f4f6f5;text-align:center}
.class-list-table .name-cell{text-align:left}
.class-list-table .cell-center{text-align:center}
.class-list-table .wrap-tight{white-space:normal;word-break:break-word;overflow-wrap:anywhere}
.class-list-wrap.class-list-dense .class-list-table th,.class-list-wrap.class-list-dense .class-list-table td{padding:5px 5px;font-size:11px}
.class-list-wrap.class-list-dense .class-list-meta td{font-size:11px}
.class-list-sign{margin-top:18px;display:flex;justify-content:space-between;gap:12px;font-size:12px;flex-wrap:wrap}
.class-list-sign .line{min-width:240px;border-bottom:1px solid #333;height:26px;display:inline-block}
.logo-box{width:72px;height:72px;border:1px solid #d5dfda;border-radius:12px;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden;box-shadow:0 8px 18px rgba(13,61,43,.08)}
.logo-box img{max-width:100%;max-height:100%;object-fit:contain}
@media (max-width: 991px){.class-list-hero{grid-template-columns:1fr}.class-list-stats{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 600px){.class-list-stats{grid-template-columns:1fr}.class-list-sign .line{min-width:180px}}
@media (max-width: 1200px){.class-list-wrap.class-list-dense{overflow-x:auto}.class-list-wrap.class-list-dense .class-list-table{min-width:960px}}
@media print {
  .app-header, .app-sidebar, .app-title, .print-controls, .app-footer, .no-print, .app-nav { display:none !important; }
  .app-content { margin:0 !important; padding:0 !important; }
  .class-list-wrap { border:none; border-radius:0; padding:0; }
  .class-list-wrap.class-list-dense .class-list-table th, .class-list-wrap.class-list-dense .class-list-table td { font-size:10px; padding:4px 5px; }
}
</style>
</head>
<body class="app sidebar-mini print-class-list-page">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<?php if ($originPortal === 'admin') { ?>
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<?php } elseif ($originPortal === 'academic') { ?>
<li><a class="dropdown-item" href="academic/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<?php } else { ?>
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<?php } ?>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php if ($originPortal === 'admin') { ?>
<?php include("admin/partials/sidebar.php"); ?>
<?php } elseif ($originPortal === 'academic') { ?>
<?php include("academic/partials/sidebar.php"); ?>
<?php } else { ?>
<?php include("teacher/partials/sidebar.php"); ?>
<?php } ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Printable Class List</h1>
<p>Prepare one clean class list with at least six working columns for attendance, marks, or notes.</p>
</div>
</div>

<section class="class-list-hero mb-3">
  <div class="class-list-copy">
    <p class="text-uppercase fw-bold mb-2" style="letter-spacing:.1em;opacity:.8;">Classroom paperwork</p>
    <h2>Generate a neat class list for attendance, manual marks entry, and daily classroom tracking.</h2>
    <p>The class teacher can print one sheet and use the extra working columns for attendance ticks, score capture, remarks, or follow-up notes.</p>
    <div class="class-list-badges">
      <span class="class-list-badge"><i class="bi bi-people"></i>Class roster</span>
      <span class="class-list-badge"><i class="bi bi-printer"></i>Print-ready</span>
      <span class="class-list-badge"><i class="bi bi-file-earmark-pdf"></i>PDF download</span>
    </div>
  </div>
  <div class="class-list-stats">
    <div class="class-list-stat"><div class="label">Classes available</div><div class="value"><?php echo count($classes); ?></div></div>
    <div class="class-list-stat"><div class="label">Working columns</div><div class="value"><?php echo (int)$columns; ?></div></div>
    <div class="class-list-stat"><div class="label">Students loaded</div><div class="value"><?php echo count($students); ?></div></div>
    <div class="class-list-stat"><div class="label">Access mode</div><div class="value"><?php echo $isAttendanceAdmin ? 'Admin' : 'Class teacher'; ?></div></div>
  </div>
</section>

<?php if ($formError !== ''): ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($formError); ?></div></div>
<?php endif; ?>

<div class="tile no-print" style="border-radius:18px;box-shadow:0 12px 28px rgba(14,53,47,.08);">
<form method="GET" action="teacher/print_class_list" class="row g-3">
  <input type="hidden" name="origin_portal" value="<?php echo app_class_list_h($originPortal); ?>">
  <div class="col-md-4">
    <label class="form-label">Class</label>
    <select class="form-control select2" name="class_id" required>
      <option value="" selected disabled>Select class</option>
      <?php foreach ($classes as $classRow): ?>
      <option value="<?php echo (int)$classRow['id']; ?>" <?php echo $selectedClassId === (int)$classRow['id'] ? 'selected' : ''; ?>>
        <?php echo htmlspecialchars((string)$classRow['name']); ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-4">
    <label class="form-label">Sheet Title</label>
    <input class="form-control" type="text" name="sheet_title" value="<?php echo app_class_list_h($sheetTitle); ?>" placeholder="Printable Class List">
  </div>
  <div class="col-md-4">
    <label class="form-label">Use / Purpose</label>
    <input class="form-control" type="text" name="sheet_use" value="<?php echo app_class_list_h($sheetUse); ?>" placeholder="Attendance, marks, and notes">
  </div>
  <div class="col-md-3">
    <label class="form-label">Working Columns</label>
    <select class="form-control" name="columns">
      <?php for ($i = 6; $i <= 12; $i++): ?>
      <option value="<?php echo $i; ?>" <?php echo $columns === $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
      <?php endfor; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Column Prefix</label>
    <input class="form-control" type="text" name="column_prefix" value="<?php echo app_class_list_h($columnPrefix); ?>" placeholder="Entry">
  </div>
  <div class="col-md-3">
    <label class="form-label">Sort Students By</label>
    <select class="form-control" name="sort">
      <option value="admission" <?php echo $sortBy === 'admission' ? 'selected' : ''; ?>>Admission Number</option>
      <option value="name" <?php echo $sortBy === 'name' ? 'selected' : ''; ?>>Alphabetical Name</option>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-layout-text-window-reverse me-1"></i>Generate Class List</button>
  </div>
</form>
</div>

<?php if (!empty($students) && $classMeta): ?>
<?php
$classListLayout = app_class_list_layout(count($columnTitles));
$classListRender = app_class_list_render_profile(count($columnTitles));
$classTeacherName = trim((string)($classMeta['teacher_fname'] ?? '') . ' ' . (string)($classMeta['teacher_lname'] ?? ''));
?>
<div class="tile print-controls no-print" style="border-radius:18px;box-shadow:0 12px 28px rgba(14,53,47,.08);">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="text-muted small">Loaded <?php echo count($students); ?> students</div>
    <div class="d-flex gap-2">
      <button class="btn btn-success" type="button" onclick="window.print();"><i class="bi bi-printer me-1"></i>Print Class List</button>
      <a class="btn btn-outline-primary" href="<?php echo app_class_list_h('teacher/print_class_list?' . http_build_query(array_merge($_GET, ['download' => '1', 'origin_portal' => $originPortal]))); ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
    </div>
  </div>
</div>

<div class="tile" style="border-radius:18px;box-shadow:0 12px 28px rgba(14,53,47,.08);">
  <div class="class-list-wrap<?php echo !empty($classListRender['is_dense']) ? ' class-list-dense' : ''; ?>">
    <div class="class-list-header">
      <div>
        <div class="class-list-school"><?php echo htmlspecialchars((string)(WBName !== '' ? WBName : APP_NAME)); ?></div>
        <div class="class-list-sub"><?php echo htmlspecialchars((string)WBMotto); ?></div>
        <div class="class-list-sub">
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

    <table class="class-list-meta">
      <tr>
        <td><strong>Class:</strong> <?php echo htmlspecialchars((string)($classMeta['name'] ?? '')); ?></td>
        <td><strong>Use:</strong> <?php echo htmlspecialchars($sheetUse); ?></td>
      </tr>
      <tr>
        <td><strong>Class Teacher:</strong> <?php echo htmlspecialchars($classTeacherName !== '' ? $classTeacherName : 'Not assigned'); ?></td>
        <td><strong>Students:</strong> <?php echo count($students); ?></td>
      </tr>
      <tr>
        <td><strong>Generated By:</strong> <?php echo htmlspecialchars(trim((string)$fname . ' ' . (string)$lname)); ?></td>
        <td><strong>Date:</strong> <?php echo htmlspecialchars(date('Y-m-d')); ?></td>
      </tr>
    </table>

    <table class="class-list-table">
      <colgroup>
        <col style="width:<?php echo number_format((float)$classListLayout['number'], 4, '.', ''); ?>%;">
        <col style="width:<?php echo number_format((float)$classListLayout['name'], 4, '.', ''); ?>%;">
        <col style="width:<?php echo number_format((float)$classListLayout['admission'], 4, '.', ''); ?>%;">
        <?php foreach ($columnTitles as $title): ?>
        <col style="width:<?php echo number_format((float)$classListLayout['work'], 4, '.', ''); ?>%;">
        <?php endforeach; ?>
      </colgroup>
      <thead>
        <tr>
          <th>No</th>
          <th class="name-cell">Student Name</th>
          <th>Adm No</th>
          <?php foreach ($columnTitles as $title): ?>
          <th class="wrap-tight"><?php echo htmlspecialchars($title); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $index => $student):
          $studentName = trim((string)$student['fname'] . ' ' . (string)$student['mname'] . ' ' . (string)$student['lname']);
          $studentAdmNo = (string)($student['admission_no'] ?? $student['id']);
        ?>
        <tr>
          <td class="cell-center"><?php echo (int)($index + 1); ?></td>
          <td class="name-cell wrap-tight"><?php echo htmlspecialchars($studentName); ?></td>
          <td class="cell-center wrap-tight"><?php echo htmlspecialchars($studentAdmNo); ?></td>
          <?php foreach ($columnTitles as $title): ?>
          <td>&nbsp;</td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="class-list-sign">
      <div>Class Teacher Signature: <span class="line"></span></div>
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
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
