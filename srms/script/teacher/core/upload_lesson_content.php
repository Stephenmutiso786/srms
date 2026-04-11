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

$lessonId = (int)($_POST['lesson_id'] ?? 0);
$contentType = trim($_POST['content_type'] ?? 'file');
$url = trim($_POST['url'] ?? '');
$filePath = '';

if ($lessonId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing lesson."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT c.teacher_id FROM tbl_lessons l JOIN tbl_courses c ON c.id = l.course_id WHERE l.id = ? LIMIT 1");
  $stmt->execute([$lessonId]);
  if ((int)$stmt->fetchColumn() !== (int)$account_id) {
    throw new RuntimeException("Not allowed to upload content to this lesson.");
  }

  if (!empty($_FILES['file']['name'])) {
    $uploadCheck = app_validate_upload($_FILES['file']);
    if (!$uploadCheck['ok']) {
      throw new RuntimeException($uploadCheck['message']);
    }
    $targetDir = __DIR__ . '/../../uploads/elearning/lessons/';
    if (!is_dir($targetDir)) {
      mkdir($targetDir, 0775, true);
    }
    $safeName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($_FILES['file']['name']));
    $targetFile = $targetDir . $safeName;
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
      throw new RuntimeException("Failed to upload file.");
    }
    $filePath = 'uploads/elearning/lessons/'.$safeName;
  }

  if ($filePath === '' && $url === '') {
    throw new RuntimeException("Provide a file or link.");
  }

  $stmt = $conn->prepare("INSERT INTO tbl_lesson_content (lesson_id, content_type, file_path, url) VALUES (?,?,?,?)");
  $stmt->execute([$lessonId, $contentType, $filePath, $url]);

  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.content.upload', 'lesson', (string)$lessonId);
  $_SESSION['reply'] = array (array("success", "Content added."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
