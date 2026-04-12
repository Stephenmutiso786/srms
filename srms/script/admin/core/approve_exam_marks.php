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
  $stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'reviewed', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ? AND status = 'submitted'");
  $stmt->execute([(int)$account_id, $submissionId]);
  if ((int)$stmt->rowCount() < 1) {
    throw new RuntimeException("Submission is no longer in submitted state.");
  }
  $meta = $conn->prepare("SELECT exam_id FROM tbl_exam_mark_submissions WHERE id = ? LIMIT 1");
  $meta->execute([$submissionId]);
  $examId = (int)$meta->fetchColumn();
  if ($examId > 0) {
    app_refresh_exam_status($conn, $examId);
  }
  app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.approve', 'submission', (string)$submissionId);
  $_SESSION['reply'] = array (array("success", "Marks reviewed successfully."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}
header("location:../marks_review");
exit;
