<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}

if (isset($_SESSION['student_result'])) {
$std = $_SESSION['student_result']['student'];
$term = $_SESSION['student_result']['term'];

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE id = ?");
$stmt->execute([$std]);
$std_data = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_terms WHERE id = ?");
$stmt->execute([$term]);
$term_data = $stmt->fetchAll();

$stmt = $conn->prepare("SELECT * FROM tbl_classes WHERE id = ?");
$stmt->execute([$std_data[0][6]]);
$class_data = $stmt->fetchAll();

$tit = ''.$std_data[0][1].' '.$std_data[0][2].' '.$std_data[0][3].' ('.$term_data[0][1].' Results)';
}catch(PDOException $e)
{
echo "Connection failed: " . $e->getMessage();
}

}else{
header("location:./");
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - <?php echo $tit ?></title>
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
<li><a class="dropdown-item" href="academic/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
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
<p class="app-sidebar__user-designation">Academic</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="academic"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="academic/terms"><i class="app-menu__icon feather icon-folder"></i><span class="app-menu__label">Academic Terms</span></a></li>

<li><a class="app-menu__item" href="academic/classes"><i class="app-menu__icon feather icon-home"></i><span class="app-menu__label">Classes</span></a></li>
<li><a class="app-menu__item" href="academic/subjects"><i class="app-menu__icon feather icon-book"></i><span class="app-menu__label">Subjects</span></a></li>
<li><a class="app-menu__item" href="academic/combinations"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">Subject Combinations</span></a></li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-users"></i><span class="app-menu__label">Students</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">
<li><a class="treeview-item" href="academic/promote_students"><i class="icon bi bi-circle-fill"></i> Promote Students</a></li>
</ul>
</li>
<li class="treeview"><a class="app-menu__item" href="javascript:void(0);" data-toggle="treeview"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Examination Results</span><i class="treeview-indicator bi bi-chevron-right"></i></a>
<ul class="treeview-menu">

<li><a class="treeview-item" href="academic/manage_results"><i class="icon bi bi-circle-fill"></i> Manage Results</a></li>
<li><a class="treeview-item" href="academic/individual_results"><i class="icon bi bi-circle-fill"></i> Individual Results</a></li>
</ul>
</li>
<li><a class="app-menu__item" href="academic/report"><i class="app-menu__icon feather icon-bar-chart-2"></i><span class="app-menu__label">Report Tool</span></a></li>
<li><a class="app-menu__item" href="academic/grading-system"><i class="app-menu__icon feather icon-award"></i><span class="app-menu__label">Grading System</span></a></li>
<li><a class="app-menu__item" href="academic/division-system"><i class="app-menu__icon feather icon-layers"></i><span class="app-menu__label">Division System</span></a></li>
<li><a class="app-menu__item" href="academic/announcement"><i class="app-menu__icon feather icon-bell"></i><span class="app-menu__label">Announcements</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title">
<div>
<h1><?php echo $tit ?></h1>
</div>
</div>


<div class="row">
<div class="col-md-12 ">
<div class="tile">
<div class="tile-body">

<form enctype="multipart/form-data" action="academic/core/update_results" class="app_frm row" method="POST" autocomplete="OFF">

<?php
$tscore = 0;
$stmt = $conn->prepare("SELECT * FROM tbl_subject_combinations LEFT JOIN tbl_subjects ON tbl_subject_combinations.subject = tbl_subjects.id");
$stmt->execute();
$result = $stmt->fetchAll();

foreach ($result as $key => $row) {
$class_list = app_unserialize($row[1]);

if (in_array($std_data[0][6], $class_list))
{

$score = 0;

$stmt = $conn->prepare("SELECT * FROM tbl_exam_results WHERE class = ? AND subject_combination = ? AND term = ? AND student = ?");
$stmt->execute([$std_data[0][6], $row[0], $term, $std]);
$ex_result = $stmt->fetchAll();

if (!empty($ex_result[0][5])) {
$score = $ex_result[0][5];
$tscore = $tscore + $score;
}

?>

<div class="mb-3 col-md-2">
<label class="form-label"><?php echo $row[6]; ?></label>
<input value="<?php echo $score; ?>" name="<?php echo $row[0];?>" class="form-control" required type="number" placeholder="Enter score">
</div>

<?php
}


}

?>
<input type="hidden" name="student" value="<?php echo $std; ?>">
<input type="hidden" name="term" value="<?php echo $term; ?>">
<input type="hidden" name="class" value="<?php echo $std_data[0][6]; ?>">
<div class="">
<button class="btn btn-primary app_btn" type="submit">Save Results</button>
<?php if ($tscore > 0) {
?><a onclick="del('academic/core/drop_results?src=single_results&std=<?php echo $std; ?>&class=<?php echo $std_data[0][6]; ?>&term=<?php echo $term; ?>', 'Delete Results?');" href="javascript:void(0);" class="btn btn-danger">Delete</a><?php
}
?>
</div>
</form>


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
