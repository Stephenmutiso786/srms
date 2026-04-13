<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
$schoolTitle = (defined('WBName') && WBName !== '') ? WBName : APP_NAME;
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
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<title><?php echo $schoolTitle; ?> - Login</title>
</head>
<body>

<section class="login-content">

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
<div class="mb-3 btn-container d-grid">
<a href="school_main_website.php" class="btn btn-outline-success btn-block" style="font-weight:700;">visit the  school main website</a>
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
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
