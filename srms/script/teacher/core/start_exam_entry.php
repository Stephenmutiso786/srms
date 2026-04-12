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
$subjectComb = (int)($_POST['subject_combination'] ?? 0);
$assessmentMode = strtolower(trim((string)($_POST['assessment_mode'] ?? 'normal'))) === 'cbc' ? 'cbc' : 'normal';

if ($examId < 1 || $subjectComb < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing exam or subject."));
  header("location:../exam_marks_entry");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_exam_subjects_table($conn);

  $stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? AND status = 'active' LIMIT 1");
  $stmt->execute([$examId]);
  $exam = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$exam) {
    throw new RuntimeException("Exam not found or not active.");
  }

  $stmt = $conn->prepare("SELECT id, class, teacher, subject FROM tbl_subject_combinations WHERE id = ?");
  $stmt->execute([$subjectComb]);
  $combo = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$combo || (int)$combo['teacher'] !== (int)$account_id) {
    throw new RuntimeException("Not assigned to that subject.");
  }
  $classList = app_unserialize($combo['class']);
  if (!in_array((string)$exam['class_id'], array_map('strval', $classList), true)) {
    throw new RuntimeException("Subject not assigned to exam class.");
  }
  if (!app_exam_has_subject($conn, (int)$exam['id'], (int)$combo['subject'])) {
    throw new RuntimeException("That subject is not enabled for this exam.");
  }

  if (app_table_exists($conn, 'tbl_teacher_assignments')) {
    $stmt = $conn->prepare("SELECT id FROM tbl_teacher_assignments
      WHERE teacher_id = ? AND class_id = ? AND subject_id = ? AND term_id = ? AND status = 1
      ORDER BY year DESC, id DESC LIMIT 1");
    $stmt->execute([(int)$account_id, (int)$exam['class_id'], (int)$combo['subject'], (int)$exam['term_id']]);
    if (!$stmt->fetchColumn()) {
      throw new RuntimeException("No active assignment for this class/subject/term.");
    }
    app_sync_subject_combination($conn, (int)$account_id, (int)$combo['subject'], (int)$exam['class_id'], false);
  }

  $examMode = app_exam_assessment_mode($conn, (int)$exam['id']);
  if ($assessmentMode === 'cbc' || $examMode === 'cbc') {
    $cbcEntryMode = 'cbc';
    if (app_table_exists($conn, 'tbl_cbc_assessments') && app_column_exists($conn, 'tbl_cbc_assessments', 'marks')) {
      $cbcEntryMode = 'marks';
    }
    $_SESSION['cbc_entry'] = [
      'term' => (int)$exam['term_id'],
      'class' => (int)$exam['class_id'],
      'subject' => (int)$combo['id'],
      'mode' => $cbcEntryMode,
      'exam_id' => (int)$exam['id'],
    ];
    header("location:../cbc_entry");
    exit;
  }

  $_SESSION['exam_entry'] = [
    'exam_id' => (int)$exam['id'],
    'class_id' => (int)$exam['class_id'],
    'term_id' => (int)$exam['term_id'],
    'subject_combination' => (int)$combo['id']
  ];

  header("location:../exam_marks_table");
  exit;
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
  header("location:../exam_marks_entry");
  exit;
}
