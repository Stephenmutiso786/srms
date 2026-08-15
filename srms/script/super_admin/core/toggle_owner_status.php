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

$ownerId = (int)($_POST['owner_id'] ?? 0);
if ($ownerId < 1) {
	$_SESSION['reply'] = [['danger', 'Select an owner account first.']];
	header('location:../index.php');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_school_subscription_schema($conn);
$stmt = $conn->prepare('SELECT status FROM tbl_staff WHERE id = ? AND level = 9 LIMIT 1');
$stmt->execute([$ownerId]);
$current = $stmt->fetchColumn();
if ($current === false) {
	$_SESSION['reply'] = [['danger', 'Owner account not found.']];
	header('location:../index.php');
	exit;
}

$next = ((string)$current === '1') ? 0 : 1;
$stmt = $conn->prepare('UPDATE tbl_staff SET status = ? WHERE id = ? AND level = 9');
$stmt->execute([$next, $ownerId]);
$_SESSION['reply'] = [['success', 'Owner status updated successfully.']];
header('location:../index.php');
