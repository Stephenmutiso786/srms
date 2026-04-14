<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}

function app_normalize_live_meeting_link(string $meetingLink): string
{
  $meetingLink = trim($meetingLink);
  if ($meetingLink === '') {
    return '';
  }
  if (!preg_match('/^https?:\/\//i', $meetingLink)) {
    $meetingLink = 'https://' . $meetingLink;
  }
  if (!filter_var($meetingLink, FILTER_VALIDATE_URL)) {
    return '';
  }
  $host = strtolower((string)(parse_url($meetingLink, PHP_URL_HOST) ?? ''));
  if ($host === '') {
    return '';
  }
  return $meetingLink;
}

function app_live_platform_matches(string $meetingLink, string $platform): bool
{
  $host = strtolower((string)(parse_url($meetingLink, PHP_URL_HOST) ?? ''));
  $platform = strtolower(trim($platform));
  if ($platform === '') {
    return true;
  }
  if (strpos($platform, 'zoom') !== false) {
    return strpos($host, 'zoom.') !== false;
  }
  if (strpos($platform, 'meet') !== false || strpos($platform, 'google') !== false) {
    return strpos($host, 'meet.google.') !== false;
  }
  return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../elearning");
  exit;
}

$courseId = (int)($_POST['course_id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$meetingLink = app_normalize_live_meeting_link((string)($_POST['meeting_link'] ?? ''));
$platform = trim($_POST['platform'] ?? 'Google Meet');
$startTime = $_POST['start_time'] ?? '';
$endTime = $_POST['end_time'] ?? null;
if (is_string($startTime)) {
  $startTime = str_replace('T', ' ', $startTime);
}
if (is_string($endTime)) {
  $endTime = str_replace('T', ' ', $endTime);
}

if ($courseId < 1 || $title === '' || $meetingLink === '' || $startTime === '') {
  $_SESSION['reply'] = array (array("danger", "Missing live class details."));
  header("location:../elearning");
  exit;
}

if (!app_live_platform_matches($meetingLink, $platform)) {
  $_SESSION['reply'] = array (array("danger", "Meeting link does not match selected platform."));
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
    throw new RuntimeException("Not allowed to schedule class for this course.");
  }

  $columns = ['course_id', 'title', 'meeting_link', 'platform', 'start_time', 'end_time', 'created_by'];
  $values = [$courseId, $title, $meetingLink, $platform, $startTime, $endTime ?: null, (int)$account_id];
  if (app_column_exists($conn, 'tbl_live_classes', 'status')) {
    $columns[] = 'status';
    $values[] = 'scheduled';
  }
  if (app_column_exists($conn, 'tbl_live_classes', 'started_at')) {
    $columns[] = 'started_at';
    $values[] = null;
  }
  if (app_column_exists($conn, 'tbl_live_classes', 'ended_at')) {
    $columns[] = 'ended_at';
    $values[] = null;
  }

  $placeholders = implode(',', array_fill(0, count($columns), '?'));
  $stmt = $conn->prepare("INSERT INTO tbl_live_classes (" . implode(',', $columns) . ") VALUES (" . $placeholders . ")");
  $stmt->execute($values);
  $liveId = $conn->lastInsertId();
  app_audit_log($conn, 'staff', (string)$account_id, 'elearning.live.create', 'live_class', (string)$liveId);

  if (app_table_exists($conn, 'tbl_notifications')) {
    $stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, link, created_by) VALUES (?,?,?,?,?,?)");
    $stmt->execute([
      'Live Class Scheduled',
      $title,
      'class',
      (int)$course['class_id'],
      'student/elearning',
      (int)$account_id
    ]);
  }

  $_SESSION['reply'] = array (array("success", "Live class scheduled."));
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
}

header("location:../elearning");
exit;
