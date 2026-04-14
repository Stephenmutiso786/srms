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
$qtypeRaw = strtolower(trim((string)($_POST['qtype'] ?? 'mcq')));
$options = trim($_POST['options'] ?? '');
$correct = trim($_POST['correct_answer'] ?? '');
$marks = (float)($_POST['marks'] ?? 1);

$allowedTypes = ['mcq', 'true_false', 'fill_blank', 'short_answer'];
$qtype = in_array($qtypeRaw, $allowedTypes, true) ? $qtypeRaw : 'mcq';
if ($marks <= 0) {
  $marks = 1;
}

if ($quizId < 1 || $question === '') {
  $_SESSION['reply'] = array (array("danger", "Missing question details."));
  header("location:../elearning");
  exit;
}

if (in_array($qtype, ['mcq', 'true_false', 'fill_blank'], true) && $correct === '') {
  $_SESSION['reply'] = array (array("danger", "Correct answer is required for this question type."));
  header("location:../elearning");
  exit;
}

if ($qtype === 'true_false') {
  $options = 'True,False';
  $normalized = strtolower($correct);
  if ($normalized === 'true' || $normalized === 't') {
    $correct = 'True';
  } elseif ($normalized === 'false' || $normalized === 'f') {
    $correct = 'False';
  } else {
    $_SESSION['reply'] = array (array("danger", "Correct answer for True/False must be True or False."));
    header("location:../elearning");
    exit;
  }
}

if ($qtype === 'mcq') {
  $opts = array_values(array_filter(array_map('trim', explode(',', $options)), function ($v) {
    return $v !== '';
  }));
  if (count($opts) < 2) {
    $_SESSION['reply'] = array (array("danger", "MCQ requires at least two options."));
    header("location:../elearning");
    exit;
  }
  $options = implode(',', $opts);
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
  $stmt->execute([$quizId, $question, $qtype, $options, $correct, $marks]);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.quiz.question', 'quiz', (string)$quizId);
  $_SESSION['reply'] = array (array("success", "Question added."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}
header("location:../elearning");
exit;
