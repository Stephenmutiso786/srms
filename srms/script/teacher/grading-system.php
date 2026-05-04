<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res != "1" || !in_array($level, ["0","1","2"])) { header("location:../"); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - CBC Grading System</title>
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
<li><a class="dropdown-item" href="teacher/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>

<?php include('teacher/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>CBC Grading System</h1>
<p class="text-muted">Competency-Based Curriculum grading bands used for assessment.</p>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">CBC Grading Bands</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>Grade Level</th>
<th>Minimum Score</th>
<th>Maximum Score</th>
<th>Points</th>
<th>Remark</th>
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
['name' => 'EE', 'min' => 90, 'max' => 100, 'points' => 4, 'remark' => 'Exceeding Expectation'],
['name' => 'ME', 'min' => 75, 'max' => 89, 'points' => 3, 'remark' => 'Meeting Expectation'],
['name' => 'AE', 'min' => 50, 'max' => 74, 'points' => 2, 'remark' => 'Approaching Expectation'],
['name' => 'BE', 'min' => 0, 'max' => 49, 'points' => 1, 'remark' => 'Below Expectation'],
];
}

foreach($grades as $row)
{
?>
<tr>
<td><strong><?php echo htmlspecialchars((string)($row['name'] ?? '')); ?></strong></td>
<td><?php echo number_format((float)($row['min'] ?? 0), 2); ?>%</td>
<td><?php echo number_format((float)($row['max'] ?? 100), 2); ?>%</td>
<td><?php echo number_format((float)($row['points'] ?? 0), 2); ?></td>
<td><?php echo htmlspecialchars((string)($row['remark'] ?? '')); ?></td>
</tr>
<?php
}

}catch(PDOException $e)
{
error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
$defaultGrades = [
['name' => 'EE', 'min' => 90, 'max' => 100, 'points' => 4, 'remark' => 'Exceeding Expectation'],
['name' => 'ME', 'min' => 75, 'max' => 89, 'points' => 3, 'remark' => 'Meeting Expectation'],
['name' => 'AE', 'min' => 50, 'max' => 74, 'points' => 2, 'remark' => 'Approaching Expectation'],
['name' => 'BE', 'min' => 0, 'max' => 49, 'points' => 1, 'remark' => 'Below Expectation'],
];
foreach($defaultGrades as $grade)
{
?>
<tr>
<td><strong><?php echo $grade['name']; ?></strong></td>
<td><?php echo $grade['min']; ?>%</td>
<td><?php echo $grade['max']; ?>%</td>
<td><?php echo $grade['points']; ?></td>
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
<h4 class="tile-title">Understanding CBC Grades</h4>
<ul>
<li><strong>EE (Exceeding Expectation):</strong> Score 90-100% - Learner exceeds expected competencies</li>
<li><strong>ME (Meeting Expectation):</strong> Score 75-89% - Learner meets expected competencies</li>
<li><strong>AE (Approaching Expectation):</strong> Score 50-74% - Learner is approaching competencies</li>
<li><strong>BE (Below Expectation):</strong> Score 0-49% - Learner needs additional support</li>
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