<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../elearning");
  exit;
}

$submissionId = (int)($_POST['submission_id'] ?? 0);
$score = (float)($_POST['score'] ?? 0);
$feedback = trim($_POST['feedback'] ?? '');

if ($submissionId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing submission."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT c.teacher_id
    FROM tbl_assignment_submissions s
    JOIN tbl_assignments a ON a.id = s.assignment_id
    JOIN tbl_courses c ON c.id = a.course_id
    WHERE s.id = ? LIMIT 1");
  $stmt->execute([$submissionId]);
  if ((int)$stmt->fetchColumn() !== (int)$account_id) {
    throw new RuntimeException("Not allowed to grade this submission.");
  }

  $stmt = $conn->prepare("UPDATE tbl_assignment_submissions SET score = ?, feedback = ? WHERE id = ?");
  $stmt->execute([$score, $feedback, $submissionId]);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.assignment.grade', 'submission', (string)$submissionId);

  $_SESSION['reply'] = array (array("success", "Submission graded."));
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
}

header("location:../elearning");
exit;
