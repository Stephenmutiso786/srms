<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

$courses = [];
$lessons = [];
$assignments = [];
$submissions = [];
$liveClasses = [];
$quizzes = [];
$lessonContent = [];
$studentClassId = 0;
$progressRows = [];
$pendingAssignments = 0;
$upcomingLiveClasses = 0;
$progressSummary = [
	'tracked_courses' => 0,
	'avg_completion' => 0,
	'ee' => 0,
	'me' => 0,
	'ae' => 0,
	'be' => 0,
];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$account_id]);
	$studentClassId = (int)$stmt->fetchColumn();

	if (app_table_exists($conn, 'tbl_courses')) {
		$stmt = $conn->prepare("SELECT c.*, sb.name AS subject_name
			FROM tbl_courses c
			LEFT JOIN tbl_subjects sb ON sb.id = c.subject_id
			WHERE c.class_id = ? AND c.status = 1
			ORDER BY c.created_at DESC");
		$stmt->execute([$studentClassId]);
		$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_lessons')) {
		$stmt = $conn->prepare("SELECT l.*, c.name AS course_name
			FROM tbl_lessons l
			LEFT JOIN tbl_courses c ON c.id = l.course_id
			WHERE c.class_id = ?
			ORDER BY l.created_at DESC");
		$stmt->execute([$studentClassId]);
		$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (app_table_exists($conn, 'tbl_lesson_content') && count($lessons) > 0) {
		$lessonIds = array_map(function ($row) { return (int)$row['id']; }, $lessons);
		$placeholders = implode(',', array_fill(0, count($lessonIds), '?'));
		$stmt = $conn->prepare("SELECT * FROM tbl_lesson_content WHERE lesson_id IN ($placeholders)");
		$stmt->execute($lessonIds);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$lessonContent[(int)$row['lesson_id']][] = $row;
		}
	}

	if (app_table_exists($conn, 'tbl_assignments')) {
		$stmt = $conn->prepare("SELECT a.*, c.name AS course_name
			FROM tbl_assignments a
			LEFT JOIN tbl_courses c ON c.id = a.course_id
			WHERE c.class_id = ?
			ORDER BY a.created_at DESC");
		$stmt->execute([$studentClassId]);
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_assignment_submissions')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_assignment_submissions WHERE student_id = ?");
		$stmt->execute([$account_id]);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$submissions[(int)$row['assignment_id']] = $row;
		}
	}

	if (app_table_exists($conn, 'tbl_live_classes')) {
		$stmt = $conn->prepare("SELECT lc.*, c.name AS course_name
			FROM tbl_live_classes lc
			LEFT JOIN tbl_courses c ON c.id = lc.course_id
			WHERE c.class_id = ?
			ORDER BY lc.start_time DESC");
		$stmt->execute([$studentClassId]);
		$liveClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$nowRef = time();
		foreach ($liveClasses as $liveRow) {
			$startTs = strtotime((string)($liveRow['start_time'] ?? ''));
			if ($startTs !== false && $startTs > $nowRef) {
				$upcomingLiveClasses++;
			}
		}
	}
	if (app_table_exists($conn, 'tbl_quizzes')) {
		$stmt = $conn->prepare("SELECT q.*, c.name AS course_name
			FROM tbl_quizzes q
			JOIN tbl_courses c ON c.id = q.course_id
			WHERE c.class_id = ?
			ORDER BY q.created_at DESC");
		$stmt->execute([$studentClassId]);
		$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (!empty($assignments)) {
		foreach ($assignments as $assignmentRow) {
			if (!isset($submissions[(int)$assignmentRow['id']])) {
				$pendingAssignments++;
			}
		}
	}

	if (app_table_exists($conn, 'tbl_elearning_progress')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_elearning_progress WHERE student_id = ? ORDER BY updated_at DESC");
		$stmt->execute([$account_id]);
		$progressRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (!empty($progressRows)) {
			$courseSeen = [];
			$totalPct = 0.0;
			foreach ($progressRows as $p) {
				$courseSeen[(int)$p['course_id']] = true;
				$totalPct += (float)$p['completion_pct'];
				$level = (string)($p['competency_level'] ?? 'AE');
				if (isset($progressSummary[strtolower($level)])) {
					$progressSummary[strtolower($level)]++;
				}
			}
			$progressSummary['tracked_courses'] = count($courseSeen);
			$progressSummary['avg_completion'] = round($totalPct / count($progressRows), 2);
		}
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to load e-learning data."));
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - E-Learning</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="stylesheet" type="text/css" href="css/elearning-ui.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini elearn-page elearn-student">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/view"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
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
<li><a class="app-menu__item active" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item" href="student/attendance"><i class="app-menu__icon feather icon-check-square"></i><span class="app-menu__label">My Attendance</span></a></li>
<li><a class="app-menu__item" href="student/fees"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">Fees</span></a></li>
<li><a class="app-menu__item" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>E-Learning</h1>
<p>Access lessons, assignments, and live classes.</p>
</div>
</div>

<section class="elearn-hero mb-3">
<div>
<p class="elearn-kicker">Learner Hub</p>
<h2>Stay on track with lessons, quizzes, and live sessions</h2>
<p class="mb-0">Everything you need for today's learning is in one place.</p>
</div>
<div class="elearn-hero-actions">
<a class="btn btn-light btn-sm" href="#studentAssignments"><i class="bi bi-journal-check me-1"></i>Assignments</a>
<a class="btn btn-warning btn-sm" href="#studentLive"><i class="bi bi-camera-video me-1"></i>Live Classes</a>
<a class="btn btn-success btn-sm" href="#studentQuizzes"><i class="bi bi-patch-question me-1"></i>Quizzes</a>
</div>
</section>

<div class="row mb-3">
<div class="col-md-3 col-sm-6 mb-3"><div class="tile elearn-stat-card tone-primary"><div class="tile-body"><h4><?php echo (int)count($courses); ?></h4><p>Active Courses</p></div></div></div>
<div class="col-md-3 col-sm-6 mb-3"><div class="tile elearn-stat-card tone-sky"><div class="tile-body"><h4><?php echo number_format((float)$progressSummary['avg_completion'], 1); ?>%</h4><p>Avg CBC Progress</p></div></div></div>
<div class="col-md-3 col-sm-6 mb-3"><div class="tile elearn-stat-card tone-leaf"><div class="tile-body"><h4><?php echo (int)$pendingAssignments; ?></h4><p>Pending Assignments</p></div></div></div>
<div class="col-md-3 col-sm-6 mb-3"><div class="tile elearn-stat-card tone-sun"><div class="tile-body"><h4><?php echo (int)$upcomingLiveClasses; ?></h4><p>Upcoming Live Classes</p></div></div></div>
</div>

<div class="tile elearn-panel" id="studentCourses">
<h3 class="tile-title"><i class="bi bi-mortarboard me-2"></i>Courses</h3>
<div class="table-responsive">
<table class="table table-hover elearn-table">
<thead><tr><th>Course</th><th>Subject</th></tr></thead>
<tbody>
<?php foreach ($courses as $course): ?>
<tr>
<td><?php echo htmlspecialchars($course['name']); ?></td>
<td><?php echo htmlspecialchars($course['subject_name']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile elearn-panel" id="studentLessons">
<h3 class="tile-title"><i class="bi bi-journal-text me-2"></i>Lessons</h3>
<div class="table-responsive">
<table class="table table-hover elearn-table">
<thead><tr><th>Course</th><th>Lesson</th><th>CBC Path</th><th>Competency/Outcome</th><th>Content</th></tr></thead>
<tbody>
<?php foreach ($lessons as $lesson): ?>
<tr>
<td><?php echo htmlspecialchars($lesson['course_name']); ?></td>
<td><?php echo htmlspecialchars($lesson['title']); ?></td>
<td>
<?php echo htmlspecialchars((string)($lesson['strand'] ?? '')); ?>
<?php if (!empty($lesson['sub_strand'])): ?> / <?php echo htmlspecialchars((string)$lesson['sub_strand']); ?><?php endif; ?>
<?php if (!empty($lesson['grade_band'])): ?><br><small class="text-muted"><?php echo htmlspecialchars((string)$lesson['grade_band']); ?></small><?php endif; ?>
</td>
<td><?php echo htmlspecialchars((string)($lesson['learning_outcome'] ?? $lesson['competency'] ?? '')); ?></td>
<td>
  <?php foreach (($lessonContent[(int)$lesson['id']] ?? []) as $content): ?>
		<?php $isOffline = !empty($content['is_offline_available']); ?>
    <?php if (($content['file_path'] ?? '') !== '') { ?>
			<a class="btn btn-sm btn-outline-secondary mb-1" href="<?php echo htmlspecialchars($content['file_path']); ?>" target="_blank">Download</a>
			<?php if ($isOffline): ?><span class="badge bg-success ms-1">Offline</span><?php endif; ?>
    <?php } elseif (($content['url'] ?? '') !== '') { ?>
      <a class="btn btn-sm btn-outline-primary mb-1" href="<?php echo htmlspecialchars($content['url']); ?>" target="_blank">Open Link</a>
			<?php if ($isOffline): ?><span class="badge bg-success ms-1">Offline</span><?php endif; ?>
    <?php } ?>
  <?php endforeach; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile elearn-panel" id="studentAssignments">
<h3 class="tile-title"><i class="bi bi-journal-check me-2"></i>Assignments</h3>
<div class="table-responsive">
<table class="table table-hover elearn-table">
<thead><tr><th>Assignment</th><th>Course</th><th>Due</th><th>Status</th><th>Submit</th></tr></thead>
<tbody>
<?php foreach ($assignments as $assignment): ?>
<?php $sub = $submissions[$assignment['id']] ?? null; ?>
<tr>
<td><?php echo htmlspecialchars($assignment['title']); ?></td>
<td><?php echo htmlspecialchars($assignment['course_name']); ?></td>
<td><?php echo htmlspecialchars($assignment['due_date']); ?></td>
<td><?php echo $sub ? 'Submitted' : 'Pending'; ?></td>
<td>
	<form class="app_frm elearn-inline-form" method="POST" action="student/core/submit_assignment" enctype="multipart/form-data">
    <input type="hidden" name="assignment_id" value="<?php echo (int)$assignment['id']; ?>">
    <input class="form-control form-control-sm mb-2" name="submission_text" placeholder="Text answer">
    <input class="form-control form-control-sm mb-2" type="file" name="file">
    <button class="btn btn-sm btn-outline-primary">Submit</button>
  </form>
 </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile elearn-panel" id="studentQuizzes">
<h3 class="tile-title"><i class="bi bi-patch-question me-2"></i>Quizzes</h3>
<div class="table-responsive">
<table class="table table-hover elearn-table">
<thead><tr><th>Quiz</th><th>Course</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($quizzes as $quiz): ?>
<tr>
<td><?php echo htmlspecialchars($quiz['title']); ?></td>
<td><?php echo htmlspecialchars($quiz['course_name']); ?></td>
<td><a class="btn btn-sm btn-outline-primary" href="student/quiz?id=<?php echo (int)$quiz['id']; ?>">Take Quiz</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<div class="tile elearn-panel" id="studentLive">
<h3 class="tile-title"><i class="bi bi-camera-video me-2"></i>Live Classes</h3>
<div class="table-responsive">
<table class="table table-hover elearn-table">
<thead><tr><th>Class</th><th>Course</th><th>Start</th><th>Link</th></tr></thead>
<tbody>
<?php foreach ($liveClasses as $live): ?>
<?php
  $canJoin = false;
  try {
    $now = new DateTime('now');
    $start = new DateTime($live['start_time']);
    $canJoin = $now >= $start;
  } catch (Throwable $e) {}
?>
<tr>
<td><?php echo htmlspecialchars($live['title']); ?></td>
<td><?php echo htmlspecialchars($live['course_name']); ?></td>
<td><?php echo htmlspecialchars($live['start_time']); ?></td>
<td>
  <?php if ($canJoin) { ?>
    <a class="btn btn-sm btn-success" href="student/core/join_live_class?id=<?php echo (int)$live['id']; ?>">Join</a>
  <?php } else { ?>
    <button class="btn btn-sm btn-secondary" disabled>Not yet</button>
  <?php } ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
