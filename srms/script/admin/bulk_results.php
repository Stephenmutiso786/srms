<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}

if (isset($_SESSION['bulk_result'])) {
$class = $_SESSION['bulk_result']['student'];
$term = $_SESSION['bulk_result']['term'];

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system");
$stmt->execute();
$grading = $stmt->fetchAll();

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class = ?");
$stmt->execute([$class]);
$std_data = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
$stmt->execute([$term]);
$term_data = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
$stmt->execute([$std_data[0][6]]);
$class_data = $stmt->fetchAll();

$tit = ''.$class_data[0][1].' ('.$term_data[0][1].' Results)';
}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

}else{
header("location:./");
}

?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - <?php echo $tit; ?></title>
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
<h1><?php echo $tit; ?></h1>
</div>
</div>


<div class="row">
<div class="col-md-12 center_form">
<div class="tile">
<div class="tile-body">

<div class="table-responsive">

<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th></th>
<th>REGISTRATION NUMBER</th>
<th>STUDENT NAME</th>
<th>TOTAL SCORE</th>
<th>AVERAGE</th>
<th>GRADE</th>
<th>REMARKS</th>
<th>DIVISION</th>
<th>POINTS</th>
<th></th>
</tr>
</thead>
<tbody>
<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id");
$stmt->execute();
$result = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class = ?");
$stmt->execute([$class]);
$result2 = $stmt->fetchAll();

foreach($result2 as $row2)
{
$tscore = 0;
$t_subjects = 0;
$subssss = array();

foreach ($result as $key => $row) {
$class_list = app_unserialize($row[1]);

if (in_array($class, $class_list))
{
$t_subjects++;
$score = 0;

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ?");
$stmt->execute([$class, $row[0], $term, $row2[0]]);
$ex_result = $stmt->fetchAll();

if (!empty($ex_result[0][5])) {
$score = $ex_result[0][5];
$tscore = $tscore + $score;
}
array_push($subssss, $score);

}


}

if ($t_subjects == "0") {
$av = '0';
}else{
$av = round($tscore/$t_subjects);
}

foreach($grading as $grade)
{

if ($av >= $grade[2] && $av <= $grade[3]) {

$grd = $grade[1];
$rm = $grade[4];

}

}
?>

<tr>
<td width="10">
<?php
if ($row2[9] == "DEFAULT") {



?><img src="images/students/<?php echo $row2[4]; ?>.png" class="avatar_img_sm"><?php
}else{
?><img src="images/students/<?php echo $row2[9]; ?>" class="avatar_img_sm"><?php
}
?>
</td>
<td><?php echo $row2[0]; ?></td>
<td><?php echo $row2[1].' '.$row2[2].' '.$row2[3].''; ?></td>
<td><?php echo $tscore; ?></td>
<td><?php echo $av; ?></td>
<td><?php echo $grd; ?></td>
<td><?php echo $rm; ?></td>
<td><?php echo get_division($subssss); ?></td>
<td><?php echo get_points($subssss); ?></td>

<td align="center" width="120">
<a href="admin/core/edit_result?std=<?php echo $row2[0]; ?>&term=<?php echo $term;?>" class="btn btn-primary btn-sm" href="javascript:void(0);">Edit</a>
<a href="admin/save_pdf?std=<?php echo $row2[0]; ?>&term=<?php echo $term;?>" class="btn btn-primary btn-sm" href="javascript:void(0);">Report</a>
</td>

</tr>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
echo "Connection failed.";
}

?>

</tbody>
</table>
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
