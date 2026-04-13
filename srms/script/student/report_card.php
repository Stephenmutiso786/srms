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
		$payload = idcard_student_payload($conn, $studentId);
		if ($payload) {
			$photoPath = (string)$payload['photo_path'];
			$photoExists = (bool)$payload['photo_exists'];
		}
		$publicationState = report_term_publish_state($conn, (int)$class, $termId);

		if (!report_term_is_published($conn, (int)$class, $termId)) {
			$card = null;
		} elseif (app_table_exists($conn, 'tbl_report_cards')) {
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
.report-shell{display:grid;grid-template-columns:340px 1fr;gap:22px}
.report-panel,.report-surface{background:#fff;border-radius:24px;box-shadow:0 18px 50px rgba(9,30,66,.08);overflow:hidden}
.report-panel-header{background:linear-gradient(135deg,#0d64b0,#1ca874);padding:22px;color:#fff}
.report-panel-body{padding:22px}
.student-avatar{width:124px;height:140px;border-radius:20px;overflow:hidden;background:#e8eef5;border:4px solid rgba(255,255,255,.35);box-shadow:0 12px 24px rgba(0,0,0,.12)}
.student-avatar img{width:100%;height:100%;object-fit:cover}
.student-avatar-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:800;color:#0d64b0;background:#fff}
.identity-list{display:grid;gap:12px;margin-top:18px}
.identity-row{display:flex;justify-content:space-between;gap:16px;border-bottom:1px dashed #d7e0ea;padding-bottom:10px}
.identity-row .label{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#7b8794}
.identity-row .value{font-weight:700;color:#123}
.report-surface-header{background:linear-gradient(90deg,#2d9cdb,#0d64b0);color:#fff;padding:14px 22px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.metric-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;padding:18px 22px}
.metric-card{background:#f7fbff;border:1px solid #e4edf7;border-radius:18px;padding:14px}
.metric-card .label{font-size:.76rem;text-transform:uppercase;color:#678}
.metric-card .value{font-size:1.35rem;font-weight:800;color:#123}
.subject-table{width:100%;border-collapse:collapse}
.subject-table th,.subject-table td{padding:12px 10px;border-bottom:1px solid #edf1f5;font-size:.95rem}
.subject-table th{font-size:.78rem;text-transform:uppercase;color:#678;letter-spacing:.04em}
.performance-bar{height:10px;background:#e9eef4;border-radius:999px;overflow:hidden;min-width:120px}
.performance-bar span{display:block;height:100%;background:linear-gradient(90deg,#6fd34d,#33b249);border-radius:999px}
.trend-pill{display:inline-flex;align-items:center;gap:6px;font-weight:700}
.trend-up{color:#1f9d57}.trend-down{color:#e09b17}.trend-steady{color:#6b7280}
.mini-history{display:flex;align-items:flex-end;gap:10px;height:130px;padding:0 4px}
.mini-history .bar{flex:1;background:linear-gradient(180deg,#7cc6ff,#2d9cdb);border-radius:12px 12px 4px 4px;position:relative;min-height:14px}
.mini-history .bar span{position:absolute;bottom:-26px;left:50%;transform:translateX(-50%);font-size:.72rem;color:#678;white-space:nowrap}
.report-footer-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:18px;padding:0 22px 22px}
.signature-box{border-top:1px solid #cfd8e3;padding-top:10px;margin-top:18px;text-align:right;font-weight:700}
@media (max-width: 991px){.report-shell{grid-template-columns:1fr}.metric-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.report-footer-grid{grid-template-columns:1fr}}
@media print{
	.app-header,.app-sidebar,.app-title,.report-actions,.app-nav,.tile:first-of-type{display:none!important}
	.app-content{margin-left:0;padding:0}
	.report-panel,.report-surface{box-shadow:none}
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Student</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Results</span></a></li>
<li><a class="app-menu__item active" href="student/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
<li><a class="app-menu__item" href="student/certificates"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Certificates</span></a></li>
<li><a class="app-menu__item" href="student/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
<li><a class="app-menu__item" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">My Attendance</span></a></li>
</ul>
</aside>

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
<label class="form-label">Term</label>
<select class="form-control" name="term" required>
<option value="">Select term</option>
<?php foreach (($terms ?? []) as $term): ?>
<option value="<?php echo $term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo $term['name']; ?></option>
<?php endforeach; ?>
</select>
</div>
<div>
<button class="btn btn-primary">View Report</button>
</div>
</form>
</div>
</div>

<?php if (!$card): ?>
<div class="tile">
<div class="tile-body">
<p class="text-muted mb-0">This report is not visible yet. Current release stage: <strong><?php echo htmlspecialchars(ucfirst($publicationState)); ?></strong>. It will appear here after the school publishes results.</p>
</div>
</div>
<?php else: ?>
<div class="report-shell">
<div class="report-panel">
<div class="report-panel-header d-flex justify-content-between align-items-start gap-3">
	<div>
		<div class="small opacity-75">Official Student Report</div>
		<h3 class="mb-1"><?php echo WBName; ?></h3>
		<div class="small opacity-75"><?php echo htmlspecialchars($termName); ?> · <?php echo htmlspecialchars($className); ?></div>
	</div>
	<span class="report-badge"><i class="bi bi-shield-check"></i> Published</span>
</div>
<div class="report-panel-body">
	<div class="d-flex gap-3 align-items-start">
		<div class="student-avatar">
			<?php if ($photoExists) { ?><img src="<?php echo htmlspecialchars($photoPath); ?>" alt="student photo"><?php } else { ?><div class="student-avatar-fallback"><?php echo htmlspecialchars(strtoupper(substr($fname,0,1).substr($lname,0,1))); ?></div><?php } ?>
		</div>
		<div class="flex-grow-1">
			<h4 class="mb-1"><?php echo $fname.' '.$lname; ?></h4>
			<div class="text-muted"><?php echo htmlspecialchars($schoolId !== '' ? $schoolId : $account_id); ?></div>
			<div class="mt-2"><span class="badge bg-primary"><?php echo htmlspecialchars($card['grade']); ?></span> <span class="badge bg-success">Position <?php echo $card['position'].'/'.$card['total_students']; ?></span></div>
		</div>
	</div>
	<div class="identity-list">
		<div class="identity-row"><div><div class="label">Class</div><div class="value"><?php echo htmlspecialchars($className); ?></div></div><div><div class="label">Attendance</div><div class="value"><?php echo $attendance['present'].' / '.$attendance['days_open']; ?></div></div></div>
		<div class="identity-row"><div><div class="label">Fees Balance</div><div class="value">KES <?php echo number_format((float)$feesBalance, 0); ?></div></div><div><div class="label">Verification</div><div class="value"><?php echo htmlspecialchars($card['verification_code']); ?></div></div></div>
		<div class="identity-row"><div><div class="label">Trend</div><div class="value"><?php echo htmlspecialchars($card['trend']); ?></div></div><div><div class="label">Remark</div><div class="value"><?php echo htmlspecialchars($card['remark']); ?></div></div></div>
	</div>
	<?php if (!empty($history)) { ?>
	<div class="mt-4">
		<div class="fw-semibold mb-2">Performance Over Time</div>
		<div class="mini-history">
			<?php foreach ($history as $point): $height = max(14, min(110, (float)$point['mean'])); ?>
			<div class="bar" style="height: <?php echo $height; ?>px"><span><?php echo htmlspecialchars($point['term_name']); ?></span></div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php } ?>
	<?php if (!$blockReport): ?>
	<div class="report-actions mt-4 d-flex flex-wrap gap-2">
		<a class="btn btn-outline-secondary" href="student/report_card_pdf?term=<?php echo $termId; ?>&print=1" target="_blank"><i class="bi bi-printer me-2"></i>Print</a>
		<a class="btn btn-primary" href="student/report_card_pdf?term=<?php echo $termId; ?>&download=1" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
		<a class="btn btn-outline-secondary" href="verify_report?code=<?php echo $card['verification_code']; ?>" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Verify</a>
	</div>
	<?php endif; ?>
</div>
</div>
<div class="report-surface">
<?php if ($blockReport): ?>
<div class="alert alert-warning">Report card is temporarily unavailable until the fees balance is cleared.</div>
<?php endif; ?>
<div class="report-surface-header">Academic Report Form · <?php echo htmlspecialchars($termName); ?></div>
<div class="metric-grid">
<div class="metric-card"><div class="label">Total Marks</div><div class="value"><?php echo $card['total']; ?></div></div>
<div class="metric-card"><div class="label">Average Score</div><div class="value"><?php echo $card['mean']; ?>%</div></div>
<div class="metric-card"><div class="label">Mean Points</div><div class="value"><?php echo number_format((float)($card['mean_points'] ?? 0), 2); ?></div></div>
<div class="metric-card"><div class="label">Overall Grade</div><div class="value"><?php echo $card['grade']; ?></div></div>
<div class="metric-card"><div class="label">Class Position</div><div class="value"><?php echo $card['position'].'/'.$card['total_students']; ?></div></div>
</div>
<div class="px-4 pb-2">
<div class="fw-semibold mb-3">Subject Performance</div>
<table class="subject-table">
<thead>
<tr>
<th>Subject</th>
<th>Performance</th>
<th>Mean</th>
<th>Change</th>
<th>Trend</th>
<th>Grade</th>
<th>Teacher</th>
</tr>
</thead>
<tbody>
<?php foreach ($subjectBreakdown as $subject): ?>
<tr>
<td><?php echo htmlspecialchars($subject['subject_name']); ?><div class="small text-muted">Score: <?php echo number_format((float)$subject['score'], 1); ?>%</div></td>
<td><div class="performance-bar"><span style="width: <?php echo (float)$subject['progress']; ?>%"></span></div></td>
<td><?php echo number_format((float)$subject['class_mean'], 2); ?>%</td>
<td><?php echo ($subject['change'] >= 0 ? '+' : '') . number_format((float)$subject['change'], 2); ?></td>
<td>
	<?php $trendClass = $subject['trend'] === 'up' ? 'trend-up' : ($subject['trend'] === 'down' ? 'trend-down' : 'trend-steady'); ?>
	<span class="trend-pill <?php echo $trendClass; ?>">
		<i class="bi <?php echo $subject['trend'] === 'up' ? 'bi-arrow-up-right' : ($subject['trend'] === 'down' ? 'bi-arrow-down-right' : 'bi-dash'); ?>"></i>
		<?php echo ucfirst($subject['trend']); ?>
	</span>
</td>
<td><?php echo htmlspecialchars($subject['grade']); ?></td>
<td><?php echo htmlspecialchars($subject['teacher_name']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="report-footer-grid">
	<div class="metric-card">
		<div class="label">Teacher / Class Teacher Comment</div>
		<div class="mt-2 fw-semibold"><?php echo htmlspecialchars($card['teacher_comment'] ?? $card['remark']); ?></div>
		<div class="label mt-3">Headteacher Remark</div>
		<div class="mt-2"><?php echo htmlspecialchars($card['headteacher_comment'] ?? $card['remark']); ?></div>
		<div class="signature-box">Signature</div>
	</div>
	<div class="metric-card">
		<div class="label">AI Performance Summary</div>
		<div class="mt-2"><?php echo htmlspecialchars($card['ai_summary'] ?? ''); ?></div>
		<div class="label mt-3">School Verification</div>
		<div class="mt-2">Use code <strong><?php echo htmlspecialchars($card['verification_code']); ?></strong> to verify this report online.</div>
		<div class="mt-3 small text-muted"><?php echo htmlspecialchars(WBAddress); ?><br><?php echo htmlspecialchars(WBEmail); ?></div>
	</div>
</div>
</div>
<?php endif; ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
