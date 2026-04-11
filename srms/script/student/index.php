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
$reportCard = null;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

	$stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE level = '1' OR level = '2' ORDER BY id DESC LIMIT 5");
	$stmt->execute();
	$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
:root{--student-primary:#00695C;--student-primary-deep:#00544a;--student-primary-soft:#e7f1ef;--student-bg:#f4f7f6;--student-card:#ffffff;--student-text:#263238;--student-muted:#6b7c93}
body.app{background:var(--student-bg)}
.student-shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}
.student-side{background:#fff;color:var(--student-text);padding:18px 14px;position:sticky;top:0;height:100vh;border-right:1px solid #e3ebe8}
.student-brand{display:flex;align-items:center;gap:10px;padding:8px 10px 18px;border-bottom:1px solid #e7efec;margin-bottom:14px}
.student-brand-mark{width:36px;height:36px;border-radius:12px;background:var(--student-primary-soft);color:var(--student-primary);display:flex;align-items:center;justify-content:center;font-weight:800}
.student-brand-title{font-weight:800;line-height:1.1}
.student-brand-sub{font-size:.75rem;color:var(--student-muted)}
.student-menu{display:grid;gap:4px;margin-top:8px}
.student-menu a{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;color:#4a5a68;text-decoration:none;font-size:.92rem}
.student-menu a.active,.student-menu a:hover{background:var(--student-primary-soft);color:var(--student-primary);font-weight:700}
.student-main{padding:0 0 28px}
.student-topbar{background:#fff;border-bottom:1px solid #e7eef5;padding:12px 24px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.student-tabbar{background:#fdfefe;border-bottom:1px solid #e9eef5;padding:0 24px;display:flex;gap:18px}
.student-tabbar a{padding:11px 0;color:#607082;text-decoration:none;font-weight:700;font-size:.86rem;border-bottom:3px solid transparent}
.student-tabbar a.active{color:var(--student-primary-deep);border-bottom-color:var(--student-primary)}
.student-content{padding:20px 24px}
.hero-banner{background:linear-gradient(135deg,var(--student-primary),#0b7d6d);border-radius:16px;padding:16px 18px;color:#fff;box-shadow:0 16px 40px rgba(0,105,92,.16);margin-bottom:18px}
.profile-card{background:#fff;border:1px solid #e5edf5;border-radius:18px;padding:16px 18px;margin-bottom:18px}
.profile-head{display:flex;align-items:center;gap:14px}
.student-avatar{width:52px;height:52px;border-radius:50%;background:var(--student-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.1rem;overflow:hidden}
.student-avatar img{width:100%;height:100%;object-fit:cover}
.section-title{font-size:.8rem;font-weight:800;color:#556270;text-transform:uppercase;margin-bottom:10px}
.analytics-layout{display:grid;grid-template-columns:1.2fr .88fr;gap:18px}
.analytics-panel{background:#fff;border:1px solid #e6edf5;border-radius:18px;overflow:hidden}
.analytics-panel .panel-body{padding:16px}
.mean-ribbon{background:linear-gradient(90deg,var(--student-primary),#0b7d6d);border-radius:8px;padding:10px 14px;color:#fff;font-weight:700;display:flex;justify-content:space-between;align-items:center}
.insight-switches{display:flex;gap:10px;margin:14px 0;flex-wrap:wrap}
.insight-switch{border:1px solid #d3e5e0;background:#fff;color:var(--student-primary-deep);border-radius:8px;padding:6px 10px;font-size:.8rem;font-weight:700}
.metric-boxes{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.metric-box{border:1px solid #e8edf3;border-radius:10px;padding:12px;background:#fff}
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
.note-item{background:#fff;border:1px solid #e8eef4;border-radius:14px;padding:12px 14px}
@media (max-width: 1100px){.student-shell{grid-template-columns:1fr}.student-side{position:relative;height:auto}.analytics-layout{grid-template-columns:1fr}}
</style>
</head>
<body class="app">
<div class="student-shell">
	<aside class="student-side">
		<div class="student-brand">
			<div class="student-brand-mark">E</div>
			<div>
				<div class="student-brand-title">Elimu Hub</div>
				<div class="student-brand-sub">Student Portal</div>
			</div>
		</div>
		<nav class="student-menu">
			<a class="active" href="student"><i class="bi bi-grid"></i><span>Dashboard</span></a>
			<a href="student/results"><i class="bi bi-graph-up"></i><span>Analytics</span></a>
			<a href="student/report_card"><i class="bi bi-file-earmark-text"></i><span>Report Card</span></a>
			<a href="student/subjects"><i class="bi bi-journal-bookmark"></i><span>Subjects</span></a>
			<a href="student/attendance"><i class="bi bi-check2-square"></i><span>Attendance</span></a>
			<a href="student/elearning"><i class="bi bi-laptop"></i><span>Learning</span></a>
			<a href="student/exam_timetable"><i class="bi bi-calendar3"></i><span>Exam Timetable</span></a>
			<a href="student/view"><i class="bi bi-person-circle"></i><span>Profile</span></a>
		</nav>
	</aside>

	<div class="student-main">
		<div class="student-topbar">
			<div class="fw-bold"><?php echo htmlspecialchars(WBName); ?></div>
			<div class="d-flex align-items-center gap-3">
				<div class="small text-muted"><?php echo htmlspecialchars($studentName); ?></div>
				<a class="text-decoration-none" href="logout"><i class="bi bi-box-arrow-right"></i></a>
			</div>
		</div>
		<div class="student-tabbar">
			<a class="active" href="student">Overview</a>
			<a href="student/results">Performance</a>
			<a href="student/report_card">Report Card</a>
			<a href="student/elearning">E-Learning</a>
		</div>

		<main class="student-content">
			<?php if ($error !== '') { ?>
			<div class="analytics-panel"><div class="panel-body"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div></div>
			<?php } else { ?>
			<div class="hero-banner">
				<div class="fw-bold mb-1">Academic Overview</div>
				<div class="small">Use published academic insights to follow performance, subject trends, and improvement over time.</div>
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
								<tr><td colspan="6" class="text-muted">No published subject analytics yet.</td></tr>
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
			<?php } ?>
		</main>
	</div>
</div>

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
</script>
</body>
</html>
