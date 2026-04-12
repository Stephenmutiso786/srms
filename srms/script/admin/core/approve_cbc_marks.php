<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('marks.review', 'admin');

$submissionId = (int)($_POST['submission_id'] ?? 0);
if ($submissionId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing submission."));
  header("location:../marks_review");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $meta = $conn->prepare("SELECT class_id, term_id FROM tbl_cbc_mark_submissions WHERE id = ? LIMIT 1");
  $meta->execute([$submissionId]);
  $submissionMeta = $meta->fetch(PDO::FETCH_ASSOC);
  $classId = (int)($submissionMeta['class_id'] ?? 0);
  $termId = (int)($submissionMeta['term_id'] ?? 0);

  $stmt = $conn->prepare("UPDATE tbl_cbc_mark_submissions SET status = 'approved', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ? AND status = 'submitted'");
  $stmt->execute([(int)$account_id, $submissionId]);
  if ((int)$stmt->rowCount() < 1) {
    throw new RuntimeException("Submission is no longer in submitted state.");
  }

  if ($classId > 0 && $termId > 0 && app_table_exists($conn, 'tbl_exams')) {
    $examStmt = $conn->prepare("SELECT id FROM tbl_exams WHERE class_id = ? AND term_id = ? AND COALESCE(assessment_mode, 'normal') = 'cbc'");
    $examStmt->execute([$classId, $termId]);
    foreach ($examStmt->fetchAll(PDO::FETCH_COLUMN) as $examId) {
      app_refresh_exam_status($conn, (int)$examId);
    }
  }

  app_audit_log($conn, 'staff', (string)$account_id, 'cbc_marks.approve', 'submission', (string)$submissionId);
  $_SESSION['reply'] = array (array("success", "CBC marks approved."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}
header("location:../marks_review");
exit;
