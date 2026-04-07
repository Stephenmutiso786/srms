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
<title><?php echo APP_NAME; ?> - Grading System</title>
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
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
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
<p class="app-sidebar__user-designation">Teacher</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="teacher"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="teacher/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="teacher/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>
<li><a class="app-menu__item" href="teacher/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/list_students"><i class="icon bi bi-circle-fill"></i> List Students</a></li>
<li><a class="treeview-item" href="teacher/export_students"><i class="icon bi bi-circle-fill"></i> Export Students</a></li>
</ul>
</li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Examination Results</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="teacher/exam_marks_entry"><i class="icon bi bi-circle-fill"></i> Exam Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/marks_entry"><i class="icon bi bi-circle-fill"></i> CBC Marks Entry</a></li>
<li><a class="treeview-item" href="teacher/import_results"><i class="icon bi bi-circle-fill"></i> Import Results</a></li>
<li><a class="treeview-item" href="teacher/manage_results"><i class="icon bi bi-circle-fill"></i> View Results</a></li>
</ul>
</li>
<li><a class="app-menu__item active" href="teacher/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="teacher/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1>Grading System</h1>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Grading System</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>Grade Name</th>
<th>Minimum Score</th>
<th>Maximum Score</th>
<th>Remark</th>
</tr>
</thead>
<tbody>
<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_grade_system");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<tr>
<td><?php echo $row[1]; ?></td>
<td><?php echo $row[2]; ?></td>
<td><?php echo $row[3]; ?></td>
<td><?php echo $row[4]; ?></td>
</tr>
<?php
}

}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

?>

</tbody>
</table>
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
<?php require_once('const/check-reply.php'); ?>
</body>

</html>
