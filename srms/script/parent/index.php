<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/id_card_engine.php');
if ($res == "1" && $level == "4") {}else{header("location:../"); exit;}

$students = [];
$notifications = [];
$classIds = [];
$selectedStudentId = (string)($_GET['student'] ?? '');
$selectedStudent = null;
$selectedTermId = (int)($_GET['term'] ?? 0);
$publishedTerms = [];
$subjectRows = [];
$history = [];
$summary = ['children' => 0, 'attendance_rate' => 0, 'avg_score' => 0, 'fees_balance' => 0, 'grade' => 'N/A', 'position' => '-'];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException("Parent module is not installed on the server.");
	}

	$stmt = $conn->prepare("SELECT st.id, st.school_id, st.class AS class_id,
		concat_ws(' ', st.fname, st.mname, st.lname) AS name, c.name AS class_name
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		LEFT JOIN tbl_classes c ON c.id = st.class
		WHERE ps.parent_id = ?
		ORDER BY st.id");
	$stmt->execute([(int)$account_id]);
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$summary['children'] = count($students);

	foreach ($students as $st) {
		if ($selectedStudentId === '' || $selectedStudentId === (string)$st['id']) {
			$selectedStudent = $selectedStudent ?: $st;
		}
		if (!empty($st['class_id'])) {
			$classIds[(int)$st['class_id']] = true;
		}
	}
	if (!$selectedStudent && !empty($students)) {
		$selectedStudent = $students[0];
		$selectedStudentId = (string)$selectedStudent['id'];
	}

	if ($selectedStudent) {
		$stmt = $conn->prepare("SELECT t.id, t.name
			FROM tbl_terms t
			WHERE EXISTS (
				SELECT 1 FROM tbl_exams e
				WHERE e.class_id = ? AND e.term_id = t.id AND e.status = 'published'
			)
			ORDER BY t.id DESC");
		$stmt->execute([(int)$selectedStudent['class_id']]);
		$publishedTerms = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($selectedTermId < 1 && !empty($publishedTerms)) {
			$selectedTermId = (int)$publishedTerms[0]['id'];
		}

		if ($selectedTermId > 0 && report_term_is_published($conn, (int)$selectedStudent['class_id'], $selectedTermId)) {
			$stmt = $conn->prepare("SELECT id FROM tbl_report_cards WHERE student_id = ? AND term_id = ? LIMIT 1");
			$stmt->execute([$selectedStudentId, $selectedTermId]);
			$reportId = (int)$stmt->fetchColumn();
			if ($reportId > 0) {
				$card = report_load_card($conn, $reportId);
				$summary['avg_score'] = (float)($card['mean'] ?? 0);
				$summary['grade'] = (string)($card['grade'] ?? 'N/A');
				$summary['position'] = isset($card['position'], $card['total_students']) ? ($card['position'].' / '.$card['total_students']) : '-';
			}
			$subjectRows = report_subject_breakdown($conn, $selectedStudentId, (int)$selectedStudent['class_id'], $selectedTermId);
			$history = report_student_term_history($conn, $selectedStudentId, (int)$selectedStudent['class_id'], 12);
			$attendance = report_attendance_summary($conn, $selectedStudentId, (int)$selectedStudent['class_id'], $selectedTermId);
			$summary['attendance_rate'] = $attendance['days_open'] > 0 ? round(($attendance['present'] / $attendance['days_open']) * 100, 1) : 0;
			$summary['fees_balance'] = report_fees_balance($conn, $selectedStudentId, $selectedTermId);
		}
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		if (count($classIds) > 0) {
			$placeholders = implode(',', array_fill(0, count($classIds), '?'));
			$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
				WHERE audience IN ('all','parents') OR (audience = 'class' AND class_id IN ($placeholders))
				ORDER BY created_at DESC LIMIT 5");
			$stmt->execute(array_keys($classIds));
		} else {
			$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications WHERE audience IN ('all','parents') ORDER BY created_at DESC LIMIT 5");
			$stmt->execute();
		}
		$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Parent Dashboard</title>
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
.parent-shell{display:grid;grid-template-columns:240px 1fr;min-height:100vh}
.parent-side{background:#fff;color:#263238;padding:18px 14px;position:sticky;top:0;height:100vh;border-right:1px solid #e3ebe8}
.parent-brand{display:flex;gap:10px;align-items:center;padding:8px 10px 18px;border-bottom:1px solid #e7efec;margin-bottom:14px}
.parent-mark{width:38px;height:38px;border-radius:12px;background:#e7f1ef;color:#00695C;display:flex;align-items:center;justify-content:center;font-weight:800}
.parent-menu{display:grid;gap:5px}
.parent-menu a{display:flex;gap:10px;align-items:center;color:#4a5a68;text-decoration:none;padding:10px 12px;border-radius:12px;font-size:.92rem}
.parent-menu a.active,.parent-menu a:hover{background:#e7f1ef;color:#00695C;font-weight:700}
.parent-main{padding-bottom:28px}
.parent-top{background:#fff;border-bottom:1px solid #e8eef5;padding:12px 24px;display:flex;justify-content:space-between;align-items:center}
.parent-content{padding:20px 24px}
.hero{background:linear-gradient(135deg,#00695C,#0b7d6d);border-radius:20px;color:#fff;padding:24px;box-shadow:0 18px 50px rgba(0,105,92,.16);margin-bottom:18px}
.hero-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;margin-top:14px}
.glass-input{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:12px;color:#fff;padding:10px 12px}
.stats-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:14px;margin-bottom:18px}
.card-panel,.stat-card{background:#fff;border:1px solid #e8edf3;border-radius:18px;box-shadow:0 14px 42px rgba(54,165,72,.08)}
.stat-card{padding:16px}
.stat-card .label{font-size:.72rem;text-transform:uppercase;color:#718096}
.stat-card .value{font-size:1.4rem;font-weight:800;color:#243447}
.grid-two{display:grid;grid-template-columns:1.05fr .95fr;gap:18px}
.panel-body{padding:18px}
.subject-table{width:100%;border-collapse:collapse}
.subject-table th,.subject-table td{padding:12px 10px;border-bottom:1px solid #edf2f7}
.subject-table th{font-size:.76rem;text-transform:uppercase;color:#718096}
.grade-badge{padding:4px 10px;border-radius:999px;background:#e7f1ef;color:#00695C;font-weight:700;font-size:.82rem}
.note-list{display:grid;gap:10px}
.note-item{background:#fff;border:1px solid #e9eef5;border-radius:14px;padding:12px 14px}
@media (max-width:1100px){.parent-shell{grid-template-columns:1fr}.parent-side{position:relative;height:auto}.hero-grid,.stats-grid,.grid-two{grid-template-columns:1fr 1fr}}
</style>
</head>
<body class="app">
<div class="parent-shell">
	<aside class="parent-side">
		<div class="parent-brand"><div class="parent-mark">P</div><div><div class="fw-bold">Elimu Hub</div><div class="small opacity-75">Parent Portal</div></div></div>
		<nav class="parent-menu">
			<a class="active" href="parent"><i class="bi bi-grid"></i><span>Dashboard</span></a>
			<a href="parent/report_card"><i class="bi bi-file-earmark-text"></i><span>Report Card</span></a>
			<a href="parent/attendance"><i class="bi bi-check2-square"></i><span>Attendance</span></a>
			<a href="parent/fees"><i class="bi bi-credit-card"></i><span>Fees</span></a>
			<a href="parent/elearning"><i class="bi bi-laptop"></i><span>E-Learning</span></a>
		</nav>
	</aside>
	<div class="parent-main">
		<div class="parent-top">
			<div class="fw-bold"><?php echo htmlspecialchars(WBName); ?></div>
			<div class="d-flex gap-3 align-items-center"><span class="small text-muted"><?php echo htmlspecialchars($fname.' '.$lname); ?></span><a href="logout" class="text-decoration-none"><i class="bi bi-box-arrow-right"></i></a></div>
		</div>
		<main class="parent-content">
			<?php if ($error !== '') { ?>
			<div class="card-panel"><div class="panel-body"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div></div>
			<?php } else { ?>
			<div class="hero">
				<div class="small text-uppercase opacity-75">Parent Dashboard</div>
				<h2 class="mb-1">Monitor your child’s performance and progress</h2>
				<div class="small">Choose a child and published term to review academic performance, attendance, fees, and report-ready analytics.</div>
				<form method="GET" action="parent" class="hero-grid">
					<select class="glass-input" name="student" onchange="this.form.submit()">
						<?php foreach ($students as $student): ?><option value="<?php echo htmlspecialchars((string)$student['id']); ?>" <?php echo $selectedStudentId===(string)$student['id']?'selected':''; ?>><?php echo htmlspecialchars($student['name'].' ('.$student['class_name'].')'); ?></option><?php endforeach; ?>
					</select>
					<select class="glass-input" name="term" onchange="this.form.submit()">
						<?php foreach ($publishedTerms as $term): ?><option value="<?php echo (int)$term['id']; ?>" <?php echo $selectedTermId===(int)$term['id']?'selected':''; ?>><?php echo htmlspecialchars($term['name']); ?></option><?php endforeach; ?>
					</select>
					<div class="glass-input d-flex align-items-center"><?php echo htmlspecialchars((string)($selectedStudent['school_id'] ?? $selectedStudentId)); ?></div>
					<a class="btn btn-light" href="parent/report_card<?php echo $selectedStudentId !== '' ? '?student='.urlencode($selectedStudentId).'&term='.$selectedTermId : ''; ?>">Open Report Card</a>
				</form>
			</div>

			<div class="stats-grid">
				<div class="stat-card"><div class="label">Children</div><div class="value"><?php echo (int)$summary['children']; ?></div></div>
				<div class="stat-card"><div class="label">Attendance</div><div class="value"><?php echo number_format((float)$summary['attendance_rate'],1); ?>%</div></div>
				<div class="stat-card"><div class="label">Mean Score</div><div class="value"><?php echo number_format((float)$summary['avg_score'],2); ?></div></div>
				<div class="stat-card"><div class="label">Grade</div><div class="value"><?php echo htmlspecialchars($summary['grade']); ?></div></div>
				<div class="stat-card"><div class="label">Position</div><div class="value"><?php echo htmlspecialchars((string)$summary['position']); ?></div></div>
			</div>

			<div class="grid-two">
				<section class="card-panel"><div class="panel-body"><h3 class="mb-3">Performance Trend</h3><div id="parentTrendChart" style="height:300px;"></div></div></section>
				<section class="card-panel"><div class="panel-body"><h3 class="mb-3">Quick Overview</h3><div class="note-list"><div class="note-item"><strong>Student:</strong> <?php echo htmlspecialchars((string)($selectedStudent['name'] ?? 'N/A')); ?></div><div class="note-item"><strong>Class:</strong> <?php echo htmlspecialchars((string)($selectedStudent['class_name'] ?? 'N/A')); ?></div><div class="note-item"><strong>Fees Balance:</strong> KES <?php echo number_format((float)$summary['fees_balance'],2); ?></div></div></div></section>
			</div>

			<section class="card-panel mt-3">
				<div class="panel-body">
					<h3 class="mb-3">Subject Performance</h3>
					<div class="table-responsive">
						<table class="subject-table">
							<thead><tr><th>Subject</th><th>Score</th><th>Class Mean</th><th>Change</th><th>Grade</th></tr></thead>
							<tbody>
							<?php if (!$subjectRows) { ?><tr><td colspan="5" class="text-muted">No published subject data yet.</td></tr><?php } ?>
							<?php foreach ($subjectRows as $row): ?>
							<tr>
								<td><?php echo htmlspecialchars($row['subject_name']); ?></td>
								<td><?php echo number_format((float)$row['score'],1); ?></td>
								<td><?php echo number_format((float)$row['class_mean'],1); ?></td>
								<td><?php echo ($row['change'] >= 0 ? '+' : '') . number_format((float)$row['change'],1); ?></td>
								<td><span class="grade-badge"><?php echo htmlspecialchars($row['grade']); ?></span></td>
							</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</section>

			<div class="grid-two mt-3">
				<section class="card-panel"><div class="panel-body"><h3 class="mb-3">Notifications</h3><div class="note-list"><?php if(!$notifications){ ?><div class="note-item text-muted">No notifications yet.</div><?php } foreach($notifications as $note){ ?><div class="note-item"><div class="fw-bold"><?php echo htmlspecialchars((string)$note['title']); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$note['message']); ?></div><div class="small text-muted mt-2"><?php echo htmlspecialchars((string)$note['created_at']); ?></div></div><?php } ?></div></div></section>
				<section class="card-panel"><div class="panel-body"><h3 class="mb-3">Children</h3><div class="note-list"><?php foreach($students as $student){ ?><div class="note-item d-flex justify-content-between align-items-center"><div><div class="fw-bold"><?php echo htmlspecialchars($student['name']); ?></div><div class="small text-muted"><?php echo htmlspecialchars((string)$student['class_name']); ?></div></div><a class="btn btn-sm btn-outline-success" href="parent?student=<?php echo urlencode((string)$student['id']); ?>&term=<?php echo (int)$selectedTermId; ?>">Open</a></div><?php } ?></div></div></section>
			</div>
			<?php } ?>
		</main>
	</div>
</div>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
const parentTrend = <?php echo json_encode($history); ?>;
const parentTrendEl = document.getElementById('parentTrendChart');
if (parentTrendEl) {
	const chart = echarts.init(parentTrendEl);
	chart.setOption({
		grid:{left:40,right:16,top:20,bottom:40},
		tooltip:{trigger:'axis'},
		xAxis:{type:'category',data:parentTrend.map(item=>item.term_name),axisLabel:{fontSize:10}},
		yAxis:{type:'value',min:0,max:100},
		series:[{type:'line',smooth:true,data:parentTrend.map(item=>item.mean),areaStyle:{color:'rgba(0,105,92,0.16)'},lineStyle:{color:'#00695C',width:2},itemStyle:{color:'#00695C'}}]
	});
}
</script>
</body>
</html>
