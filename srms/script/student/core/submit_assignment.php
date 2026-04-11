<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../elearning");
  exit;
}

$assignmentId = (int)($_POST['assignment_id'] ?? 0);
$text = trim($_POST['submission_text'] ?? '');
$filePath = '';

if ($assignmentId < 1 && $text === '') {
  $_SESSION['reply'] = array (array("danger", "Missing submission."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT c.class_id
    FROM tbl_assignments a
    JOIN tbl_courses c ON c.id = a.course_id
    WHERE a.id = ? LIMIT 1");
  $stmt->execute([$assignmentId]);
  $classId = (int)$stmt->fetchColumn();
  if ($classId < 1) {
    throw new RuntimeException("Assignment not found.");
  }

  $stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
  $stmt->execute([$account_id]);
  if ((int)$stmt->fetchColumn() !== $classId) {
    throw new RuntimeException("Not allowed to submit to this assignment.");
  }

  if (!empty($_FILES['file']['name'])) {
    $uploadCheck = app_validate_upload($_FILES['file']);
    if (!$uploadCheck['ok']) {
      throw new RuntimeException($uploadCheck['message']);
    }
    $targetDir = __DIR__ . '/../../uploads/elearning/assignments/';
    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0775, true);
    }
    $safeName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['file']['name']));
    $targetFile = $targetDir . $safeName;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
      throw new RuntimeException("Failed to upload file.");
    }
    $filePath = 'uploads/elearning/assignments/'.$safeName;
  }

  $stmt = $conn->prepare("SELECT id FROM tbl_assignment_submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1");
  $stmt->execute([$assignmentId, $account_id]);
  $existing = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($existing) {
    $stmt = $conn->prepare("UPDATE tbl_assignment_submissions SET submission_text = ?, file_path = ?, submitted_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$text, $filePath, $existing['id']]);
  } else {
    $stmt = $conn->prepare("INSERT INTO tbl_assignment_submissions (assignment_id, student_id, submission_text, file_path) VALUES (?,?,?,?)");
    $stmt->execute([$assignmentId, $account_id, $text, $filePath]);
  }

  app_audit_log($conn, 'student', (string)$account_id, 'elearning.assignment.submit', 'assignment', (string)$assignmentId);
  $_SESSION['reply'] = array (array("success", "Assignment submitted."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
