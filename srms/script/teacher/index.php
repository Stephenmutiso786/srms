<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
$canOverrideMarks = app_current_user_can_override_marks();
if ($res == "1" && ($level == "2" || $canOverrideMarks)) {}else{header("location:../"); exit;}

$notifications = [];
$announcements = [];
$assignments = [];
$classOptions = [];
$subjectOptions = [];
$termOptions = [];
$examOptions = [];
$selectedClass = (int)($_GET['class_id'] ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$selectedTerm = (int)($_GET['term_id'] ?? 0);
$selectedExam = (int)($_GET['exam_id'] ?? 0);
$summary = ['subjects' => 0, 'classes' => 0, 'students' => 0, 'avg' => 0, 'best' => 0];
$rows = [];
$trendPoints = [];
$recentDiscipline = [];
$roleNames = [];
$visibleModules = [];
$allocatedModules = [];
$promotionQueue = [];
$autoPromotionRun = [];
$isSeniorTeacher = false;
$teacherGapWarnings = [];
$classTeacherClassIds = [];
$classTeacherPanels = [];
$dashboardEntryCards = [];
$error = '';
$showDownloadsShortcut = false;
$isClassTeacher = false;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$schoolId = app_current_school_id();
	$hasClassSchool = app_column_exists($conn, 'tbl_classes', 'school_id');
	$hasStudentSchool = app_column_exists($conn, 'tbl_students', 'school_id');
	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$stmt = $conn->prepare("SELECT ta.class_id, ta.subject_id, ta.term_id,
			c.name AS class_name, s.name AS subject_name, t.name AS term_name
			FROM tbl_teacher_assignments ta
			LEFT JOIN tbl_classes c ON c.id = ta.class_id
			LEFT JOIN tbl_subjects s ON s.id = ta.subject_id
			LEFT JOIN tbl_terms t ON t.id = ta.term_id
			WHERE ta.teacher_id = ? AND ta.status = 1
			ORDER BY ta.class_id, ta.subject_id");
		$stmt->execute([(int)$account_id]);
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
	$classTeacherClassIds = app_staff_class_teacher_ids($conn, (int)$account_id);
	$isClassTeacher = !empty($classTeacherClassIds);
	$isSeniorTeacher = in_array('Senior Teacher', $roleNames, true) || app_staff_has_role_name($conn, (int)$account_id, 'Senior Teacher');
	if ($isSeniorTeacher) {
		app_ensure_promotion_workflow_schema($conn);
		$autoPromotionRun = app_auto_prepare_year_end_promotions($conn, (int)($account_id ?? 0));
		$promotionQueue = app_promotion_queue_summary($conn);
	}
	$visibleModules = app_teacher_portal_visible_modules($conn, (string)$account_id, (string)$level);
	$allocatedModules = app_teacher_portal_allocated_modules($conn, (string)$account_id, (string)$level);
	$showDownloadsShortcut = (!empty($classOptions) || !empty($assignments)) && (
		app_has_permission($conn, (string)$account_id, (string)$level, 'report.view')
		|| app_has_permission($conn, (string)$account_id, (string)$level, 'marks.enter')
		|| app_has_permission($conn, (string)$account_id, (string)$level, 'attendance.manage')
	);

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
	if (!empty($classTeacherClassIds)) {
		$placeholders = implode(',', array_fill(0, count($classTeacherClassIds), '?'));
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE id IN ($placeholders) ORDER BY name");
		$stmt->execute($classTeacherClassIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $classRow) {
			$classOptions[(int)$classRow['id']] = (string)$classRow['name'];
		}
	}
	$classOptions = app_sort_named_options($classOptions, 'class');
	$termOptions = app_sort_named_options($termOptions, 'term');

	$summary['subjects'] = count($subjectOptions);
	$summary['classes'] = count($classOptions);

	if (!empty($classOptions)) {
		$placeholders = implode(',', array_fill(0, count($classOptions), '?'));
		$studentSql = $hasStudentSchool
			? "SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders) AND (school_id IS NULL OR school_id = ?)"
			: "SELECT COUNT(*) FROM tbl_students WHERE class IN ($placeholders)";
		$stmt = $conn->prepare($studentSql);
		$params = array_keys($classOptions);
		if ($hasStudentSchool) {
			$params[] = $schoolId;
		}
		$stmt->execute($params);
		$summary['students'] = (int)$stmt->fetchColumn();
	}

	foreach ($classTeacherClassIds as $ctClassId) {
		$classNameForPanel = $classOptions[(int)$ctClassId] ?? '';
		if ($classNameForPanel === '') {
			$stmt = $conn->prepare("SELECT name FROM tbl_classes WHERE id = ? LIMIT 1");
			$stmt->execute([(int)$ctClassId]);
			$classNameForPanel = (string)$stmt->fetchColumn();
		}

		$panelStudentSql = $hasStudentSchool
			? "SELECT id, school_id, concat_ws(' ', fname, mname, lname) AS student_name, gender
				FROM tbl_students
				WHERE class = ? AND (school_id IS NULL OR school_id = ?)
				ORDER BY fname, lname
				LIMIT 30"
			: "SELECT id, school_id, concat_ws(' ', fname, mname, lname) AS student_name, gender
				FROM tbl_students
				WHERE class = ?
				ORDER BY fname, lname
				LIMIT 30";
		$stmt = $conn->prepare($panelStudentSql);
		$stmt->execute($hasStudentSchool ? [(int)$ctClassId, $schoolId] : [(int)$ctClassId]);
		$panelStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$panelTeachers = [];
		if (app_table_exists($conn, 'tbl_teacher_assignments')) {
			$stmt = $conn->prepare("SELECT DISTINCT st.id, concat_ws(' ', st.fname, st.lname) AS teacher_name, sb.name AS subject_name, tr.name AS term_name
				FROM tbl_teacher_assignments ta
				JOIN tbl_staff st ON st.id = ta.teacher_id
				JOIN tbl_subjects sb ON sb.id = ta.subject_id
				LEFT JOIN tbl_terms tr ON tr.id = ta.term_id
				WHERE ta.class_id = ? AND ta.status = 1
				ORDER BY sb.name, teacher_name");
			$stmt->execute([(int)$ctClassId]);
			$panelTeachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}

		$performance = ['entries' => 0, 'average' => 0.0, 'best' => 0.0];
		if (app_table_exists($conn, 'tbl_exam_results')) {
			$stmt = $conn->prepare("SELECT COUNT(*) AS entries, AVG(score) AS average_score, MAX(score) AS best_score
				FROM tbl_exam_results
				WHERE class = ?");
			$stmt->execute([(int)$ctClassId]);
			$perfRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
			$performance = [
				'entries' => (int)($perfRow['entries'] ?? 0),
				'average' => round((float)($perfRow['average_score'] ?? 0), 2),
				'best' => round((float)($perfRow['best_score'] ?? 0), 2),
			];
		}

		$classTeacherPanels[] = [
			'class_id' => (int)$ctClassId,
			'class_name' => $classNameForPanel !== '' ? $classNameForPanel : ('Class #' . (int)$ctClassId),
			'students' => $panelStudents,
			'teachers' => $panelTeachers,
			'performance' => $performance,
		];
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

	if ($selectedClass > 0 && $selectedTerm > 0 && app_table_exists($conn, 'tbl_exams')) {
		$stmt = $conn->prepare("SELECT id, name, status, COALESCE(assessment_mode, 'normal') AS assessment_mode
			FROM tbl_exams
			WHERE class_id = ? AND term_id = ? AND COALESCE(status, 'draft') <> 'draft'
			ORDER BY created_at DESC, id DESC");
		$stmt->execute([$selectedClass, $selectedTerm]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $examRow) {
			$examOptions[(int)$examRow['id']] = $examRow;
		}
		if ($selectedExam < 1 && !empty($examOptions)) {
			$selectedExam = (int)array_key_first($examOptions);
		}
		if ($selectedExam > 0 && !isset($examOptions[$selectedExam])) {
			$selectedExam = !empty($examOptions) ? (int)array_key_first($examOptions) : 0;
		}
	}

	foreach ($examOptions as $examRow) {
		$gapSummary = report_exam_submission_gap_summary($conn, (int)$examRow['id']);
		foreach ((array)($gapSummary['missing_subjects'] ?? []) as $missingSubject) {
			if ((int)($missingSubject['teacher_id'] ?? 0) !== (int)$account_id) {
				continue;
			}
			$teacherGapWarnings[] = [
				'exam_name' => (string)($examRow['name'] ?? ''),
				'class_name' => (string)($classOptions[(int)($examRow['class_id'] ?? 0)] ?? ''),
				'term_name' => (string)($termOptions[(int)($examRow['term_id'] ?? 0)] ?? ''),
				'subject_name' => (string)($missingSubject['subject_name'] ?? ''),
				'missing_students_count' => (int)($missingSubject['missing_students_count'] ?? 0),
				'missing_students' => array_slice((array)($missingSubject['missing_students'] ?? []), 0, 5),
			];
		}
	}

	foreach ($examOptions as $examId => $examRow) {
		foreach ($assignments as $assignment) {
			if ((int)($assignment['class_id'] ?? 0) !== (int)($examRow['class_id'] ?? 0)) {
				continue;
			}
			if ((int)($assignment['term_id'] ?? 0) > 0 && (int)($assignment['term_id'] ?? 0) !== (int)($examRow['term_id'] ?? 0)) {
				continue;
			}
			if (!app_exam_has_subject($conn, (int)$examId, (int)($assignment['subject_id'] ?? 0))) {
				continue;
			}
			$comboId = app_get_teacher_subject_combination_id($conn, (int)$account_id, (int)$assignment['subject_id'], (int)$examRow['class_id'], true);
			if ($comboId < 1) {
				continue;
			}
			$dashboardEntryCards[] = [
				'exam_id' => (int)$examId,
				'exam_name' => (string)($examRow['name'] ?? ''),
				'class_name' => (string)($classOptions[(int)($examRow['class_id'] ?? 0)] ?? ''),
				'term_name' => (string)($termOptions[(int)($examRow['term_id'] ?? 0)] ?? ''),
				'assessment_mode' => (string)($examRow['assessment_mode'] ?? 'normal'),
				'subject_combination' => $comboId,
				'subject_name' => (string)($assignment['subject_name'] ?? ''),
			];
		}
	}

	if ($canOverrideMarks) {
		$subjectSql = app_column_exists($conn, 'tbl_subjects', 'school_id')
			? "SELECT id, name FROM tbl_subjects WHERE school_id IS NULL OR school_id = ? ORDER BY name"
			: "SELECT id, name FROM tbl_subjects ORDER BY name";
		$stmt = $conn->prepare($subjectSql);
		$stmt->execute(app_column_exists($conn, 'tbl_subjects', 'school_id') ? [$schoolId] : []);
		$allSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($examOptions as $examId => $examRow) {
			$examSubjectIds = app_exam_subject_ids($conn, (int)$examId);
			$subjectPool = !empty($examSubjectIds) ? $examSubjectIds : array_map(static fn($r) => (int)$r['id'], $allSubjects);
			foreach ($subjectPool as $subjectId) {
				$subjectName = '';
				foreach ($allSubjects as $subjectRow) {
					if ((int)$subjectRow['id'] === (int)$subjectId) {
						$subjectName = (string)$subjectRow['name'];
						break;
					}
				}
				if ($subjectName === '') {
					continue;
				}
				$comboId = app_get_teacher_subject_combination_id($conn, (int)$account_id, (int)$subjectId, (int)$examRow['class_id'], true);
				if ($comboId < 1) {
					continue;
				}
				$dashboardEntryCards[] = [
					'exam_id' => (int)$examId,
					'exam_name' => (string)($examRow['name'] ?? ''),
					'class_name' => (string)($classOptions[(int)($examRow['class_id'] ?? 0)] ?? ''),
					'term_name' => (string)($termOptions[(int)($examRow['term_id'] ?? 0)] ?? ''),
					'assessment_mode' => (string)($examRow['assessment_mode'] ?? 'normal'),
					'subject_combination' => $comboId,
					'subject_name' => $subjectName,
				];
			}
		}
	}

	if ($selectedClass > 0 && $selectedSubject > 0 && $selectedTerm > 0) {
		$stmt = $conn->prepare("SELECT sc.id FROM tbl_subject_combinations sc
			WHERE sc.teacher = ? AND sc.subject = ? LIMIT 1");
		$stmt->execute([(int)$account_id, $selectedSubject]);
		$combinationId = (int)$stmt->fetchColumn();
		if ($combinationId > 0) {
			$hasExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
			$sql = "SELECT st.id AS student_id, st.school_id,
				concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
				COALESCE(er.score, 0) AS score
				FROM tbl_students st
				LEFT JOIN tbl_exam_results er
					ON er.student = st.id
					AND er.class = st.class
					AND er.term = ?
					AND er.subject_combination = ?";
			$args = [$selectedTerm, $combinationId];
			if ($hasExamId && $selectedExam > 0) {
				$sql .= " AND er.exam_id = ?";
				$args[] = $selectedExam;
			}
			$sql .= " WHERE st.class = ?
				ORDER BY student_name";
			$args[] = $selectedClass;
			$stmt = $conn->prepare($sql);
			$stmt->execute($args);
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
			$sql = "SELECT AVG(er.score)
				FROM tbl_exam_results er
				JOIN tbl_subject_combinations sc ON sc.id = er.subject_combination
				WHERE er.class = ? AND er.term = ? AND sc.teacher = ? AND sc.subject = ?";
			$args = [$selectedClass, (int)$term['id'], (int)$account_id, $selectedSubject];
			if ($hasExamId && $selectedExam > 0 && !empty($examOptions[$selectedExam]['name'])) {
				$sql .= " AND er.exam_id IN (SELECT id FROM tbl_exams WHERE class_id = ? AND term_id = ? AND name = ?)";
				$args[] = $selectedClass;
				$args[] = (int)$term['id'];
				$args[] = (string)$examOptions[$selectedExam]['name'];
			}
			$stmt2 = $conn->prepare($sql);
			$stmt2->execute($args);
			$trendPoints[] = [
				'term_name' => (string)$term['name'],
				'mean' => round((float)$stmt2->fetchColumn(), 2)
			];
		}
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("SELECT title, message, link, created_at FROM tbl_notifications
			WHERE audience IN ('all','staff')
			AND (user_role IS NULL OR user_role = '' OR user_role = 'teacher')
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
		$stmt = $conn->prepare("SELECT id, title, announcement, create_date FROM tbl_announcements WHERE level IN ('0','2','3') ORDER BY id DESC LIMIT 5");
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
.hero-actions{margin-top:18px;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}
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
			<?php if ($isClassTeacher) { ?>
			<div class="tile mb-3">
				<div class="alert alert-primary mb-0 d-flex flex-wrap align-items-center justify-content-between gap-3">
					<div>
						<div class="fw-bold">Class Admin Access</div>
						<div class="small mb-0">You are allocated as class teacher for <?php echo count($classTeacherClassIds); ?> class<?php echo count($classTeacherClassIds) === 1 ? '' : 'es'; ?>. Your dashboard shows class-admin tools, attendance, and class lists for those classes.</div>
					</div>
					<div class="d-flex flex-wrap gap-2">
						<span class="badge bg-light text-dark border">Role: Class Admin</span>
						<span class="badge bg-light text-dark border">Role: Teacher</span>
					</div>
				</div>
			</div>
			<?php } ?>
			<div class="dashboard-hero">
				<div class="d-flex justify-content-between flex-wrap gap-3">
					<div class="hero-main">
						<span class="hero-kicker">Teacher Dashboard</span>
						<h2 class="mb-1">Track class or subject performance</h2>
						<p>Choose the class, subject, term, and exam you want to review from one place.</p>
					</div>
					<div class="text-end">
						<div class="small opacity-75">Current Time</div>
						<div class="fw-bold" id="teacherCurrentTime"><?php echo date('H:i:s'); ?></div>
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
					<select class="glass-input" name="exam_id" onchange="this.form.submit()">
						<?php if (!empty($examOptions)): ?>
							<?php foreach ($examOptions as $id => $exam): ?>
								<option value="<?php echo (int)$id; ?>" <?php echo $selectedExam===$id?'selected':''; ?>>
									<?php echo htmlspecialchars((string)$exam['name'] . ' [' . strtoupper((string)$exam['status']) . ']'); ?>
								</option>
							<?php endforeach; ?>
						<?php else: ?>
							<option value="0">No Exam Found</option>
						<?php endif; ?>
					</select>
					<a class="btn btn-light" href="teacher/exam_marks_entry">Open Allocated Marks</a>
					<?php if ($showDownloadsShortcut): ?>
					<a class="btn btn-light" href="teacher/downloads_center">Downloads Hub</a>
					<?php endif; ?>
				</form>
			</div>

			<?php if (!empty($teacherGapWarnings)) { ?>
			<div class="tile mb-3">
				<div class="alert alert-danger mb-0">
					<div class="fw-bold mb-2">Missing marks need your action</div>
					<?php foreach ($teacherGapWarnings as $warning): ?>
					<div class="mb-2">
						<strong><?php echo htmlspecialchars($warning['subject_name']); ?></strong>
						in <?php echo htmlspecialchars($warning['exam_name'] . ' - ' . $warning['class_name']); ?>
						has <?php echo (int)$warning['missing_students_count']; ?> learner(s) missing marks.
						<?php if (!empty($warning['missing_students'])) { ?>
						Missing: <?php echo htmlspecialchars(implode(', ', array_map(static function ($row) { return (string)($row['student_name'] ?? ''); }, $warning['missing_students']))); ?>.
						<?php } ?>
					</div>
					<?php endforeach; ?>
					<div class="mt-2">
						<a class="btn btn-sm btn-outline-danger" href="teacher/exam_marks_entry">Open Allocated Marks</a>
					</div>
				</div>
			</div>
			<?php } ?>

			<?php if (!empty($dashboardEntryCards)) { ?>
			<section class="tile mb-3">
				<h3 class="tile-title">Allocated Exam Entries</h3>
				<div class="small text-muted mb-3">These are the exact exam and subject combinations available to you right now.</div>
				<div class="row g-3">
					<?php foreach ($dashboardEntryCards as $card): ?>
					<div class="col-md-6 col-lg-4">
						<div class="border rounded-4 p-3 h-100" style="background:linear-gradient(180deg,#ffffff,#f7fbf9);">
							<div class="d-flex justify-content-between gap-2 mb-2">
								<div>
									<div class="fw-bold text-success"><?php echo htmlspecialchars((string)$card['exam_name']); ?></div>
									<div class="small text-muted"><?php echo htmlspecialchars((string)$card['class_name'] . ' · ' . (string)$card['term_name']); ?></div>
								</div>
								<span class="badge bg-light text-dark"><?php echo htmlspecialchars(strtoupper((string)$card['assessment_mode'])); ?></span>
							</div>
							<div class="mb-3">
								<div class="small text-muted">Subject</div>
								<div class="fw-bold"><?php echo htmlspecialchars((string)$card['subject_name']); ?></div>
							</div>
							<form method="POST" action="teacher/core/start_exam_entry">
								<input type="hidden" name="exam_id" value="<?php echo (int)$card['exam_id']; ?>">
								<input type="hidden" name="subject_combination" value="<?php echo (int)$card['subject_combination']; ?>">
								<input type="hidden" name="assessment_mode" value="<?php echo htmlspecialchars((string)$card['assessment_mode']); ?>">
								<input type="hidden" name="origin_portal" value="teacher">
								<button class="btn btn-sm btn-primary w-100" type="submit">Open Marks Entry</button>
							</form>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php } ?>

			<div class="dashboard-stats">
				<div class="stat-card"><div class="label">Classes</div><div class="value"><?php echo (int)$summary['classes']; ?></div></div>
				<div class="stat-card"><div class="label">Subjects</div><div class="value"><?php echo (int)$summary['subjects']; ?></div></div>
				<div class="stat-card"><div class="label">Students</div><div class="value"><?php echo (int)$summary['students']; ?></div></div>
				<div class="stat-card"><div class="label">Selected Avg</div><div class="value"><?php echo number_format((float)$summary['avg'],2); ?></div></div>
				<div class="stat-card"><div class="label">Best Score</div><div class="value"><?php echo number_format((float)$summary['best'],0); ?></div></div>
			</div>

			<?php if (!empty($classTeacherPanels)): ?>
			<section class="tile mb-3">
				<h3 class="tile-title">My Class Teacher Classes</h3>
				<div class="small text-muted mb-3">Students, subject teachers, and performance overview for classes allocated to you as class teacher.</div>
				<div class="mb-3">
					<span class="badge bg-primary me-2">Class Admin</span>
					<span class="badge bg-secondary">Teacher</span>
				</div>
				<?php foreach ($classTeacherPanels as $panel): ?>
				<div class="border rounded p-3 mb-3">
					<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
						<div>
							<h5 class="mb-1"><?php echo htmlspecialchars((string)$panel['class_name']); ?></h5>
							<div class="small text-muted">Class teacher view</div>
						</div>
						<div class="d-flex flex-wrap gap-2">
							<span class="badge bg-light text-dark border">Students: <?php echo count((array)$panel['students']); ?></span>
							<span class="badge bg-light text-dark border">Marks: <?php echo (int)$panel['performance']['entries']; ?></span>
							<span class="badge bg-light text-dark border">Average: <?php echo number_format((float)$panel['performance']['average'], 2); ?></span>
							<span class="badge bg-light text-dark border">Best: <?php echo number_format((float)$panel['performance']['best'], 2); ?></span>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6 mb-3">
							<h6 class="fw-bold">Teachers Teaching This Class</h6>
							<div class="table-responsive">
								<table class="table table-sm table-hover">
									<thead><tr><th>Subject</th><th>Teacher</th><th>Term</th></tr></thead>
									<tbody>
									<?php if (empty($panel['teachers'])): ?><tr><td colspan="3" class="text-muted">No subject teachers allocated yet.</td></tr><?php endif; ?>
									<?php foreach ((array)$panel['teachers'] as $teacherRow): ?>
									<tr>
										<td><?php echo htmlspecialchars((string)$teacherRow['subject_name']); ?></td>
										<td><?php echo htmlspecialchars((string)$teacherRow['teacher_name']); ?></td>
										<td><?php echo htmlspecialchars((string)($teacherRow['term_name'] ?? '')); ?></td>
									</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
						<div class="col-md-6 mb-3">
							<h6 class="fw-bold">Students In This Class</h6>
							<div class="table-responsive">
								<table class="table table-sm table-hover">
									<thead><tr><th>Student</th><th>School ID</th><th>Gender</th></tr></thead>
									<tbody>
									<?php if (empty($panel['students'])): ?><tr><td colspan="3" class="text-muted">No students found in this class.</td></tr><?php endif; ?>
									<?php foreach ((array)$panel['students'] as $studentRow): ?>
									<tr>
										<td><?php echo htmlspecialchars((string)$studentRow['student_name']); ?></td>
										<td><?php echo htmlspecialchars((string)($studentRow['school_id'] ?: $studentRow['id'])); ?></td>
										<td><?php echo htmlspecialchars((string)$studentRow['gender']); ?></td>
									</tr>
									<?php endforeach; ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<div class="d-flex flex-wrap gap-2">
						<a class="btn btn-outline-primary btn-sm" href="teacher/print_class_list?class_id=<?php echo (int)$panel['class_id']; ?>">Print Class List</a>
						<a class="btn btn-outline-secondary btn-sm" href="teacher/class_report?class_id=<?php echo (int)$panel['class_id']; ?>">Open Class Report</a>
					</div>
				</div>
				<?php endforeach; ?>
			</section>
			<?php endif; ?>

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
					<section class="tile"><h3 class="tile-title">Announcements</h3><div class="note-list"><?php if(!$announcements){ ?><div class="note-item text-muted">No announcements right now.</div><?php } foreach($announcements as $row){ ?><div class="note-item"><div class="fw-bold"><?php echo htmlspecialchars((string)($row['title'] ?? '')); ?></div><div class="small text-muted mt-1"><?php echo htmlspecialchars((string)($row['announcement'] ?? '')); ?></div><div class="small text-muted mt-2"><?php echo htmlspecialchars((string)($row['create_date'] ?? '')); ?></div></div><?php } ?></div></section>
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
					<?php if ($isSeniorTeacher && !empty($promotionQueue)): ?>
					<section class="tile mt-3">
						<h3 class="tile-title">Promotion Queue</h3>
						<div class="alert alert-warning mb-3">
							<strong><?php echo (int)$promotionQueue['pending_review']; ?> batch(es)</strong> waiting for Headteacher or Deputy review.
						</div>
						<div class="alert alert-info mb-3">
							<strong><?php echo (int)$promotionQueue['ready_for_super_admin']; ?> batch(es)</strong> waiting for Super Admin completion.
						</div>
						<div class="alert alert-success mb-3">
							<strong><?php echo (int)$promotionQueue['completed']; ?> batch(es)</strong> already completed.
						</div>
						<div class="alert alert-light mb-0">
							<strong>Auto promotion:</strong> <?php echo !empty($promotionQueue['auto_enabled']) ? 'Enabled' : 'Disabled'; ?>.
							Your role is to monitor the queue and support the leadership review process.
							<a href="admin/promotions">Open Promotions</a>.
							<?php if (!empty($autoPromotionRun['message'])): ?>
							<span class="ms-2"><?php echo htmlspecialchars((string)$autoPromotionRun['message']); ?></span>
							<?php endif; ?>
						</div>
					</section>
					<?php endif; ?>
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
(function () {
	function updateClock() {
		var node = document.getElementById('teacherCurrentTime');
		if (!node) return;
		node.textContent = new Intl.DateTimeFormat('en-KE', { hour:'2-digit', minute:'2-digit', second:'2-digit', hour12:false, timeZone:'Africa/Nairobi' }).format(new Date());
	}
	updateClock();
	setInterval(updateClock, 1000);
})();
</script>
</body>
</html>
