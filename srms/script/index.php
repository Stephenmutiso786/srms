<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/public_media.php');
$schoolTitle = (defined('WBName') && WBName !== '') ? WBName : APP_NAME;
$redirectTo = isset($_GET['redirect_to']) ? trim((string)$_GET['redirect_to']) : '';
$redirectTo = preg_replace('/[^a-zA-Z0-9_\/-]/', '', $redirectTo);
$redirectTo = ltrim($redirectTo, '/');
$allowedRedirectPrefixes = ['student/', 'parent/', 'teacher/', 'admin/', 'accountant/', 'bom/'];
$isAllowedRedirect = false;
foreach ($allowedRedirectPrefixes as $prefix) {
  if ($redirectTo !== '' && strpos($redirectTo, $prefix) === 0) {
    $isAllowedRedirect = true;
    break;
  }
}
if (!$isAllowedRedirect) {
  $redirectTo = '';
}
$loginBackgroundSrc = '';
$loginBackgroundMeta = ['src' => '', 'width' => 0, 'height' => 0];
try {
  $conn = app_db();
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $loginBackgroundMeta = app_public_login_background_data($conn);
  $loginBackgroundSrc = isset($loginBackgroundMeta['src']) ? (string)$loginBackgroundMeta['src'] : '';
} catch (Throwable $e) {
  $loginBackgroundSrc = '';
  $loginBackgroundMeta = ['src' => '', 'width' => 0, 'height' => 0];
}
?>
<!DOCTYPE html>
<html>
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="manifest" href="manifest.webmanifest">
<meta name="theme-color" content="#006400">
<link rel="apple-touch-icon" href="images/pwa/icon-192.png">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<title><?php echo $schoolTitle; ?> - Login</title>
<style>
.login-content {
  position: relative;
  overflow: hidden;
  min-height: 100vh;
  background-image: linear-gradient(rgba(14, 43, 31, 0.56), rgba(14, 43, 31, 0.62));
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
}

<?php if ($loginBackgroundSrc !== ''): ?>
.login-content__bg {
  position: absolute;
  inset: 0;
  z-index: 0;
  pointer-events: none;
}

.login-content__bg img {
  width: 100%;
  height: 100%;
  display: block;
  object-fit: cover;
  object-position: center center;
}

.login-content__veil {
  position: absolute;
  inset: 0;
  z-index: 1;
  background: linear-gradient(rgba(14, 43, 31, 0.48), rgba(14, 43, 31, 0.58));
}

.login-content .login-box {
  position: relative;
  z-index: 2;
}
<?php endif; ?>

.login-content .login-box {
  background-color: rgba(255, 255, 255, 0.96);
  border-radius: 12px;
}
</style>
</head>
<body>

<section class="login-content">
<?php if ($loginBackgroundSrc !== ''): ?>
<div class="login-content__bg" aria-hidden="true">
  <img src="<?php echo htmlspecialchars($loginBackgroundSrc, ENT_QUOTES, 'UTF-8'); ?>" alt=""<?php echo !empty($loginBackgroundMeta['width']) ? ' width="' . (int)$loginBackgroundMeta['width'] . '"' : ''; ?><?php echo !empty($loginBackgroundMeta['height']) ? ' height="' . (int)$loginBackgroundMeta['height'] . '"' : ''; ?> loading="eager" fetchpriority="high" decoding="async">
</div>
<div class="login-content__veil" aria-hidden="true"></div>
<?php endif; ?>

<div class="login-box">

<form class="login-form app_frm" action="core/auth" autocomplete="OFF" method="POST">
<center><img height="140" src="images/logo/<?php echo WBLogo; ?>"></center>
<h4 class="login-head"><?php echo $schoolTitle; ?></h4>
<p class="text-center"><?php echo $schoolTitle; ?> — <?php echo APP_TAGLINE; ?></p>
<div class="mb-3">
<label class="form-label">USERNAME</label>
<input class="form-control" type="text" placeholder="Email or Registration Number" required name="username">
</div>
<div class="mb-3">
<label class="form-label">PASSWORD</label>
<div class="input-group">
<input class="form-control" id="loginPassword" type="password" placeholder="Login Password" required name="password">
<button class="btn btn-outline-secondary" type="button" id="toggleLoginPassword"><i class="bi bi-eye"></i></button>
</div>
</div>
<div class="mb-3">
<div class="utility">
<p class="semibold-text mb-2"><a href="javascript:void(0);" data-toggle="flip">Forgot Password ?</a></p>
</div>
</div>
<div class="mb-3 btn-container d-grid">
<button type="submit" class="btn btn-primary btn-block app_btn"><i class="bi bi-box-arrow-in-right me-2 fs-5"></i>SIGN IN</button>
</div>
<?php if ($redirectTo !== ''): ?>
<input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectTo, ENT_QUOTES, 'UTF-8'); ?>">
<?php endif; ?>
<div class="mb-3 btn-container d-grid">
<a href="index.php?redirect_to=student/elearning" class="btn btn-warning btn-block" style="font-weight:800;"><i class="bi bi-mortarboard-fill me-2 fs-5"></i>E-LEARNING LOGIN</a>
</div>
<div class="mb-3 btn-container d-grid">
<a href="school_main_website.php" class="btn btn-primary btn-block app_btn" style="font-weight:700;"><i class="bi bi-globe2 me-2 fs-5"></i>visit the  school main website</a>
</div>
</form>

<form class="forget-form app_frm" action="core/forgot_pw" method="POST" autocomplete="OFF">
<center><img height="140" src="images/logo/<?php echo WBLogo; ?>"></center>
<h4 class="login-head"><?php echo $schoolTitle; ?></h4>
<p class="text-center"><?php echo $schoolTitle; ?> — <?php echo APP_TAGLINE; ?></p>
<div class="mb-3">
<label class="form-label">USERNAME</label>
<input class="form-control" type="text" placeholder="Email or Registration Number" required name="username">
</div>
<div class="mb-3 btn-container d-grid">
<button type="submit" class="btn btn-primary btn-block app_btn"><i class="bi bi-unlock me-2 fs-5"></i>RESET PASSWORD</button>
</div>
<div class="mb-3 mt-3">
<p class="semibold-text mb-0"><a href="javascript:void(0);" data-toggle="flip"><i class="bi bi-chevron-left me-1"></i> Back to Login</a></p>
</div>
</form>
</div>
</section>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script type="text/javascript">
$('.login-content [data-toggle="flip"]').click(function() {
$('.login-box').toggleClass('flipped');
return false;
});
$('#toggleLoginPassword').on('click', function () {
  var input = document.getElementById('loginPassword');
  if (!input) return;
  var isPassword = input.getAttribute('type') === 'password';
  input.setAttribute('type', isPassword ? 'text' : 'password');
  this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
});

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js').catch(function () { return null; });
}
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
