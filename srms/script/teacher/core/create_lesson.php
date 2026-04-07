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
$strand = trim($_POST['strand'] ?? '');
$competency = trim($_POST['competency'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($courseId < 1 || $title === '') {
  $_SESSION['reply'] = array (array("danger", "Missing lesson details."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT teacher_id FROM tbl_courses WHERE id = ? LIMIT 1");
  $stmt->execute([$courseId]);
  if ((int)$stmt->fetchColumn() !== (int)$account_id) {
    throw new RuntimeException("Not allowed to add lesson to this course.");
  }

  $stmt = $conn->prepare("INSERT INTO tbl_lessons (course_id, title, strand, competency, description) VALUES (?,?,?,?,?)");
  $stmt->execute([$courseId, $title, $strand, $competency, $description]);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.lesson.create', 'lesson', (string)$conn->lastInsertId());

  $_SESSION['reply'] = array (array("success", "Lesson created."));
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
}

header("location:../elearning");
exit;
