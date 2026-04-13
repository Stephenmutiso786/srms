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

  $stmt = $conn->prepare("SELECT c.id AS course_id, c.class_id FROM tbl_quizzes q JOIN tbl_courses c ON c.id = q.course_id WHERE q.id = ? LIMIT 1");
  $stmt->execute([$quizId]);
  $quizMeta = $stmt->fetch(PDO::FETCH_ASSOC);
  $classId = (int)($quizMeta['class_id'] ?? 0);
  $courseId = (int)($quizMeta['course_id'] ?? 0);
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

  if ($courseId > 0 && app_table_exists($conn, 'tbl_elearning_progress')) {
    $level = 'BE';
    if ($score >= 80) {
      $level = 'EE';
    } elseif ($score >= 60) {
      $level = 'ME';
    } elseif ($score >= 40) {
      $level = 'AE';
    }

    $stmt = $conn->prepare("SELECT id, completion_pct FROM tbl_elearning_progress WHERE student_id = ? AND course_id = ? AND lesson_id IS NULL LIMIT 1");
    $stmt->execute([$account_id, $courseId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($progress) {
      $nextPct = min(100, max((float)$progress['completion_pct'], 35));
      $stmt = $conn->prepare("UPDATE tbl_elearning_progress SET score = ?, completion_pct = ?, competency_level = ?, last_activity_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
      $stmt->execute([$score, $nextPct, $level, (int)$progress['id']]);
    } else {
      $stmt = $conn->prepare("INSERT INTO tbl_elearning_progress (student_id, course_id, lesson_id, competency_level, completion_pct, score) VALUES (?,?,?,?,?,?)");
      $stmt->execute([$account_id, $courseId, null, $level, 35, $score]);
    }
  }

  app_audit_log($conn, 'student', (string)$account_id, 'elearning.quiz.submit', 'quiz', (string)$quizId);
  $_SESSION['reply'] = array (array("success", "Quiz submitted. Score: ".number_format($score, 2)));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
