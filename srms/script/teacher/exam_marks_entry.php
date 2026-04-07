<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

$exams = [];
$classSubjects = [];

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT sc.id, sc.class, s.name AS subject_name
    FROM tbl_subject_combinations sc
    LEFT JOIN tbl_subjects s ON sc.subject = s.id
    WHERE sc.teacher = ?");
  $stmt->execute([$account_id]);
  $combos = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $classIds = [];
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
    $stmt = $conn->prepare("SELECT e.id, e.name, e.class_id, e.term_id, c.name AS class_name, t.name AS term_name
      FROM tbl_exams e
      LEFT JOIN tbl_classes c ON c.id = e.class_id
      LEFT JOIN tbl_terms t ON t.id = e.term_id
      WHERE e.status = 'open' AND e.class_id IN ($placeholders)
      ORDER BY e.created_at DESC");
    $stmt->execute($classIds);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", "Failed to load exams."));
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
<li><a class="app-menu__item" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
<li><a class="app-menu__item" href="teacher/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/list_students"><i class="icon bi bi-circle-fill"></i> List Students</a></li>
<li><a class="treeview-item" href="teacher/export_students"><i class="icon bi bi-circle-fill"></i> Export Students</a></li>
</ul>
</li>
<li class="treeview is-expanded"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Examination Results</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item active" href="teacher/exam_marks_entry"><i class="icon bi bi-circle-fill"></i> Exam Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/marks_entry"><i class="icon bi bi-circle-fill"></i> CBC Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/import_results"><i class="icon bi bi-circle-fill"></i> Import Results</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="teacher/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="teacher/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Exam Marks Entry</h1>
<p>Select an open exam and subject, then start entry.</p>
</div>
</div>

<div class="row">
<div class="col-md-6 center_form">
<div class="tile">
<div class="tile-body">
<h3 class="tile-title">Start Exam Entry</h3>
<form class="app_frm" method="POST" action="teacher/core/start_exam_entry">
<div class="mb-3">
<label class="form-label">Open Exam</label>
<select class="form-control select2" name="exam_id" id="examSelect" required>
<option value="" selected disabled>Select exam</option>
<?php foreach ($exams as $exam): ?>
<option value="<?php echo (int)$exam['id']; ?>" data-class="<?php echo (int)$exam['class_id']; ?>">
<?php echo htmlspecialchars($exam['name'].' - '.$exam['class_name'].' ('.$exam['term_name'].')'); ?>
</option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Subject</label>
<select class="form-control select2" name="subject_combination" id="subjectSelect" required>
<option value="" selected disabled>Select subject</option>
</select>
</div>
<button class="btn btn-primary app_btn">Start Entry</button>
</form>
</div>
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
  $('#examSelect').on('change', function () {
    const classId = $(this).find(':selected').data('class');
    const subjects = classSubjects[classId] || [];
    const $subject = $('#subjectSelect');
    $subject.empty().append('<option value="" selected disabled>Select subject</option>');
    subjects.forEach(item => {
      $subject.append(`<option value="${item.id}">${item.name}</option>`);
    });
    $subject.trigger('change');
  });
</script>
</body>
</html>
