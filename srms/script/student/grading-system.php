<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res != "1" || $level != "3") { header("location:../"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - CBE Grading System</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
</head>
<body class="app sidebar-mini">

<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>

<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('student/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>CBE Grading System</h1>
<p class="text-muted">Understand how your work is assessed using CBE standards.</p>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">How Your Work is Graded</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>Grade</th>
<th>Score Range</th>
<th>What It Means</th>
</tr>
</thead>
<tbody>
<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

require_once('const/report_engine.php');
$gradingSystemId = report_default_grading_system_id($conn, 'marks');
$grades = report_grading_scales($conn, $gradingSystemId);

if (empty($grades)) {
$grades = [
['name' => 'EE', 'min' => 90, 'max' => 100, 'remark' => 'Exceeding Expectation'],
['name' => 'ME', 'min' => 75, 'max' => 89, 'remark' => 'Meeting Expectation'],
['name' => 'AE', 'min' => 50, 'max' => 74, 'remark' => 'Approaching Expectation'],
['name' => 'BE', 'min' => 0, 'max' => 49, 'remark' => 'Below Expectation'],
];
}

foreach($grades as $row)
{
$min = number_format((float)($row['min'] ?? 0), 0);
$max = number_format((float)($row['max'] ?? 100), 0);
$remark = (string)($row['remark'] ?? '');
?>
<tr>
<td><strong class="text-primary"><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></strong></td>
<td><?php echo $min; ?>% - <?php echo $max; ?>%</td>
<td><?php echo htmlspecialchars($remark); ?></td>
</tr>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
$defaultGrades = [
['name' => 'EE', 'min' => 90, 'max' => 100, 'remark' => 'Exceeding Expectation'],
['name' => 'ME', 'min' => 75, 'max' => 89, 'remark' => 'Meeting Expectation'],
['name' => 'AE', 'min' => 50, 'max' => 74, 'remark' => 'Approaching Expectation'],
['name' => 'BE', 'min' => 0, 'max' => 49, 'remark' => 'Below Expectation'],
];
foreach($defaultGrades as $grade)
{
?>
<tr>
<td><strong class="text-primary"><?php echo $grade['name']; ?></strong></td>
<td><?php echo $grade['min']; ?>% - <?php echo $grade['max']; ?>%</td>
<td><?php echo $grade['remark']; ?></td>
</tr>
<?php
}
}

?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>

<div class="row mt-4">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<h4 class="tile-title">What Do These Grades Mean?</h4>
<div class="row">
<div class="col-md-6">
<div class="alert alert-success">
<strong>EE - Exceeding Expectation</strong>
<p class="mb-0 mt-2">You have gone above and beyond what was expected. You demonstrate advanced understanding and can apply your knowledge in new situations.</p>
</div>
</div>
<div class="col-md-6">
<div class="alert alert-info">
<strong>ME - Meeting Expectation</strong>
<p class="mb-0 mt-2">You have achieved what was expected. You understand the key concepts and can apply them correctly.</p>
</div>
</div>
</div>
<div class="row mt-3">
<div class="col-md-6">
<div class="alert alert-warning">
<strong>AE - Approaching Expectation</strong>
<p class="mb-0 mt-2">You are getting there! You understand some concepts but need to work on others to fully meet expectations.</p>
</div>
</div>
<div class="col-md-6">
<div class="alert alert-danger">
<strong>BE - Below Expectation</strong>
<p class="mb-0 mt-2">You need additional support and practice. Talk to your teacher about extra help and study strategies.</p>
</div>
</div>
</div>
</div>
</div>
</div>

<div class="row mt-4">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<h4 class="tile-title">Tips for Success</h4>
<ul>
<li><strong>Attend all classes</strong> and participate actively in lessons</li>
<li><strong>Complete all assignments</strong> on time and to the best of your ability</li>
<li><strong>Ask questions</strong> when you don't understand something</li>
<li><strong>Review your work</strong> regularly and seek feedback from teachers</li>
<li><strong>Study consistently</strong> rather than cramming before exams</li>
<li><strong>Work with classmates</strong> in study groups to reinforce learning</li>
</ul>
</div>
</div>
</div>
</div>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript">$('#srmsTable').DataTable({"sort" : false});</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>