<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../exam_marks_entry");
  exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$termId = (int)($_POST['term_id'] ?? 0);
$subjectComb = (int)($_POST['subject_combination'] ?? 0);
$scores = $_POST['scores'] ?? [];

if ($examId < 1 || $classId < 1 || $termId < 1 || $subjectComb < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing entry parameters."));
  header("location:../exam_marks_table");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  if (app_results_locked($conn, $classId, $termId)) {
    throw new RuntimeException("Results are locked for this class/term.");
  }

  if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
    $stmt = $conn->prepare("SELECT status FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
    $stmt->execute([$examId, $subjectComb]);
    $status = (string)$stmt->fetchColumn();
    if (in_array($status, ['submitted','reviewed','finalized'], true)) {
      throw new RuntimeException("Marks are submitted and locked.");
    }
  }

  $stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
  $stmt->execute([$examId]);
  $exam = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$exam || !app_exam_can_enter_marks((string)($exam['status'] ?? 'draft'))) {
    throw new RuntimeException("Exam not found or not active.");
  }

  $stmt = $conn->prepare("SELECT id, class, teacher FROM tbl_subject_combinations WHERE id = ?");
  $stmt->execute([$subjectComb]);
  $combo = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$combo || (int)$combo['teacher'] !== (int)$account_id) {
    throw new RuntimeException("Not assigned to this subject.");
  }
  $classList = app_unserialize($combo['class']);
  if (!in_array((string)$classId, array_map('strval', $classList), true)) {
    throw new RuntimeException("Subject not assigned to selected class.");
  }

  $stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
  $stmt->execute([$classId]);
  $validStudents = array_map(function ($row) { return (string)$row['id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
  $validLookup = array_flip($validStudents);

  $useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

  $conn->beginTransaction();
  foreach ($scores as $studentId => $score) {
    $studentId = (string)$studentId;
    if (!isset($validLookup[$studentId])) {
      continue;
    }
    if ($score === '' || $score === null) {
      continue;
    }
    $scoreVal = (float)$score;
    if ($scoreVal < 0) { $scoreVal = 0; }
    if ($scoreVal > 100) { $scoreVal = 100; }

    if ($useExamId) {
      $stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE exam_id = ? AND student = ? AND subject_combination = ? LIMIT 1");
      $stmt->execute([$examId, $studentId, $subjectComb]);
    } else {
      $stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
      $stmt->execute([$studentId, $classId, $subjectComb, $termId]);
    }
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
      $stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
      $stmt->execute([$scoreVal, $existing['id']]);
    } else {
      if ($useExamId) {
        $stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal, $examId]);
      } else {
        $stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
        $stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal]);
      }
    }
  }
  $conn->commit();

  $_SESSION['reply'] = array (array("success", "Exam marks saved."));
  header("location:../exam_marks_table");
  exit;
} catch (Throwable $e) {
  if ($conn && $conn->inTransaction()) {
    $conn->rollBack();
  }
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
  header("location:../exam_marks_table");
  exit;
}
