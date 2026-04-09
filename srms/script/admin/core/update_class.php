<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "0" || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$name = ucfirst(trim((string)($_POST['name'] ?? '')));
$id = (int)($_POST['id'] ?? 0);
if ($name === '' || $id < 1) {
	app_reply_redirect('danger', 'Invalid class update request.', '../classes');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT 1 FROM tbl_classes WHERE name = ? AND id <> ? LIMIT 1");
	$stmt->execute([$name, $id]);
	if ($stmt->fetchColumn()) {
		app_reply_redirect('danger', 'Class is already registered.', '../classes');
	}
	$stmt = $conn->prepare("UPDATE tbl_classes SET name = ? WHERE id = ?");
	$stmt->execute([$name, $id]);
	app_reply_redirect('success', 'Class updated successfully.', '../classes');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to update class.', '../classes');
}
