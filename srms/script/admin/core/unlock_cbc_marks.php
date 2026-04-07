<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res != "1") { header("location:../"); }
app_require_permission('marks.review', 'admin');

$submissionId = (int)($_POST['submission_id'] ?? 0);
if ($submissionId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing submission."));
  header("location:../marks_review");
  exit;
}

try {
  if ((int)$level !== 9) {
    throw new RuntimeException("Only Super Admin can unlock marks.");
  }
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $stmt = $conn->prepare("UPDATE tbl_cbc_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ? AND status = 'approved'");
  $stmt->execute([(int)$account_id, $submissionId]);
  app_audit_log($conn, 'staff', (string)$account_id, 'cbc_marks.unlock', 'submission', (string)$submissionId);
  $_SESSION['reply'] = array (array("success", "CBC marks unlocked to draft."));
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
}
header("location:../marks_review");
exit;
