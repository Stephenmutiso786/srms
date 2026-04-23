<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($res !== "1" || $level !== "2") { header("location:../"); }
if (!isset($_SESSION['result__data'])) { header("location:manage_results"); exit; }

$term = (int)($_SESSION['result__data']['term'] ?? 0);
$class = (int)($_SESSION['result__data']['class'] ?? 0);
$subjectCombination = (int)($_SESSION['result__data']['subject'] ?? 0);
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$termData = $classData = $subjectData = null;
$rows = [];
$examOptions = [];
$selectedExam = null;
$summary = ['count' => 0, 'average' => 0, 'highest' => 0, 'lowest' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (!report_teacher_has_class_access($conn, (int)$account_id, $class, $term)) {
		header("location:manage_results");
		exit;
	}

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms WHERE id = ? LIMIT 1");
	$stmt->execute([$term]);
	$termData = $stmt->fetch(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id = ? LIMIT 1");
	$stmt->execute([$class]);
	$classData = $stmt->fetch(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT sc.id, sc.subject, s.name AS subject_name
		FROM tbl_subject_combinations sc
		LEFT JOIN tbl_subjects s ON s.id = sc.subject
		WHERE sc.id = ?
		LIMIT 1");
	$stmt->execute([$subjectCombination]);
	$subjectData = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!report_term_is_published($conn, $class, $term)) {
		$error = 'Results for this class and term are not published yet.';
	}

	$hasExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
	if ($error === '' && $hasExamId) {
		$examOptions = report_term_exam_options($conn, $class, $term);
		if ($examId < 1 && !empty($examOptions)) {
			$examId = (int)$examOptions[0]['id'];
		}
		foreach ($examOptions as $option) {
			if ((int)$option['id'] === $examId) {
				$selectedExam = $option;
				break;
			}
		}
		if ($examId < 1) {
			$error = 'No published exam is available for this class and term.';
		}
	}

	if ($error === '') {
		$stmt = $conn->prepare("SELECT name, min, max, remark FROM tbl_grade_system ORDER BY min DESC");
		$stmt->execute();
		$grading = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$sql = "SELECT er.student, er.score, st.fname, st.mname, st.lname, st.school_id
			FROM tbl_exam_results er
			JOIN tbl_students st ON st.id = er.student
			WHERE er.class = ? AND er.subject_combination = ? AND er.term = ?";
		$args = [$class, $subjectCombination, $term];
		if ($hasExamId && $examId > 0) {
			$sql .= " AND er.exam_id = ?";
			$args[] = $examId;
		}
		$sql .= " ORDER BY st.fname, st.lname";
		$stmt = $conn->prepare($sql);
		$stmt->execute($args);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$gradeName = 'N/A';
			$remark = 'N/A';
			$score = (float)$row['score'];
			foreach ($grading as $grade) {
				if ($score >= (float)$grade['min'] && $score <= (float)$grade['max']) {
					$gradeName = (string)$grade['name'];
					$remark = (string)$grade['remark'];
					break;
				}
			}
			$rows[] = [
				'student_id' => (string)$row['student'],
				'school_id' => (string)($row['school_id'] ?? ''),
				'name' => trim(($row['fname'] ?? '') . ' ' . ($row['mname'] ?? '') . ' ' . ($row['lname'] ?? '')),
				'score' => $score,
				'grade' => $gradeName,
				'remark' => $remark,
			];
		}
	}

	if (!empty($rows)) {
		$scores = array_column($rows, 'score');
		$summary = [
			'count' => count($rows),
			'average' => round(array_sum($scores) / count($scores), 2),
			'highest' => max($scores),
			'lowest' => min($scores),
		];
	}
} catch (Throwable $e) {
	$rows = [];
	$error = 'Failed to load results for the selected class/subject.';
	error_log('[teacher/results] ' . $e->getMessage());
}

$title = trim(($subjectData['subject_name'] ?? 'Subject') . ' - ' . ($termData['name'] ?? 'Term') . ' - ' . ($classData['name'] ?? 'Class') . ' Results');
if ($selectedExam) {
	$title .= ' - ' . (string)$selectedExam['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - <?php echo htmlspecialchars($title); ?></title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<style>
@media print{
	.app-header,.app-sidebar,.app-title,.results-actions,.dataTables_filter,.dataTables_length,.dataTables_paginate,.dataTables_info{display:none!important}
	.app-content{margin-left:0;padding:0}
	.tile{box-shadow:none;border:0}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include("teacher/partials/sidebar.php"); ?>
<main class="app-content">
<div class="app-title"><div><h1>Class Results</h1><p class="mb-0 text-muted"><?php echo htmlspecialchars($title); ?></p></div></div>
<?php if ($error !== ''): ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php endif; ?>
<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="results-actions d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
<div class="d-flex gap-2 flex-wrap">
<span class="badge bg-primary">Students: <?php echo $summary['count']; ?></span>
<span class="badge bg-secondary">Average: <?php echo $summary['average']; ?>%</span>
<span class="badge bg-success">Highest: <?php echo $summary['highest']; ?>%</span>
<span class="badge bg-danger">Lowest: <?php echo $summary['lowest']; ?>%</span>
<?php if ($selectedExam): ?>
<span class="badge bg-dark">Exam: <?php echo htmlspecialchars((string)$selectedExam['name']); ?></span>
<?php endif; ?>
</div>
<div class="d-flex gap-2 flex-wrap">
<a class="btn btn-outline-secondary btn-sm" href="teacher/manage_results"><i class="bi bi-arrow-left me-2"></i>Back</a>
<a class="btn btn-outline-primary btn-sm" href="teacher/class_report?term=<?php echo $term; ?>&class=<?php echo $class; ?>"><i class="bi bi-table me-2"></i>Class Summary</a>
<button class="btn btn-primary btn-sm" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print Whole Class</button>
</div>
</div>
<?php if (!empty($examOptions)): ?>
<form method="get" class="row g-2 align-items-end mb-3">
<div class="col-md-4 col-lg-3">
<label class="form-label">Exam</label>
<select class="form-control" name="exam">
<option value="">Latest published exam</option>
<?php foreach ($examOptions as $exam): ?>
<option value="<?php echo (int)$exam['id']; ?>" <?php echo ((int)$exam['id'] === $examId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-auto">
<button class="btn btn-outline-primary">Load Exam</button>
</div>
</form>
<?php endif; ?>
<div class="table-responsive">
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>School ID</th>
<th>Student Name</th>
<th>Score</th>
<th>Grade</th>
<th>Remark</th>
<th class="results-actions">Actions</th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['school_id'] ?: $row['student_id']); ?></td>
<td><?php echo htmlspecialchars($row['name']); ?></td>
<td><?php echo $row['score']; ?>%</td>
<td><?php echo htmlspecialchars($row['grade']); ?></td>
<td><?php echo htmlspecialchars($row['remark']); ?></td>
<td class="results-actions">
<a class="btn btn-outline-primary btn-sm" href="teacher/report_card?term=<?php echo $term; ?>&student=<?php echo urlencode($row['student_id']); ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>"><i class="bi bi-file-earmark-text me-1"></i>Report</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script>$('#srmsTable').DataTable({"sort":false});</script>
</body>
</html>
