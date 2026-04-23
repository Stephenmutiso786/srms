<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/id_card_engine.php');
if ($res == "1" && $level == "3") {}else{header("location:../"); exit;}

$notifications = [];
$announcements = [];
$studentClassId = 0;
$studentName = trim($fname . ' ' . $lname);
$schoolId = '';
$photoPath = '';
$photoExists = false;
$publishedTerms = [];
$selectedTermId = (int)($_GET['term'] ?? 0);
$selectedTermName = '';
$summary = [
	'attendance_rate' => 0,
	'avg_score' => 0,
	'fees_balance' => 0,
	'subjects' => 0,
	'position' => '-',
	'total_marks' => 0,
	'grade' => 'N/A'
];
$subjectRows = [];
$history = [];
$disciplineCases = [];
$reportCard = null;
$visibleModules = [];
$allocatedModules = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_cases_table($conn);

	$stmt = $conn->prepare("SELECT class, school_id FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$account_id]);
	$studentRow = $stmt->fetch(PDO::FETCH_ASSOC);
	$studentClassId = (int)($studentRow['class'] ?? 0);
	$schoolId = (string)($studentRow['school_id'] ?? '');

	$payload = idcard_student_payload($conn, (string)$account_id);
	if ($payload) {
		$photoPath = (string)$payload['photo_path'];
		$photoExists = (bool)$payload['photo_exists'];
	}

	$stmt = $conn->prepare("SELECT t.id, t.name
		FROM tbl_terms t
		WHERE EXISTS (
			SELECT 1 FROM tbl_exams e
			WHERE e.term_id = t.id AND e.class_id = ? AND e.status = 'published'
		)
		ORDER BY t.id DESC");
	$stmt->execute([$studentClassId]);
	$publishedTerms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if ($selectedTermId < 1 && !empty($publishedTerms)) {
		$selectedTermId = (int)$publishedTerms[0]['id'];
	}

	foreach ($publishedTerms as $term) {
		if ((int)$term['id'] === $selectedTermId) {
			$selectedTermName = (string)$term['name'];
			break;
		}
	}

	if (app_table_exists($conn, 'tbl_attendance_sessions') && app_table_exists($conn, 'tbl_attendance_records')) {
		$driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
		$dateExpr = $driver === 'mysql' ? "DATE_SUB(CURDATE(), INTERVAL 30 DAY)" : "CURRENT_DATE - INTERVAL '30 days'";
		$stmt = $conn->prepare("SELECT SUM(CASE WHEN r.status = 'present' THEN 1 ELSE 0 END) AS present_count,
			COUNT(*) AS total_count
			FROM tbl_attendance_records r
			JOIN tbl_attendance_sessions s ON s.id = r.session_id
			WHERE r.student_id = ? AND s.session_date >= $dateExpr");
		$stmt->execute([$account_id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($row && (int)$row['total_count'] > 0) {
			$summary['attendance_rate'] = ((int)$row['present_count'] / (int)$row['total_count']) * 100;
		}
	}

	if (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines')) {
		if (app_table_exists($conn, 'tbl_payments')) {
			$stmt = $conn->prepare("
				SELECT COALESCE(SUM(lines.total_amount - COALESCE(paid.total_paid, 0)), 0) AS outstanding
				FROM (
					SELECT i.id, SUM(l.amount) AS total_amount
					FROM tbl_invoices i
					INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
					WHERE i.student_id = ? AND i.status <> 'void'
					GROUP BY i.id
				) lines
				LEFT JOIN (
					SELECT invoice_id, SUM(amount) AS total_paid
					FROM tbl_payments
					GROUP BY invoice_id
				) paid ON paid.invoice_id = lines.id
			");
			$stmt->execute([$account_id]);
			$summary['fees_balance'] = (float)$stmt->fetchColumn();
		}
	}

	if ($studentClassId > 0 && app_table_exists($conn, 'tbl_subject_combinations')) {
		if (app_table_exists($conn, 'tbl_subject_class_assignments')) {
			$summary['subjects'] = count(app_class_subject_ids($conn, $studentClassId));
		} else {
			$stmt = $conn->prepare("SELECT subject, class FROM tbl_subject_combinations");
			$stmt->execute();
			$seenSubjects = [];
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				$classList = app_unserialize($row['class']);
				if (in_array((string)$studentClassId, array_map('strval', $classList), true)) {
					$seenSubjects[(int)$row['subject']] = true;
				}
			}
			$summary['subjects'] = count($seenSubjects);
		}
	}

	if ($selectedTermId > 0 && report_term_is_published($conn, $studentClassId, $selectedTermId)) {
		$reportCard = report_ensure_card_generated($conn, (string)$account_id, $studentClassId, $selectedTermId);
		if ($reportCard) {
			$summary['avg_score'] = (float)($reportCard['mean'] ?? 0);
			$summary['grade'] = (string)($reportCard['grade'] ?? 'N/A');
			$summary['position'] = isset($reportCard['position'], $reportCard['total_students']) ? ($reportCard['position'].' / '.$reportCard['total_students']) : '-';
			$summary['total_marks'] = (float)($reportCard['total'] ?? 0);
		}
		$subjectRows = report_subject_breakdown($conn, (string)$account_id, $studentClassId, $selectedTermId);
		$history = report_student_term_history($conn, (string)$account_id, $studentClassId, 12);
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
			WHERE audience IN ('all','students') OR (audience = 'class' AND class_id = ?)
			ORDER BY created_at DESC LIMIT 5");
		$stmt->execute([$studentClassId]);
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

		if (app_table_exists($conn, 'tbl_discipline_cases')) {
			$stmt = $conn->prepare("SELECT incident_type, description, severity, status, created_at
					FROM tbl_discipline_cases
					WHERE student_id = ?
					ORDER BY id DESC
					LIMIT 10");
			$stmt->execute([(string)$account_id]);
			$disciplineCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		if (app_table_exists($conn, 'tbl_announcements')) {
			$stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE level = '1' OR level = '2' ORDER BY id DESC LIMIT 5");
			$stmt->execute();
			$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		$visibleModules = app_current_user_visible_portal_modules('student');
		$allocatedModules = app_current_user_allocated_portal_modules('student');
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Student Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<style>
:root{--student-primary:#00695C;--student-primary-deep:#00544a;--student-primary-soft:#e7f1ef;--student-bg:linear-gradient(180deg,#eef5f3 0%,#f4f7f6 40%,#eef3f1 100%);--student-card:#ffffff;--student-text:#263238;--student-muted:#6b7c93;--student-accent:#f0b24a}
body.app{background:var(--student-bg)}
.portal-content{padding:20px clamp(16px,2vw,28px);max-width:1520px;margin:0 auto}
.hero-banner{position:relative;overflow:hidden;background:linear-gradient(135deg,#054d46 0%,#0b7d6d 55%,#0d8aa7 100%);border-radius:26px;padding:26px 28px;color:#fff;box-shadow:0 24px 55px rgba(0,105,92,.2);margin-bottom:18px;display:grid;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);gap:20px;align-items:stretch}
.hero-banner:before,.hero-banner:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.12);pointer-events:none}
.hero-banner:before{width:220px;height:220px;right:-70px;top:-80px}
.hero-banner:after{width:160px;height:160px;right:110px;bottom:-80px}
.hero-copy{position:relative;z-index:1}
.hero-copy .small{max-width:62ch;line-height:1.65}
.hero-summary{position:relative;z-index:1;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-content:start}
.hero-summary-card{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16);border-radius:18px;padding:14px 15px;backdrop-filter:blur(10px)}
.hero-summary-card .label{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;opacity:.8}
.hero-summary-card .value{font-size:1.35rem;font-weight:800;margin-top:4px}
.profile-card{background:#fff;border:1px solid #e5edf5;border-radius:22px;padding:18px 20px;margin-bottom:18px;box-shadow:0 12px 32px rgba(16,41,38,.06)}
.profile-head{display:flex;align-items:center;gap:14px}
.student-avatar{width:52px;height:52px;border-radius:50%;background:var(--student-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;overflow:hidden}
.student-avatar img{width:100%;height:100%;object-fit:cover}
.section-title{font-size:.8rem;font-weight:800;color:#556270;text-transform:uppercase;margin-bottom:10px}
.analytics-layout{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,.88fr);gap:18px}
.analytics-panel{background:#fff;border:1px solid #e6edf5;border-radius:22px;overflow:hidden;box-shadow:0 12px 32px rgba(16,41,38,.06)}
.analytics-panel .panel-body{padding:18px}
.mean-ribbon{background:linear-gradient(90deg,var(--student-primary),#0b7d6d);border-radius:12px;padding:10px 14px;color:#fff;font-weight:700;display:flex;justify-content:space-between;align-items:center;box-shadow:0 12px 22px rgba(0,105,92,.14)}
.insight-switches{display:flex;gap:10px;margin:14px 0;flex-wrap:wrap}
.insight-switch{border:1px solid #d3e5e0;background:#fff;color:var(--student-primary-deep);border-radius:999px;padding:7px 12px;font-size:.8rem;font-weight:700;box-shadow:0 6px 16px rgba(16,41,38,.04)}
.metric-boxes{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px}
.metric-box{border:1px solid #e8edf3;border-radius:18px;padding:14px;background:#fff;box-shadow:0 8px 18px rgba(16,41,38,.05);position:relative;overflow:hidden}
.metric-box:before{content:"";position:absolute;inset:auto -18px -18px auto;width:54px;height:54px;border-radius:50%;background:rgba(0,105,92,.08)}
.metric-box .label{font-size:.72rem;color:#7a8796;text-transform:uppercase}
.metric-box .value{font-size:1.1rem;font-weight:800;color:#1f2d3d}
.subject-table{width:100%;border-collapse:collapse;margin-top:12px}
.subject-table th,.subject-table td{padding:10px 8px;border-bottom:1px solid #eef2f6;font-size:.9rem}
.subject-table th{font-size:.75rem;text-transform:uppercase;color:#718096}
.subject-table .subject-name{font-weight:600}
.perf-track{height:10px;border-radius:999px;background:#edf1f4;min-width:110px;overflow:hidden}
.perf-track span{display:block;height:100%;background:linear-gradient(90deg,#24a07d,var(--student-primary))}
.trend-up{color:#27ae60;font-weight:800}.trend-down{color:#f0a120;font-weight:800}.trend-steady{color:#8492a6;font-weight:800}
.bottom-panel{margin-top:18px}
.note-list{display:grid;gap:10px}
.note-item{background:#fff;border:1px solid #e8eef4;border-radius:16px;padding:12px 14px;box-shadow:0 8px 18px rgba(16,41,38,.04)}
.empty-card{background:linear-gradient(180deg,#fff,#f8fbfa);border:1px dashed #cfe0da;border-radius:16px;padding:14px 16px;color:#667788}
.module-launch-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:18px}
.module-launch-tile{display:flex;flex-direction:column;gap:10px;padding:16px 18px;border:1px solid #dfe9e5;border-radius:20px;background:linear-gradient(180deg,#ffffff,#f7fbfa);box-shadow:0 10px 24px rgba(16,41,38,.05);text-decoration:none;color:#21303a;transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.module-launch-tile:hover{transform:translateY(-1px);border-color:#9ecdc3;box-shadow:0 16px 30px rgba(0,105,92,.10)}
.module-launch-top{display:flex;align-items:flex-start;gap:12px}
.module-launch-icon{width:42px;height:42px;border-radius:14px;background:#e7f1ef;color:#00695C;display:flex;align-items:center;justify-content:center;flex:0 0 auto}
.module-launch-title{font-weight:800;line-height:1.2}
.module-launch-desc{font-size:.84rem;color:#6f7e8f;line-height:1.5}
.module-launch-cta{align-self:flex-start;margin-top:auto;font-size:.75rem;font-weight:800;color:#00695C;background:#e7f1ef;border-radius:999px;padding:7px 10px}
.module-launch-empty{background:#fff;border:1px dashed #cfe0da;border-radius:18px;padding:14px 16px;color:#667788}
@media (max-width: 1100px){.analytics-layout{grid-template-columns:1fr}}
@media (max-width: 768px){.portal-content{padding:16px}.profile-head{align-items:flex-start}.mean-ribbon{flex-direction:column;align-items:flex-start;gap:6px}}
@media (max-width: 991px){.hero-banner{grid-template-columns:1fr}.hero-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width: 600px){.hero-summary{grid-template-columns:1fr}}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/settings"><i class="bi bi-person me-2 fs-5"></i> Change Password</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("student/partials/sidebar.php"); ?>

<main class="app-content">
	<div class="app-title">
		<div>
			<h1>Student Dashboard</h1>
			<p><?php echo htmlspecialchars($studentName); ?><?php echo !empty($act_class) ? ' - '.htmlspecialchars((string)$act_class) : ''; ?></p>
		</div>
	</div>

	<div class="portal-content">
		<div class="section-title">Quick Module Access</div>
		<div class="module-launch-grid">
			<?php if (!empty($allocatedModules)) { ?>
				<?php foreach ($allocatedModules as $module) { ?>
					<a class="module-launch-tile" href="<?php echo htmlspecialchars((string)$module['href']); ?>">
						<div class="module-launch-top">
							<div class="module-launch-icon"><i class="<?php echo htmlspecialchars((string)$module['icon']); ?>"></i></div>
							<div>
								<div class="module-launch-title"><?php echo htmlspecialchars((string)$module['label']); ?></div>
								<div class="module-launch-desc"><?php echo htmlspecialchars((string)$module['description']); ?></div>
							</div>
						</div>
						<span class="module-launch-cta">Open</span>
					</a>
				<?php } ?>
			<?php } else { ?>
				<div class="module-launch-empty">No additional modules are available yet.</div>
			<?php } ?>
		</div>

			<?php if ($error !== '') { ?>
			<div class="analytics-panel"><div class="panel-body"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div></div>
			<?php } else { ?>
			<div class="hero-banner">
				<div class="hero-copy">
					<div class="fw-bold mb-1">Academic Overview</div>
					<div class="small">Use published academic insights to follow performance, subject trends, and improvement over time.</div>
					<div class="insight-switches mt-3">
						<span class="insight-switch"><i class="bi bi-calendar2-event me-1"></i><?php echo htmlspecialchars($selectedTermName !== '' ? $selectedTermName : 'Latest published term'); ?></span>
						<span class="insight-switch"><i class="bi bi-person-badge me-1"></i><?php echo htmlspecialchars($studentClassId > 0 ? 'Class ' . (string)$studentClassId : 'Class not set'); ?></span>
					</div>
				</div>
				<div class="hero-summary">
					<div class="hero-summary-card"><div class="label">Attendance</div><div class="value"><?php echo number_format((float)$summary['attendance_rate'], 1); ?>%</div></div>
					<div class="hero-summary-card"><div class="label">Grade</div><div class="value"><?php echo htmlspecialchars($summary['grade']); ?></div></div>
					<div class="hero-summary-card"><div class="label">Total Marks</div><div class="value"><?php echo number_format((float)$summary['total_marks'], 0); ?></div></div>
					<div class="hero-summary-card"><div class="label">Fees Balance</div><div class="value">KES <?php echo number_format((float)$summary['fees_balance'], 0); ?></div></div>
				</div>
			</div>

			<div class="profile-card">
				<div class="profile-head">
					<div class="student-avatar">
						<?php if ($photoExists) { ?><img src="<?php echo htmlspecialchars($photoPath); ?>" alt="student photo"><?php } else { echo htmlspecialchars(strtoupper(substr($fname,0,1).substr($lname,0,1))); } ?>
					</div>
					<div>
						<div class="fw-bold"><?php echo htmlspecialchars($studentName); ?> - <?php echo htmlspecialchars($act_class ?? ''); ?></div>
						<div class="small text-muted">Admission Number: <?php echo htmlspecialchars($schoolId !== '' ? $schoolId : (string)$account_id); ?></div>
					</div>
				</div>
			</div>

			<div class="analytics-layout">
				<section class="analytics-panel">
					<div class="panel-body">
						<div class="section-title">Analysis</div>
						<div class="small text-muted mb-3">Student exam performance analytics</div>
						<form method="GET" action="student" class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-3">
							<div class="mean-ribbon flex-grow-1">
								<span>Mean Grade</span>
								<span><?php echo htmlspecialchars($summary['grade']); ?></span>
							</div>
							<div style="min-width:220px">
								<select class="form-control form-control-sm" name="term" onchange="this.form.submit()">
									<option value=""><?php echo $publishedTerms ? 'Select published term' : 'No published term'; ?></option>
									<?php foreach ($publishedTerms as $term): ?>
									<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $selectedTermId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($term['name']); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</form>

						<div class="insight-switches">
							<span class="insight-switch"><i class="bi bi-check-circle me-1"></i>Previous exam results</span>
							<span class="insight-switch"><i class="bi bi-bullseye me-1"></i>Student targets</span>
						</div>

						<div class="metric-boxes">
							<div class="metric-box"><div class="label">Total Marks</div><div class="value"><?php echo number_format((float)$summary['total_marks'], 0); ?></div></div>
							<div class="metric-box"><div class="label">Mean Points</div><div class="value"><?php echo number_format((float)$summary['avg_score'], 2); ?></div></div>
							<div class="metric-box"><div class="label">Fees Balance</div><div class="value">KES <?php echo number_format((float)$summary['fees_balance'], 2); ?></div></div>
							<div class="metric-box"><div class="label">Overall Position</div><div class="value"><?php echo htmlspecialchars((string)$summary['position']); ?></div></div>
							<div class="metric-box"><div class="label">Average Position</div><div class="value"><?php echo count($history) > 0 ? number_format(array_sum(array_column($history,'mean'))/count($history),2) : '0.00'; ?></div></div>
						</div>

						<table class="subject-table">
							<thead>
								<tr>
									<th>Name</th>
									<th>Points</th>
									<th>Dev Exam</th>
									<th>Dev Target</th>
									<th>Grade</th>
									<th>Class Rank</th>
								</tr>
							</thead>
							<tbody>
							<?php if (!$subjectRows) { ?>
								<tr><td colspan="6"><div class="empty-card">No published subject analytics yet. When the school releases results, each subject will show here with trend and class comparison.</div></td></tr>
							<?php } ?>
							<?php foreach ($subjectRows as $row): ?>
								<tr>
									<td class="subject-name"><?php echo htmlspecialchars($row['subject_name']); ?></td>
									<td><?php echo number_format((float)$row['score'], 0); ?></td>
									<td class="<?php echo $row['trend'] === 'up' ? 'trend-up' : ($row['trend'] === 'down' ? 'trend-down' : 'trend-steady'); ?>">
										<i class="bi <?php echo $row['trend'] === 'up' ? 'bi-arrow-up-right' : ($row['trend'] === 'down' ? 'bi-arrow-down-right' : 'bi-dash'); ?>"></i>
										<?php echo ($row['change'] >= 0 ? '+' : '') . number_format((float)$row['change'], 1); ?>
									</td>
									<td>
										<div class="perf-track"><span style="width: <?php echo (float)$row['progress']; ?>%"></span></div>
									</td>
									<td><?php echo htmlspecialchars($row['grade']); ?></td>
									<td><?php echo number_format((float)$row['class_mean'], 1); ?>/100</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>

				<section class="analytics-panel">
					<div class="panel-body">
						<div id="termTrendChart" style="height:320px;"></div>
					</div>
				</section>
			</div>

			<div class="analytics-panel bottom-panel">
				<div class="panel-body">
					<div class="section-title">Performance Over Time</div>
					<div id="historyChart" style="height:240px;"></div>
				</div>
			</div>

			<div class="analytics-layout mt-3">
				<section class="analytics-panel">
					<div class="panel-body">
						<div class="section-title">Notifications</div>
						<div class="note-list">
						<?php if (!$notifications) { ?><div class="note-item text-muted">No notifications yet.</div><?php } ?>
						<?php foreach ($notifications as $note): ?>
							<div class="note-item">
								<div class="fw-bold"><?php echo htmlspecialchars((string)$note['title']); ?></div>
								<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$note['message']); ?></div>
								<div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$note['created_at']); ?></div>
							</div>
						<?php endforeach; ?>
						</div>
					</div>
				</section>
				<section class="analytics-panel">
					<div class="panel-body">
						<div class="section-title">Announcements</div>
						<div class="note-list">
						<?php if (!$announcements) { ?><div class="note-item text-muted">No announcements at the moment.</div><?php } ?>
						<?php foreach ($announcements as $row): ?>
							<div class="note-item">
								<div class="fw-bold"><?php echo htmlspecialchars((string)$row[1]); ?></div>
								<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$row[2]); ?></div>
								<div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$row[3]); ?></div>
							</div>
						<?php endforeach; ?>
						</div>
					</div>
				</section>
			</div>

			<div class="analytics-panel bottom-panel mt-3">
				<div class="panel-body">
					<div class="section-title">Discipline Cases (Live)</div>
					<div class="small text-muted mb-2">Auto refresh every 5 seconds. Full list in Student -> Discipline.</div>
					<div class="table-responsive">
						<table class="table table-hover table-striped">
							<thead><tr><th>Date</th><th>Type</th><th>Severity</th><th>Status</th><th>Description</th></tr></thead>
							<tbody>
							<?php if (!$disciplineCases) { ?><tr><td colspan="5" class="text-muted">No discipline incidents yet.</td></tr><?php } ?>
							<?php foreach ($disciplineCases as $dc): ?>
							<tr>
								<td><?php echo htmlspecialchars((string)$dc['created_at']); ?></td>
								<td><?php echo htmlspecialchars((string)$dc['incident_type']); ?></td>
								<td><?php echo htmlspecialchars(ucfirst((string)$dc['severity'])); ?></td>
								<td><?php echo htmlspecialchars((string)$dc['status']); ?></td>
								<td><?php echo htmlspecialchars((string)$dc['description']); ?></td>
							</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<?php } ?>
	</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const subjectRows = <?php echo json_encode($subjectRows); ?>;
const historyRows = <?php echo json_encode($history); ?>;

const termTrendEl = document.getElementById('termTrendChart');
if (termTrendEl) {
	const chart = echarts.init(termTrendEl);
	chart.setOption({
		grid: {left: 40, right: 12, top: 20, bottom: 30},
		tooltip: {trigger: 'axis'},
		xAxis: {type: 'category', data: subjectRows.map(row => row.subject_name), axisLabel: {fontSize: 10}},
		yAxis: {type: 'value', min: 0, max: 100},
		series: [
			{
				type: 'line',
				smooth: true,
				data: subjectRows.map(row => row.class_mean),
				areaStyle: {color: 'rgba(67,186,78,0.18)'},
				lineStyle: {color: '#00695C', width: 2},
				itemStyle: {color: '#00695C'}
			}
		]
	});
}

const historyEl = document.getElementById('historyChart');
if (historyEl) {
	const chart = echarts.init(historyEl);
	chart.setOption({
		grid: {left: 40, right: 12, top: 20, bottom: 50},
		tooltip: {trigger: 'axis'},
		xAxis: {type: 'category', data: historyRows.map(row => row.term_name), axisLabel: {fontSize: 10, rotate: 25}},
		yAxis: {type: 'value', min: 0, max: 100},
		series: [
			{
				name: 'Mean Score',
				type: 'line',
				smooth: true,
				data: historyRows.map(row => row.mean),
				lineStyle: {color: '#00695C', width: 2},
				itemStyle: {color: '#00695C'}
			}
		]
	});
}

let pauseRefresh = false;
document.addEventListener('focusin', function() { pauseRefresh = true; });
document.addEventListener('focusout', function() { pauseRefresh = false; });
setInterval(function() {
	if (!pauseRefresh) {
		window.location.reload();
	}
}, 5000);
</script>
</body>
</html>
