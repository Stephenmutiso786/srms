<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/calculations.php');
if ($res == "1" && $level == "3") {}else{header("location:../");}

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system");
$stmt->execute();
$grading = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - My Examination Results</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">

<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/settings"><i class="bi bi-person me-2 fs-5"></i> Change Password</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Student</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item" href="student/subjects"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">My Subjects</span></a></li>
<li><a class="app-menu__item active" href="student/results"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">My Examination Results</span></a></li>
<li><a class="app-menu__item" href="student/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="student/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>My Examination Results</h1>
</div>

</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<h4 class="tile-title">My Examination Results</h4>

<?php
if (WBResAvi == "1") {

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


$stmt = $conn->prepare("SELECT class FROM tbl_exam_results GROUP BY class");
$stmt->execute();
$_classes = $stmt->fetchAll();

foreach ($_classes as $key => $class) {

$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
$stmt->execute([$class[0]]);
$class_de = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE class = ? AND student = ? LIMIT 1");
$stmt->execute([$class[0], $account_id]);
$myyyyy = $stmt->fetchAll();

if (count($myyyyy) > 0) {


$stmt = $conn->prepare("SELECT term FROM tbl_exam_results WHERE class = ? GROUP BY term");
$stmt->execute([$class[0]]);
$_terms = $stmt->fetchAll();
?>
<div class="col-md-12">
<div class="tile">
<div class="tile-title-w-btn">
<h5 class="title"><?php echo $class_de[0][1]; ?></h5>

</div>
<div class="tile-body">

<div class="bs-component">
<ul class="nav nav-tabs" role="tablist">

<?php
$t = 1;
foreach ($_terms as $key => $_term) {

$stmt = $conn->prepare("SELECT name FROM tbl_terms WHERE id = ?");
$stmt->execute([$_term[0]]);
$_term_data = $stmt->fetchAll();

if ($t == "1"){
?><li class="nav-item" role="presentation"><a class="nav-link active" data-bs-toggle="tab" href="#term_<?php echo $_term[0]; ?>" aria-selected="true" role="tab"><?php echo $_term_data[0][0]; ?></a></li><?php
}else{
?><li class="nav-item" role="presentation"><a class="nav-link" data-bs-toggle="tab" href="#term_<?php echo $_term[0]; ?>" aria-selected="false" tabindex="-1" role="tab"><?php echo $_term_data[0][0]; ?></a></li><?php
}

$t++;
}
?>
</li>
</ul>
<div class="tab-content" id="myTabContent">


<?php
$t = 1;

foreach ($_terms as $key => $_term) {

if ($t == "1"){
?>
<div class="mt-3 tab-pane fade active show" id="term_<?php echo $_term[0]; ?>" role="tabpanel">

<table class="table table-bordered table-striped table-sm">
<thead>
<tr>
<th width="40">#</th>
<th>SUBJECT</th>
<th>SCORE</th>
<th>GRADE</th>
<th>REMARK</th>
</tr>
</thead>
<tbody>

<?php

$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id");
$stmt->execute();
$result = $stmt->fetchAll();
$n = 1;
$tscore = 0;
$t_subjects = 0;
$subssss = array();

foreach ($result as $key => $row) {
$class_list = app_unserialize($row[1]);

if (in_array($class[0], $class_list))
{
$t_subjects++;
$score = 0;
$grd = "N/A";
$rm = "N/A";

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ?");
$stmt->execute([$class[0], $row[0], $_term[0], $account_id]);
$ex_result = $stmt->fetchAll();

if (!empty($ex_result[0][5])) {
$score = $ex_result[0][5];
}
array_push($subssss, $score);

$tscore = $tscore + $score;
foreach($grading as $grade)
{

if ($score >= $grade[2] && $score <= $grade[3]) {

$grd = $grade[1];
$rm = $grade[4];

}

}

?>
<tr>
<td><?php echo $n; ?></td>
<td ><?php echo $row[6]; ?></td>
<td align="center" width="100"><?php echo $score; ?>%</td>
<td align="center" width="100"><?php echo $grd; ?></td>
<td align="center" width="200"><?php echo $rm; ?></td>
</tr>
<?php
}

$n++;
}
?>

</tbody>
</table>

<?php
if ($t_subjects == "0") {
$av = '0';
}else{
$av = round($tscore/$t_subjects);
}
foreach($grading as $grade)
{

if ($av >= $grade[2] && $av <= $grade[3]) {

$grd_ = $grade[1];
$rm_ = $grade[4];

}

}

?>


<p>
TOTAL SCORE <span class="badge bg-secondary rounded-pill"><?php echo $tscore; ?></span>
AVERAGE <span class="badge bg-secondary rounded-pill"><?php echo $av; ?></span>
GRADE <span class="badge bg-secondary rounded-pill"><?php echo $grd_; ?></span>
REMARK <span class="badge bg-secondary rounded-pill"><?php echo $rm_; ?></span>
DIVISION <span class="badge bg-secondary rounded-pill"><?php echo get_division($subssss); ?></span>
POINTS <span class="badge bg-secondary rounded-pill"><?php echo get_points($subssss); ?></span>
</p>

<a target="_blank" href="student/save_pdf?term=<?php echo $_term[0]; ?>" class="btn btn-primary btn-sm">Save PDF</a>
</div>
<?php
}else{
?>
<div class="mt-3 tab-pane fade" id="term_<?php echo $_term[0]; ?>" role="tabpanel">
<table class="table table-bordered table-striped table-sm">
<thead>
<tr>
<th width="40">#</th>
<th>SUBJECT</th>
<th>SCORE</th>
<th>GRADE</th>
<th>REMARK</th>
</tr>
</thead>
<tbody>

<?php

$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id");
$stmt->execute();
$result = $stmt->fetchAll();
$n = 1;
$tscore = 0;
$t_subjects = 0;
$subssss = array();

foreach ($result as $key => $row) {
$class_list = app_unserialize($row[1]);

if (in_array($class[0], $class_list))
{
$t_subjects++;
$score = 0;
$grd = "N/A";
$rm = "N/A";

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ?");
$stmt->execute([$class[0], $row[0], $_term[0], $account_id]);
$ex_result = $stmt->fetchAll();

if (!empty($ex_result[0][5])) {
$score = $ex_result[0][5];
}
array_push($subssss, $score);

$tscore = $tscore + $score;
foreach($grading as $grade)
{

if ($score >= $grade[2] && $score <= $grade[3]) {

$grd = $grade[1];
$rm = $grade[4];

}

}

?>
<tr>
<td><?php echo $n; ?></td>
<td ><?php echo $row[6]; ?></td>
<td align="center" width="100"><?php echo $score; ?>%</td>
<td align="center" width="100"><?php echo $grd; ?></td>
<td align="center" width="200"><?php echo $rm; ?></td>
</tr>
<?php
}

$n++;
}
?>

</tbody>
</table>

<?php
if ($t_subjects == "0") {
$av = '0';
}else{
$av = round($tscore/$t_subjects);
}
foreach($grading as $grade)
{

if ($av >= $grade[2] && $av <= $grade[3]) {

$grd_ = $grade[1];
$rm_ = $grade[4];

}

}

?>


<p>
TOTAL SCORE <span class="badge bg-secondary rounded-pill"><?php echo $tscore; ?></span>
AVERAGE <span class="badge bg-secondary rounded-pill"><?php echo $av; ?></span>
GRADE <span class="badge bg-secondary rounded-pill"><?php echo $grd_; ?></span>
REMARK <span class="badge bg-secondary rounded-pill"><?php echo strtoupper($rm_); ?></span>
DIVISION <span class="badge bg-secondary rounded-pill"><?php echo get_division($subssss); ?></span>
POINTS <span class="badge bg-secondary rounded-pill"><?php echo get_points($subssss); ?></span>
</p>

<a target="_blank" href="student/save_pdf?term=<?php echo $_term[0]; ?>" class="btn btn-primary btn-sm">Save PDF</a>
</div>
<?php
}

$t++;
}
?>

</div>
</div>



</div>
</div>
</div>

<?php


}


}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

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
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script type="text/javascript">$('#srmsTable').DataTable({"sort" : false});</script>
<script src="js/sweetalert2@11.js"></script>
</body>

</html>
