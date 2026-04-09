<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
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

	$stmt = $conn->prepare("SELECT * FROM tbl_announcements WHERE level = '0' OR level = '2' ORDER BY id DESC LIMIT 5");
	$stmt->execute();
	$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Teacher Analytics</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<script src="cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
<style>
body.app{background:#f4f7fb}
.portal-shell{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
.portal-side{background:linear-gradient(180deg,#113b5c,#0d64b0 70%,#27ae60);color:#fff;padding:18px 14px;position:sticky;top:0;height:100vh}
.portal-brand{display:flex;gap:10px;align-items:center;padding:8px 10px 18px;border-bottom:1px solid rgba(255,255,255,.15);margin-bottom:14px}
.portal-mark{width:38px;height:38px;border-radius:12px;background:#fff;color:#0d64b0;display:flex;align-items:center;justify-content:center;font-weight:800}
.portal-menu{display:grid;gap:5px}
.portal-menu a{display:flex;gap:10px;align-items:center;color:#fff;text-decoration:none;padding:10px 12px;border-radius:12px;font-size:.92rem}
.portal-menu a.active,.portal-menu a:hover{background:rgba(255,255,255,.14)}
.portal-main{padding-bottom:28px}
.portal-top{background:#fff;border-bottom:1px solid #e8eef5;padding:12px 24px;display:flex;justify-content:space-between;align-items:center}
.portal-content{padding:20px 24px}
.hero{background:linear-gradient(135deg,#0d64b0,#1d8fb9 60%,#2db763);border-radius:20px;color:#fff;padding:24px;box-shadow:0 20px 50px rgba(13,100,176,.16);margin-bottom:18px}
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
.grade-badge{padding:4px 10px;border-radius:999px;background:#eef7ee;color:#2f9b40;font-weight:700;font-size:.82rem}
.note-list{display:grid;gap:10px}
.note-item{background:#fff;border:1px solid #e9eef5;border-radius:14px;padding:12px 14px}
@media (max-width:1100px){.portal-shell{grid-template-columns:1fr}.portal-side{position:relative;height:auto}.hero-controls,.stats-grid,.grid-two{grid-template-columns:1fr 1fr}}
</style>
</head>
<body class="app">
<div class="portal-shell">
	<aside class="portal-side">
		<div class="portal-brand"><div class="portal-mark">T</div><div><div class="fw-bold">Elimu Hub</div><div class="small opacity-75">Teacher Portal</div></div></div>
		<nav class="portal-menu">
			<a class="active" href="teacher"><i class="bi bi-grid"></i><span>Dashboard</span></a>
			<a href="teacher/exam_marks_entry"><i class="bi bi-pencil-square"></i><span>Exam Marks Entry</span></a>
			<a href="teacher/marks_entry"><i class="bi bi-journal-check"></i><span>CBC Marks Entry</span></a>
			<a href="teacher/manage_results"><i class="bi bi-graph-up-arrow"></i><span>View Results</span></a>
			<a href="teacher/import_results"><i class="bi bi-upload"></i><span>Import Results</span></a>
			<a href="teacher/exam_timetable"><i class="bi bi-calendar3"></i><span>Exam Timetable</span></a>
			<a href="teacher/elearning"><i class="bi bi-laptop"></i><span>E-Learning</span></a>
		</nav>
	</aside>
	<div class="portal-main">
		<div class="portal-top">
			<div class="fw-bold"><?php echo htmlspecialchars(WBName); ?></div>
			<div class="d-flex gap-3 align-items-center"><span class="small text-muted"><?php echo htmlspecialchars($fname.' '.$lname); ?></span><a href="logout" class="text-decoration-none"><i class="bi bi-box-arrow-right"></i></a></div>
		</div>
		<main class="portal-content">
			<?php if ($error !== '') { ?>
			<div class="panel"><div class="panel-body"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div></div>
			<?php } else { ?>
			<div class="hero">
				<div class="d-flex justify-content-between flex-wrap gap-3">
					<div>
						<div class="small text-uppercase opacity-75">Teacher Performance Analytics</div>
						<h2 class="mb-1">Track class or subject performance</h2>
						<div class="small">Choose the class, subject, and term you want to review. Exams stay visible and accessible from the left menu.</div>
					</div>
				</div>
				<form method="GET" action="teacher" class="hero-controls">
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

			<div class="stats-grid">
				<div class="stat-card"><div class="label">Subjects</div><div class="value"><?php echo (int)$summary['subjects']; ?></div></div>
				<div class="stat-card"><div class="label">Classes</div><div class="value"><?php echo (int)$summary['classes']; ?></div></div>
				<div class="stat-card"><div class="label">Students</div><div class="value"><?php echo (int)$summary['students']; ?></div></div>
				<div class="stat-card"><div class="label">Selected Avg</div><div class="value"><?php echo number_format((float)$summary['avg'],2); ?></div></div>
				<div class="stat-card"><div class="label">Best Score</div><div class="value"><?php echo number_format((float)$summary['best'],0); ?></div></div>
			</div>

			<div class="grid-two">
				<section class="panel">
					<div class="panel-body">
						<h3 class="mb-3">Class / Subject Performance</h3>
						<div id="teacherTrendChart" style="height:300px;"></div>
					</div>
				</section>
				<section class="panel">
					<div class="panel-body">
						<h3 class="mb-3">Current Selection Summary</h3>
						<div class="small text-muted mb-3">The selected class and subject drive the student list and chart below.</div>
						<div class="note-list">
							<div class="note-item"><strong>Class:</strong> <?php echo htmlspecialchars($classOptions[$selectedClass] ?? 'N/A'); ?></div>
							<div class="note-item"><strong>Subject:</strong> <?php echo htmlspecialchars($subjectOptions[$selectedSubject] ?? 'N/A'); ?></div>
							<div class="note-item"><strong>Term:</strong> <?php echo htmlspecialchars($termOptions[$selectedTerm] ?? 'N/A'); ?></div>
						</div>
					</div>
				</section>
			</div>

			<section class="panel mt-3">
				<div class="panel-body">
					<h3 class="mb-3">Student Performance List</h3>
					<div class="table-responsive">
						<table class="subject-table">
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
				</div>
			</section>

			<div class="grid-two mt-3">
				<section class="panel"><div class="panel-body"><h3 class="mb-3">Notifications</h3><div class="note-list"><?php if(!$notifications){ ?><div class="note-item text-muted">No notifications yet.</div><?php } foreach($notifications as $note){ ?><div class="note-item"><div class="fw-bold"><?php echo htmlspecialchars((string)$note['title']); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$note['message']); ?></div><div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$note['created_at']); ?></div></div><?php } ?></div></div></section>
				<section class="panel"><div class="panel-body"><h3 class="mb-3">Announcements</h3><div class="note-list"><?php if(!$announcements){ ?><div class="note-item text-muted">No announcements right now.</div><?php } foreach($announcements as $row){ ?><div class="note-item"><div class="fw-bold"><?php echo htmlspecialchars((string)$row[1]); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$row[2]); ?></div><div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$row[3]); ?></div></div><?php } ?></div></div></section>
			</div>
			<?php } ?>
		</main>
	</div>
</div>
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
		series:[{type:'line',smooth:true,data:teacherTrend.map(item=>item.mean),areaStyle:{color:'rgba(13,100,176,0.18)'},lineStyle:{color:'#27ae60',width:2},itemStyle:{color:'#27ae60'}}]
	});
}
</script>
</body>
</html>
