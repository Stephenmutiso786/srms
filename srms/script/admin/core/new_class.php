<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "0" || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$name = app_build_class_name(
	(string)($_POST['grade_name'] ?? ''),
	(string)($_POST['stream_name'] ?? ''),
	(string)($_POST['name'] ?? '')
);
if ($name === '') {
	app_reply_redirect('danger', 'Class name is required.', '../classes');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT 1 FROM tbl_classes WHERE name = ? LIMIT 1");
	$stmt->execute([$name]);
	if ($stmt->fetchColumn()) {
		app_reply_redirect('danger', 'Class is already registered.', '../classes');
	}
	$stmt = $conn->prepare("INSERT INTO tbl_classes (name, registration_date) VALUES (?,?)");
	$stmt->execute([$name, date('Y-m-d G:i:s')]);
	app_reply_redirect('success', 'Class registered successfully.', '../classes');
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to save class.', '../classes');
}
