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

$quizId = (int)($_POST['quiz_id'] ?? 0);
$question = trim($_POST['question'] ?? '');
$options = trim($_POST['options'] ?? '');
$correct = trim($_POST['correct_answer'] ?? '');
$marks = (float)($_POST['marks'] ?? 1);

if ($quizId < 1 || $question === '' || $correct === '') {
  $_SESSION['reply'] = array (array("danger", "Missing question details."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt = $conn->prepare("SELECT c.teacher_id FROM tbl_quizzes q JOIN tbl_courses c ON c.id = q.course_id WHERE q.id = ? LIMIT 1");
  $stmt->execute([$quizId]);
  if ((int)$stmt->fetchColumn() !== (int)$account_id) {
    throw new RuntimeException("Not allowed to edit this quiz.");
  }
  $stmt = $conn->prepare("INSERT INTO tbl_quiz_questions (quiz_id, question, qtype, options, correct_answer, marks) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$quizId, $question, 'mcq', $options, $correct, $marks]);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.quiz.question', 'quiz', (string)$quizId);
  $_SESSION['reply'] = array (array("success", "Question added."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}
header("location:../elearning");
exit;
