<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Academic Account</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('admin/partials/sidebar.php'); ?>


<main class="app-content">
<div class="app-title">
<div>
<h1>Academic Account</h1>
<p class="text-muted mb-0">Review the actual leadership account currently signed into this portal so the page always matches the active account details.</p>
</div>

</div>
<div class="row">


<div class="tile">
<div class="tile-body">

<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT id, fname, lname, gender, email, level, status FROM tbl_staff WHERE id = ? LIMIT 1");
$stmt->execute([(int)$account_id]);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($result) < 1) {
?>
<div class="alert alert-warning mb-0">The active leadership account could not be loaded for this session.</div>
<?php
}else{

foreach($result as $row) {
$staffId = (int)($row['id'] ?? 0);
$designationLabel = app_staff_primary_title($conn, $staffId, (string)($row['level'] ?? '1'));
$statusLabel = ((string)($row['status'] ?? '0') === '1') ? 'Active' : 'Blocked';
?>
<div class="alert alert-light border mb-3">
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
<div>
<strong><?php echo htmlspecialchars($designationLabel); ?></strong>
<div class="text-muted small">This information is now pulled from the current signed-in account, not from a separate deputy or academic record.</div>
</div>
<span class="badge bg-<?php echo $statusLabel === 'Active' ? 'success' : 'secondary'; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
</div>
</div>
<div class="mb-2">
<label class="form-label">First Name</label>
<input value="<?php echo htmlspecialchars((string)($row['fname'] ?? '')); ?>" disabled name="fname" class="form-control" type="text" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input value="<?php echo htmlspecialchars((string)($row['lname'] ?? '')); ?>" disabled required name="lname" class="form-control" type="text" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input value="<?php echo htmlspecialchars((string)($row['email'] ?? '')); ?>" disabled required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>

<div class="mb-3">
<label class="form-label">Gender</label>
<select disabled class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option <?php if (($row['gender'] ?? '') == "Male") { print ' selected '; } ?> value="Male">Male</option>
<option <?php if (($row['gender'] ?? '') == "Female") { print ' selected '; } ?> value="Female">Female</option>
</select>
</div>

<div class="box-footer">
<?php if ($staffId !== (int)$account_id) { ?>
<a onclick="del('admin/core/drop_user?id=<?php echo $staffId; ?>', 'Delete Academic Account?');" href="javascript:void(0);" class="btn btn-danger">Delete</a>
<?php } else { ?>
<button class="btn btn-secondary" type="button" disabled>Current Signed-in Account</button>
<?php } ?>
</div>
<?php
}

}


}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>


</div>
</div>
</div>


</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>

</html>
