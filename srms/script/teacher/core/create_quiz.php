<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../elearning");
  exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$title = trim($_POST['title'] ?? '');

if ($courseId < 1 || $title === '') {
  $_SESSION['reply'] = array (array("danger", "Missing quiz details."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT teacher_id FROM tbl_courses WHERE id = ? LIMIT 1");
  $stmt->execute([$courseId]);
  if ((int)$stmt->fetchColumn() !== (int)$account_id) {
    throw new RuntimeException("Not allowed to create quiz for this course.");
  }

  $stmt = $conn->prepare("INSERT INTO tbl_quizzes (course_id, title, created_by) VALUES (?,?,?)");
  $stmt->execute([$courseId, $title, (int)$account_id]);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.quiz.create', 'quiz', (string)$conn->lastInsertId());

  $_SESSION['reply'] = array (array("success", "Quiz created."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
