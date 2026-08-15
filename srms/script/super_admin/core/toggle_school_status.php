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

$stmt = $conn->prepare('SELECT id, is_locked FROM tbl_school WHERE id = ? LIMIT 1');
$stmt->execute([$schoolId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
	$_SESSION['reply'] = [['danger', 'School not found.']];
	header('location:../index.php');
	exit;
}

$next = ((int)($row['is_locked'] ?? 0) === 1) ? 0 : 1;
$stmt = $conn->prepare('UPDATE tbl_school SET is_locked = ? WHERE id = ?');
$stmt->execute([$next, $schoolId]);
$_SESSION['reply'] = [['success', $next === 1 ? 'School locked successfully.' : 'School unlocked successfully.']];
header('location:../index.php');
