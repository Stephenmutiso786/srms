<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "1") {}else{header("location:../");}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Announcements</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<link rel="stylesheet" href="cdn.datatables.net/v/bs5/dt-1.13.4/datatables.min.css">
<script src="summernote/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<link href="summernote/summernote-lite.min.css" rel="stylesheet">
<script src="summernote/summernote-lite.min.js"></script>
<script src="summernote/summernote-fontawesome.js"></script>
<link type="text/css" rel="stylesheet" href="loader/waitMe.css">
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

<?php include('academic/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Announcements</h1>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add</button></li>
</ul>
</div>

<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addModalLabel">Add Announcements</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="academic/core/new_announcement">
<div class="mb-2">
<label class="form-label">Enter Title</label>
<input required type="text" name="title" class="form-control txt-cap" placeholder="Enter Announcement Title">
</div>

<div class="mb-3">
<label class="form-label">Audience</label>
<select class="form-control" name="audience" required>
<option selected disabled value="">Select one</option>
<option value="1">Students Only</option>
<option value="0">Teachers Only</option>
<option value="2">Students & Teachers</option>
</select>
</div>

<div class="mb-3">
<label class="form-label">Announcement</label>
<textarea   name="announcement" id="summernote" required></textarea>
<script>
$('#summernote').summernote({
tabsize: 2,
height: 120,
fontNames: ['Comic Sans MS']
});
</script>
</div>

<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editModalLabel">Edit Announcement</h5>
</div>
<div class="modal-body" id="ajax_callback">

</div>

</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Announcements</h3>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th>Title</th>
<th>Audience</th>
<th>Create Date</th>
<th width="120"></th>
</tr>
</thead>
<tbody>
<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_announcements ORDER BY id DESC");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
?>
<tr>
<td><?php echo $row[1]; ?></td>
<td>
<?php
switch ($row[4]) {
case '0':
echo "Teachers Only";
break;

case '1':
echo "Students Only";
break;

case '2':
echo "Teachers & Students";
break;

}
?>
</td>
<td><?php echo $row[3]; ?></td>
<td align="center">

<a onclick="set_announcement('<?php echo $row[0]; ?>');" class="btn btn-primary btn-sm" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal">Edit</a>
<a onclick="del('academic/core/drop_announcement?id=<?php echo $row[0]; ?>', 'Delete Announcement?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
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
<script>

</script>
</body>

</html>
