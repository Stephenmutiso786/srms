<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');

if ($res !== "1" || $level !== "3") { header("location:../"); exit; }

$studentId = (string)$account_id;
$classId = (int)$class;
$termId = (int)($_GET['term'] ?? 0);
$examId = (int)($_GET['exam'] ?? 0);
$terms = [];
$examOptions = [];
$subjectRows = [];
$history = [];
$card = null;
$summary = ['mean' => 0, 'grade' => 'N/A', 'position' => '-', 'total' => 0];
$publicationState = 'draft';
$isPublished = false;
$selectedExam = null;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$visibleStatuses = report_visible_exam_statuses();
	$placeholders = implode(',', array_fill(0, count($visibleStatuses), '?'));
	$stmt = $conn->prepare("SELECT t.id, t.name
		FROM tbl_terms t
		WHERE EXISTS (
			SELECT 1 FROM tbl_exams e
			WHERE e.term_id = t.id AND e.class_id = ? AND COALESCE(e.status, 'draft') IN ($placeholders)
		)
		ORDER BY t.id DESC");
	$stmt->execute(array_merge([$classId], $visibleStatuses));
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
	if (empty($terms)) {
		$stmt = $conn->prepare("SELECT t.id, t.name
			FROM tbl_terms t
			WHERE EXISTS (
				SELECT 1 FROM tbl_exams e
				WHERE e.term_id = t.id AND e.class_id = ?
			)
			ORDER BY t.id DESC");
		$stmt->execute([$classId]);
		$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if ($termId < 1 && !empty($terms)) {
		$termId = (int)$terms[0]['id'];
	}

	if ($termId > 0) {
		$publicationState = report_term_publish_state($conn, $classId, $termId);
		$isPublished = report_term_is_published($conn, $classId, $termId);
		$examOptions = report_term_exam_options($conn, $classId, $termId);
		if ($examId < 1 && !empty($examOptions)) {
			$examId = (int)$examOptions[0]['id'];
		}
		foreach ($examOptions as $option) {
			if ((int)$option['id'] === $examId) {
				$selectedExam = $option;
				break;
			}
		}
		if ($selectedExam) {
			$examSummary = report_exam_summary($conn, $studentId, $classId, $termId, (int)$selectedExam['id']);
			if ($examSummary) {
				$summary = [
					'mean' => (float)($examSummary['mean'] ?? 0),
					'grade' => (string)($examSummary['grade'] ?? 'N/A'),
					'position' => (string)($examSummary['position'] ?? '-'),
					'total' => (float)($examSummary['total'] ?? 0),
				];
				$subjectRows = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, (int)$selectedExam['id']);
			}
		}

		if ($isPublished) {
			$card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
			if (!$selectedExam && $card) {
				$summary = [
					'mean' => (float)($card['mean'] ?? 0),
					'grade' => (string)($card['grade'] ?? 'N/A'),
					'position' => isset($card['position'], $card['total_students']) ? ($card['position'].'/'.$card['total_students']) : '-',
					'total' => (float)($card['total'] ?? 0),
				];
				$subjectRows = report_subject_breakdown($conn, $studentId, $classId, $termId);
			}
			$history = report_student_term_history($conn, $studentId, $classId);
		}
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - My Results</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.analytics-shell{display:grid;gap:22px}
.analytics-hero{background:linear-gradient(135deg,#0e1525,#0d64b0 65%,#33b249);padding:26px;border-radius:24px;color:#fff;box-shadow:0 24px 60px rgba(14,21,37,.18)}
.analytics-grid{display:grid;grid-template-columns:1.15fr 1fr;gap:22px}
.panel-card{background:#fff;border-radius:24px;box-shadow:0 16px 42px rgba(15,95,168,.09);overflow:hidden}
.panel-body{padding:22px}
.panel-header{padding:16px 22px;background:#f7fbff;border-bottom:1px solid #e6edf5}
.metric-row{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.metric-tile{background:#fff;border-radius:18px;padding:16px;border:1px solid rgba(255,255,255,.18);backdrop-filter:blur(8px)}
.metric-tile .label{font-size:.75rem;text-transform:uppercase;letter-spacing:.05em;color:rgba(255,255,255,.78)}
.metric-tile .value{font-size:1.55rem;font-weight:800}
.history-chart{display:flex;align-items:flex-end;gap:12px;height:190px;padding-top:16px}
.history-bar{flex:1;min-height:16px;border-radius:14px 14px 6px 6px;background:linear-gradient(180deg,#97d3ff,#2d9cdb);position:relative}
.history-bar .bar-value{position:absolute;top:-24px;left:50%;transform:translateX(-50%);font-size:.72rem;font-weight:700;color:#123}
.history-bar .bar-label{position:absolute;bottom:-28px;left:50%;transform:translateX(-50%);font-size:.72rem;color:#6b7280;white-space:nowrap}
.subject-table{width:100%;border-collapse:collapse}
.subject-table th,.subject-table td{padding:14px 12px;border-bottom:1px solid #ecf0f4}
.subject-table th{font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7280}
.performance-bar{height:10px;min-width:120px;background:#e9eef4;border-radius:999px;overflow:hidden}
.performance-bar span{display:block;height:100%;background:linear-gradient(90deg,#77d84a,#35b14a)}
.trend-up{color:#18a957;font-weight:700}.trend-down{color:#db9d1a;font-weight:700}.trend-steady{color:#6b7280;font-weight:700}
.results-summary{display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap}
@media (max-width: 991px){.analytics-grid{grid-template-columns:1fr}.metric-row{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="student/settings"><i class="bi bi-person me-2 fs-5"></i> Change Password</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul>
</header>
<?php include("student/partials/sidebar.php"); ?>
<main class="app-content">
<div class="app-title"><div><h1>My Results</h1><p>Published academic performance and subject analytics.</p></div></div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="analytics-shell">
	<div class="analytics-hero">
		<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
			<div>
				<div class="small text-uppercase opacity-75">Published Results Center</div>
				<h2 class="mb-2"><?php echo $fname.' '.$lname; ?></h2>
				<p class="mb-0">Choose a term and exam to view that specific performance snapshot. Published term reports remain available in the report card section.</p>
			</div>
			<form method="GET" action="student/results" class="d-flex gap-2 align-items-end flex-wrap">
				<div>
					<label class="form-label text-white-50">Term</label>
					<select class="form-control" name="term">
						<option value="">Select term</option>
						<?php foreach ($terms as $term): ?>
						<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($term['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div>
					<label class="form-label text-white-50">Exam</label>
					<select class="form-control" name="exam">
						<option value="">Latest visible exam</option>
						<?php foreach ($examOptions as $exam): ?>
						<option value="<?php echo (int)$exam['id']; ?>" <?php echo ((int)$exam['id'] === $examId) ? 'selected' : ''; ?>>
							<?php echo htmlspecialchars($exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?>
						</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div><button class="btn btn-light">Load</button></div>
				<div><a class="btn btn-outline-light" href="student/report_card<?php echo $termId > 0 ? '?term='.$termId : ''; ?>">Open Report Card</a></div>
			</form>
		</div>
		<div class="metric-row mt-4">
			<div class="metric-tile"><div class="label">Mean Score</div><div class="value"><?php echo number_format((float)$summary['mean'], 2); ?>%</div></div>
			<div class="metric-tile"><div class="label">Overall Grade</div><div class="value"><?php echo htmlspecialchars($summary['grade']); ?></div></div>
			<div class="metric-tile"><div class="label">Class Position</div><div class="value"><?php echo htmlspecialchars((string)$summary['position']); ?></div></div>
			<div class="metric-tile"><div class="label">Total Marks</div><div class="value"><?php echo number_format((float)$summary['total'], 1); ?></div></div>
		</div>
	</div>

	<?php if ($termId < 1 || (empty($subjectRows) && !$isPublished)) { ?>
	<div class="tile"><div class="alert alert-info mb-0">No exam results are available for the selected term yet. Once teachers publish or release exams for this term, they will appear here.</div></div>
	<?php } else { ?>
	<div class="analytics-grid">
		<div class="panel-card">
			<div class="panel-header"><h3 class="mb-0">Performance Trend</h3></div>
			<div class="panel-body">
				<?php if (!$history) { ?>
				<div class="text-muted">No trend history yet.</div>
				<?php } else { ?>
				<div class="history-chart">
					<?php foreach ($history as $point): $height = max(20, min(150, (float)$point['mean'] * 1.5)); ?>
					<div class="history-bar" style="height: <?php echo $height; ?>px">
						<div class="bar-value"><?php echo number_format((float)$point['mean'], 1); ?></div>
						<div class="bar-label"><?php echo htmlspecialchars($point['term_name']); ?></div>
					</div>
					<?php endforeach; ?>
				</div>
				<?php } ?>
			</div>
		</div>
		<div class="panel-card">
			<div class="panel-header"><h3 class="mb-0">Release Summary</h3></div>
			<div class="panel-body">
				<div class="results-summary">
					<div><div class="text-muted small">Release Stage</div><div class="fw-bold fs-5"><?php echo htmlspecialchars(ucfirst($publicationState)); ?></div></div>
					<div><div class="text-muted small">Subjects</div><div class="fw-bold fs-5"><?php echo count($subjectRows); ?></div></div>
					<div><div class="text-muted small">Exam</div><div class="fw-bold fs-5"><?php echo htmlspecialchars($selectedExam['name'] ?? 'Latest'); ?></div></div>
					<div><div class="text-muted small">Term</div><div class="fw-bold fs-5"><?php foreach ($terms as $term){ if((int)$term['id']===$termId){ echo htmlspecialchars($term['name']); break; } } ?></div></div>
				</div>
				<hr>
				<p class="mb-0 text-muted">Choose any exam completed in this term to view that exact paper or assessment. The report card remains the official overall term result.</p>
			</div>
		</div>
	</div>

	<div class="panel-card">
		<div class="panel-header d-flex justify-content-between align-items-center flex-wrap gap-2">
			<h3 class="mb-0">Exam Subject Performance</h3>
			<a class="btn btn-sm btn-outline-primary" href="student/report_card?term=<?php echo $termId; ?>">Open Official Report Card</a>
		</div>
		<div class="panel-body">
			<div class="table-responsive">
				<table class="subject-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Performance</th>
							<th>Score</th>
							<th>Class Mean</th>
							<th>Grade</th>
							<th>Teacher</th>
							<th>Source</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($subjectRows as $row): ?>
						<tr>
							<td><?php echo htmlspecialchars($row['subject_name']); ?></td>
							<td><div class="performance-bar"><span style="width: <?php echo (float)$row['progress']; ?>%"></span></div></td>
							<td><?php echo number_format((float)$row['score'], 2); ?>%</td>
							<td><?php echo number_format((float)$row['class_mean'], 2); ?>%</td>
							<td><?php echo htmlspecialchars($row['grade']); ?></td>
							<td><?php echo htmlspecialchars($row['teacher_name'] ?? ''); ?></td>
							<td><?php echo htmlspecialchars($row['source'] ?? 'Exam result'); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
	<?php } ?>
</div>
<?php } ?>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
