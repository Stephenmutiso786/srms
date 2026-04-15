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
$autoSubmit = ((string)($_POST['auto_submit'] ?? '0') === '1');
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

  $stmt = $conn->prepare("SELECT id, qtype, correct_answer, marks FROM tbl_quiz_questions WHERE quiz_id = ?");
  $stmt->execute([$quizId]);
  $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $score = 0.0;
  $totalMarks = 0.0;
  $pendingManual = 0;

  foreach ($questions as $q) {
    $qid = (int)$q['id'];
    $qtype = strtolower(trim((string)($q['qtype'] ?? 'mcq')));
    $marks = (float)$q['marks'];
    if ($marks < 0) {
      $marks = 0;
    }

    $correct = trim((string)$q['correct_answer']);
    $ans = trim((string)($answers[$qid] ?? ''));

    $totalMarks += $marks;

    if ($qtype === 'short_answer' && $correct === '') {
      if ($ans !== '') {
        $pendingManual++;
      }
      continue;
    }

    if ($ans !== '' && $correct !== '' && strcasecmp($ans, $correct) === 0) {
      $score += $marks;
    }
  }

  $scorePct = $totalMarks > 0 ? (($score / $totalMarks) * 100) : 0.0;

  $stmt = $conn->prepare("SELECT id FROM tbl_quiz_results WHERE quiz_id = ? AND student_id = ? LIMIT 1");
  $stmt->execute([$quizId, $account_id]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($existing) {
    $stmt = $conn->prepare("UPDATE tbl_quiz_results SET score = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$scorePct, $existing['id']]);
  } else {
    $stmt = $conn->prepare("INSERT INTO tbl_quiz_results (quiz_id, student_id, score) VALUES (?,?,?)");
    $stmt->execute([$quizId, $account_id, $scorePct]);
  }

  if ($courseId > 0 && app_table_exists($conn, 'tbl_elearning_progress')) {
    $competencyLevel = 'BE';

    if (app_table_exists($conn, 'tbl_cbc_grading')) {
      $stmt = $conn->prepare("SELECT level, min_mark, max_mark, sort_order FROM tbl_cbc_grading WHERE active = 1 ORDER BY min_mark DESC, sort_order ASC");
      $stmt->execute();
      $bands = $stmt->fetchAll(PDO::FETCH_ASSOC);
      foreach ($bands as $band) {
        $min = (float)$band['min_mark'];
        $max = (float)$band['max_mark'];
        if ($scorePct >= $min && $scorePct <= $max) {
          $competencyLevel = strtoupper(trim((string)$band['level']));
          break;
        }
      }
    }

    if ($competencyLevel === 'BE') {
      if ($scorePct >= 80) {
        $competencyLevel = 'EE';
      } elseif ($scorePct >= 60) {
        $competencyLevel = 'ME';
      } elseif ($scorePct >= 40) {
        $competencyLevel = 'AE';
      }
    }

    $stmt = $conn->prepare("SELECT id, completion_pct FROM tbl_elearning_progress WHERE student_id = ? AND course_id = ? AND lesson_id IS NULL LIMIT 1");
    $stmt->execute([$account_id, $courseId]);
    $progress = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($progress) {
      $nextPct = min(100, max((float)$progress['completion_pct'], 35));
      $stmt = $conn->prepare("UPDATE tbl_elearning_progress SET score = ?, completion_pct = ?, competency_level = ?, last_activity_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
      $stmt->execute([$scorePct, $nextPct, $competencyLevel, (int)$progress['id']]);
    } else {
      $stmt = $conn->prepare("INSERT INTO tbl_elearning_progress (student_id, course_id, lesson_id, competency_level, completion_pct, score) VALUES (?,?,?,?,?,?)");
      $stmt->execute([$account_id, $courseId, null, $competencyLevel, 35, $scorePct]);
    }
  }

  app_audit_log($conn, 'student', (string)$account_id, 'elearning.quiz.submit', 'quiz', (string)$quizId);
  $summaryPrefix = $autoSubmit ? "Quiz auto-submitted when time ended." : "Quiz submitted.";
  $summary = $summaryPrefix . " Score: " . number_format($score, 2) . " / " . number_format($totalMarks, 2) . " (" . number_format($scorePct, 1) . "%).";
  if ($pendingManual > 0) {
    $summary .= " " . $pendingManual . " short answer response(s) need manual review.";
  }
  $_SESSION['reply'] = array (array("success", $summary));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
