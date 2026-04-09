<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = (int)($_GET['id'] ?? 0);
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("DELETE FROM tbl_announcements WHERE id = ?");
	$stmt->execute([$id]);
	app_reply_redirect('success', 'Announcement deleted successfully.', '../announcement');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Unable to delete announcement right now.', '../announcement');
}
