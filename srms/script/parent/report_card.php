<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/id_card_engine.php');

if ($res !== "1" || $level !== "4") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$studentId = isset($_GET['student']) ? (string)$_GET['student'] : '';
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$card = null;
$attendance = ['days_open' => 0, 'present' => 0, 'absent' => 0];
$feesBalance = 0;
$blockReport = false;
$termName = '';
$className = '';
$studentName = '';
$schoolId = '';
$photoPath = '';
$photoExists = false;
$subjectBreakdown = [];
$examBreakdown = [];
$examSummary = null;
$examOptions = [];
$selectedExam = null;
$history = [];
$publicationState = 'draft';
$kcpeScore = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT ps.student_id, s.fname, s.lname FROM tbl_parent_students ps JOIN tbl_students s ON s.id = ps.student_id WHERE ps.parent_id = ? ORDER BY s.fname");
	$stmt->execute([$account_id]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($studentId === '' && !empty($students)) {
		$studentId = (string)$students[0]['student_id'];
	}

	if ($termId < 1 && !empty($terms)) {
		$termId = (int)$terms[count($terms)-1]['id'];
	}

	if ($termId > 0 && $studentId !== '') {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();

		$stmt = $conn->prepare("SELECT class, fname, lname, school_id FROM tbl_students WHERE id = ? LIMIT 1");
		$stmt->execute([$studentId]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row) {
			$classId = (int)$row['class'];
			$studentName = trim(($row['fname'] ?? '') . ' ' . ($row['lname'] ?? ''));
			$schoolId = (string)($row['school_id'] ?? '');
			$payload = idcard_student_payload($conn, $studentId);
			if ($payload) {
				$photoPath = (string)$payload['photo_path'];
				$photoExists = (bool)$payload['photo_exists'];
			}
			$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
			$stmt->execute([$classId]);
			$className = (string)$stmt->fetchColumn();
			if (app_column_exists($conn, 'tbl_students', 'kcpe')) {
				$stmt = $conn->prepare("SELECT kcpe FROM tbl_students WHERE id = ? LIMIT 1");
				$stmt->execute([$studentId]);
				$kcpeScore = (string)$stmt->fetchColumn();
			}
			$publicationState = report_term_publish_state($conn, $classId, $termId);
			$examOptions = report_term_exam_options($conn, $classId, $termId);
			if ($examId < 1 && !empty($examOptions)) {
				$examId = (int)$examOptions[0]['id'];
			}
			$examAllowed = false;
			foreach ($examOptions as $option) {
				if ((int)$option['id'] === $examId) {
					$examAllowed = true;
					$selectedExam = $option;
					break;
				}
			}

			if (!report_term_is_published($conn, $classId, $termId)) {
				$card = null;
			} else {
				if ($examId > 0 && $examAllowed) {
					$examSummary = report_exam_summary($conn, $studentId, $classId, $termId, $examId);
					$examBreakdown = report_exam_subject_breakdown($conn, $studentId, $classId, $termId, $examId);
				}
				$card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
				if ($card) {
					$attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
					$feesBalance = report_fees_balance($conn, $studentId, $termId);
					$settings = report_get_settings($conn);
					$blockReport = ((int)$settings['require_fees_clear'] === 1 && $feesBalance > 0);
					$subjectBreakdown = report_subject_breakdown($conn, $studentId, $classId, $termId);
					$history = report_student_term_history($conn, $studentId, $classId);
				}
			}
		}
	}
} catch (Throwable $e) {
	$card = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Report Card</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
:root {
	--report-blue: #00aeef;
	--report-gray: #f4f4f4;
	--report-border: #d7d7d7;
	--report-text: #1b2733;
}
.report-container {
	max-width: 1080px;
	margin: 0 auto;
	background: #fff;
	border-left: 15px solid var(--report-blue);
	padding: 18px 18px 22px;
	box-shadow: 0 14px 36px rgba(20, 40, 60, 0.08);
}
.report-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 16px;
	border-bottom: 1px solid #e9eef2;
	padding-bottom: 10px;
}
.logo-wrap {
	width: 88px;
	height: 88px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1px solid var(--report-border);
	background: #fff;
}
.logo {
	max-width: 82px;
	max-height: 82px;
	object-fit: contain;
}
.school-info {
	text-align: right;
	color: var(--report-text);
}
.school-info h1 {
	margin: 0;
	font-size: 1.5rem;
	font-weight: 800;
}
.school-info p {
	margin: 4px 0 0;
	font-size: 0.92rem;
	color: #4c5b68;
}
.report-title {
	background: var(--report-blue);
	color: #fff;
	text-align: center;
	padding: 10px;
	font-weight: 700;
	margin: 16px 0;
	letter-spacing: 0.01em;
}
.student-profile {
	display: grid;
	grid-template-columns: 145px 1fr 300px;
	gap: 16px;
	border-bottom: 2px solid var(--report-border);
	padding-bottom: 16px;
}
.photo-box {
	width: 132px;
	height: 152px;
	border: 1px solid #c7d0d9;
	overflow: hidden;
	background: #f9fbfd;
	display: flex;
	align-items: center;
	justify-content: center;
}
.photo-box img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}
.photo-fallback {
	font-size: 1.9rem;
	font-weight: 700;
	color: #1f4d75;
}
.details p {
	margin: 6px 0;
	font-size: 0.94rem;
	color: #2c3a46;
}
.performance-chart {
	border: 1px solid var(--report-border);
	padding: 10px;
	background: #fcfeff;
}
.performance-chart p {
	margin: 0 0 8px;
	font-size: 0.82rem;
	font-weight: 700;
	color: #4c5b68;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}
.chart-placeholder {
	display: grid;
	gap: 6px;
}
.chart-row {
	display: grid;
	grid-template-columns: 58px 1fr;
	gap: 8px;
	align-items: center;
}
.chart-row span {
	font-size: 0.76rem;
	color: #4f5d68;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}
.chart-bars {
	height: 12px;
	background: #e5ecf2;
	position: relative;
	overflow: hidden;
}
.chart-bars .student-bar {
	position: absolute;
	height: 12px;
	left: 0;
	top: 0;
	background: #1a8fd4;
	opacity: 0.9;
}
.chart-bars .class-bar {
	position: absolute;
	height: 6px;
	left: 0;
	bottom: 0;
	background: #38b56a;
	opacity: 0.75;
}
.stats-row {
	display: grid;
	grid-template-columns: repeat(5, minmax(0, 1fr));
	gap: 8px;
	margin: 16px 0;
}
.stat-card {
	background: var(--report-gray);
	padding: 10px;
	text-align: center;
	border-top: 3px solid var(--report-blue);
	font-size: 0.88rem;
	color: #2f3f4c;
}
.stat-card strong {
	display: inline-block;
	margin-left: 4px;
	color: #13222d;
}
.dev {
	font-size: 0.78em;
	margin-left: 5px;
	font-weight: 700;
}
.dev.down { color: #da8a00; }
.dev.up { color: #128a42; }
.dev.flat { color: #687886; }
.report-table {
	width: 100%;
	border-collapse: collapse;
	margin-top: 10px;
}
.report-table th,
.report-table td {
	border: 1px solid #999;
	padding: 7px;
	text-align: left;
	font-size: 12px;
	color: #1f2f3a;
}
.report-table thead th {
	background: #fff;
	font-weight: 700;
	text-transform: uppercase;
	font-size: 11px;
}
.report-table td.center,
.report-table th.center {
	text-align: center;
}
.remarks-section {
	display: flex;
	justify-content: space-between;
	gap: 14px;
	margin-top: 22px;
	border-top: 1px solid #dde5ec;
	padding-top: 14px;
}
.remarks {
	flex: 1;
	background: #fafcfe;
	border: 1px solid #d8e2eb;
	padding: 12px;
}
.remarks p {
	margin: 7px 0;
	font-size: 0.9rem;
	color: #293843;
}
.qr-code {
	width: 112px;
	display: flex;
	align-items: center;
	justify-content: center;
	border: 1px solid #d8e2eb;
	background: #fff;
	padding: 8px;
}
.qr-code img {
	width: 92px;
	height: 92px;
	object-fit: contain;
}
@media (max-width: 991px) {
	.student-profile {
		grid-template-columns: 1fr;
	}
	.stats-row {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
	.remarks-section {
		flex-direction: column;
	}
	.school-info {
		text-align: left;
	}
}
@media (max-width: 640px) {
	.report-header {
		flex-direction: column;
		align-items: flex-start;
	}
	.stats-row {
		grid-template-columns: 1fr;
	}
}
@media print{
	.app-header,.app-sidebar,.app-title,.report-actions,.app-nav,.tile:first-of-type{display:none!important}
	.app-content{margin-left:0;padding:0}
	.report-container{box-shadow:none;max-width:100%;margin:0;border-left-width:10px}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);\"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("parent/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Report Card</h1>
</div>
</div>

<div class="tile mb-3">
<div class="tile-body">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end">
<div>
<label class="form-label">Student</label>
<select class="form-control" name="student" required>
<option value="">Select student</option>
<?php foreach (($students ?? []) as $std): ?>
<option value="<?php echo $std['student_id']; ?>" <?php echo ($std['student_id'] === $studentId) ? 'selected' : ''; ?>><?php echo $std['fname'].' '.$std['lname']; ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Term</label>
<select class="form-control" name="term" required>
<option value="">Select term</option>
<?php foreach (($terms ?? []) as $term): ?>
<option value="<?php echo $term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo $term['name']; ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<label class="form-label">Exam</label>
<select class="form-control" name="exam">
<option value="">Latest published exam</option>
<?php foreach (($examOptions ?? []) as $exam): ?>
<option value="<?php echo (int)$exam['id']; ?>" <?php echo ((int)$exam['id'] === $examId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<button class="btn btn-primary">View Report</button>
</div>
</form>
</div>
</div>

<?php if ($examSummary && !empty($examBreakdown)): ?>
<div class="tile mb-3">
<div class="tile-body">
<div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
<div>
<h4 class="mb-1">Selected Exam Snapshot</h4>
<p class="text-muted mb-0"><?php echo htmlspecialchars((string)$examSummary['exam_name']); ?> · <?php echo htmlspecialchars(strtoupper((string)$examSummary['status'])); ?> · <?php echo htmlspecialchars(strtoupper((string)$examSummary['assessment_mode'])); ?></p>
</div>
<div class="d-flex gap-2 flex-wrap">
<span class="badge bg-primary">Mean <?php echo number_format((float)$examSummary['mean'], 2); ?>%</span>
<span class="badge bg-success">Grade <?php echo htmlspecialchars((string)$examSummary['grade']); ?></span>
<span class="badge bg-info text-dark">Position <?php echo htmlspecialchars((string)$examSummary['position']); ?></span>
<span class="badge bg-secondary">Total <?php echo number_format((float)$examSummary['total'], 1); ?></span>
</div>
</div>
<div class="table-responsive">
<table class="subject-table">
<thead>
<tr>
<th>Subject</th>
<th>Performance</th>
<th>Score</th>
<th>Class Mean</th>
<th>Grade</th>
<th>Teacher</th>
<th>Source</th>
</tr>
</thead>
<tbody>
<?php foreach ($examBreakdown as $subject): ?>
<tr>
<td><?php echo htmlspecialchars((string)$subject['subject_name']); ?></td>
<td><div class="performance-bar"><span style="width: <?php echo (float)$subject['progress']; ?>%"></span></div></td>
<td><?php echo number_format((float)$subject['score'], 2); ?>%</td>
<td><?php echo number_format((float)$subject['class_mean'], 2); ?>%</td>
<td><?php echo htmlspecialchars((string)$subject['grade']); ?></td>
<td><?php echo htmlspecialchars((string)($subject['teacher_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)($subject['source'] ?? 'Exam result')); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<?php endif; ?>

<?php if (!$card): ?>
<div class="tile">
<div class="tile-body">
<p class="text-muted mb-0">This report is not visible yet. Current release stage: <strong><?php echo htmlspecialchars(ucfirst($publicationState)); ?></strong>. It will appear here after the school publishes results.</p>
</div>
</div>
<?php else: ?>
<?php
$rows = !empty($examBreakdown) ? $examBreakdown : $subjectBreakdown;
$subjectCount = count($rows);
$totalMarks = isset($examSummary['total']) ? (float)$examSummary['total'] : (float)($card['total'] ?? 0);
$meanScore = isset($examSummary['mean']) ? (float)$examSummary['mean'] : (float)($card['mean'] ?? 0);
$maxMarks = max(100, $subjectCount * 100);
$classMeanTotal = 0.0;
$gradePointMap = [
	'A+' => 12, 'A' => 11, 'A-' => 10, 'B+' => 9, 'B' => 8, 'B-' => 7,
	'C+' => 6, 'C' => 5, 'C-' => 4, 'D+' => 3, 'D' => 2, 'D-' => 1, 'E' => 0
];
$totalPoints = 0.0;
foreach ($rows as $subjectRow) {
	$classMeanTotal += (float)($subjectRow['class_mean'] ?? 0);
	$gradeKey = strtoupper(trim((string)($subjectRow['grade'] ?? '')));
	$totalPoints += (float)($gradePointMap[$gradeKey] ?? 0);
}
$classMeanAvg = $subjectCount > 0 ? $classMeanTotal / $subjectCount : 0.0;
$pointsMax = max(12, $subjectCount * 12);
$classPointEstimate = ($classMeanAvg / 100) * $pointsMax;
$meanDev = $meanScore - $classMeanAvg;
$totalDev = $totalMarks - $classMeanTotal;
$pointsDev = $totalPoints - $classPointEstimate;
$schoolContact = trim(implode(' | ', array_filter([trim((string)WBAddress), trim((string)WBPhone), trim((string)WBEmail)])));
$logoPath = 'images/logo/' . trim((string)WBLogo);
$logoExists = trim((string)WBLogo) !== '' && is_file($logoPath);
?>
<?php if ($blockReport): ?>
<div class="alert alert-warning">Report card is temporarily unavailable until the fees balance is cleared.</div>
<?php endif; ?>
<div class="report-actions mb-3 d-flex flex-wrap gap-2">
	<a class="btn btn-outline-secondary" href="parent/report_card_pdf?term=<?php echo $termId; ?>&student=<?php echo $studentId; ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>&print=1" target="_blank"><i class="bi bi-printer me-2"></i>Print</a>
	<a class="btn btn-primary" href="parent/report_card_pdf?term=<?php echo $termId; ?>&student=<?php echo $studentId; ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>&download=1" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
	<a class="btn btn-outline-secondary" href="verify_report?code=<?php echo $card['verification_code']; ?>" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Verify</a>
</div>
<div class="report-container">
	<header class="report-header">
		<div class="logo-wrap">
			<?php if ($logoExists): ?>
			<img src="<?php echo htmlspecialchars($logoPath); ?>" alt="School Logo" class="logo">
			<?php endif; ?>
		</div>
		<div class="school-info">
			<h1><?php echo htmlspecialchars((string)WBName); ?></h1>
			<p><?php echo htmlspecialchars($schoolContact); ?></p>
		</div>
	</header>

	<div class="report-title">
		ACADEMIC REPORT FORM - <?php echo strtoupper(htmlspecialchars($className)); ?> - <?php echo strtoupper(htmlspecialchars((string)($selectedExam['name'] ?? 'END TERM COMBINED'))); ?> - (<?php echo strtoupper(htmlspecialchars($termName)); ?>)
	</div>

	<section class="student-profile">
		<div class="photo-box">
			<?php if ($photoExists): ?>
			<img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Student Photo">
			<?php else: ?>
			<div class="photo-fallback"><?php echo htmlspecialchars(strtoupper(substr($studentName, 0, 1))); ?></div>
			<?php endif; ?>
		</div>
		<div class="details">
			<p><strong>NAME:</strong> <?php echo htmlspecialchars($studentName); ?></p>
			<p><strong>ADMNO:</strong> <?php echo htmlspecialchars($schoolId !== '' ? $schoolId : $studentId); ?></p>
			<p><strong>FORM:</strong> <?php echo htmlspecialchars($className); ?></p>
			<p><strong>KCPE:</strong> <?php echo htmlspecialchars($kcpeScore !== '' ? $kcpeScore : 'N/A'); ?></p>
		</div>
		<div class="performance-chart">
			<p>Subject Performance - Student vs Class</p>
			<div class="chart-placeholder">
				<?php foreach (array_slice($rows, 0, 6) as $chartRow): ?>
				<div class="chart-row">
					<span><?php echo htmlspecialchars((string)$chartRow['subject_name']); ?></span>
					<div class="chart-bars">
						<div class="student-bar" style="width: <?php echo max(0, min(100, (float)($chartRow['score'] ?? 0))); ?>%;"></div>
						<div class="class-bar" style="width: <?php echo max(0, min(100, (float)($chartRow['class_mean'] ?? 0))); ?>%;"></div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<div class="stats-row">
		<div class="stat-card">Mean: <strong><?php echo htmlspecialchars((string)($examSummary['grade'] ?? $card['grade'])); ?></strong> <span class="dev <?php echo $meanDev > 0 ? 'up' : ($meanDev < 0 ? 'down' : 'flat'); ?>"><?php echo ($meanDev > 0 ? '+' : '') . number_format($meanDev, 1); ?></span></div>
		<div class="stat-card">Total Marks: <strong><?php echo number_format($totalMarks, 0) . '/' . number_format($maxMarks, 0); ?></strong> <span class="dev <?php echo $totalDev > 0 ? 'up' : ($totalDev < 0 ? 'down' : 'flat'); ?>"><?php echo ($totalDev > 0 ? '+' : '') . number_format($totalDev, 0); ?></span></div>
		<div class="stat-card">Total Points: <strong><?php echo number_format($totalPoints, 1) . '/' . number_format($pointsMax, 0); ?></strong> <span class="dev <?php echo $pointsDev > 0 ? 'up' : ($pointsDev < 0 ? 'down' : 'flat'); ?>"><?php echo ($pointsDev > 0 ? '+' : '') . number_format($pointsDev, 1); ?></span></div>
		<div class="stat-card">Stream Position: <strong><?php echo htmlspecialchars((string)$card['position'] . '/' . (string)$card['total_students']); ?></strong> <span class="dev flat">0</span></div>
		<div class="stat-card">Overall Position: <strong><?php echo htmlspecialchars((string)$card['position'] . '/' . (string)$card['total_students']); ?></strong> <span class="dev flat">0</span></div>
	</div>

	<table class="report-table">
		<thead>
			<tr>
				<th>Subject</th>
				<th class="center">Cat 1</th>
				<th class="center">Cat 2</th>
				<th class="center" colspan="2"><?php echo strtoupper(htmlspecialchars((string)($selectedExam['name'] ?? 'END TERM COMBINED'))); ?></th>
				<th class="center">Rank</th>
				<th>Comment</th>
				<th>Teacher</th>
			</tr>
			<tr>
				<th></th><th></th><th></th><th class="center">Marks</th><th class="center">Dev.</th><th></th><th></th><th></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($rows as $subject):
				$cat1 = $subject['cat1'] ?? ($subject['cat_1'] ?? '-');
				$cat2 = $subject['cat2'] ?? ($subject['cat_2'] ?? '-');
				$score = (float)($subject['score'] ?? 0);
				$classMean = (float)($subject['class_mean'] ?? 0);
				$dev = $score - $classMean;
			?>
			<tr>
				<td><?php echo htmlspecialchars((string)$subject['subject_name']); ?></td>
				<td class="center"><?php echo is_numeric($cat1) ? number_format((float)$cat1, 1) . '%' : htmlspecialchars((string)$cat1); ?></td>
				<td class="center"><?php echo is_numeric($cat2) ? number_format((float)$cat2, 1) . '%' : htmlspecialchars((string)$cat2); ?></td>
				<td class="center"><?php echo number_format($score, 1); ?>%</td>
				<td class="center dev <?php echo $dev > 0 ? 'up' : ($dev < 0 ? 'down' : 'flat'); ?>"><?php echo ($dev > 0 ? '+' : '') . number_format($dev, 1); ?></td>
				<td class="center"><?php echo htmlspecialchars((string)($subject['rank'] ?? '-')); ?></td>
				<td><?php echo htmlspecialchars((string)($subject['remark'] ?? '')); ?></td>
				<td><?php echo htmlspecialchars((string)($subject['teacher_name'] ?? '')); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<footer class="remarks-section">
		<div class="remarks">
			<p><strong>Remarks</strong></p>
			<p><strong>Class Teacher:</strong> <?php echo htmlspecialchars((string)($card['teacher_comment'] ?? $card['remark'])); ?></p>
			<p><strong>Principal:</strong> <?php echo htmlspecialchars((string)($card['headteacher_comment'] ?? $card['remark'])); ?></p>
		</div>
		<div class="qr-code">
			<img src="https://api.qrserver.com/v1/create-qr-code/?size=92x92&data=<?php echo urlencode((string)($card['verification_code'] ?? '')); ?>" alt="QR Code">
		</div>
	</footer>
</div>
<?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
