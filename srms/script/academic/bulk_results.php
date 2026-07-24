<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/calculations.php');

if ($res !== '1' || $level !== '1') {
	header('location:../');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$std_data = [];
$term_data = [];
$class_data = [];
$examOptions = [];
$termPublished = false;
$class = (int)($_SESSION['bulk_result']['student'] ?? ($_GET['class'] ?? 0));
$term = (int)($_SESSION['bulk_result']['term'] ?? ($_GET['term'] ?? 0));
$examId = (int)($_SESSION['bulk_result']['exam'] ?? ($_GET['exam'] ?? 0));
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
	// CBE-ONLY: Remove legacy tbl_grade_system. Use only CBE/new grading tables.
	$grading = app_default_marks_grading_rows($conn);

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

		$termPublished = report_term_is_published($conn, $class, $term);
		$examOptions = report_term_exam_options($conn, $class, $term);

		if (!empty($class_data) && !empty($term_data)) {
			$tit = (string)($class_data[0]['name'] ?? 'Results') . ' (' . (string)($term_data[0]['name'] ?? 'Term') . ' Results)';
		}
	}
} catch (PDOException $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . " PDO] " . $e->getMessage());
	echo 'Connection failed.';
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
<style>
@media print {
	.app-header,
	.app-sidebar,
	.app-sidebar__overlay,
	.no-print,
	.dataTables_filter,
	.dataTables_length,
	.dataTables_paginate,
	.dataTables_info {
		display: none !important;
	}

	.app-content {
		margin: 0 !important;
		padding: 0 !important;
	}

	.tile {
		box-shadow: none !important;
		border: 0 !important;
	}

	table {
		width: 100% !important;
	}
}
</style>
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
<h1><?php echo $tit; ?></h1>
</div>
<div class="no-print">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end d-inline-flex">
	<div>
		<label class="form-label mb-1">Exam</label>
		<select class="form-control" name="exam">
			<option value="">Select an exam</option>
			<?php foreach (($examOptions ?? []) as $exam): ?>
			<option value="<?php echo (int)$exam['id']; ?>" <?php echo ((int)$exam['id'] === $examId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div><button class="btn btn-primary" type="submit">Apply</button></div>
</form>
<button class="btn btn-primary" type="button" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print All</button>
<a class="btn btn-danger ms-2" href="javascript:void(0);" onclick="del('academic/core/drop_results.php?src=bulk_results&amp;std=all&amp;class=<?php echo $class; ?>&amp;term=<?php echo $term; ?>&amp;exam=<?php echo $examId; ?>', 'Delete all results for this class, term, and exam?');"><i class="bi bi-trash me-2"></i>Delete All</a>
</div>
</div>


<div class="row">
<div class="col-md-12 center_form">
<div class="tile">
<div class="tile-body">

<?php if (!$termPublished): ?>
<div class="alert alert-warning">Results are not published for this class and term yet.</div>
<?php elseif (empty($examOptions)): ?>
<div class="alert alert-warning">No published exam is available for this class and term.</div>
<?php elseif ($examId < 1): ?>
<div class="alert alert-info">Select an exam to print or download the report PDF.</div>
<?php endif; ?>

<div class="table-responsive">

<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th></th>
<th>REGISTRATION NUMBER</th>
<th>STUDENT NAME</th>
<th>TOTAL SCORE</th>
<th>AVERAGE</th>
<th>GRADE</th>
<th>REMARKS</th>
<!-- CBE-ONLY: Division column removed -->
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
$studentRowsById = [];
$studentScores = [];

if (!$termPublished) {
	$result2 = [];
}

if ($useExamId && $examId > 0) {
	foreach ($examOptions as $examOption) {
		if ((int)$examOption['id'] === $examId) {
			$selectedExamName = (string)$examOption['name'];
			break;
		}
	}
}

$tit = $tit . ($selectedExamName !== '' ? ' - ' . $selectedExamName : ($examId > 0 ? ' - Selected Exam' : ''));

if ($termPublished && (!$useExamId || $examId > 0)) {
	$stmt = $conn->prepare("SELECT id, fname, mname, lname, gender, class, display_image FROM tbl_students WHERE class = ?");
	$stmt->execute([$class]);
	$result2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($result2 as $studentRow) {
		$studentId = trim((string)($studentRow['id'] ?? ''));
		if ($studentId !== '') {
			$studentRowsById[$studentId] = $studentRow;
		}
	}

	$subjectMeta = report_fetch_subjects_for_class($conn, $class, $term, $examId);
	$validCombinationIds = [];
	foreach ($subjectMeta as $subjectRow) {
		$combinationId = (int)($subjectRow['combination_id'] ?? 0);
		if ($combinationId > 0) {
			$validCombinationIds[$combinationId] = true;
		}
	}

	$sql = "SELECT id, student, subject_combination, score
		FROM tbl_exam_results
		WHERE class = ? AND term = ?";
	$params = [$class, $term];
	if ($useExamId && $examId > 0) {
		$sql .= " AND exam_id = ?";
		$params[] = $examId;
	}
	$sql .= " ORDER BY id DESC";
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);

	$seenStudentSubjects = [];
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $resultRow) {
		$studentId = trim((string)($resultRow['student'] ?? ''));
		$combinationId = (int)($resultRow['subject_combination'] ?? 0);
		if ($studentId === '' || !isset($studentRowsById[$studentId]) || $combinationId < 1) {
			continue;
		}
		if (!empty($validCombinationIds) && !isset($validCombinationIds[$combinationId])) {
			continue;
		}

		$dedupeKey = $studentId . ':' . $combinationId;
		if (isset($seenStudentSubjects[$dedupeKey])) {
			continue;
		}
		$seenStudentSubjects[$dedupeKey] = true;
		$studentScores[$studentId][] = (float)($resultRow['score'] ?? 0);
	}
} else {
	$result2 = [];
}

