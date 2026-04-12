<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

if ($res !== '1') { header('location:../'); exit; }
app_require_permission('bom.view', '../');

$profile = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare('SELECT id, fname, lname, gender, email, level FROM tbl_staff WHERE id = ? LIMIT 1');
	$stmt->execute([(int)$account_id]);
	$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array('danger', 'Failed to load profile.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - BOM Profile</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?> BOM</a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a></header>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar"><ul class="app-menu"><li><a class="app-menu__item" href="bom"><span class="app-menu__label">Dashboard</span></a></li><li><a class="app-menu__item active" href="bom/profile"><span class="app-menu__label">My Profile</span></a></li><li><a class="app-menu__item" href="logout"><span class="app-menu__label">Logout</span></a></li></ul></aside>
<main class="app-content">
<div class="app-title"><div><h1>My BOM Profile</h1></div></div>
<div class="tile">
<div class="table-responsive"><table class="table table-hover"><tbody>
<tr><th>Staff ID</th><td><?php echo htmlspecialchars((string)($profile['id'] ?? '')); ?></td></tr>
<tr><th>First Name</th><td><?php echo htmlspecialchars((string)($profile['fname'] ?? '')); ?></td></tr>
<tr><th>Last Name</th><td><?php echo htmlspecialchars((string)($profile['lname'] ?? '')); ?></td></tr>
<tr><th>Gender</th><td><?php echo htmlspecialchars((string)($profile['gender'] ?? '')); ?></td></tr>
<tr><th>Email</th><td><?php echo htmlspecialchars((string)($profile['email'] ?? '')); ?></td></tr>
<tr><th>Designation</th><td><?php echo htmlspecialchars((string)($designation ?? 'BOM Member')); ?></td></tr>
</tbody></table></div>
<p class="text-muted mb-0">Profile creation and account assignment is managed by the school admin in BOM Management.</p>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
