<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('communication.manage', '../notifications');
app_require_unlocked('communication', '../notifications');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../notifications");
	exit;
}

$title = trim($_POST['title'] ?? '');
$message = trim($_POST['message'] ?? '');
$audience = $_POST['audience'] ?? 'all';
$classId = $_POST['class_id'] ?? null;
$termId = $_POST['term_id'] ?? null;
$link = trim($_POST['link'] ?? '');

$classId = $classId === '' ? null : (int)$classId;
$termId = $termId === '' ? null : (int)$termId;

if ($title === '' || $message === '') {
	$_SESSION['reply'] = array (array("danger", "Title and message are required."));
	header("location:../notifications");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$createdBy = isset($account_id) ? (int)$account_id : null;

	if (!app_table_exists($conn, 'tbl_notifications')) {
		$_SESSION['reply'] = array (array("danger", "Notifications table missing. Run migration 008."));
		header("location:../notifications");
		exit;
	}

	$stmt = $conn->prepare("INSERT INTO tbl_notifications (title, message, audience, class_id, term_id, link, created_by) VALUES (?,?,?,?,?,?,?)");
	$stmt->execute([$title, $message, $audience, $classId, $termId, $link, $createdBy]);

	$_SESSION['reply'] = array (array("success", "Notification sent."));
	header("location:../notifications");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Failed to send: " . $e->getMessage()));
	header("location:../notifications");
}