foreach($result2 as $row2)
{
$studentId = trim((string)($row2['id'] ?? ''));
$subssss = $studentScores[$studentId] ?? [];
$tscore = !empty($subssss) ? array_sum($subssss) : 0;
$t_subjects = count($subssss);
$grd = 'N/A';
$rm = 'No marks entered';

if ($t_subjects == "0") {
$av = '0';
}else{
$av = round($tscore/$t_subjects);
}

list($grd, $rm) = report_grade_for_score($conn, (float)$av, report_default_grading_system_id($conn, 'marks'));
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
<!-- CBE-ONLY: Division cell removed -->
<td><?php echo get_points($subssss); ?></td>

<td align="center" width="190" class="no-print">
<a href="academic/core/edit_result.php?std=<?php echo (int)($row2['id'] ?? 0); ?>&term=<?php echo $term; ?>&exam=<?php echo $examId; ?>" class="btn btn-primary btn-sm" href="javascript:void(0);">Edit</a>
<a href="<?php echo ($examId > 0) ? 'academic/save_pdf.php?std=' . urlencode((string)($row2['id'] ?? 0)) . '&term=' . (int)$term . '&exam=' . (int)$examId . '&download=1' : 'javascript:void(0);'; ?>" class="btn btn-primary btn-sm<?php echo $examId > 0 ? '' : ' disabled'; ?>">Report</a>
<a onclick="del('academic/core/drop_results.php?src=bulk_results&std=<?php echo (int)($row2['id'] ?? 0); ?>&class=<?php echo $class; ?>&term=<?php echo $term; ?>&exam=<?php echo $examId; ?>', 'Delete Results?');" href="javascript:void(0);" class="btn btn-danger btn-sm">Delete</a>
</td>

</tr>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo '<tr><td colspan="9" class="text-center text-danger">Failed to load results right now.</td></tr>';
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
