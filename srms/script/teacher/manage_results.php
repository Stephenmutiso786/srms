<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "2") {}else{header("location:../");}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - View Results</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
<link rel="stylesheet" href="select2/dist/css/select2.min.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include("teacher/partials/sidebar.php"); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>View Results</h1>
</div>
</div>

<div class="row">
<div class="col-md-4 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">View Results</h3>
<form class="app_frm" enctype="multipart/form-data" method="POST" autocomplete="OFF" action="teacher/core/view_results">

<div class="mb-2">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" id="termSelect" required style="width: 100%;">
<option selected disabled value="">Select Term</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="mb-2">
<label class="form-label">Select Class</label>
<select onchange="fetch_subjects(this.value);" class="form-control select2" name="class" required style="width: 100%;">
<option selected disabled value="">Select Class</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
app_ensure_class_teachers_table($conn);

if (app_table_exists($conn, 'tbl_teacher_assignments')) {
  $stmt = $conn->prepare("SELECT DISTINCT class_id FROM tbl_teacher_assignments WHERE teacher_id = ? AND status = 1");
  $stmt->execute([$account_id]);
  $myclasses = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
  $stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations
  LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id
  LEFT JOIN tbl_staff ON tbl_subject_combinations.teacher = tbl_staff.id WHERE tbl_subject_combinations.teacher = ?");
  $stmt->execute([$account_id]);
  $result = $stmt->fetchAll();

  $myclasses = array();
  foreach ($result as $value) {
    $class_arr = app_unserialize($value[1]);
    foreach ($class_arr as $value) {
      array_push($myclasses, $value);
    }
  }
}

$stmt = $conn->prepare("SELECT class_id FROM tbl_class_teachers WHERE teacher_id = ? AND active = 1");
$stmt->execute([$account_id]);
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $classTeacherClassId) {
  $myclasses[] = $classTeacherClassId;
}

$myclasses = array_values(array_unique(array_map('strval', $myclasses)));

if (!empty($myclasses)) {
  $matches = str_split(str_repeat("?", count($myclasses)));
  $matches = implode(",", $matches);
  $stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id IN ($matches)");
  $stmt->execute($myclasses);
  $result = $stmt->fetchAll();
} else {
  $result = [];
}

foreach($result as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Subject</label>
<select class="form-control" name="subject" required id="sub_imp">
<option selected disabled value="">Select One</option>
</select>
</div>

<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">View Results</button>
</form>
</div>
</div>
</div>

<div class="col-md-4 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Class Teacher Summary</h3>
<form method="GET" action="teacher/class_report" autocomplete="OFF">

<div class="mb-2">
<label class="form-label">Select Term</label>
<select class="form-control select2" name="term" required style="width: 100%;">
<option selected disabled value="">Select Term</option>
<?php
try {
$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE status = '1'");
$stmt->execute();
foreach($stmt->fetchAll() as $row)
{
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
}
} catch (Throwable $e) {}
?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Select Class</label>
<select class="form-control select2" name="class" required style="width: 100%;">
<option selected disabled value="">Select Class</option>
<?php
try {
if (!empty($myclasses)) {
  $matches = str_split(str_repeat("?", count($myclasses)));
  $matches = implode(",", $matches);
  $stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id IN ($matches)");
  $stmt->execute($myclasses);
  foreach($stmt->fetchAll() as $row)
  {
?>
<option value="<?php echo $row[0]; ?>"><?php echo $row[1]; ?> </option>
<?php
  }
}
} catch (Throwable $e) {}
?>
</select>
</div>

<button type="submit" class="btn btn-outline-primary app_btn">Open Class Summary</button>
</form>
</div>
</div>
</div>
</div>
</div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script type="text/javascript">$('#srmsTable').DataTable({"sort" : false});</script>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.select2').select2()
</script>
</body>

</html>
