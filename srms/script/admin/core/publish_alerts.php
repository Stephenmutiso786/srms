<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

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

	$insert = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, term_id, link, created_by) VALUES (?,?,?,?,?,?,?)");
	$update = $conn->prepare("UPDATE tbl_insights_alerts SET status = 'sent' WHERE id = ?");

	foreach ($alerts as $alert) {
		$audience = $alert['student_id'] ? 'parents' : 'staff';
		$title = (string)$alert['title'];
		$message = (string)$alert['message'];
		$insert->execute([$title, $message, $audience, $alert['class_id'], $alert['term_id'], 'notifications', $account_id]);
		$update->execute([$alert['id']]);
	}

	$_SESSION['reply'] = array (array("success", "Published ".count($alerts)." alerts to notifications."));
	header("location:../analytics_engine");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Publish failed: " . $e->getMessage()));
	header("location:../analytics_engine");
}
