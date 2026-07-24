<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
if ($res !== "1") { header("location:../../"); exit; }
$portalHome = ((string)$level === '1') ? '../../academic' : '../marks_review';
app_require_permission('marks.review', $portalHome);

$submissionId = (int)($_POST['submission_id'] ?? 0);
if ($submissionId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing submission."));
  header("location:../marks_review");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $beforeSnapshot = app_exam_submission_archive_payload($conn, $submissionId);
  $reason = trim((string)($_POST['reason'] ?? ''));
  $stmt = $conn->prepare("UPDATE tbl_exam_mark_submissions SET status = 'rejected', reviewed_at = CURRENT_TIMESTAMP, reviewed_by = ?, review_note = ? WHERE id = ? AND status = 'submitted'");
  $stmt->execute([(int)$account_id, $reason !== '' ? $reason : null, $submissionId]);
  if ((int)$stmt->rowCount() < 1) {
    throw new RuntimeException("Submission is no longer in submitted state.");
  }
  $meta = $conn->prepare("SELECT exam_id FROM tbl_exam_mark_submissions WHERE id = ? LIMIT 1");
  $meta->execute([$submissionId]);
  $examId = (int)$meta->fetchColumn();
  if ($examId > 0) {
    app_refresh_exam_status($conn, $examId);
  }
  app_audit_log($conn, 'staff', (string)$account_id, 'exam_marks.reject', 'submission', (string)$submissionId);
  $afterSnapshot = app_exam_submission_archive_payload($conn, $submissionId);
  $submissionMeta = (array)($afterSnapshot['submission'] ?? $beforeSnapshot['submission'] ?? []);
  app_data_camp_store_event($conn, [
    'module_key' => 'exams',
    'record_type' => 'exam_marks_rejected',
    'entity_table' => 'tbl_exam_mark_submissions',
    'entity_id' => (string)$submissionId,
    'title' => 'Exam Mark Submission #' . (string)$submissionId,
    'description' => 'Marks review snapshot retained before and after rejection',
    'class_id' => (int)($submissionMeta['class_id'] ?? 0) > 0 ? (int)$submissionMeta['class_id'] : null,
    'owner_portal' => 'admin,academic,teacher',
    'mime_type' => 'application/json',
    'status' => 'retained',
    'payload_json' => [
      'reason' => $reason,
      'before' => $beforeSnapshot,
      'after' => $afterSnapshot,
    ],
    'created_by' => (int)$account_id,
  ]);
  $_SESSION['reply'] = array (array("success", "Marks returned to the teacher for correction."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}
header("location:../marks_review");
exit;
