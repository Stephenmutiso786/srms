<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
if ($res !== "1" || $level !== "2") { header("location:../"); exit; }

$termId = (int)($_GET['term'] ?? 0);
$classId = (int)($_GET['class'] ?? 0);
$subjectCombinationId = (int)($_GET['subject'] ?? 0);
$classes = [];
$terms = [];
$subjectRows = [];
$summary = ['count' => 0, 'average' => 0, 'highest' => 0, 'lowest' => 0];
$gradeDistribution = [];
$students = [];
$published = false;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT DISTINCT c.id, c.name
			FROM tbl_teacher_assignments ta
			JOIN tbl_classes c ON c.id = ta.class_id
			WHERE ta.teacher_id = ? AND ta.status = 1
			ORDER BY c.name");
		$stmt->execute([$account_id]);
		$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
		$stmt->execute();
		$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($classId > 0 && $termId > 0) {
		if (!report_teacher_has_class_access($conn, (int)$account_id, $classId, $termId)) {
			throw new RuntimeException('You are not assigned to that class for the selected term.');
		}

		$published = report_term_is_published($conn, $classId, $termId);

		if ($subjectCombinationId > 0) {
			$stmt = $conn->prepare("SELECT er.student, er.score, COALESCE(er.grade_label, '') AS grade_label,
				st.school_id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name
				FROM tbl_exam_results er
				JOIN tbl_students st ON st.id = er.student
				WHERE er.class = ? AND er.term = ? AND er.subject_combination = ?
				ORDER BY er.score DESC, student_name");
			$stmt->execute([$classId, $termId, $subjectCombinationId]);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$score = (float)$row['score'];
				$grade = trim((string)$row['grade_label']);
				if ($grade === '') {
					list($grade) = report_grade_for_score($conn, $score);
				}
				$students[] = [
					'student_id' => (string)$row['student'],
					'school_id' => (string)($row['school_id'] ?? ''),
					'name' => (string)$row['student_name'],
					'score' => $score,
					'grade' => $grade,
				];
				$gradeDistribution[$grade] = ($gradeDistribution[$grade] ?? 0) + 1;
			}
			if ($students) {
				$scores = array_column($students, 'score');
				$summary = [
					'count' => count($students),
					'average' => round(array_sum($scores) / count($scores), 2),
					'highest' => max($scores),
					'lowest' => min($scores),
				];
			}
		}
	}

	if (isset($_GET['export']) && $_GET['export'] === 'csv' && $published && $classId > 0 && $termId > 0 && $subjectCombinationId > 0) {
		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="published-analytics.csv"');
		$out = fopen('php://output', 'w');
		fputcsv($out, ['School ID', 'Student', 'Score', 'Grade']);
		foreach ($students as $student) {
			fputcsv($out, [$student['school_id'], $student['name'], $student['score'], $student['grade']]);
		}
		fclose($out);
		exit;
	}
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Published Analytics</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<style>
.analytics-card{border-radius:18px;border:1px solid rgba(15,118,110,.12);box-shadow:0 12px 30px rgba(15,118,110,.08)}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user"><div><p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p><p class="app-sidebar__user-designation">Teacher</p></div></div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li class="treeview is-expanded"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/exam_marks_entry"><i class="icon bi bi-circle-fill"></i> Exam Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
<li><a class="treeview-item active" href="teacher/published_analytics"><i class="icon bi bi-circle-fill"></i> Published Analytics</a></li>
</ul></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title"><div><h1>Published Results Analytics</h1><p>Teachers can analyze only published results for their assigned class and subject.</p></div></div>

<?php if ($error !== '') { ?><div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div><?php } ?>

