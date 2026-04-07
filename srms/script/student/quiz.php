<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

$quizId = (int)($_GET['id'] ?? 0);
$quiz = null;
$questions = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT q.*, c.class_id
		FROM tbl_quizzes q
		JOIN tbl_courses c ON c.id = q.course_id
		WHERE q.id = ? LIMIT 1");
	$stmt->execute([$quizId]);
	$quiz = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$quiz) {
		throw new RuntimeException("Quiz not found.");
	}

	$stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
	$stmt->execute([$account_id]);
	if ((int)$stmt->fetchColumn() !== (int)$quiz['class_id']) {
		throw new RuntimeException("Not allowed to take this quiz.");
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_quiz_questions WHERE quiz_id = ? ORDER BY id");
	$stmt->execute([$quizId]);
	$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Quiz</title>
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
<li><a class="dropdown-item" href="student/elearning"><i class="bi bi-arrow-left me-2 fs-5"></i> Back</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<main class="app-content">
<div class="app-title">
<div>
<h1><?php echo htmlspecialchars($quiz['title'] ?? 'Quiz'); ?></h1>
</div>
</div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<form class="app_frm" method="POST" action="student/core/submit_quiz">
<input type="hidden" name="quiz_id" value="<?php echo (int)$quizId; ?>">
<?php foreach ($questions as $q): ?>
  <div class="tile">
    <h3 class="tile-title"><?php echo htmlspecialchars($q['question']); ?></h3>
    <?php
      $opts = array_filter(array_map('trim', explode(',', $q['options'] ?? '')));
    ?>
    <?php if ($q['qtype'] === 'mcq' && count($opts) > 0): ?>
      <?php foreach ($opts as $opt): ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="answers[<?php echo (int)$q['id']; ?>]" value="<?php echo htmlspecialchars($opt); ?>" required>
          <label class="form-check-label"><?php echo htmlspecialchars($opt); ?></label>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <input class="form-control" name="answers[<?php echo (int)$q['id']; ?>]" required>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
<button class="btn btn-primary">Submit Quiz</button>
</form>
<?php } ?>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
