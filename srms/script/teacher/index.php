<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
if ($res == "1" && $level == "2") {}else{header("location:../"); exit;}

$notifications = [];
$announcements = [];
$assignments = [];
$classOptions = [];
$subjectOptions = [];
$termOptions = [];
$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$selectedTerm = (int)($_GET['term_id'] ?? 0);
$summary = ['subjects' => 0, 'classes' => 0, 'students' => 0, 'avg' => 0, 'best' => 0];
$rows = [];
$trendPoints = [];
$recentDiscipline = [];
$roleNames = [];
$visibleModules = [];
$allocatedModules = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$year = (int)date('Y');

	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT ta.class_id, ta.subject_id, ta.term_id,
			c.name AS class_name, s.name AS subject_name, t.name AS term_name
			FROM tbl_teacher_assignments ta
			LEFT JOIN tbl_classes c ON c.id = ta.class_id
			LEFT JOIN tbl_subjects s ON s.id = ta.subject_id
			LEFT JOIN tbl_terms t ON t.id = ta.term_id
			WHERE ta.teacher_id = ? AND ta.status = 1 AND ta.year = ?
			ORDER BY ta.class_id, ta.subject_id");
		$stmt->execute([(int)$account_id, $year]);
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$stmt = $conn->prepare("SELECT sc.subject AS subject_id, s.name AS subject_name, c.id AS class_id, c.name AS class_name
			FROM tbl_subject_combinations sc
			LEFT JOIN tbl_subjects s ON s.id = sc.subject
			LEFT JOIN tbl_classes c ON 1=1
			WHERE sc.teacher = ?");
		$stmt->execute([(int)$account_id]);
		$assignments = [];
	}

	$roleNames = app_staff_role_names($conn, (int)$account_id);
	$visibleModules = app_teacher_portal_visible_modules($conn, (string)$account_id, (string)$level);
	$allocatedModules = app_teacher_portal_allocated_modules($conn, (string)$account_id, (string)$level);

	foreach ($assignments as $assignment) {
		if (!empty($assignment['class_id'])) {
			$classOptions[(int)$assignment['class_id']] = (string)$assignment['class_name'];
		}
		if (!empty($assignment['subject_id'])) {
			$subjectOptions[(int)$assignment['subject_id']] = (string)$assignment['subject_name'];
		}
		if (!empty($assignment['term_id'])) {
			$termOptions[(int)$assignment['term_id']] = (string)$assignment['term_name'];
		}
	}

	$summary['subjects'] = count($subjectOptions);
	$summary['classes'] = count($classOptions);

	if (!empty($classOptions)) {
		$placeholders = implode(',', array_fill(0, count($classOptions), '?'));
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders)");
		$stmt->execute(array_keys($classOptions));
		$summary['students'] = (int)$stmt->fetchColumn();
	}

	if ($selectedClass < 1 && !empty($classOptions)) {
		$selectedClass = (int)array_key_first($classOptions);
	}
	if ($selectedSubject < 1 && !empty($subjectOptions)) {
		$selectedSubject = (int)array_key_first($subjectOptions);
	}
	if ($selectedTerm < 1 && !empty($termOptions)) {
		$selectedTerm = (int)array_key_first($termOptions);
	}

	if ($selectedClass > 0 && $selectedSubject > 0 && $selectedTerm > 0) {
		$stmt = $conn->prepare("SELECT sc.id FROM tbl_subject_combinations sc
			WHERE sc.teacher = ? AND sc.subject = ? LIMIT 1");
		$stmt->execute([(int)$account_id, $selectedSubject]);
		$combinationId = (int)$stmt->fetchColumn();
		if ($combinationId > 0) {
			$stmt = $conn->prepare("SELECT st.id AS student_id, st.school_id,
				concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
				COALESCE(er.score, 0) AS score
				FROM tbl_students st
				LEFT JOIN tbl_exam_results er
					ON er.student = st.id
					AND er.class = st.class
					AND er.term = ?
					AND er.subject_combination = ?
				WHERE st.class = ?
				ORDER BY student_name");
			$stmt->execute([$selectedTerm, $combinationId, $selectedClass]);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
				list($grade,) = report_grade_for_score($conn, (float)$row['score']);
				$rows[] = [
					'student_id' => (string)$row['student_id'],
					'school_id' => (string)($row['school_id'] ?? ''),
					'student_name' => (string)$row['student_name'],
					'score' => (float)$row['score'],
					'grade' => $grade
				];
			}
			if ($rows) {
				$scores = array_column($rows, 'score');
				$summary['avg'] = round(array_sum($scores) / count($scores), 2);
				$summary['best'] = max($scores);
			}
		}

		$stmt = $conn->prepare("SELECT t.id, t.name
			FROM tbl_terms t
			WHERE t.id <= ?
			ORDER BY t.id ASC");
		$stmt->execute([$selectedTerm]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $term) {
			$stmt2 = $conn->prepare("SELECT AVG(er.score)
				FROM tbl_exam_results er
				JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination
				WHERE er.class = ? AND er.term = ? AND sc.teacher = ? AND sc.subject = ?");
			$stmt2->execute([$selectedClass, (int)$term['id'], (int)$account_id, $selectedSubject]);
			$trendPoints[] = [
				'term_name' => (string)$term['name'],
				'mean' => round((float)$stmt2->fetchColumn(), 2)
			];
		}
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
			WHERE audience IN ('all','staff')
			ORDER BY created_at DESC LIMIT 5");
		$stmt->execute();
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_discipline_cases')) {
		app_ensure_discipline_cases_table($conn);
		$stmt = $conn->prepare("SELECT d.created_at, d.incident_type, d.severity, d.status,
			concat_ws(' ', st.fname, st.mname, st.lname) AS student_name
			FROM tbl_discipline_cases d
			JOIN tbl_students st ON st.id = d.student_id
			WHERE d.teacher_id = ?
			ORDER BY d.id DESC
			LIMIT 5");
		$stmt->execute([(int)$account_id]);
		$recentDiscipline = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_announcements')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE level = '0' OR level = '2' ORDER BY id DESC LIMIT 5");
		$stmt->execute();
		$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Teacher Dashboard</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<style>
body.app{background:#f4f7f6}
.portal-shell{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
.portal-side{background:#fff;color:#263238;padding:18px 14px;position:sticky;top:0;height:100vh;border-right:1px solid #e3ebe8}
.portal-brand{display:flex;gap:10px;align-items:center;padding:8px 10px 18px;border-bottom:1px solid #e7efec;margin-bottom:14px}
.portal-mark{width:38px;height:38px;border-radius:12px;background:#e7f1ef;color:#00695C;display:flex;align-items:center;justify-content:center;font-weight:800}
.portal-menu{display:grid;gap:5px}
.portal-menu a{display:flex;gap:10px;align-items:center;color:#4a5a68;text-decoration:none;padding:10px 12px;border-radius:12px;font-size:.92rem}
.portal-menu a.active,.portal-menu a:hover{background:#e7f1ef;color:#00695C;font-weight:700}
.portal-main{padding-bottom:28px}
.portal-top{background:#fff;border-bottom:1px solid #e8eef5;padding:12px 24px;display:flex;justify-content:space-between;align-items:center}
.portal-content{padding:20px 24px}
.hero{background:linear-gradient(135deg,#00695C,#0b7d6d);border-radius:20px;color:#fff;padding:24px;box-shadow:0 20px 50px rgba(0,105,92,.16);margin-bottom:18px}
.hero-controls{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:16px}
.glass-input{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);color:#fff;border-radius:12px;padding:10px 12px}
.stats-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}
.stat-card,.panel{background:#fff;border:1px solid #e7edf5;border-radius:18px;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.stat-card{padding:16px}
.stat-card .label{font-size:.72rem;text-transform:uppercase;color:#6f7e8f}
.stat-card .value{font-size:1.45rem;font-weight:800;color:#1f2d3d}
.grid-two{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
.panel-body{padding:18px}
.subject-table{width:100%;border-collapse:collapse}
.subject-table th,.subject-table td{padding:12px 10px;border-bottom:1px solid #edf2f7}
.subject-table th{font-size:.76rem;text-transform:uppercase;color:#718096}
.grade-badge{padding:4px 10px;border-radius:999px;background:#e7f1ef;color:#00695C;font-weight:700;font-size:.82rem}
.note-list{display:grid;gap:10px}
.note-item{background:#fff;border:1px solid #e9eef5;border-radius:14px;padding:12px 14px}
.dashboard-hero{background:linear-gradient(135deg,#00695C,#0b7d6d);border-radius:22px;color:#fff;padding:24px;box-shadow:0 20px 50px rgba(0,105,92,.16);margin-bottom:18px}
.hero-kicker{display:inline-block;font-size:.72rem;text-transform:uppercase;letter-spacing:.1em;font-weight:800;opacity:.82;margin-bottom:8px}
.hero-main h2{font-weight:900;letter-spacing:-.02em}
.hero-main p{max-width:72ch;opacity:.93;line-height:1.6;margin:0}
.hero-actions{margin-top:18px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.hero-actions .btn,.hero-actions .glass-input{min-height:44px;border-radius:12px;font-weight:700}
.dashboard-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}
.dashboard-grid{display:grid;grid-template-columns:1fr;gap:16px}
.dashboard-grid .tile{border-radius:18px;border:1px solid #e7edf5;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.chart-lg{height:320px}
.access-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:14px}
.access-card{background:#fff;border:1px solid #e7edf5;border-radius:18px;padding:16px;box-shadow:0 14px 40px rgba(15,95,168,.08)}
.access-card.roles{grid-column:span 4}
.access-card.modules{grid-column:span 8}
.chip-wrap{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.access-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 10px;border-radius:999px;font-size:.82rem;font-weight:700}
.access-chip{background:#e7f1ef;color:#00695C}
.module-list{display:grid;gap:10px;margin-top:12px}
.module-link{display:flex;gap:12px;align-items:flex-start;padding:14px 15px;border:1px solid #e7edf5;border-radius:18px;text-decoration:none;color:#203040;background:linear-gradient(180deg,#ffffff,#f8fbff);box-shadow:0 8px 18px rgba(16,41,38,.04);transition:transform .18s ease,border-color .18s ease,box-shadow .18s ease}
.module-link:hover{border-color:#0b7d6d;background:linear-gradient(180deg,#ffffff,#eefaf7);box-shadow:0 14px 26px rgba(0,105,92,.10);transform:translateY(-1px)}
.module-icon{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:#e7f1ef;color:#00695C;flex:0 0 auto}
.module-title{font-weight:800;color:#123;line-height:1.2}
.module-desc{font-size:.84rem;color:#6f7e8f;margin-top:2px}
.module-cta{margin-left:auto;align-self:center;font-size:.75rem;font-weight:800;color:#0b7d6d;background:#e7f1ef;border-radius:999px;padding:7px 10px;white-space:nowrap}
@media (max-width:1100px){.portal-shell{grid-template-columns:1fr}.portal-side{position:relative;height:auto}.hero-controls,.stats-grid,.grid-two{grid-template-columns:1fr 1fr}}

@media (max-width: 1200px){
	.hero-actions{grid-template-columns:repeat(2,minmax(0,1fr))}
	.dashboard-stats{grid-template-columns:repeat(3,minmax(0,1fr))}
}

@media (max-width: 760px){
	.hero-actions{grid-template-columns:1fr}
	.dashboard-stats{grid-template-columns:1fr 1fr}
	.grid-two{grid-template-columns:1fr}
	.chart-lg{height:260px}
}

@media (max-width: 520px){
	.dashboard-stats{grid-template-columns:1fr}
}
	</style>
	</head>
	<body class="app sidebar-mini">
	<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
	<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
	<ul class="app-nav">
	<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
	<ul class="dropdown-menu settings-menu dropdown-menu-right">
	<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
	<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
	</ul>
	</li>
	</ul>
	</header>

	<?php include('teacher/partials/sidebar.php'); ?>
	<main class="app-content dashboard">
			<?php if ($error !== '') { ?>
			<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
			<?php } else { ?>
			<div class="dashboard-hero">
				<div class="d-flex justify-content-between flex-wrap gap-3">
					<div class="hero-main">
						<span class="hero-kicker">Teacher Dashboard</span>
						<h2 class="mb-1">Track class or subject performance</h2>
						<p>Choose the class, subject, and term you want to review. Exams stay visible and accessible from the left menu.</p>
					</div>
				</div>
				<form method="GET" action="teacher" class="hero-actions">
					<select class="glass-input" name="class_id" onchange="this.form.submit()">
						<?php foreach ($classOptions as $id => $name): ?><option value="<?php echo (int)$id; ?>" <?php echo $selectedClass===$id?'selected':''; ?>><?php echo htmlspecialchars($name); ?></option><?php endforeach; ?>
					</select>
					<select class="glass-input" name="subject_id" onchange="this.form.submit()">
						<?php foreach ($subjectOptions as $id => $name): ?><option value="<?php echo (int)$id; ?>" <?php echo $selectedSubject===$id?'selected':''; ?>><?php echo htmlspecialchars($name); ?></option><?php endforeach; ?>
					</select>
					<select class="glass-input" name="term_id" onchange="this.form.submit()">
						<?php foreach ($termOptions as $id => $name): ?><option value="<?php echo (int)$id; ?>" <?php echo $selectedTerm===$id?'selected':''; ?>><?php echo htmlspecialchars($name); ?></option><?php endforeach; ?>
					</select>
					<a class="btn btn-light" href="teacher/exam_marks_entry">Open Exams</a>
				</form>
			</div>

			<div class="dashboard-stats">
				<div class="stat-card"><div class="label">Classes</div><div class="value"><?php echo (int)$summary['classes']; ?></div></div>
				<div class="stat-card"><div class="label">Subjects</div><div class="value"><?php echo (int)$summary['subjects']; ?></div></div>
				<div class="stat-card"><div class="label">Students</div><div class="value"><?php echo (int)$summary['students']; ?></div></div>
				<div class="stat-card"><div class="label">Selected Avg</div><div class="value"><?php echo number_format((float)$summary['avg'],2); ?></div></div>
				<div class="stat-card"><div class="label">Best Score</div><div class="value"><?php echo number_format((float)$summary['best'],0); ?></div></div>
			</div>

			<div class="access-grid mb-3">
				<div class="access-card roles">
					<div class="tile-title mb-2">Assigned Roles</div>
					<div class="small text-muted">These are the staff roles currently attached to your account.</div>
					<div class="chip-wrap">
						<?php if (!empty($roleNames)): ?>
							<?php foreach ($roleNames as $roleName): ?>
								<span class="access-chip"><?php echo htmlspecialchars($roleName); ?></span>
							<?php endforeach; ?>
						<?php else: ?>
							<span class="access-chip">Teacher</span>
						<?php endif; ?>
					</div>
				</div>
				<div class="access-card modules">
					<div class="tile-title mb-2">Allocated Modules</div>
					<div class="small text-muted">Modules unlocked by the current permission set.</div>
					<div class="module-list">
						<?php if (!empty($allocatedModules)): ?>
							<?php foreach ($allocatedModules as $module): ?>
								<a class="module-link" href="<?php echo htmlspecialchars((string)$module['href']); ?>">
									<div class="module-icon"><i class="<?php echo htmlspecialchars((string)$module['icon']); ?>"></i></div>
									<div>
										<div class="module-title"><?php echo htmlspecialchars((string)$module['label']); ?></div>
										<div class="module-desc"><?php echo htmlspecialchars((string)$module['description']); ?></div>
									</div>
									<span class="module-cta">Open</span>
								</a>
							<?php endforeach; ?>
						<?php else: ?>
							<div class="text-muted">No additional allocated modules found yet.</div>
						<?php endif; ?>
					</div>
				</div>
			</div>

			<div class="dashboard-grid">
				<section class="tile">
					<h3 class="tile-title">Class / Subject Performance</h3>
					<div class="chart-lg" id="teacherTrendChart"></div>
				</section>
				<section class="tile">
					<h3 class="tile-title">Current Selection Summary</h3>
					<div class="small text-muted mb-3">The selected class and subject drive the student list and chart below.</div>
					<div class="note-list">
						<div class="note-item"><strong>Class:</strong> <?php echo htmlspecialchars($classOptions[$selectedClass] ?? 'N/A'); ?></div>
						<div class="note-item"><strong>Subject:</strong> <?php echo htmlspecialchars($subjectOptions[$selectedSubject] ?? 'N/A'); ?></div>
						<div class="note-item"><strong>Term:</strong> <?php echo htmlspecialchars($termOptions[$selectedTerm] ?? 'N/A'); ?></div>
					</div>
				</section>
				<section class="tile">
					<h3 class="tile-title">Student Performance List</h3>
					<div class="table-responsive">
						<table class="table table-hover table-striped">
							<thead><tr><th>Student</th><th>School ID</th><th>Score</th><th>Grade</th></tr></thead>
							<tbody>
							<?php if (!$rows) { ?><tr><td colspan="4" class="text-muted">No performance data yet for the selected class/subject.</td></tr><?php } ?>
							<?php foreach ($rows as $row): ?>
							<tr>
								<td><?php echo htmlspecialchars($row['student_name']); ?></td>
								<td><?php echo htmlspecialchars($row['school_id'] ?: $row['student_id']); ?></td>
								<td><?php echo number_format((float)$row['score'],1); ?></td>
								<td><span class="grade-badge"><?php echo htmlspecialchars($row['grade']); ?></span></td>
							</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<div class="grid-two">
					<section class="tile"><h3 class="tile-title">Notifications</h3><div class="note-list"><?php if(!$notifications){ ?><div class="note-item text-muted">No notifications yet.</div><?php } foreach($notifications as $note){ ?><div class="note-item"><div class="fw-bold"><?php echo htmlspecialchars((string)$note['title']); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$note['message']); ?></div><div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$note['created_at']); ?></div></div><?php } ?></div></section>
					<section class="tile"><h3 class="tile-title">Announcements</h3><div class="note-list"><?php if(!$announcements){ ?><div class="note-item text-muted">No announcements right now.</div><?php } foreach($announcements as $row){ ?><div class="note-item"><div class="fw-bold"><?php echo htmlspecialchars((string)$row[1]); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$row[2]); ?></div><div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$row[3]); ?></div></div><?php } ?></div></section>
				</div>
					<section class="tile mt-3">
						<h3 class="tile-title">Recent Discipline Cases</h3>
						<div class="small text-muted mb-2">Auto refresh every 5 seconds. Open Teacher -> Discipline to submit or view all.</div>
						<div class="table-responsive">
							<table class="table table-hover table-striped">
								<thead><tr><th>Date</th><th>Student</th><th>Type</th><th>Severity</th><th>Status</th></tr></thead>
								<tbody>
								<?php if (!$recentDiscipline) { ?><tr><td colspan="5" class="text-muted">No discipline incidents yet.</td></tr><?php } ?>
								<?php foreach ($recentDiscipline as $dc): ?>
								<tr>
									<td><?php echo htmlspecialchars((string)$dc['created_at']); ?></td>
									<td><?php echo htmlspecialchars((string)$dc['student_name']); ?></td>
									<td><?php echo htmlspecialchars((string)$dc['incident_type']); ?></td>
									<td><?php echo htmlspecialchars(ucfirst((string)$dc['severity'])); ?></td>
									<td><?php echo htmlspecialchars((string)$dc['status']); ?></td>
								</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<a class="btn btn-outline-primary btn-sm" href="teacher/discipline">Open Discipline Module</a>
					</section>
			<?php } ?>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const teacherTrend = <?php echo json_encode($trendPoints); ?>;
const teacherTrendEl = document.getElementById('teacherTrendChart');
if (teacherTrendEl) {
	const chart = echarts.init(teacherTrendEl);
	chart.setOption({
		grid:{left:40,right:16,top:20,bottom:40},
		tooltip:{trigger:'axis'},
		xAxis:{type:'category',data:teacherTrend.map(item=>item.term_name),axisLabel:{fontSize:10}},
		yAxis:{type:'value',min:0,max:100},
		series:[{type:'line',smooth:true,data:teacherTrend.map(item=>item.mean),areaStyle:{color:'rgba(0,105,92,0.16)'},lineStyle:{color:'#00695C',width:2},itemStyle:{color:'#00695C'}}]
	});
}

let pauseRefresh = false;
document.addEventListener('focusin', function() { pauseRefresh = true; });
document.addEventListener('focusout', function() { pauseRefresh = false; });
</script>
</body>
</html>
