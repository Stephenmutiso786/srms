<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/id_card_engine.php');
require_once('const/report_card_layout.php');

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
$reportArchive = [];
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
		if (app_table_exists($conn, 'tbl_report_cards')) {
			if ($selectedExam) {
				$examSummary = report_exam_summary($conn, $studentId, (int)$class, $termId, (int)$selectedExam['id']);
				$examBreakdown = report_exam_subject_breakdown($conn, $studentId, (int)$class, $termId, (int)$selectedExam['id']);
			}
			$card = report_ensure_card_generated($conn, $studentId, (int)$class, $termId, null, $examId);
			if ($card) {
				if ((int)($card['class_id'] ?? 0) > 0 && (int)($card['class_id'] ?? 0) !== (int)$class) {
					$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
					$stmt->execute([(int)$card['class_id']]);
					$className = (string)$stmt->fetchColumn();
				}
				$attendance = report_attendance_summary($conn, $studentId, (int)$class, $termId);
				$feesBalance = report_fees_balance($conn, $studentId, $termId);
				$settings = report_get_settings($conn);
				$blockReport = ((int)$settings['require_fees_clear'] === 1 && $feesBalance > 0);
				$subjectBreakdown = report_subject_breakdown($conn, $studentId, (int)$class, $termId, $examId);
				$history = report_student_term_history($conn, $studentId, (int)$class);
			}
		}
		$reportArchive = report_student_report_archive($conn, $studentId, 24);
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
	width: min(100%, 1500px);
	margin: 0 auto 16px;
	background: #fff;
	border: 1px solid var(--report-line);
	border-radius: 18px;
	padding: 16px 18px;
	box-shadow: 0 14px 40px rgba(17, 61, 103, 0.08);
}

.report-sheet {
	width: min(100%, 1500px);
	margin: 0 auto;
	background: #fff;
	border: 1px solid var(--report-line);
	border-radius: 22px;
	box-shadow: 0 18px 48px rgba(17, 61, 103, 0.10);
	overflow: hidden;
}

