<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');

if ($res != "1" || $level != "2") {
	header("location:../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../elearning");
	exit;
}

$liveId = (int)($_POST['live_class_id'] ?? 0);
$action = trim((string)($_POST['action'] ?? ''));
$allowedActions = ['start', 'end'];

if ($liveId < 1 || !in_array($action, $allowedActions, true)) {
	$_SESSION['reply'] = array(array('danger', 'Invalid live class request.'));
	header("location:../elearning");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$stmt = $conn->prepare("SELECT lc.*, c.teacher_id, c.class_id, c.name AS course_name
		FROM tbl_live_classes lc
		JOIN tbl_courses c ON c.id = lc.course_id
		WHERE lc.id = ? LIMIT 1");
	$stmt->execute([$liveId]);
	$live = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$live || (int)$live['teacher_id'] !== (int)$account_id) {
		throw new RuntimeException('Not allowed to update this live class.');
	}

	$currentStatus = strtolower(trim((string)($live['status'] ?? 'scheduled')));
	$now = new DateTime('now');
	$start = new DateTime((string)$live['start_time']);
	$hasStatusColumn = app_column_exists($conn, 'tbl_live_classes', 'status');
	$hasStartedAtColumn = app_column_exists($conn, 'tbl_live_classes', 'started_at');
	$hasEndedAtColumn = app_column_exists($conn, 'tbl_live_classes', 'ended_at');
	$hasUpdatedAtColumn = app_column_exists($conn, 'tbl_live_classes', 'updated_at');
	$hasEndTimeColumn = app_column_exists($conn, 'tbl_live_classes', 'end_time');

	if ($action === 'start') {
		if ($currentStatus === 'active') {
			$_SESSION['reply'] = array(array('success', 'Live class is already running.'));
			header("location:../elearning");
			exit;
		}
		if ($currentStatus === 'ended') {
			throw new RuntimeException('Ended live classes cannot be restarted.');
		}
		if ($now < $start) {
			throw new RuntimeException('This live class is scheduled for later. You can start it when the scheduled time arrives.');
		}

		$updateParts = [];
		$values = [];
		if ($hasStatusColumn) {
			$updateParts[] = 'status = ?';
			$values[] = 'active';
		}
		if ($hasStartedAtColumn) {
			$updateParts[] = 'started_at = CURRENT_TIMESTAMP';
		}
		if ($hasUpdatedAtColumn) {
			$updateParts[] = 'updated_at = CURRENT_TIMESTAMP';
		}
		if ($updateParts) {
			$values[] = $liveId;
			$stmt = $conn->prepare('UPDATE tbl_live_classes SET ' . implode(', ', $updateParts) . ' WHERE id = ?');
			$stmt->execute($values);
		}

		if (app_table_exists($conn, 'tbl_notifications')) {
			$stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, link, created_by) VALUES (?,?,?,?,?,?)");
			$stmt->execute([
				'Live Class Started',
				(string)$live['title'] . ' has started.',
				'class',
				(int)$live['class_id'],
				'student/elearning',
				(int)$account_id,
			]);
		}

		app_audit_log($conn, 'staff', (string)$account_id, 'elearning.live.start', 'live_class', (string)$liveId);
		$_SESSION['reply'] = array(array('success', 'Live class started.'));
		header("location:../elearning");
		exit;
	}

	if ($currentStatus !== 'active') {
		throw new RuntimeException('Only a running live class can be ended.');
	}

	$updateParts = [];
	$values = [];
	if ($hasStatusColumn) {
		$updateParts[] = 'status = ?';
		$values[] = 'ended';
	}
	if ($hasEndTimeColumn) {
		$updateParts[] = 'end_time = COALESCE(end_time, CURRENT_TIMESTAMP)';
	}
	if ($hasEndedAtColumn) {
		$updateParts[] = 'ended_at = CURRENT_TIMESTAMP';
	}
	if ($hasUpdatedAtColumn) {
		$updateParts[] = 'updated_at = CURRENT_TIMESTAMP';
	}
	if ($updateParts) {
		$values[] = $liveId;
		$stmt = $conn->prepare('UPDATE tbl_live_classes SET ' . implode(', ', $updateParts) . ' WHERE id = ?');
		$stmt->execute($values);
	}

	if (app_table_exists($conn, 'tbl_notifications')) {
		$stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, link, created_by) VALUES (?,?,?,?,?,?)");
		$stmt->execute([
			'Live Class Ended',
			(string)$live['title'] . ' has ended.',
			'class',
			(int)$live['class_id'],
			'student/elearning',
			(int)$account_id,
		]);
	}

	app_audit_log($conn, 'staff', (string)$account_id, 'elearning.live.end', 'live_class', (string)$liveId);
	$_SESSION['reply'] = array(array('success', 'Live class ended.'));
	header("location:../elearning");
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', 'Operation failed. Please try again.'));
	header("location:../elearning");
}
exit;
