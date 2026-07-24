<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/calculations.php');
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$classesList = [];
$termsList = [];
$grading = [];
$std_data = [];
$term_data = [];
$class_data = [];
$examOptions = [];
$termPublished = false;
$class = (int)($_GET['class_id'] ?? ($_SESSION['bulk_result']['student'] ?? 0));
$term = (int)($_GET['term_id'] ?? ($_SESSION['bulk_result']['term'] ?? 0));
$examId = (int)($_GET['exam_id'] ?? ($_SESSION['bulk_result']['exam'] ?? ($_GET['exam'] ?? 0)));
$hasSelection = ($class > 0 && $term > 0);
$tit = 'Bulk Results';

if ($hasSelection) {
	$_SESSION['bulk_result'] = [
		'student' => $class,
		'term' => $term,
		'exam' => $examId,
	];
}

try {
	// Use CBE grading system instead of legacy tbl_grade_system
	$gradingSystemId = report_default_grading_system_id($conn, 'marks');
	$grading = report_grading_scales($conn, $gradingSystemId);
	if (empty($grading)) {
		$grading = [
			['name' => 'EE', 'min' => 90, 'max' => 100, 'points' => 4, 'remark' => 'Exceeding Expectation'],
			['name' => 'ME', 'min' => 75, 'max' => 89, 'points' => 3, 'remark' => 'Meeting Expectation'],
			['name' => 'AE', 'min' => 50, 'max' => 74, 'points' => 2, 'remark' => 'Approaching Expectation'],
			['name' => 'BE', 'min' => 0, 'max' => 49, 'points' => 1, 'remark' => 'Below Expectation'],
		];
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classesList = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE status = '1' ORDER BY id DESC");
	$stmt->execute();
	$termsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($hasSelection) {
		$stmt = $conn->prepare("SELECT id, fname, mname, lname, gender, class, display_image FROM tbl_students WHERE class = ?");
		$stmt->execute([$class]);
		$std_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id = ?");
		$stmt->execute([$term]);
		$term_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!empty($std_data)) {
			$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ?");
			$stmt->execute([(int)($std_data[0]['class'] ?? 0)]);
			$class_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		$termPublished = report_term_is_published($conn, (int)$class, (int)$term);
		$examOptions = report_term_exam_options($conn, (int)$class, (int)$term);

		if (!empty($class_data) && !empty($term_data)) {
			$tit = (string)($class_data[0]['name'] ?? 'Results') . ' (' . (string)($term_data[0]['name'] ?? 'Term') . ' Results)';
		}
	}
} catch (PDOException $e) {
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	echo "Connection failed.";
}

?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - <?php echo $tit; ?></title>
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
<h1><?php echo $tit; ?></h1>
</div>
<div class="no-print">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end app_frm">
	<div>
		<label class="form-label mb-1">Class</label>
		<select class="form-control select2" name="class_id" id="classSelect" required onchange="fetch_exams(this.value);">
			<option value="">Select class</option>
			<?php foreach (($classesList ?? []) as $classRow): ?>
			<option value="<?php echo (int)$classRow['id']; ?>" <?php echo ((int)$classRow['id'] === (int)$class) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$classRow['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div>
		<label class="form-label mb-1">Term</label>
		<select class="form-control select2" name="term_id" id="termSelect" required onchange="fetch_exams($('#classSelect').val() || '');">
			<option value="">Select term</option>
			<?php foreach (($termsList ?? []) as $termRow): ?>
			<option value="<?php echo (int)$termRow['id']; ?>" <?php echo ((int)$termRow['id'] === (int)$term) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)$termRow['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div>
		<label class="form-label mb-1">Exam</label>
		<select class="form-control select2" name="exam_id" id="examSelect" required>
			<option value="">Select class and term first</option>
			<?php foreach (($examOptions ?? []) as $exam): ?>
			<option value="<?php echo (int)$exam['id']; ?>" <?php echo ((int)$exam['id'] === (int)$examId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div><button class="btn btn-primary" type="submit">Load</button></div>
	<div><a class="btn btn-outline-primary<?php echo ($class > 0 && $term > 0 && $examId > 0) ? '' : ' disabled'; ?>" href="<?php echo ($class > 0 && $term > 0 && $examId > 0) ? 'admin/class_report_cards_pdf?class_id=' . (int)$class . '&term_id=' . (int)$term . '&exam=' . (int)$examId : 'javascript:void(0)'; ?>" target="_blank"><i class="bi bi-download me-2"></i>Class PDF</a></div>
</form>
<div class="mt-2">
<button class="btn btn-primary btn-sm" type="button" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print All</button>
</div>
</div>
</div>


<div class="row">
<div class="col-md-12 center_form">
<div class="tile">
<div class="tile-body">

<?php if (!$hasSelection): ?>
<div class="alert alert-info">Select a class, term, and exam above to load results for printing.</div>
<?php elseif (!$termPublished): ?>
<div class="alert alert-warning">Results are not published for this class and term yet.</div>
<?php elseif (empty($examOptions)): ?>
<div class="alert alert-warning">No published exam is available for this class and term.</div>
<?php elseif ($examId < 1): ?>
<div class="alert alert-info">Select an exam before printing or downloading the class PDF.</div>
<?php endif; ?>

<div class="table-responsive">

<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th></th>
<th>REGISTRATION NUMBER</th>
<th>STUDENT NAME</th>
<th>TOTAL SCORE<?php echo ($examWeightPercentage != 100.0) ? ' <small>(' . number_format($examWeightPercentage, 1) . '% weight)</small>' : ''; ?></th>
<th>AVERAGE</th>
<th>GRADE</th>
<th>REMARKS</th>
<th>DIVISION</th>
<th>POINTS</th>
<th></th>
</tr>
</thead>
<tbody>
<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
$selectedExamName = '';
$examWeightPercentage = 100.0;

if (!$termPublished) {
	$result = [];
	$result2 = [];
}

if ($useExamId && $examId > 0) {
	foreach ($examOptions as $examOption) {
		if ((int)$examOption['id'] === $examId) {
			$selectedExamName = (string)$examOption['name'];
			break;
		}
	}
	// Load exam weight percentage for display and report generation
	$examWeightPercentage = report_exam_weight_percentage($conn, $examId);
}

$tit = $tit . ($selectedExamName !== '' ? ' - ' . $selectedExamName : ($examId > 0 ? ' - Selected Exam' : ''));

if ($termPublished && (!$useExamId || $examId > 0)) {
	$stmt = $conn->prepare("SELECT sc.id, sc.class, sb.name AS subject_name FROM tbl_subject_combinations sc LEFT JOIN tbl_subjects sb ON sc.subject = sb.id");
	$stmt->execute();
	$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, fname, mname, lname, gender, class, display_image FROM tbl_students WHERE class = ?");
	$stmt->execute([$class]);
	$result2 = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
	$result = [];
	$result2 = [];
}

foreach($result2 as $row2)
{
$tscore = 0;
$t_subjects = 0;
$subssss = array();

foreach ($result as $key => $row) {
$class_list = app_unserialize((string)($row['class'] ?? ''));

if (in_array($class, $class_list))
{
$t_subjects++;
$score = 0;

if ($useExamId && $examId > 0) {
	$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? AND exam_id = ? LIMIT 1");
	$stmt->execute([$class, (int)($row['id'] ?? 0), $term, (int)($row2['id'] ?? 0), $examId]);
} else {
	$stmt = $conn->prepare("SELECT score FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ? LIMIT 1");
	$stmt->execute([$class, (int)($row['id'] ?? 0), $term, (int)($row2['id'] ?? 0)]);
}
$scoreValue = $stmt->fetchColumn();

if ($scoreValue !== false && $scoreValue !== null && $scoreValue !== '') {
$score = (float)$scoreValue;
$tscore = $tscore + $score;
}
array_push($subssss, $score);

}


}

if ($t_subjects == "0") {
$av = '0';
}else{
$av = round($tscore/$t_subjects);
}

// Determine grade using CBE grading system
$grd = 'BE';
$rm = 'Below Expectation';
foreach($grading as $grade) {
	$min = (float)($grade['min'] ?? 0);
	$max = (float)($grade['max'] ?? 100);
	if ($av >= $min && $av <= $max) {
		$grd = (string)($grade['name'] ?? 'BE');
		$rm = (string)($grade['remark'] ?? 'Below Expectation');
		break;
	}
}
?>

<tr>
<td width="10">
<?php
if (($row2['display_image'] ?? 'DEFAULT') == "DEFAULT") {



?><img src="images/students/<?php echo htmlspecialchars((string)($row2['gender'] ?? '')); ?>.png" class="avatar_img_sm"><?php
}else{
?><img src="images/students/<?php echo htmlspecialchars((string)($row2['display_image'] ?? '')); ?>" class="avatar_img_sm"><?php
}
?>
</td>
<td><?php echo htmlspecialchars((string)($row2['id'] ?? '')); ?></td>
<td><?php echo htmlspecialchars(trim((string)($row2['fname'] ?? '').' '.(string)($row2['mname'] ?? '').' '.(string)($row2['lname'] ?? ''))); ?></td>
<td><?php echo $tscore; ?></td>
<td><?php echo $av; ?></td>
<td><?php echo $grd; ?></td>
<td><?php echo $rm; ?></td>
<td><?php echo get_division($subssss); ?></td>
<td><?php echo get_points($subssss); ?></td>

<td align="center" width="120">
<a href="admin/core/edit_result?std=<?php echo (int)($row2['id'] ?? 0); ?>&term=<?php echo $term; ?>&exam=<?php echo $examId; ?>" class="btn btn-primary btn-sm" href="javascript:void(0);">Edit</a>
<a href="<?php echo ($examId > 0) ? 'admin/save_pdf?std=' . urlencode((string)($row2['id'] ?? 0)) . '&term=' . (int)$term . '&exam=' . (int)$examId . '&download=1' : 'javascript:void(0);'; ?>" class="btn btn-primary btn-sm<?php echo $examId > 0 ? '' : ' disabled'; ?>">Report</a>
</td>

</tr>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

?>

</tbody>
</table>
</div>


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
