<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

$liveId = (int)($_GET['id'] ?? 0);
if ($liveId < 1) {
  header("location:../elearning");
  exit;
}

try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $stmt = $conn->prepare("SELECT lc.meeting_link, lc.start_time, c.id AS course_id, c.class_id
    FROM tbl_live_classes lc
    JOIN tbl_courses c ON c.id = lc.course_id
    WHERE lc.id = ? LIMIT 1");
  $stmt->execute([$liveId]);
  $live = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$live) {
    throw new RuntimeException("Live class not found.");
  }

  $stmt = $conn->prepare("SELECT class FROM tbl_students WHERE id = ? LIMIT 1");
  $stmt->execute([$account_id]);
  if ((int)$stmt->fetchColumn() !== (int)$live['class_id']) {
    throw new RuntimeException("Not allowed to join this class.");
  }

  $now = new DateTime('now');
  $start = new DateTime($live['start_time']);
  if ($now < $start) {
    throw new RuntimeException("Class not started yet.");
  }

  if (app_table_exists($conn, 'tbl_attendance_elearning')) {
    $stmt = $conn->prepare("SELECT id FROM tbl_attendance_elearning WHERE live_class_id = ? AND student_id = ? LIMIT 1");
    $stmt->execute([$liveId, $account_id]);
    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
      $stmt = $conn->prepare("INSERT INTO tbl_attendance_elearning (live_class_id, student_id) VALUES (?,?)");
      $stmt->execute([$liveId, $account_id]);
    }
  }

  if (app_table_exists($conn, 'tbl_elearning_progress')) {
    $courseId = (int)($live['course_id'] ?? 0);
    if ($courseId > 0) {
      $stmt = $conn->prepare("SELECT id, completion_pct FROM tbl_elearning_progress WHERE student_id = ? AND course_id = ? AND lesson_id IS NULL LIMIT 1");
      $stmt->execute([$account_id, $courseId]);
      $progress = $stmt->fetch(PDO::FETCH_ASSOC);
      if ($progress) {
        $nextPct = min(100, max((float)$progress['completion_pct'], 20));
        $stmt = $conn->prepare("UPDATE tbl_elearning_progress SET completion_pct = ?, last_activity_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$nextPct, (int)$progress['id']]);
      } else {
        $stmt = $conn->prepare("INSERT INTO tbl_elearning_progress (student_id, course_id, lesson_id, competency_level, completion_pct, score) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$account_id, $courseId, null, 'AE', 20, 0]);
      }
    }
  }

  app_audit_log($conn, 'student', (string)$account_id, 'elearning.live.join', 'live_class', (string)$liveId);
  header("location:".$live['meeting_link']);
  exit;
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Operation failed. Please try again."));
  header("location:../elearning");
  exit;
}
