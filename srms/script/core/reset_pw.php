<?php
session_start();
chdir('../');
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$token = trim((string)($_POST['token'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

if ($token === '' || $password === '' || $confirmPassword === '') {
	$_SESSION['reply'] = array(array("danger", "Fill all password reset fields."));
	header("location:../reset_password.php?token=" . urlencode($token));
	exit;
}

if ($password !== $confirmPassword) {
	$_SESSION['reply'] = array(array("danger", "Passwords do not match."));
	header("location:../reset_password.php?token=" . urlencode($token));
	exit;
}

if (mb_strlen($password) < 6) {
	$_SESSION['reply'] = array(array("danger", "Password must be at least 6 characters."));
	header("location:../reset_password.php?token=" . urlencode($token));
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_password_reset_table($conn);

	$stmt = $conn->prepare("SELECT * FROM tbl_password_resets WHERE used_at IS NULL ORDER BY id DESC");
	$stmt->execute();
	$resetRow = null;
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$expiresAt = strtotime((string)($row['expires_at'] ?? ''));
		if (($expiresAt !== false && $expiresAt < time())) {
			continue;
		}
		if (password_verify($token, (string)$row['token_hash'])) {
			$resetRow = $row;
			break;
		}
	}

	if (!$resetRow) {
		$_SESSION['reply'] = array(array("danger", "This reset link is invalid or has expired."));
		header("location:../reset_password.php?token=" . urlencode($token));
		exit;
	}

	$accountType = (string)$resetRow['account_type'];
	$accountId = (string)$resetRow['account_id'];
	$newHash = password_hash($password, PASSWORD_DEFAULT);

	if ($accountType === 'staff') {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$update = $conn->prepare("UPDATE tbl_staff SET password = ? WHERE id = CAST(? AS integer)");
		} else {
			$update = $conn->prepare("UPDATE tbl_staff SET password = ? WHERE id = ?");
		}
		$update->execute([$newHash, $accountId]);
	} elseif ($accountType === 'student') {
		$update = $conn->prepare("UPDATE tbl_students SET password = ? WHERE id = ?");
		$update->execute([$newHash, $accountId]);
	} elseif ($accountType === 'parent' && app_table_exists($conn, 'tbl_parents')) {
		if (defined('DBDriver') && DBDriver === 'pgsql') {
			$update = $conn->prepare("UPDATE tbl_parents SET password = ? WHERE id = CAST(? AS integer)");
		} else {
			$update = $conn->prepare("UPDATE tbl_parents SET password = ? WHERE id = ?");
		}
		$update->execute([$newHash, $accountId]);
	} else {
		throw new RuntimeException('Unsupported account type.');
	}

	$markUsed = $conn->prepare("UPDATE tbl_password_resets SET used_at = CURRENT_TIMESTAMP WHERE id = ?");
	$markUsed->execute([(int)$resetRow['id']]);

	$_SESSION['reply'] = array(array("success", "Your password has been reset. You can now sign in."));
	header("location:../");
	exit;
} catch (Throwable $e) {
	error_log('[core.reset_pw] ' . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Unable to reset password right now."));
	header("location:../reset_password.php?token=" . urlencode($token));
	exit;
}
?>
