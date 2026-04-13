<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

$courses = [];
$classes = [];
$subjects = [];
$lessons = [];
$assignments = [];
$submissions = [];
$liveClasses = [];
$quizzes = [];
$quizQuestions = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_table_exists($conn, 'tbl_teacher_assignments')) {
		$year = (int)date('Y');
		$stmt = $conn->prepare("SELECT DISTINCT c.id, c.name
			FROM tbl_teacher_assignments ta
			JOIN tbl_classes c ON c.id = ta.class_id
			WHERE ta.teacher_id = ? AND ta.year = ? AND ta.status = 1
			ORDER BY c.name");
		$stmt->execute([$account_id, $year]);
		$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $conn->prepare("SELECT DISTINCT s.id, s.name
			FROM tbl_teacher_assignments ta
			JOIN tbl_subjects s ON s.id = ta.subject_id
			WHERE ta.teacher_id = ? AND ta.year = ? AND ta.status = 1
			ORDER BY s.name");
		$stmt->execute([$account_id, $year]);
		$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} else {
		$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
		$stmt->execute();
		$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

		$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
		$stmt->execute();
		$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_courses')) {
		$stmt = $conn->prepare("SELECT c.*, cl.name AS class_name, sb.name AS subject_name
			FROM tbl_courses c
			LEFT JOIN tbl_classes cl ON cl.id = c.class_id
			LEFT JOIN tbl_subjects sb ON sb.id = c.subject_id
			WHERE c.teacher_id = ?
			ORDER BY c.created_at DESC");
		$stmt->execute([$account_id]);
		$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_lessons')) {
		$stmt = $conn->prepare("SELECT l.*, c.name AS course_name
			FROM tbl_lessons l
			LEFT JOIN tbl_courses c ON c.id = l.course_id
			WHERE c.teacher_id = ?
			ORDER BY l.created_at DESC");
		$stmt->execute([$account_id]);
		$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_assignments')) {
		$stmt = $conn->prepare("SELECT a.*, c.name AS course_name
			FROM tbl_assignments a
			LEFT JOIN tbl_courses c ON c.id = a.course_id
			WHERE c.teacher_id = ?
			ORDER BY a.created_at DESC");
		$stmt->execute([$account_id]);
		$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_assignment_submissions')) {
		$stmt = $conn->prepare("SELECT s.*, a.title AS assignment_title
			FROM tbl_assignment_submissions s
			LEFT JOIN tbl_assignments a ON a.id = s.assignment_id
			LEFT JOIN tbl_courses c ON c.id = a.course_id
			WHERE c.teacher_id = ?
			ORDER BY s.submitted_at DESC");
		$stmt->execute([$account_id]);
		$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_live_classes')) {
		$stmt = $conn->prepare("SELECT lc.*, c.name AS course_name
			FROM tbl_live_classes lc
			LEFT JOIN tbl_courses c ON c.id = lc.course_id
			WHERE c.teacher_id = ?
			ORDER BY lc.start_time DESC");
		$stmt->execute([$account_id]);
		$liveClasses = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_quizzes')) {
		$stmt = $conn->prepare("SELECT q.*, c.name AS course_name
			FROM tbl_quizzes q
			LEFT JOIN tbl_courses c ON c.id = q.course_id
			WHERE c.teacher_id = ?
			ORDER BY q.created_at DESC");
		$stmt->execute([$account_id]);
		$quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	if (app_table_exists($conn, 'tbl_quiz_questions')) {
		$stmt = $conn->prepare("SELECT qq.*, q.title AS quiz_title
			FROM tbl_quiz_questions qq
			LEFT JOIN tbl_quizzes q ON q.id = qq.quiz_id
			LEFT JOIN tbl_courses c ON c.id = q.course_id
			WHERE c.teacher_id = ?
			ORDER BY qq.id DESC");
		$stmt->execute([$account_id]);
		$quizQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Teacher</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
<li><a class="app-menu__item" href="teacher/combinations"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Exams</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/exam_marks_entry"><i class="icon bi bi-circle-fill"></i> Exam Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/import_results"><i class="icon bi bi-circle-fill"></i> Import Results</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
</ul>
</li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>E-Learning</h1>
<p>Courses, lessons, assignments, quizzes, and live classes.</p>
</div>
</div>

<div class="row">
<div class="col-md-5">
<div class="tile">
<h3 class="tile-title">Create Course</h3>
<form class="app_frm" action="teacher/core/create_course" method="POST">
<div class="mb-3">
<label class="form-label">Course Name</label>
<input class="form-control" name="name" required>
</div>
<div class="mb-3">
<label class="form-label">Class</label>
<select class="form-control" name="class_id" required>
<option value="">Select class</option>
<?php foreach ($classes as $row): ?>
<option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Subject</label>
<select class="form-control" name="subject_id" required>
<option value="">Select subject</option>
<?php foreach ($subjects as $row): ?>
<option value="<?php echo $row['id']; ?>"><?php echo htmlspecialchars($row['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<button class="btn btn-primary">Create Course</button>
</form>
</div>
</div>

<div class="col-md-7">
<div class="tile">
<h3 class="tile-title">My Courses</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Name</th><th>Class</th><th>Subject</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($courses as $course): ?>
<tr>
<td><?php echo htmlspecialchars($course['name']); ?></td>
<td><?php echo htmlspecialchars($course['class_name']); ?></td>
<td><?php echo htmlspecialchars($course['subject_name']); ?></td>
<td><?php echo ((int)$course['status'] === 1) ? 'Active' : 'Inactive'; ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Create Lesson</h3>
<form class="app_frm" action="teacher/core/create_lesson" method="POST">
<div class="mb-3">
<label class="form-label">Course</label>
<select class="form-control" name="course_id" required>
<option value="">Select course</option>
<?php foreach ($courses as $course): ?>
<option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Lesson Title</label>
<input class="form-control" name="title" required>
</div>
<div class="mb-3">
<label class="form-label">Strand</label>
<input class="form-control" name="strand">
</div>
<div class="mb-3">
<label class="form-label">Sub-Strand</label>
<input class="form-control" name="sub_strand" placeholder="e.g. Numbers up to 1000">
</div>
<div class="mb-3">
<label class="form-label">Competency</label>
<input class="form-control" name="competency">
</div>
<div class="mb-3">
<label class="form-label">Learning Outcome</label>
<input class="form-control" name="learning_outcome" placeholder="What learner should achieve by end of lesson">
</div>
<div class="mb-3">
<label class="form-label">Grade Band</label>
<select class="form-control" name="grade_band">
<option value="">Select grade band</option>
<option value="PP1">PP1</option>
<option value="PP2">PP2</option>
<option value="G1">Grade 1</option>
<option value="G2">Grade 2</option>
<option value="G3">Grade 3</option>
<option value="G4">Grade 4</option>
<option value="G5">Grade 5</option>
<option value="G6">Grade 6</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Description</label>
<textarea class="form-control" name="description" rows="3"></textarea>
</div>
<button class="btn btn-primary">Save Lesson</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Upload Lesson Content</h3>
<form class="app_frm" action="teacher/core/upload_lesson_content" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<label class="form-label">Lesson</label>
<select class="form-control" name="lesson_id" required>
<option value="">Select lesson</option>
<?php foreach ($lessons as $lesson): ?>
<option value="<?php echo $lesson['id']; ?>"><?php echo htmlspecialchars($lesson['course_name'].' - '.$lesson['title']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Content Type</label>
<select class="form-control" name="content_type" required>
<option value="file">File</option>
<option value="link">Link</option>
<option value="video">Video</option>
<option value="audio">Audio</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Content Title (optional)</label>
<input class="form-control" name="content_title" placeholder="e.g. Audio phonics practice">
</div>
<div class="mb-3">
<label class="form-label">Link (optional)</label>
<input class="form-control" name="url">
</div>
<div class="mb-3">
<label class="form-label">Upload File (optional)</label>
<input class="form-control" type="file" name="file">
</div>
<div class="mb-3 form-check">
<input class="form-check-input" type="checkbox" value="1" id="offlineAvail" name="is_offline_available">
<label class="form-check-label" for="offlineAvail">Available offline for learners</label>
</div>
<button class="btn btn-outline-primary">Add Content</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Create Assignment</h3>
<form class="app_frm" action="teacher/core/create_assignment" method="POST" enctype="multipart/form-data">
<div class="mb-3">
<label class="form-label">Course</label>
<select class="form-control" name="course_id" required>
<option value="">Select course</option>
<?php foreach ($courses as $course): ?>
<option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Title</label>
<input class="form-control" name="title" required>
</div>
<div class="mb-3">
<label class="form-label">Instructions</label>
<textarea class="form-control" name="instructions" rows="3" required></textarea>
</div>
<div class="mb-3">
<label class="form-label">Due Date</label>
<input class="form-control" type="datetime-local" name="due_date">
</div>
<div class="mb-3">
<label class="form-label">Attachment</label>
<input class="form-control" type="file" name="attachment">
</div>
<button class="btn btn-primary">Create Assignment</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Create Live Class</h3>
<form class="app_frm" action="teacher/core/create_live_class" method="POST">
<div class="mb-3">
<label class="form-label">Course</label>
<select class="form-control" name="course_id" required>
<option value="">Select course</option>
<?php foreach ($courses as $course): ?>
<option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Title</label>
<input class="form-control" name="title" required>
</div>
<div class="mb-3">
<label class="form-label">Meeting Link</label>
<input class="form-control" name="meeting_link" required>
</div>
<div class="mb-3">
<label class="form-label">Platform</label>
<select class="form-control" name="platform">
<option>Google Meet</option>
<option>Zoom</option>
</select>
</div>
<div class="mb-3">
<label class="form-label">Start Time</label>
<input class="form-control" type="datetime-local" name="start_time" required>
</div>
<div class="mb-3">
<label class="form-label">End Time</label>
<input class="form-control" type="datetime-local" name="end_time">
</div>
<button class="btn btn-primary">Schedule Class</button>
</form>
</div>
</div>
</div>

<div class="row">
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Create Quiz</h3>
<form class="app_frm" action="teacher/core/create_quiz" method="POST">
<div class="mb-3">
<label class="form-label">Course</label>
<select class="form-control" name="course_id" required>
<option value="">Select course</option>
<?php foreach ($courses as $course): ?>
<option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Quiz Title</label>
<input class="form-control" name="title" required>
</div>
<button class="btn btn-outline-primary">Create Quiz</button>
</form>
</div>
</div>

<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Add Quiz Question</h3>
<form class="app_frm" action="teacher/core/add_quiz_question" method="POST">
<div class="mb-3">
<label class="form-label">Quiz</label>
<select class="form-control" name="quiz_id" required>
<option value="">Select quiz</option>
<?php foreach ($quizzes as $quiz): ?>
<option value="<?php echo $quiz['id']; ?>"><?php echo htmlspecialchars($quiz['title']); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label class="form-label">Question</label>
<textarea class="form-control" name="question" rows="2" required></textarea>
</div>
<div class="mb-3">
<label class="form-label">Options (comma separated)</label>
<input class="form-control" name="options">
</div>
<div class="mb-3">
<label class="form-label">Correct Answer</label>
<input class="form-control" name="correct_answer" required>
</div>
<div class="mb-3">
<label class="form-label">Marks</label>
<input class="form-control" type="number" name="marks" value="1" step="0.5">
</div>
<button class="btn btn-primary">Add Question</button>
</form>
</div>
</div>
</div>

<div class="tile">
<h3 class="tile-title">Assignment Submissions</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>Assignment</th><th>Student</th><th>Submitted</th><th>Score</th><th>Feedback</th></tr></thead>
<tbody>
<?php foreach ($submissions as $row): ?>
<tr>
<td><?php echo htmlspecialchars($row['assignment_title']); ?></td>
<td><?php echo htmlspecialchars($row['student_id']); ?></td>
<td><?php echo htmlspecialchars($row['submitted_at']); ?></td>
<td>
  <form class="d-flex gap-2" method="POST" action="teacher/core/grade_submission">
    <input type="hidden" name="submission_id" value="<?php echo (int)$row['id']; ?>">
    <input class="form-control form-control-sm" type="number" step="0.1" name="score" value="<?php echo htmlspecialchars($row['score']); ?>" style="max-width:120px;">
    <input class="form-control form-control-sm" name="feedback" value="<?php echo htmlspecialchars($row['feedback']); ?>" placeholder="Feedback">
    <button class="btn btn-sm btn-outline-success">Save</button>
  </form>
</td>
<td><?php echo htmlspecialchars($row['feedback']); ?></td>
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
