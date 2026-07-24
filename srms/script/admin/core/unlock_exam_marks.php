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
  $beforeSnapshot = app_exam_submission_archive_payload($conn, $submissionId);
  $stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'draft', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ? WHERE id = ? AND status IN ('reviewed','finalized')");
  $stmt->execute([(int)$account_id, $submissionId]);
  $meta = $conn->prepare("SELECT exam_id FROM tbl_exam_mark_submissions WHERE id = ? LIMIT 1");
  $meta->execute([$submissionId]);
  $examId = (int)$meta->fetchColumn();
  if ($examId > 0) {
    app_refresh_exam_status($conn, $examId);
  }
  app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.unlock', 'submission', (string)$submissionId);
  $afterSnapshot = app_exam_submission_archive_payload($conn, $submissionId);
  $submissionMeta = (array)($afterSnapshot['submission'] ?? $beforeSnapshot['submission'] ?? []);
  app_data_camp_store_event($conn, [
    'module_key' => 'exams',
    'record_type' => 'exam_marks_unlocked',
    'entity_table' => 'tbl_exam_mark_submissions',
    'entity_id' => (string)$submissionId,
    'title' => 'Exam Mark Submission #' . (string)$submissionId,
    'description' => 'Marks submission snapshot retained before and after unlock',
    'class_id' => (int)($submissionMeta['class_id'] ?? 0) > 0 ? (int)$submissionMeta['class_id'] : null,
    'owner_portal' => 'admin,academic,teacher',
    'mime_type' => 'application/json',
    'status' => 'retained',
    'payload_json' => [
      'before' => $beforeSnapshot,
      'after' => $afterSnapshot,
    ],
    'created_by' => (int)$account_id,
  ]);
  $_SESSION['reply'] = array (array("success", "Marks unlocked to draft."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}
header("location:../marks_review");
exit;
