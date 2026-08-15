<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$isForced = (string)($_POST['forced'] ?? '') === '1';
$returnPath = $isForced ? "../force_password_change" : "../profile";
$cpassword = (string)($_POST['cpassword'] ?? '');
$newPlainPassword = (string)($_POST['npassword'] ?? '');
$confirmPassword = (string)($_POST['cnpassword'] ?? '');

if ($newPlainPassword === '' || strlen($newPlainPassword) < 8 || $newPlainPassword !== $confirmPassword) {
$_SESSION['reply'] = array (array("warning", "Enter matching passwords with at least 8 characters."));
header("location:".$returnPath);
exit;
}

if (password_verify($newPlainPassword, $login) || in_array($newPlainPassword, ['Password123', '12345678'], true)) {
$_SESSION['reply'] = array (array("warning", "Choose a new password that is not the current or default password."));
header("location:".$returnPath);
exit;
}

$npassword = password_hash($newPlainPassword, PASSWORD_DEFAULT);

if (password_verify($cpassword, $login)) {

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_staff_password_policy_columns($conn);

if (app_column_exists($conn, 'tbl_staff', 'force_password_change') && app_column_exists($conn, 'tbl_staff', 'password_changed_at')) {
	$stmt = $conn->prepare("UPDATE tbl_staff SET password = ?, force_password_change = 0, password_changed_at = CURRENT_TIMESTAMP WHERE id = ?");
	$stmt->execute([$npassword, $account_id]);
} else {
	$stmt = $conn->prepare("UPDATE tbl_staff SET password = ? WHERE id = ?");
	$stmt->execute([$npassword, $account_id]);
}

$_SESSION['reply'] = array (array("success", "Password updated"));
header("location:../");

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
$_SESSION['reply'] = array (array("warning", "Current password is not correct"));
header("location:".$returnPath);
}
}else{
header("location:../");
}
?>
