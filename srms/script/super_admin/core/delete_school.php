<?php
chdir('../../../script');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../../');
	exit;
}

$schoolId = (int)($_POST['school_id'] ?? 0);
if ($schoolId < 1) {
	$_SESSION['reply'] = [['danger', 'Select a school first.']];
	header('location:../index.php');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_school_subscription_schema($conn);
$currentSchoolId = app_current_school_id();
if ($schoolId === $currentSchoolId && $currentSchoolId > 0) {
	$_SESSION['reply'] = [['danger', 'Switch away from the current school before deleting it.']];
	header('location:../index.php');
	exit;
}
$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_school");
$stmt->execute();
if ((int)$stmt->fetchColumn() <= 1) {
	$_SESSION['reply'] = [['danger', 'Keep at least one school in the system.']];
	header('location:../index.php');
	exit;
}
$stmt = $conn->prepare("DELETE FROM tbl_school WHERE id = ?");
$stmt->execute([$schoolId]);
$_SESSION['reply'] = [['success', 'School deleted successfully.']];
header('location:../index.php');
