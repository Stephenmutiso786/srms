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
$subStrand = trim($_POST['sub_strand'] ?? '');
$competency = trim($_POST['competency'] ?? '');
$learningOutcome = trim($_POST['learning_outcome'] ?? '');
$gradeBand = trim($_POST['grade_band'] ?? '');
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

  $fields = ['course_id', 'title', 'strand'];
  $values = [$courseId, $title, $strand];

  if (app_column_exists($conn, 'tbl_lessons', 'sub_strand')) {
    $fields[] = 'sub_strand';
    $values[] = $subStrand;
  }

  $fields[] = 'competency';
  $values[] = $competency;

  if (app_column_exists($conn, 'tbl_lessons', 'learning_outcome')) {
    $fields[] = 'learning_outcome';
    $values[] = $learningOutcome;
  }

  if (app_column_exists($conn, 'tbl_lessons', 'grade_band')) {
    $fields[] = 'grade_band';
    $values[] = $gradeBand;
  }

  $fields[] = 'description';
  $values[] = $description;

  $placeholders = implode(',', array_fill(0, count($fields), '?'));
  $stmt = $conn->prepare("INSERT INTO tbl_lessons (" . implode(',', $fields) . ") VALUES (" . $placeholders . ")");
  $stmt->execute($values);
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.lesson.create', 'lesson', (string)$conn->lastInsertId());

  $_SESSION['reply'] = array (array("success", "Lesson created."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
