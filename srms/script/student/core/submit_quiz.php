<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../elearning");
  exit;
}

$quizId = (int)($_POST['quiz_id'] ?? 0);
$answers = $_POST['answers'] ?? [];
if ($quizId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing quiz."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT c.class_id FROM tbl_quizzes q JOIN tbl_courses c ON c.id = q.course_id WHERE q.id = ? LIMIT 1");
  $stmt->execute([$quizId]);
  $classId = (int)$stmt->fetchColumn();
  if ($classId < 1) {
    throw new RuntimeException("Quiz not found.");
  }

  $stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
  $stmt->execute([$account_id]);
  if ((int)$stmt->fetchColumn() !== $classId) {
    throw new RuntimeException("Not allowed to take this quiz.");
  }

  $stmt = $conn->prepare("SELECT id, correct_answer, marks FROM tbl_quiz_questions WHERE quiz_id = ?");
  $stmt->execute([$quizId]);
  $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $score = 0;
  foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $correct = trim((string)$q['correct_answer']);
    $ans = trim((string)($answers[$qid] ?? ''));
    if ($ans !== '' && strcasecmp($ans, $correct) === 0) {
      $score += (float)$q['marks'];
    }
  }

  $stmt = $conn->prepare("SELECT id FROM tbl_quiz_results WHERE quiz_id = ? AND student_id = ? LIMIT 1");
  $stmt->execute([$quizId, $account_id]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($existing) {
    $stmt = $conn->prepare("UPDATE tbl_quiz_results SET score = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$score, $existing['id']]);
  } else {
    $stmt = $conn->prepare("INSERT INTO tbl_quiz_results (quiz_id, student_id, score) VALUES (?,?,?)");
    $stmt->execute([$quizId, $account_id, $score]);
  }

  app_audit_log($conn, 'student', (string)$account_id, 'elearning.quiz.submit', 'quiz', (string)$quizId);
  $_SESSION['reply'] = array (array("success", "Quiz submitted. Score: ".number_format($score, 2)));
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
}

header("location:../elearning");
exit;