<div class="tile mb-3">
<form class="row g-3" method="GET" action="teacher/published_analytics">
	<div class="col-md-4">
		<label class="form-label">Term</label>
		<select class="form-control select2" name="term" id="termSelect" required>
			<option value="">Select term</option>
			<?php foreach ($terms as $term): ?>
			<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($term['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-md-4">
		<label class="form-label">Class</label>
		<select class="form-control select2" name="class" id="classSelect" required onchange="fetch_subjects(this.value);">
			<option value="">Select class</option>
			<?php foreach ($classes as $class): ?>
			<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$class['id'] === $classId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['name']); ?></option>
			<?php endforeach; ?>
		</select>
	</div>
	<div class="col-md-4">
		<label class="form-label">Subject</label>
		<select class="form-control" name="subject" id="sub_imp" required>
			<option value="">Select subject</option>
		</select>
	</div>
	<div class="col-md-12 d-flex gap-2">
		<button class="btn btn-primary" type="submit">Load Analytics</button>
		<?php if ($published && $subjectCombinationId > 0): ?>
		<a class="btn btn-outline-success" href="teacher/published_analytics?term=<?php echo $termId; ?>&class=<?php echo $classId; ?>&subject=<?php echo $subjectCombinationId; ?>&export=csv">Export CSV</a>
		<button class="btn btn-outline-secondary" type="button" onclick="window.print();">Print</button>
		<?php endif; ?>
	</div>
</form>
</div>

<?php if ($classId > 0 && $termId > 0): ?>
<?php if (!$published): ?>
<div class="alert alert-warning">Results for this class and term are not published yet, so analytics is still locked for teachers.</div>
<?php elseif ($subjectCombinationId > 0): ?>
<div class="row">
	<div class="col-md-6 col-lg-3"><div class="widget-small primary coloured-icon analytics-card"><i class="icon feather icon-users fs-1"></i><div class="info"><h4>Learners</h4><p><b><?php echo number_format($summary['count']); ?></b></p></div></div></div>
	<div class="col-md-6 col-lg-3"><div class="widget-small primary coloured-icon analytics-card"><i class="icon feather icon-activity fs-1"></i><div class="info"><h4>Mean</h4><p><b><?php echo number_format($summary['average'], 2); ?></b></p></div></div></div>
	<div class="col-md-6 col-lg-3"><div class="widget-small primary coloured-icon analytics-card"><i class="icon feather icon-trending-up fs-1"></i><div class="info"><h4>Highest</h4><p><b><?php echo number_format($summary['highest'], 2); ?></b></p></div></div></div>
	<div class="col-md-6 col-lg-3"><div class="widget-small primary coloured-icon analytics-card"><i class="icon feather icon-trending-down fs-1"></i><div class="info"><h4>Lowest</h4><p><b><?php echo number_format($summary['lowest'], 2); ?></b></p></div></div></div>
</div>

<div class="row mt-3">
	<div class="col-lg-5 mb-3"><div class="tile analytics-card"><h3 class="tile-title">Grade Distribution</h3><div id="gradeDistributionChart" style="height:320px;"></div></div></div>
	<div class="col-lg-7 mb-3"><div class="tile analytics-card"><h3 class="tile-title">Student Performance</h3><div id="studentPerformanceChart" style="height:320px;"></div></div></div>
</div>

<div class="tile analytics-card">
	<h3 class="tile-title">Published Subject Results</h3>
	<div class="table-responsive">
		<table class="table table-hover">
			<thead><tr><th>School ID</th><th>Student</th><th>Score</th><th>Grade</th></tr></thead>
			<tbody>
				<?php foreach ($students as $student): ?>
				<tr>
					<td><?php echo htmlspecialchars($student['school_id'] ?: $student['student_id']); ?></td>
					<td><?php echo htmlspecialchars($student['name']); ?></td>
					<td><?php echo number_format((float)$student['score'], 2); ?></td>
					<td><?php echo htmlspecialchars($student['grade']); ?></td>
				</tr>
				<?php endforeach; ?>
				<?php if (!$students): ?>
				<tr><td colspan="4" class="text-center text-muted">No published marks available for the selected subject yet.</td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
<?php endif; ?>
<?php endif; ?>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="select2/dist/js/select2.full.min.js"></script>
<script>$('.select2').select2()</script>
<script>
function fetch_subjects(id){
  $.post('app/ajax/fetch_subjects', {id:id, term_id: $('#termSelect').val()}, function(data){
    $('#sub_imp').html(data);
    <?php if ($subjectCombinationId > 0): ?>
    $('#sub_imp').val('<?php echo $subjectCombinationId; ?>');
    <?php endif; ?>
  });
}
<?php if ($classId > 0): ?>fetch_subjects(<?php echo $classId; ?>);<?php endif; ?>
<?php if ($published && $subjectCombinationId > 0): ?>
var gradeChart = echarts.init(document.getElementById('gradeDistributionChart'));
gradeChart.setOption({
	tooltip:{trigger:'item'},
	series:[{type:'pie', radius:['45%','72%'], data:[
		<?php foreach ($gradeDistribution as $grade => $count): ?>
		{name:'<?php echo addslashes($grade); ?>', value:<?php echo (int)$count; ?>},
		<?php endforeach; ?>
	]}]
});
var perfChart = echarts.init(document.getElementById('studentPerformanceChart'));
perfChart.setOption({
	tooltip:{trigger:'axis'},
	xAxis:{type:'category', data:[<?php foreach ($students as $student): ?>'<?php echo addslashes($student['name']); ?>',<?php endforeach; ?>], axisLabel:{interval:0, rotate:25}},
	yAxis:{type:'value', min:0, max:100},
	series:[{type:'bar', data:[<?php foreach ($students as $student): ?><?php echo (float)$student['score']; ?>,<?php endforeach; ?>], itemStyle:{color:'#0f766e'}}]
});
<?php endif; ?>
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
