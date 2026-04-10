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

$courseId = (int)($_POST['course_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$instructions = trim($_POST['instructions'] ?? '');
$dueDate = $_POST['due_date'] ?? null;
if (is_string($dueDate)) {
  $dueDate = str_replace('T', ' ', $dueDate);
}
$attachmentPath = '';

if ($courseId < 1 || $title === '' || $instructions === '') {
  $_SESSION['reply'] = array (array("danger", "Missing assignment details."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT teacher_id, class_id FROM tbl_courses WHERE id = ? LIMIT 1");
  $stmt->execute([$courseId]);
  $course = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$course || (int)$course['teacher_id'] !== (int)$account_id) {
    throw new RuntimeException("Not allowed to create assignment for this course.");
  }

  if (!empty($_FILES['attachment']['name'])) {
    $uploadCheck = app_validate_upload($_FILES['attachment']);
    if (!$uploadCheck['ok']) {
      throw new RuntimeException($uploadCheck['message']);
    }
    $targetDir = __DIR__ . '/../../uploads/elearning/assignments/';
    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0775, true);
    }
    $safeName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['attachment']['name']));
    $targetFile = $targetDir . $safeName;
    if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $targetFile)) {
      throw new RuntimeException("Failed to upload attachment.");
    }
    $attachmentPath = 'uploads/elearning/assignments/'.$safeName;
  }

  $stmt = $conn->prepare("INSERT INTO tbl_assignments (course_id, title, instructions, due_date, attachment, created_by) VALUES (?,?,?,?,?,?)");
  $stmt->execute([$courseId, $title, $instructions, $dueDate ?: null, $attachmentPath, (int)$account_id]);
  $assignmentId = $conn->lastInsertId();
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.assignment.create', 'assignment', (string)$assignmentId);

  if (app_table_exists($conn, 'tbl_notifications')) {
    $stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, link, created_by) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
      'New Assignment',
      $title,
      'class',
      (int)$course['class_id'],
      'student/elearning',
      (int)$account_id
    ]);
  }

  $_SESSION['reply'] = array (array("success", "Assignment created."));
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
}

header("location:../elearning");
exit;
