<?php
session_start();
chdir('../');
require_once('db/config.php');
require_once('const/notify.php');
require_once('const/school.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$username = trim((string)($_POST['username'] ?? ''));
if ($username === '') {
	$_SESSION['reply'] = array(array("danger", "Enter your email or registration number."));
	header("location:../");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_password_reset_table($conn);

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$accounts = [];

	if ($isPgsql) {
		$sql = "
			SELECT 'staff' AS account_type, id::text AS account_id, fname, lname, email, status
			FROM tbl_staff
			WHERE id::text = ? OR email = ?
			UNION ALL
			SELECT 'student' AS account_type, id::text AS account_id, fname, lname, email, status
			FROM tbl_students
			WHERE id::text = ? OR email = ?";
		$params = [$username, $username, $username, $username];
		if (app_table_exists($conn, 'tbl_parents')) {
			$sql .= "
			UNION ALL
			SELECT 'parent' AS account_type, id::text AS account_id, fname, lname, email, status
			FROM tbl_parents
			WHERE id::text = ? OR email = ?";
			$params[] = $username;
			$params[] = $username;
		}
	} else {
		$sql = "
			SELECT 'staff' AS account_type, CAST(id AS char) AS account_id, fname, lname, email, status
			FROM tbl_staff
			WHERE id = ? OR email = ?
			UNION ALL
			SELECT 'student' AS account_type, CAST(id AS char) AS account_id, fname, lname, email, status
			FROM tbl_students
			WHERE id = ? OR email = ?";
		$params = [$username, $username, $username, $username];
		if (app_table_exists($conn, 'tbl_parents')) {
			$sql .= "
			UNION ALL
			SELECT 'parent' AS account_type, CAST(id AS char) AS account_id, fname, lname, email, status
			FROM tbl_parents
			WHERE id = ? OR email = ?";
			$params[] = $username;
			$params[] = $username;
		}
	}

	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

	if (!$accounts) {
		$_SESSION['reply'] = array(array("danger", "Account was not found."));
		header("location:../");
		exit;
	}

	$account = null;
	foreach ($accounts as $candidate) {
		if ((int)($candidate['status'] ?? 0) > 0 && trim((string)($candidate['email'] ?? '')) !== '') {
			$account = $candidate;
			break;
		}
	}

	if (!$account) {
		$_SESSION['reply'] = array(array("danger", "This account cannot reset password by email right now."));
		header("location:../");
		exit;
	}

	$token = bin2hex(random_bytes(32));
	$tokenHash = password_hash($token, PASSWORD_DEFAULT);
	$expiresAt = date('Y-m-d H:i:s', time() + 3600);
	$accountType = (string)$account['account_type'];
	$accountId = (string)$account['account_id'];
	$email = trim((string)$account['email']);
	$name = trim((string)($account['fname'] ?? '') . ' ' . (string)($account['lname'] ?? ''));
	$name = $name !== '' ? $name : $accountId;

	$cleanup = $conn->prepare("DELETE FROM tbl_password_resets WHERE account_type = ? AND account_id = ?");
	$cleanup->execute([$accountType, $accountId]);

	$insert = $conn->prepare("INSERT INTO tbl_password_resets (account_type, account_id, email, token_hash, expires_at) VALUES (?,?,?,?,?)");
	$insert->execute([$accountType, $accountId, $email, $tokenHash, $expiresAt]);

	$baseUrl = app_base_url();
	$resetUrl = ($baseUrl !== '' ? $baseUrl : '') . '/reset_password.php?token=' . urlencode($token);
	$schoolName = defined('WBName') && trim((string)WBName) !== '' ? (string)WBName : APP_NAME;
	$message = "
		<h3 style='font-size:22px;'>Reset your password</h3>
		<p style='font-size:16px;'>Hello " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>
		<p style='font-size:16px;'>We received a request to reset your password for {$schoolName}.</p>
		<p style='font-size:16px;'>
			<a href='" . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . "' style='display:inline-block;padding:10px 18px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:6px;font-weight:700;'>Reset Password</a>
		</p>
		<p style='font-size:15px;'>If the button does not open, use this link:<br>" . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . "</p>
		<p style='font-size:15px;'>This link will expire in 1 hour.</p>
	";

	$result = app_send_email($conn, $email, 'Password Reset Request', $message);
	if (empty($result['ok'])) {
		error_log('[core.forgot_pw.smtp] ' . (string)($result['error'] ?? 'Email send failed'));
		$_SESSION['reply'] = array(array("danger", "Unable to send reset email right now. Check SMTP settings and try again."));
		header("location:../");
		exit;
	}

	$_SESSION['reply'] = array(array("success", "A password reset link has been sent to $email"));
	header("location:../");
	exit;
} catch (Throwable $e) {
	error_log('[core.forgot_pw] ' . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Something went wrong. Please try again."));
	header("location:../");
	exit;
}
?>
