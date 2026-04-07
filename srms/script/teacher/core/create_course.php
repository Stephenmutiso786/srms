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

$name = trim($_POST['name'] ?? '');
$classId = (int)($_POST['class_id'] ?? 0);
$subjectId = (int)($_POST['subject_id'] ?? 0);

if ($name === '' || $classId < 1 || $subjectId < 1) {
  $_SESSION['reply'] = array (array("danger", "Missing course details."));
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("INSERT INTO tbl_courses (name, class_id, subject_id, teacher_id) VALUES (?,?,?,?)");
  $stmt->execute([$name, $classId, $subjectId, (int)$account_id]);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.course.create', 'course', (string)$conn->lastInsertId());

  $_SESSION['reply'] = array (array("success", "Course created."));
} catch (Throwable $e) {
  $_SESSION['reply'] = array (array("danger", $e->getMessage()));
}

header("location:../elearning");
exit;
