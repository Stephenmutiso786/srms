<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');
require_once('const/system_notifications.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('results.approve', '../analytics_engine');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../analytics_engine");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_insights_alerts') || !app_table_exists($conn, 'tbl_notifications')) {
		$_SESSION['reply'] = array (array("danger", "Alerts or notifications table missing. Run migration 016 and 008."));
		header("location:../analytics_engine");
		exit;
	}

	$stmt = $conn->prepare("SELECT * FROM tbl_insights_alerts WHERE status = 'new' ORDER BY created_at DESC LIMIT 200");
	$stmt->execute();
	$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$update = $conn->prepare("UPDATE tbl_insights_alerts SET status = 'sent' WHERE id = ?");

	foreach ($alerts as $alert) {
		$audience = $alert['student_id'] ? 'parents' : 'staff';
		$title = (string)$alert['title'];
		$message = (string)$alert['message'];
		$emailTargets = [];
		if ($alert['student_id']) {
			$stmt = $conn->prepare("SELECT p.phone, p.email, CONCAT_WS(' ', p.fname, p.lname) AS name
				FROM tbl_parent_students ps
				JOIN tbl_parents p ON p.id = ps.parent_id
				WHERE ps.student_id = ?");
			$stmt->execute([$alert['student_id']]);
			$emailTargets = $stmt->fetchAll(PDO::FETCH_ASSOC);
		} else {
			$stmt = $conn->prepare("SELECT email, CONCAT_WS(' ', fname, lname) AS name FROM tbl_staff WHERE level IN (0,2)");
			$stmt->execute();
			$emailTargets = $stmt->fetchAll(PDO::FETCH_ASSOC);
		}
		app_system_notify($conn, $title, $message, [
			'audience' => $audience,
			'class_id' => $alert['class_id'],
			'term_id' => $alert['term_id'],
			'link' => 'notifications',
			'created_by' => (int)$account_id,
			'module_name' => 'notifications',
			'type' => 'warning',
			'priority' => 75,
			'email_targets' => $emailTargets,
			'email_targets_only' => true,
			'email_link' => 'admin/notifications',
		]);
		$update->execute([$alert['id']]);

		if ($alert['student_id']) {
			$stmt = $conn->prepare("SELECT p.phone, p.email
				FROM tbl_parent_students ps
				JOIN tbl_parents p ON p.id = ps.parent_id
				WHERE ps.student_id = ?");
			$stmt->execute([$alert['student_id']]);
			foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $parent) {
				if (!empty($parent['phone'])) {
					app_send_sms($conn, $parent['phone'], $title.': '.$message);
				}
			}
		}
	}

	$_SESSION['reply'] = array (array("success", "Published ".count($alerts)." alerts to notifications."));
	header("location:../analytics_engine");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Publish failed: " . $e->getMessage()));
	header("location:../analytics_engine");
}
