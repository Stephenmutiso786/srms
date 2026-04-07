<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../marks_entry");
  exit;
}

$termId = (int)($_POST['term_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$subjectComb = (int)($_POST['subject_combination'] ?? 0);
$subjectId = (int)($_POST['subject_id'] ?? 0);

if ($termId < 1 || $classId < 1 || $subjectComb < 1 || $subjectId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing submission details."));
  header("location:../cbc_entry");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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

  if (app_results_locked($conn, $classId, $termId)) {
    throw new RuntimeException("Results are locked for this class/term.");
  }

  // Missing marks check
  $stmt = $conn->prepare("SELECT id FROM tbl_students WHERE class = ?");
  $stmt->execute([$classId]);
  $studentIds = array_map(function ($row) { return (string)$row['id']; }, $stmt->fetchAll(PDO::FETCH_ASSOC));
  $total = count($studentIds);
  if ($total < 1) {
    throw new RuntimeException("No students found for this class.");
  }

  $stmt = $conn->prepare("SELECT COUNT(DISTINCT student_id) FROM tbl_cbc_assessments WHERE class_id = ? AND term_id = ? AND subject_id = ?");
  $stmt->execute([$classId, $termId, $subjectId]);
  $filled = (int)$stmt->fetchColumn();
  if ($filled < $total) {
    throw new RuntimeException("Marks missing for ".($total - $filled)." students.");
  }

  if (!app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
    throw new RuntimeException("Marks submission table not installed.");
  }

  $stmt = $conn->prepare("SELECT id, status FROM tbl_cbc_mark_submissions WHERE term_id = ? AND class_id = ? AND subject_combination_id = ? LIMIT 1");
  $stmt->execute([$termId, $classId, $subjectComb]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
    if (in_array($existing['status'], ['submitted','approved'], true)) {
      throw new RuntimeException("Marks already submitted.");
    }
    $stmt = $conn->prepare("UPDATE tbl_cbc_mark_submissions SET status = 'submitted', submitted_at = CURRENT_TIMESTAMP, reviewed_at = NULL, reviewed_by = NULL, review_note = NULL WHERE id = ?");
    $stmt->execute([$existing['id']]);
  } else {
    $stmt = $conn->prepare("INSERT INTO tbl_cbc_mark_submissions (term_id, class_id, subject_id, subject_combination_id, teacher_id, status, submitted_at) VALUES (?,?,?,?,?,'submitted',CURRENT_TIMESTAMP)");
    $stmt->execute([$termId, $classId, $subjectId, $subjectComb, (int)$account_id]);
  }

  app_audit_log($conn, 'staff', (string)$account_id, 'cbc_marks.submit', 'cbc', $classId.':'.$termId);
  $_SESSION['reply'] = array (array("success", "Marks submitted for review."));
  header("location:../cbc_entry");
  exit;
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
  header("location:../cbc_entry");
  exit;
}
