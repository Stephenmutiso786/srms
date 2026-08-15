<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');
$canOverrideMarks = app_current_user_can_override_marks();
if ($res == "1" && ($level == "2" || $canOverrideMarks)) {}else{header("location:../");}

function app_exam_entry_redirect_target(string $portal, string $page): string
{
  $portal = strtolower(trim($portal));
  if (!in_array($portal, ['admin', 'academic', 'teacher'], true)) {
    $portal = 'teacher';
  }
  return '../../' . $portal . '/' . ltrim($page, '/');
}

$originPortal = strtolower(trim((string)($_POST['origin_portal'] ?? ($_SESSION['exam_entry']['origin_portal'] ?? $_SESSION['exam_entry_portal'] ?? 'teacher'))));
if (!in_array($originPortal, ['admin', 'academic', 'teacher'], true)) {
  $originPortal = 'teacher';
}
$_SESSION['exam_entry_portal'] = $originPortal;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_entry'));
  exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$subjectComb = (int)($_POST['subject_combination'] ?? 0);

if ($examId < 1 || $subjectComb < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing exam or subject."));
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_table'));
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  app_ensure_exam_grading_schema($conn);

  $stmt = $conn->prepare("SELECT * FROM tbl_exams WHERE id = ? LIMIT 1");
  $stmt->execute([$examId]);
  $exam = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$exam || (!app_exam_teacher_can_reenter($conn, $examId, $subjectComb, (int)$account_id, (string)($exam['status'] ?? 'draft')) && !$canOverrideMarks)) {
    throw new RuntimeException("Exam not found or not active.");
  }

  $stmt = $conn->prepare("SELECT id, class, teacher FROM tbl_subject_combinations WHERE id = ?");
  $stmt->execute([$subjectComb]);
  $combo = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$combo || !app_teacher_can_enter_exam_subject($conn, (int)$account_id, $examId, $subjectComb)) {
    throw new RuntimeException("Not assigned to this subject.");
  }
  $classList = app_unserialize($combo['class']);
  if (!in_array((string)$exam['class_id'], array_map('strval', $classList), true)) {
    throw new RuntimeException("Subject not assigned to exam class.");
  }

  if (app_results_locked($conn, (int)$exam['class_id'], (int)$exam['term_id'], $examId)) {
    throw new RuntimeException("Results are locked for this class/term.");
  }

  // Check missing marks
  $stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
  $stmt->execute([(int)$exam['class_id']]);
  $studentIds = array_map(function ($row) { return (string)$row['id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
  $total = count($studentIds);
  if ($total < 1) {
    throw new RuntimeException("No students found for this class.");
  }

  $useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
  if ($useExamId) {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT student) FROM tbl_exam_results WHERE exam_id = ? AND subject_combination = ?");
    $stmt->execute([$examId, $subjectComb]);
  } else {
    $stmt = $conn->prepare("SELECT COUNT(DISTINCT student) FROM tbl_exam_results WHERE class = ? AND term = ? AND subject_combination = ?");
    $stmt->execute([(int)$exam['class_id'], (int)$exam['term_id'], $subjectComb]);
  }
  $filled = (int)$stmt->fetchColumn();
  if ($filled < $total) {
    throw new RuntimeException("Marks missing for ".($total - $filled)." students.");
  }

  if (!app_table_exists($conn, 'tbl_exam_mark_submissions')) {
    throw new RuntimeException("Marks submission table not installed.");
  }

  $stmt = $conn->prepare("SELECT id, status FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
  $stmt->execute([$examId, $subjectComb]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
    if (in_array($existing['status'], ['submitted','reviewed','finalized'], true)) {
      throw new RuntimeException("Marks already submitted.");
    }
    $stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, reviewed_at = NULL, reviewed_by = NULL, review_note = NULL WHERE id = ?");
    $stmt->execute([$existing['id']]);
  } else {
    $stmt = $conn->prepare("INSERT INTO tbl_exam_mark_submissions (exam_id, class_id, term_id, subject_combination_id, teacher_id, status, submitted_at) VALUES (?,?,?,?,?,'submitted',CURRENT_TIMESTAMP)");
    $stmt->execute([$examId, (int)$exam['class_id'], (int)$exam['term_id'], $subjectComb, (int)$account_id]);
  }

  app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.submit', 'exam', (string)$examId);
  app_refresh_exam_status($conn, $examId);
  $_SESSION['reply'] = array (array("success", "Marks submitted for review."));
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_table'));
  exit;
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
  header("location:" . app_exam_entry_redirect_target($originPortal, 'exam_marks_table'));
  exit;
}
