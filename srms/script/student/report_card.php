<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/id_card_engine.php');

if ($res !== "1" || $level !== "3") { header("location:../"); }

$termId = isset($_GET['term']) ? (int)$_GET['term'] : 0;
$examId = isset($_GET['exam']) ? (int)$_GET['exam'] : 0;
$card = null;
$attendance = ['days_open' => 0, 'present' => 0, 'absent' => 0];
$feesBalance = 0;
$blockReport = false;
$termName = '';
$className = '';
$schoolId = '';
$photoPath = '';
$photoExists = false;
$subjectBreakdown = [];
$history = [];
$publicationState = 'draft';
$isPublished = false;
$examOptions = [];
$selectedExam = null;
$examSummary = null;
$examBreakdown = [];
$kcpeScore = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$studentId = (string)$account_id;

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($termId < 1 && !empty($terms)) {
		$termId = (int)$terms[count($terms)-1]['id'];
	}

	if ($termId > 0) {
		$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ? LIMIT 1");
		$stmt->execute([$termId]);
		$termName = (string)$stmt->fetchColumn();

		$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
		$stmt->execute([$class]);
		$className = (string)$stmt->fetchColumn();
		if (app_column_exists($conn, 'tbl_students', 'school_id')) {
			$stmt = $conn->prepare("SELECT school_id FROM tbl_students WHERE id = ? LIMIT 1");
			$stmt->execute([$studentId]);
			$schoolId = (string)$stmt->fetchColumn();
		}
		if (app_column_exists($conn, 'tbl_students', 'kcpe')) {
			$stmt = $conn->prepare("SELECT kcpe FROM tbl_students WHERE id = ? LIMIT 1");
			$stmt->execute([$studentId]);
			$kcpeScore = (string)$stmt->fetchColumn();
		}
		$payload = idcard_student_payload($conn, $studentId);
		if ($payload) {
			$photoPath = (string)$payload['photo_path'];
			$photoExists = (bool)$payload['photo_exists'];
		}
		$publicationState = report_term_publish_state($conn, (int)$class, $termId);
		$isPublished = report_term_is_published($conn, (int)$class, $termId);
		$examOptions = report_term_exam_options($conn, (int)$class, $termId);
		if ($examId < 1 && !empty($examOptions)) {
			$examId = (int)$examOptions[0]['id'];
		}
		foreach ($examOptions as $option) {
			if ((int)$option['id'] === $examId) {
				$selectedExam = $option;
				break;
			}
		}
		if (!$isPublished) {
			$card = null;
		} elseif (app_table_exists($conn, 'tbl_report_cards')) {
			if ($selectedExam) {
				$examSummary = report_exam_summary($conn, $studentId, (int)$class, $termId, (int)$selectedExam['id']);
				$examBreakdown = report_exam_subject_breakdown($conn, $studentId, (int)$class, $termId, (int)$selectedExam['id']);
			}
			$card = report_ensure_card_generated($conn, $studentId, (int)$class, $termId);
			if ($card) {
				$attendance = report_attendance_summary($conn, $studentId, (int)$class, $termId);
				$feesBalance = report_fees_balance($conn, $studentId, $termId);
				$settings = report_get_settings($conn);
				$blockReport = ((int)$settings['require_fees_clear'] === 1 && $feesBalance > 0);
				$subjectBreakdown = report_subject_breakdown($conn, $studentId, (int)$class, $termId);
				$history = report_student_term_history($conn, $studentId, (int)$class);
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
	--report-navy: #0f2f4a;
	--report-gold: #c79a2d;
	--report-slate: #5c6f80;
	--report-ink: #13222d;
	--report-line: #d6dde6;
	--report-bg: #f4f8fb;
	--report-soft: #f7fbff;
}

.report-toolbar {
	width: min(100%, 1380px);
	margin: 0 auto 16px;
	background: #fff;
	border: 1px solid var(--report-line);
	border-radius: 18px;
	padding: 14px 16px;
	box-shadow: 0 14px 40px rgba(17, 61, 103, 0.08);
}

.report-sheet {
	width: min(100%, 1380px);
	margin: 0 auto;
	background: #fff;
	border: 1px solid var(--report-line);
	border-radius: 22px;
	box-shadow: 0 18px 48px rgba(17, 61, 103, 0.10);
	overflow: hidden;
}

.report-hero {
	background: linear-gradient(90deg, #0c2740 0%, var(--report-navy) 58%, #184f73 100%);
	color: #fff;
	padding: 18px 18px 16px;
}

.report-brand {
	display: grid;
	grid-template-columns: 76px 1fr auto;
	gap: 14px;
	align-items: center;
}

.report-brand__logo {
	width: 76px;
	height: 76px;
	border-radius: 16px;
	background: rgba(255, 255, 255, 0.12);
	border: 1px solid rgba(255, 255, 255, 0.24);
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}

.report-brand__logo img {
	max-width: 66px;
	max-height: 66px;
	object-fit: contain;
}

.report-brand__name {
	font-size: 1.58rem;
	font-weight: 800;
	line-height: 1.05;
	margin: 0;
}

.report-brand__meta {
	font-size: 0.88rem;
	line-height: 1.45;
	opacity: 0.95;
}

.report-tag {
	background: rgba(199, 154, 45, 0.16);
	border: 1px solid rgba(199, 154, 45, 0.42);
	border-radius: 999px;
	padding: 7px 12px;
	font-size: 0.8rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.report-section {
	padding: 22px;
	border-bottom: 1px solid var(--report-line);
	background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
}

.student-grid {
	display: grid;
	grid-template-columns: 132px minmax(0, 1.25fr) minmax(0, 1.15fr);
	gap: 16px;
	align-items: stretch;
}

.student-photo {
	width: 132px;
	height: 156px;
	border-radius: 20px;
	border: 1px solid var(--report-line);
	background: linear-gradient(180deg, #f9fcff 0%, #eef6fb 100%);
	overflow: hidden;
	display: flex;
	align-items: center;
	justify-content: center;
}

.student-photo img {
	width: 100%;
	height: 100%;
	object-fit: cover;
}

.student-fallback {
	font-size: 2rem;
	font-weight: 800;
	color: #1f4d75;
	letter-spacing: 0.05em;
}

.identity-card,
.snapshot-card,
.remarks-card,
.data-card {
	border: 1px solid var(--report-line);
	border-radius: 20px;
	background: #fff;
	overflow: hidden;
}

.identity-card__head,
.snapshot-card__head,
.data-card__head,
.remarks-card__head {
	padding: 10px 12px;
	font-size: 0.78rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.06em;
	background: linear-gradient(90deg, #f4f7fa 0%, #eef2f6 100%);
	color: var(--report-navy);
}

.identity-card__body,
.snapshot-card__body,
.remarks-card__body,
.data-card__body {
	padding: 14px;
}

.student-details {
	padding: 16px;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	background: linear-gradient(180deg, #fcfdff 0%, var(--report-soft) 100%);
	height: 100%;
}

.student-details h2 {
	margin: 0 0 6px;
	font-size: 1.3rem;
	color: var(--report-ink);
}

.student-details p {
	margin: 5px 0;
	font-size: 0.92rem;
	color: #324450;
}

.pill-row {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 8px;
	margin-top: 10px;
}

.pill {
	border-radius: 14px;
	padding: 10px 10px;
	background: #fff;
	border: 1px solid var(--report-line);
	font-size: 0.84rem;
	line-height: 1.2;
	text-align: center;
}

.pill strong {
	display: block;
	font-size: 1.1rem;
	margin-top: 4px;
	color: var(--report-ink);
}

.snapshot-list {
	display: grid;
	gap: 8px;
}

.snapshot-row {
	display: grid;
	grid-template-columns: 96px 1fr;
	gap: 8px;
	align-items: center;
}

.snapshot-row span {
	font-size: 0.78rem;
	color: #49606e;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.snapshot-bars {
	height: 12px;
	background: #e5ecf2;
	position: relative;
	border-radius: 999px;
	overflow: hidden;
}

.snapshot-bars .student-bar {
	position: absolute;
	height: 12px;
	left: 0;
	top: 0;
	background: linear-gradient(90deg, #184f73, #2f77a1);
	opacity: 0.9;
}

.snapshot-bars .class-bar {
	position: absolute;
	height: 6px;
	left: 0;
	bottom: 0;
	background: linear-gradient(90deg, #b48b27, #d2aa4a);
	opacity: 0.75;
}

.stats-grid {
	display: grid;
	grid-template-columns: repeat(6, minmax(0, 1fr));
	gap: 10px;
	margin-top: 14px;
}

.stat-card {
	background: linear-gradient(180deg, #fcfdff 0%, #f0f4f8 100%);
	padding: 12px 10px;
	text-align: center;
	border: 1px solid var(--report-line);
	border-radius: 14px;
	font-size: 0.84rem;
	color: #30414d;
}
.stat-card strong {
	display: block;
	margin-top: 5px;
	font-size: 1.05rem;
	color: var(--report-ink);
}

.stat-card .dev {
	display: block;
	margin-top: 4px;
	font-size: 0.76rem;
	font-weight: 700;
}

.dev.down { color: #d18b00; }
.dev.up { color: #0f6a46; }
.dev.flat { color: #6d7a86; }

.performance-wrap {
	margin-top: 14px;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	overflow: hidden;
	background: #fff;
}

.performance-head {
	background: linear-gradient(90deg, #0f2f4a, #1b4c73);
	color: #fff;
	padding: 11px 14px;
	font-size: 0.82rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.05em;
}

.report-table {
	width: 100%;
	border-collapse: collapse;
	background: #fff;
}
.report-table th,
.report-table td {
	border: 1px solid #cad7e2;
	padding: 8px 7px;
	text-align: left;
	font-size: 0.86rem;
	color: #1f2f3a;
}
.report-table thead th {
	background: #f2f6fa;
	font-weight: 700;
	text-transform: uppercase;
	font-size: 0.72rem;
	letter-spacing: 0.03em;
}
.report-table td.center,
.report-table th.center {
	text-align: center;
}

.footer-grid {
	display: flex;
	gap: 14px;
	margin-top: 14px;
}

.remarks {
	flex: 1;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
	overflow: hidden;
}
.remarks p {
	margin: 7px 0;
	font-size: 0.88rem;
	color: #293843;
}

.verifier {
	width: 360px;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
	overflow: hidden;
}

.verifier__body {
	padding: 14px;
	font-size: 0.86rem;
	line-height: 1.45;
	color: #31414d;
}

.verifier__badge {
	display: inline-block;
	background: linear-gradient(90deg, #0f2f4a, #184f73);
	color: #fff;
	border-radius: 999px;
	padding: 6px 10px;
	font-size: 0.74rem;
	font-weight: 800;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.verifier__qr {
	width: 94px;
	height: 94px;
	border-radius: 18px;
	background: linear-gradient(180deg, #f4f7fa, #e6edf4);
	border: 1px solid #b9c8d6;
	margin-bottom: 10px;
}

.report-actions {
	max-width: 1180px;
	margin: 0 auto 14px;
}

.report-empty {
	max-width: 1180px;
	margin: 0 auto;
	background: #fff;
	border: 1px solid var(--report-line);
	border-radius: 18px;
	padding: 18px;
	box-shadow: 0 14px 40px rgba(17, 61, 103, 0.08);
}

@media (max-width: 991px) {
	.student-grid,
	.report-brand {
		grid-template-columns: 1fr;
	}
	.pill-row,
	.stats-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
	.footer-grid {
		flex-direction: column;
	}
	.verifier {
		width: 100%;
	}
	.report-brand__logo {
		margin-bottom: 4px;
	}
}
@media (max-width: 640px) {
	.report-toolbar,
	.report-sheet,
	.report-empty {
		border-radius: 14px;
	}
	.pill-row,
	.stats-grid {
		grid-template-columns: 1fr;
	}
	.student-grid {
		grid-template-columns: 1fr;
	}
}
@media print{
	.app-header,.app-sidebar,.app-title,.report-actions,.app-nav,.tile:first-of-type,.report-toolbar{display:none!important}
	.app-content{margin-left:0;padding:0}
	.report-sheet,.report-empty{box-shadow:none;max-width:100%;margin:0;border-radius:0;border:0}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);\"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("student/partials/sidebar.php"); ?>

<main class="app-content">
<div class="app-title">
<div>
<h1>Report Card</h1>
</div>
</div>

<div class="report-toolbar">
<form method="get" class="d-flex flex-wrap gap-2 align-items-end">
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

<?php if (!$card): ?>
<div class="report-empty">
	<p class="text-muted mb-0">This report is not visible yet. Current release stage: <strong><?php echo htmlspecialchars(ucfirst($publicationState)); ?></strong>. It will appear here after the school publishes results.</p>
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
$attendanceRate = ((int)($attendance['days_open'] ?? 0) > 0) ? ((int)($attendance['present'] ?? 0) / max(1, (int)($attendance['days_open'] ?? 0)) * 100) : 0;
$currentGrade = (string)($examSummary['grade'] ?? $card['grade'] ?? 'N/A');
$streamPosition = (string)($examSummary['position'] ?? $card['position'] ?? '-') . '/' . (string)($examSummary['total'] ?? $card['total_students'] ?? '-');
$overallPosition = (string)($card['position'] ?? '-') . '/' . (string)($card['total_students'] ?? '-');
?>
<?php if ($blockReport): ?>
<div class="report-empty mb-3" style="border-left:6px solid #d18b00;">
	<p class="mb-0"><strong>Report card is temporarily unavailable until the fees balance is cleared.</strong></p>
</div>
<?php endif; ?>
<div class="report-actions d-flex flex-wrap gap-2">
	<a class="btn btn-outline-secondary" href="student/report_card_pdf?term=<?php echo $termId; ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>&print=1" target="_blank"><i class="bi bi-printer me-2"></i>Print</a>
	<a class="btn btn-primary" href="student/report_card_pdf?term=<?php echo $termId; ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>&download=1" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
	<a class="btn btn-outline-secondary" href="verify_report?code=<?php echo $card['verification_code']; ?>" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Verify</a>
</div>

<section class="report-sheet">
	<header class="report-hero">
		<div class="report-brand">
			<div class="report-brand__logo">
				<?php if ($logoExists): ?>
				<img src="<?php echo htmlspecialchars($logoPath); ?>" alt="School Logo">
				<?php endif; ?>
			</div>
			<div>
				<h2 class="report-brand__name mb-1"><?php echo htmlspecialchars((string)WBName); ?></h2>
				<div class="report-brand__meta"><?php echo htmlspecialchars($schoolContact); ?></div>
			</div>
			<div class="report-tag">Official Academic Report</div>
		</div>
	</header>

	<div class="report-section">
		<div class="student-grid">
			<div class="student-photo">
				<?php if ($photoExists): ?>
				<img src="<?php echo htmlspecialchars($photoPath); ?>" alt="Student Photo">
				<?php else: ?>
				<div class="student-fallback"><?php echo htmlspecialchars(strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1))); ?></div>
				<?php endif; ?>
			</div>

			<div class="student-details">
				<h2><?php echo htmlspecialchars($fname . ' ' . $lname); ?></h2>
				<p><strong>Admission No:</strong> <?php echo htmlspecialchars($schoolId !== '' ? $schoolId : $account_id); ?></p>
				<p><strong>Class/Form:</strong> <?php echo htmlspecialchars($className); ?></p>
				<p><strong>Term:</strong> <?php echo htmlspecialchars($termName); ?></p>
				<p><strong>Exam:</strong> <?php echo htmlspecialchars((string)($selectedExam['name'] ?? 'End Term Combined')); ?></p>
				<p><strong>KCPE:</strong> <?php echo htmlspecialchars($kcpeScore !== '' ? $kcpeScore : 'N/A'); ?></p>

				<div class="pill-row">
					<div class="pill">Mean<strong><?php echo htmlspecialchars($currentGrade); ?></strong></div>
					<div class="pill">Stream<strong><?php echo htmlspecialchars($streamPosition); ?></strong></div>
					<div class="pill">Overall<strong><?php echo htmlspecialchars($overallPosition); ?></strong></div>
				</div>
			</div>

			<div class="snapshot-card">
				<div class="snapshot-card__head">Subject Snapshot</div>
				<div class="snapshot-card__body">
					<div class="snapshot-list">
						<?php foreach (array_slice($rows, 0, 5) as $chartRow): ?>
						<div class="snapshot-row">
							<span><?php echo htmlspecialchars((string)$chartRow['subject_name']); ?></span>
							<div class="snapshot-bars">
								<div class="student-bar" style="width: <?php echo max(0, min(100, (float)($chartRow['score'] ?? 0))); ?>%;"></div>
								<div class="class-bar" style="width: <?php echo max(0, min(100, (float)($chartRow['class_mean'] ?? 0))); ?>%;"></div>
							</div>
						</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</div>

		<div class="stats-grid">
			<div class="stat-card">Mean Grade<strong><?php echo htmlspecialchars($currentGrade); ?></strong><span class="dev <?php echo $meanDev > 0 ? 'up' : ($meanDev < 0 ? 'down' : 'flat'); ?>"><?php echo ($meanDev > 0 ? '+' : '') . number_format($meanDev, 1); ?> vs class</span></div>
			<div class="stat-card">Total Marks<strong><?php echo number_format($totalMarks, 0) . '/' . number_format($maxMarks, 0); ?></strong><span class="dev <?php echo $totalDev > 0 ? 'up' : ($totalDev < 0 ? 'down' : 'flat'); ?>"><?php echo ($totalDev > 0 ? '+' : '') . number_format($totalDev, 0); ?> dev.</span></div>
			<div class="stat-card">Total Points<strong><?php echo number_format($totalPoints, 1) . '/' . number_format($pointsMax, 0); ?></strong><span class="dev <?php echo $pointsDev > 0 ? 'up' : ($pointsDev < 0 ? 'down' : 'flat'); ?>"><?php echo ($pointsDev > 0 ? '+' : '') . number_format($pointsDev, 1); ?> dev.</span></div>
			<div class="stat-card">Attendance<strong><?php echo (int)$attendance['present']; ?>/<?php echo (int)$attendance['days_open']; ?></strong><span class="dev flat"><?php echo number_format($attendanceRate, 1); ?>%</span></div>
			<div class="stat-card">Stream Position<strong><?php echo htmlspecialchars($streamPosition); ?></strong><span class="dev flat">current</span></div>
			<div class="stat-card">Overall Position<strong><?php echo htmlspecialchars($overallPosition); ?></strong><span class="dev flat">school wide</span></div>
		</div>

		<div class="performance-wrap">
			<div class="performance-head">Academic Performance</div>
			<table class="report-table">
				<thead>
					<tr>
						<th>Subject</th>
						<th class="center">Cat 1</th>
						<th class="center">Cat 2</th>
						<th class="center">Score</th>
						<th class="center">Dev.</th>
						<th class="center">Grade</th>
						<th class="center">Rank</th>
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
						<td><strong><?php echo htmlspecialchars((string)$subject['subject_name']); ?></strong></td>
						<td class="center"><?php echo is_numeric($cat1) ? number_format((float)$cat1, 1) . '%' : htmlspecialchars((string)$cat1); ?></td>
						<td class="center"><?php echo is_numeric($cat2) ? number_format((float)$cat2, 1) . '%' : htmlspecialchars((string)$cat2); ?></td>
						<td class="center"><?php echo number_format($score, 1); ?>%</td>
						<td class="center dev <?php echo $dev > 0 ? 'up' : ($dev < 0 ? 'down' : 'flat'); ?>"><?php echo ($dev > 0 ? '+' : '') . number_format($dev, 1); ?></td>
						<td class="center"><strong><?php echo htmlspecialchars((string)($subject['grade'] ?? '-')); ?></strong></td>
						<td class="center"><?php echo htmlspecialchars((string)($subject['rank'] ?? '-')); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="footer-grid">
			<div class="remarks">
				<div class="remarks-card__head">Remarks</div>
				<div class="remarks-card__body">
					<p><strong>Class Teacher:</strong> <?php echo htmlspecialchars((string)($card['teacher_comment'] ?? $card['remark'])); ?></p>
					<p><strong>Principal:</strong> <?php echo htmlspecialchars((string)($card['headteacher_comment'] ?? $card['remark'])); ?></p>
					<p><strong>School Motto:</strong> <?php echo htmlspecialchars((string)WBMotto); ?></p>
				</div>
			</div>

			<div class="verifier">
				<div class="remarks-card__head">Verification</div>
				<div class="verifier__body">
					<div class="verifier__badge mb-2">Secure QR Verification</div>
					<div class="verifier__qr d-flex align-items-center justify-content-center"><i class="bi bi-qr-code-scan fs-2 text-success"></i></div>
					<div><strong>Code:</strong> <?php echo htmlspecialchars((string)($card['verification_code'] ?? '')); ?></div>
					<div><strong>Portal User:</strong> <?php echo htmlspecialchars((string)($schoolId !== '' ? $schoolId : $account_id)); ?></div>
					<div class="mt-2 text-muted">Verify this report through the school portal or the PDF download.</div>
				</div>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
