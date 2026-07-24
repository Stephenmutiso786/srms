<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

function app_exam_entry_redirect_target(string $portal, string $page): string
{
  $portal = strtolower(trim($portal));
  if (!in_array($portal, ['admin', 'academic', 'teacher'], true)) {
    $portal = 'teacher';
  }
  return '../../' . $portal . '/' . ltrim($page, '/');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  $originPortal = strtolower(trim((string)($_SESSION['exam_entry_portal'] ?? 'teacher')));
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_entry'));
  exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$subjectComb = (int)($_POST['subject_combination'] ?? 0);
$assessmentMode = strtolower(trim((string)($_POST['assessment_mode'] ?? 'normal'))) === 'cbe' ? 'cbe' : 'normal';
$originPortal = strtolower(trim((string)($_POST['origin_portal'] ?? ($_SESSION['exam_entry_portal'] ?? 'teacher'))));
if (!in_array($originPortal, ['admin', 'academic', 'teacher'], true)) {
  $originPortal = 'teacher';
}
$_SESSION['exam_entry_portal'] = $originPortal;

if ($examId < 1 || $subjectComb < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing exam or subject."));
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_entry'));
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_exam_grading_schema($conn);
  app_ensure_exam_subjects_table($conn);

  // Load exam regardless of status; allow opening if there's a rejected submission for this teacher
  $stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
  $stmt->execute([$examId]);
  $exam = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$exam) {
    throw new RuntimeException("Exam not found.");
  }

  // If exam is not active/open, allow entry only when this teacher has a rejected submission for this exam+subject
  if (!in_array((string)($exam['status'] ?? ''), ['active','open'], true)) {
    if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
      $chk = $conn->prepare("SELECT id FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? AND teacher_id = ? AND status = 'rejected' LIMIT 1");
      $chk->execute([$examId, $subjectComb, (int)$account_id]);
      if (!$chk->fetchColumn()) {
        throw new RuntimeException("Exam not open for mark entry.");
      }
      // else: proceed because teacher needs to correct rejected submission
    } else {
      throw new RuntimeException("Exam not open for mark entry.");
    }
  }

  // Check if exam is locked (for national assessments like KJSEA/KPSEA)
  if (app_is_exam_locked($conn, $examId)) {
    throw new RuntimeException("This exam is locked and cannot be edited. Contact the administration if you need to make changes.");
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
    if (!app_teacher_assignment_is_effective($conn, (int)$account_id, (int)$exam['class_id'], (int)$combo['subject'], (int)$exam['term_id'], (int)($exam['year'] ?? date('Y')))) {
      throw new RuntimeException("No active assignment for this class/subject/term.");
    }
    app_sync_subject_combination($conn, (int)$account_id, (int)$combo['subject'], (int)$exam['class_id'], false);
  }

  $examMode = app_exam_assessment_mode($conn, (int)$exam['id']);
  if ($examMode === 'consolidated') {
    throw new RuntimeException("Consolidated exams are auto-computed from selected source exams and do not accept direct mark entry.");
  }
  if ($assessmentMode === 'cbe' || $examMode === 'cbe') {
    $cbeEntryMode = 'cbe';
    if (app_table_exists($conn, 'tbl_cbe_assessments') && app_column_exists($conn, 'tbl_cbe_assessments', 'marks')) {
      $cbeEntryMode = 'marks';
    }
    $_SESSION['cbe_entry'] = [
      'term' => (int)$exam['term_id'],
      'class' => (int)$exam['class_id'],
      'subject' => (int)$combo['id'],
      'mode' => $cbeEntryMode,
      'exam_id' => (int)$exam['id'],
      'origin_portal' => $originPortal,
    ];
    header("location:" . app_exam_entry_redirect_target($originPortal, 'cbe_entry'));
    exit;
  }

  $_SESSION['exam_entry'] = [
    'exam_id' => (int)$exam['id'],
    'class_id' => (int)$exam['class_id'],
    'term_id' => (int)$exam['term_id'],
    'subject_combination' => (int)$combo['id'],
    'origin_portal' => $originPortal,
  ];

  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_table'));
  exit;
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", $e->getMessage() !== '' ? $e->getMessage() : "Operation failed. Please try again."));
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_entry'));
  exit;
}
