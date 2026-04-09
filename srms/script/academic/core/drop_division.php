<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = trim((string)($_GET['id'] ?? ''));
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("DELETE FROM tbl_division_system WHERE division = ?");
	$stmt->execute([$id]);
	app_reply_redirect('success', 'Division deleted.', '../division-system');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Unable to delete division right now.', '../division-system');
}
