<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

if (isset($_SESSION['student_result'])) {
$std = $_SESSION['student_result']['student'];
$term = $_SESSION['student_result']['term'];
$examId = (int)($_SESSION['student_result']['exam'] ?? 0);
$studentData = null;
$termData = null;
$classData = null;
$subjectRows = [];
$selectedExamName = '';
$useExamId = false;
$lockedSubjectIds = [];
$hasLockedSubjects = false;
$examReadOnly = false;

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, fname, mname, lname, class FROM tbl_students WHERE id = ?");
$stmt->execute([$std]);
$studentData = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id = ?");
$stmt->execute([$term]);
$termData = $stmt->fetch(PDO::FETCH_ASSOC);

$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
if ($examId > 0 && app_table_exists($conn, 'tbl_exams')) {
	$stmt = $conn->prepare("SELECT id, name, COALESCE(status, 'draft') AS status FROM tbl_exams WHERE id = ?");
	$stmt->execute([$examId]);
	$examData = $stmt->fetch(PDO::FETCH_ASSOC);
	$selectedExamName = (string)($examData['name'] ?? '');
	$examReadOnly = in_array(strtolower(trim((string)($examData['status'] ?? 'draft'))), ['finalized', 'published'], true);
}

$studentClassId = (int)($studentData['class'] ?? 0);
$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
$stmt->execute([$studentClassId]);
$classData = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT sc.id, sc.class, sb.name AS subject_name
	FROM tbl_subject_combinations sc
	LEFT JOIN tbl_subjects sb ON sb.id = sc.subject
	ORDER BY sb.name");
$stmt->execute();
$subjectRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($useExamId && $examId > 0) {
	foreach ($subjectRows as $subjectRow) {
		$subjectId = (int)($subjectRow['id'] ?? 0);
		if ($subjectId < 1) {
			continue;
		}
		$status = app_exam_submission_status($conn, $examId, $subjectId);
		if (in_array($status, ['submitted', 'reviewed', 'finalized'], true)) {
			$lockedSubjectIds[$subjectId] = $status;
			$hasLockedSubjects = true;
		}
	}
}

$tit = trim((string)($studentData['fname'] ?? '').' '.(string)($studentData['mname'] ?? '').' '.(string)($studentData['lname'] ?? '')).' ('.(string)($termData['name'] ?? 'Term').' Results'.($selectedExamName !== '' ? ' - '.$selectedExamName : '').')';
}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
header("location:./");
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - <?php echo $tit ?></title>
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
<li><a class="dropdown-item" href="academic/profile.php"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('academic/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1><?php echo $tit ?></h1>
</div>
</div>


<div class="row">
<div class="col-md-12 ">
<div class="tile">
<div class="tile-body">

<form enctype="multipart/form-data" action="academic/core/update_results.php" class="app_frm row" method="POST" autocomplete="OFF">

<?php if ($useExamId && $examId < 1) { ?>
<div class="col-12">
<div class="alert alert-warning">Select a specific exam first before editing individual results.</div>
</div>
<?php } ?>
<?php if ($examReadOnly) { ?>
<div class="col-12">
<div class="alert alert-warning">This exam is already finalized or published. Academic edits are disabled.</div>
</div>
<?php } ?>
<?php if ($hasLockedSubjects) { ?>
<div class="col-12">
<div class="alert alert-info">Some subjects are already submitted for review. Submitted, reviewed, and finalized mark sheets are read-only for academic edits.</div>
</div>
<?php } ?>

<?php
$tscore = 0;
foreach ($subjectRows as $row) {
$class_list = app_unserialize((string)($row['class'] ?? ''));

if (in_array((int)($studentData['class'] ?? 0), $class_list))
{

$score = 0;
$subjectId = (int)($row['id'] ?? 0);
$subjectLockedStatus = $lockedSubjectIds[$subjectId] ?? '';

if ($useExamId && $examId > 0) {
	$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? AND exam_id = ? LIMIT 1");
	$stmt->execute([(int)($studentData['class'] ?? 0), (int)($row['id'] ?? 0), $term, $std, $examId]);
} else {
	$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? LIMIT 1");
	$stmt->execute([(int)($studentData['class'] ?? 0), (int)($row['id'] ?? 0), $term, $std]);
}
$scoreValue = $stmt->fetchColumn();

if ($scoreValue !== false && $scoreValue !== null && $scoreValue !== '') {
$score = (float)$scoreValue;
$tscore = $tscore + $score;
}

?>

<div class="mb-3 col-md-2">
<label class="form-label"><?php echo htmlspecialchars((string)($row['subject_name'] ?? '')); ?></label>
<input value="<?php echo htmlspecialchars((string)$score); ?>" name="<?php echo (int)($row['id'] ?? 0);?>" class="form-control" required type="number" placeholder="Enter score" <?php echo ($subjectLockedStatus !== '' || $examReadOnly) ? 'readonly' : ''; ?>>
<?php if ($subjectLockedStatus !== '') { ?>
<div class="small text-muted mt-1">Locked: <?php echo htmlspecialchars(ucfirst($subjectLockedStatus)); ?></div>
<?php } ?>
</div>

<?php
}


}

?>
<input type="hidden" name="student" value="<?php echo $std; ?>">
<input type="hidden" name="term" value="<?php echo $term; ?>">
<input type="hidden" name="exam" value="<?php echo $examId; ?>">
<input type="hidden" name="class" value="<?php echo (int)($studentData['class'] ?? 0); ?>">
<div class="">
<button class="btn btn-primary app_btn" type="submit" <?php echo ($useExamId && $examId < 1) || $examReadOnly ? 'disabled' : ''; ?>>Save Results</button>
<?php if ($tscore > 0) {
?><a onclick="del('academic/core/drop_results.php?src=single_results&std=<?php echo $std; ?>&class=<?php echo (int)($studentData['class'] ?? 0); ?>&term=<?php echo $term; ?>&exam=<?php echo $examId; ?>', 'Delete Results?');" href="javascript:void(0);" class="btn btn-danger <?php echo $hasLockedSubjects ? 'disabled' : ''; ?>" <?php echo $hasLockedSubjects ? 'aria-disabled="true"' : ''; ?>>Delete</a><?php
}
?>
</div>
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
$('.select2').select2()
</script>
</body>

</html>
