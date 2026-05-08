	<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$tokenValid = false;
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_password_reset_table($conn);

	if ($token === '') {
		$error = 'Reset token is missing.';
	} else {
		$stmt = $conn->prepare("SELECT id, token_hash, expires_at, used_at FROM tbl_password_resets WHERE used_at IS NULL ORDER BY id DESC");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$expiresAt = strtotime((string)($row['expires_at'] ?? ''));
			if (($row['used_at'] ?? null) !== null || ($expiresAt !== false && $expiresAt < time())) {
				continue;
			}
			if (password_verify($token, (string)$row['token_hash'])) {
				$tokenValid = true;
				break;
			}
		}
		if (!$tokenValid) {
			$error = 'This reset link is invalid or has expired.';
		}
	}
} catch (Throwable $e) {
	$error = 'Unable to load reset form right now.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Reset Password</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="./">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body>
<section class="material-half-bg"><div class="cover"></div></section>
<section class="login-content">
<div class="logo"><h1><?php echo APP_NAME; ?></h1></div>
<div class="login-box" style="max-width:480px;">
<form class="login-form app_frm" action="core/reset_pw" method="POST" autocomplete="off">
<center><img height="120" src="images/logo/<?php echo WBLogo; ?>"></center>
<h4 class="login-head"><?php echo defined('WBName') ? WBName : APP_NAME; ?></h4>
<p class="text-center">Create a new password for your account.</p>
<?php if ($error !== '') { ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php } ?>
<input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
<div class="mb-3">
<label class="form-label">NEW PASSWORD</label>
<input class="form-control" type="password" name="password" required minlength="6" <?php echo $tokenValid ? '' : 'disabled'; ?>>
</div>
<div class="mb-3">
<label class="form-label">CONFIRM PASSWORD</label>
<input class="form-control" type="password" name="confirm_password" required minlength="6" <?php echo $tokenValid ? '' : 'disabled'; ?>>
</div>
<div class="mb-3 btn-container d-grid">
<button type="submit" class="btn btn-primary btn-block app_btn" <?php echo $tokenValid ? '' : 'disabled'; ?>><i class="bi bi-shield-lock me-2"></i>SET NEW PASSWORD</button>
</div>
<div class="mb-3 mt-3">
<p class="semibold-text mb-0"><a href="./"><i class="bi bi-chevron-left me-1"></i> Back to Login</a></p>
</div>
</form>
</div>
</section>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