.report-hero {
	background: linear-gradient(90deg, #092032 0%, #0f2f4a 58%, #173f61 100%);
	color: #fff;
	padding: 14px 18px 13px;
}

.report-brand {
	display: grid;
	grid-template-columns: 68px 1fr auto;
	gap: 14px;
	align-items: center;
}

.report-brand__logo {
	width: 68px;
	height: 68px;
	border-radius: 11px;
	background: rgba(255, 255, 255, 0.10);
	border: 1px solid rgba(255, 255, 255, 0.18);
	display: flex;
	align-items: center;
	justify-content: center;
	overflow: hidden;
}

.report-brand__logo img {
	max-width: 60px;
	max-height: 60px;
	object-fit: contain;
}

.report-brand__name {
	font-size: 1.48rem;
	font-weight: 800;
	line-height: 1.05;
	margin: 0;
}

.report-brand__meta {
	font-size: 0.88rem;
	line-height: 1.45;
	opacity: 0.92;
}

.report-tag {
	background: rgba(199, 154, 45, 0.12);
	border: 1px solid rgba(199, 154, 45, 0.35);
	border-radius: 999px;
	padding: 6px 13px;
	font-size: 0.74rem;
	font-weight: 700;
	text-transform: uppercase;
	letter-spacing: 0.04em;
}

.report-section {
	padding: 28px;
	border-bottom: 1px solid var(--report-line);
	background: linear-gradient(180deg, #fff 0%, #fbfdff 100%);
}

.student-grid {
	display: grid;
	grid-template-columns: 192px minmax(0, 1.4fr) minmax(0, 1.08fr);
	gap: 22px;
	align-items: stretch;
}

.student-photo {
	width: 192px;
	height: 224px;
	border-radius: 18px;
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
	font-size: 2.2rem;
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
	padding: 12px 14px;
	font-size: 0.84rem;
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
	padding: 20px 22px;
}

.student-details {
	padding: 26px 28px;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	background: linear-gradient(180deg, #fcfdff 0%, var(--report-soft) 100%);
	height: 100%;
}

.student-details h2 {
	margin: 0 0 6px;
	font-size: 1.6rem;
	color: var(--report-ink);
}

.student-details p {
	margin: 5px 0;
	font-size: 1rem;
	color: #324450;
}

.pill-row {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 12px;
	margin-top: 14px;
}

.pill {
	border-radius: 14px;
	padding: 13px 12px;
	background: #fff;
	border: 1px solid var(--report-line);
	font-size: 0.82rem;
	line-height: 1.2;
	text-align: center;
}

.pill strong {
	display: block;
	font-size: 1.14rem;
	margin-top: 4px;
	color: var(--report-ink);
}

.snapshot-list {
	display: grid;
	gap: 11px;
}

.snapshot-row {
	display: grid;
	grid-template-columns: 124px 1fr;
	gap: 12px;
	align-items: center;
}

.snapshot-row span {
	font-size: 0.86rem;
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
	gap: 14px;
	margin-top: 16px;
}

.stat-card {
	background: linear-gradient(180deg, #fcfdff 0%, #f0f4f8 100%);
	padding: 16px 13px;
	text-align: center;
	border: 1px solid var(--report-line);
	border-radius: 14px;
	font-size: 0.94rem;
	color: #30414d;
}
.stat-card strong {
	display: block;
	margin-top: 5px;
	font-size: 1.16rem;
	color: var(--report-ink);
}

.stat-card .dev {
	display: block;
	margin-top: 4px;
	font-size: 0.84rem;
	font-weight: 700;
}

.dev.down { color: #d18b00; }
.dev.up { color: #0f6a46; }
.dev.flat { color: #6d7a86; }

.performance-wrap {
	margin-top: 18px;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	overflow: hidden;
	background: #fff;
}

.performance-head {
	background: linear-gradient(90deg, #0f2f4a, #1b4c73);
	color: #fff;
	padding: 14px 18px;
	font-size: 0.9rem;
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
	padding: 13px 10px;
	text-align: left;
	font-size: 0.98rem;
	color: #1f2f3a;
}
.report-table thead th {
	background: #f2f6fa;
	font-weight: 700;
	text-transform: uppercase;
	font-size: 0.82rem;
	letter-spacing: 0.03em;
}
.report-table td.center,
.report-table th.center {
	text-align: center;
}

.footer-grid {
	display: flex;
	gap: 12px;
	margin-top: 14px;
	align-items: stretch;
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
	font-size: 0.98rem;
	color: #293843;
}

.verifier {
	width: 388px;
	border: 1px solid var(--report-line);
	border-radius: 20px;
	background: linear-gradient(180deg, #ffffff 0%, #fbfcfe 100%);
	overflow: hidden;
}

.verifier__body {
	padding: 14px;
	font-size: 0.96rem;
	line-height: 1.45;
	color: #31414d;
}

.verifier__badge {
	display: inline-block;
	background: linear-gradient(90deg, #0f2f4a, #184f73);
	color: #fff;
	border-radius: 999px;
	padding: 7px 12px;
	font-size: 0.82rem;
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
<?php echo app_report_card_view_styles(); ?>
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
	<p class="text-muted mb-0">No report card could be generated yet. Current release stage: <strong><?php echo htmlspecialchars(ucfirst($publicationState)); ?></strong>.</p>
</div>
<?php else: ?>
<?php
$schoolContact = trim(implode(' | ', array_filter([trim((string)WBAddress), trim((string)WBPhone), trim((string)WBEmail)])));
$logoPath = 'images/logo/' . trim((string)WBLogo);
$logoExists = trim((string)WBLogo) !== '' && is_file($logoPath);
?>
<?php if ($blockReport): ?>
<div class="report-empty mb-3" style="border-left:6px solid #d18b00;">
	<p class="mb-0"><strong>Report card is temporarily unavailable until the fees balance is cleared.</strong></p>
</div>
<?php endif; ?>
<?php if (!$isPublished): ?>
<div class="report-empty mb-3" style="border-left:6px solid #00aeef;">
	<p class="mb-0"><strong>Preview mode:</strong> results are not published yet, but the report card template is shown here for review.</p>
</div>
<?php endif; ?>
<div class="report-actions d-flex flex-wrap gap-2">
	<a class="btn btn-outline-secondary" href="student/report_card_pdf?term=<?php echo $termId; ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>&print=1" target="_blank"><i class="bi bi-printer me-2"></i>Print</a>
	<a class="btn btn-primary" href="student/report_card_pdf?term=<?php echo $termId; ?><?php echo $examId > 0 ? '&exam=' . $examId : ''; ?>&download=1" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
	<a class="btn btn-outline-secondary" href="verify_report?code=<?php echo $card['verification_code']; ?>" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Verify</a>
</div>
<?php
echo app_report_card_render($conn, [
	'student_id' => (string)$account_id,
	'student_name' => trim($fname . ' ' . $lname),
	'school_id' => ($schoolId !== '' ? $schoolId : (string)$account_id),
	'class_name' => $className,
	'term_name' => $termName,
	'exam_name' => (string)($selectedExam['name'] ?? 'END TERM COMBINED'),
	'kcpe_score' => $kcpeScore,
	'school_contact' => $schoolContact,
	'logo_path' => $logoPath,
	'logo_exists' => $logoExists,
	'photo_path' => $photoPath,
	'photo_exists' => $photoExists,
	'card' => $card,
	'rows' => !empty($examBreakdown) ? $examBreakdown : $subjectBreakdown,
	'overall_grade' => (string)($examSummary['grade'] ?? $card['grade'] ?? 'N/A'),
]);
?>
<?php endif; ?>
<?php if (!empty($reportArchive)): ?>
<div class="report-empty mt-3">
	<h5 class="mb-3">Archived Report Cards</h5>
	<div class="table-responsive">
	<table class="table table-sm table-striped mb-0">
	<thead><tr><th>Term</th><th>Exam</th><th>Class</th><th>Mean</th><th>Grade</th><th>Generated</th><th>Action</th></tr></thead>
	<tbody>
	<?php foreach ($reportArchive as $archiveRow): ?>
	<tr>
		<td><?php echo htmlspecialchars((string)($archiveRow['term_name'] ?? '')); ?></td>
		<td><?php echo htmlspecialchars((string)($archiveRow['exam_name'] ?? 'Latest Published')); ?></td>
		<td><?php echo htmlspecialchars((string)($archiveRow['class_name'] ?? '')); ?></td>
		<td><?php echo number_format((float)($archiveRow['mean'] ?? 0), 2); ?></td>
		<td><?php echo htmlspecialchars((string)($archiveRow['grade'] ?? '')); ?></td>
		<td><?php echo htmlspecialchars((string)($archiveRow['generated_at'] ?? '')); ?></td>
		<td><a href="student/report_card_pdf?report_id=<?php echo (int)$archiveRow['id']; ?>&download=1" target="_blank">PDF</a></td>
	</tr>
	<?php endforeach; ?>
	</tbody>
	</table>
	</div>
</div>
<?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
