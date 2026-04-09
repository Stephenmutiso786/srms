<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
if (!isset($_SESSION['student_list'])) {
header("location:./");
}
$students = $_SESSION['student_list'];
//$matches = implode(',', $students);
//$matches = preg_replace('/[A-Z0-9]/', '?', $matches);
$matches = str_split(str_repeat("?", count($students)));
$matches = implode(',', $matches);
// print_r($matches);
// exit;
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Manage Students</title>
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
<h1>Manage Students</h1>
</div>
</div>


<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Manage Students</h3>
<form id="bulkStudentsForm" method="POST" action="admin/core/bulk_delete_students" onsubmit="return confirmBulkDelete('students');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
<select class="form-control form-control-sm" name="bulk_action" style="max-width:200px;">
<option value="delete" selected>Delete selected</option>
<option value="set_active">Set status: Active</option>
<option value="set_blocked">Set status: Blocked</option>
</select>
<button type="submit" class="btn btn-primary btn-sm">Apply</button>
<div class="form-check ms-2">
<input class="form-check-input" type="checkbox" id="selectAllStudents">
<label class="form-check-label" for="selectAllStudents">Select all</label>
</div>
</div>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th width="40"><input class="form-check-input" type="checkbox" id="selectAllStudentsHead"></th>
<th></th>
<th>Registration Number</th>
<th>School ID</th>
<th>Firstname</th>
<th>Middlename</th>
<th>Lastname</th>
<th>Gender</th>
<th>Email</th>
<th>Class</th>
<th></th>
</tr>
</thead>
<tbody>
<?php

try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$empty_classes = array();

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
$stmt->execute();
$classes = $stmt->fetchAll();

foreach ($classes as $value) {
$empty_classes[$value[0]] = $value[1];
}


$stmt = $conn->prepare("SELECT * FROM tbl_students WHERE class IN ($matches)");
$stmt->execute($students);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach($result as $row)
{
?>

<tr>
<td>
<input class="form-check-input student-checkbox" type="checkbox" name="student_ids[]" value="<?php echo $row[0]; ?>">
</td>
<td width="10">
<?php
if (($row['display_image'] ?? '') == "DEFAULT") {
?><img src="images/students/<?php echo $row['gender']; ?>.png" class="avatar_img_sm"><?php
}else{
?><img src="images/students/<?php echo $row['display_image']; ?>" class="avatar_img_sm"><?php
}
?>
</td>
<td><?php echo $row['id']; ?></td>
<td><?php echo htmlspecialchars($row['school_id'] ?? ''); ?></td>
<td><?php echo $row['fname']; ?></td>
<td><?php echo $row['mname']; ?></td>
<td><?php echo $row['lname']; ?></td>
<td><?php echo $row['gender']; ?></td>
<td><?php echo $row['email']; ?></td>
<td><?php echo $empty_classes[$row['class']]; ?></td>

<td align="center" width="130">

<textarea style="display:none;" id="fname_<?php echo $row['id']; ?>"><?php echo $row['fname']; ?></textarea>
<textarea style="display:none;" id="mname_<?php echo $row['id']; ?>"><?php echo $row['mname']; ?></textarea>
<textarea style="display:none;" id="lname_<?php echo $row['id']; ?>"><?php echo $row['lname']; ?></textarea>
<textarea style="display:none;" id="gender_<?php echo $row['id']; ?>"><?php echo $row['gender']; ?></textarea>

<textarea style="display:none;" id="email_<?php echo $row['id']; ?>"><?php echo $row['email']; ?></textarea>
<textarea style="display:none;" id="class_<?php echo $row['id']; ?>"><?php echo $row['class']; ?></textarea>
<textarea style="display:none;" id="img_<?php echo $row['id']; ?>"><?php echo $row['display_image']; ?></textarea>
<textarea style="display:none;" id="status_<?php echo $row['id']; ?>"><?php echo $row['status']; ?></textarea>

<a onclick="set_student('<?php echo $row['id']; ?>');" class="btn btn-primary btn-sm" href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#editModal">Edit</a>
<a onclick="del('admin/core/drop_student?id=<?php echo $row['id']; ?>&img=<?php echo $row['display_image']; ?>', 'Delete Student?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
</td>

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
</form>
</div>
</div>
</div>
</div>
</div>
</div>

<div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editModalLabel">Edit Student</h5>
</div>
<div class="modal-body">

<form enctype="multipart/form-data" action="admin/core/update_student" class="app_frm" method="POST" autocomplete="OFF">
<div class="mb-2">
<label class="form-label">First Name</label>
<input id="fname" name="fname" required class="form-control" onkeypress="return lettersOnly(event)" type="text" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Middle Name</label>
<input id="mname" name="mname" required class="form-control" onkeypress="return lettersOnly(event)" type="text" placeholder="Enter middle name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input id="lname" name="lname" required class="form-control" onkeypress="return lettersOnly(event)" type="text" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select id="gender" class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>

<div class="mb-2">
<label class="form-label">Select Class</label>
<select id="class" class="form-control select2" name="class" required style="width: 100%;">
<option value="" selected disabled> Select One</option>
<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_classes");
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
echo "Connection failed: " . $e->getMessage();
}
?>
</select>
</div>

<div class="mb-2">
<label class="form-label">Email</label>
<input id="email" name="email" required class="form-control" type="text" placeholder="Enter email address">
</div>

<div class="mb-3">
<label class="form-label">Display Image (Optional)</label>
<input name="image" class="form-control" type="file" accept=".png, .jpg, .jpeg">
</div>

<input type="hidden" name="old_photo" id="photo">
<input type="hidden" name="id" id="id">
<div class="">
<button class="btn btn-primary app_btn" type="submit">Save</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</div>
</form>

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
function confirmBulkDelete(label){
  var checked = document.querySelectorAll('.student-checkbox:checked');
  if (!checked.length) {
    alert('Please select at least one student.');
    return false;
  }
  var action = document.querySelector('select[name="bulk_action"]');
  var val = action ? action.value : 'delete';
  if (val === 'delete') {
    return confirm('Delete selected ' + label + '? This action cannot be undone.');
  }
  return confirm('Update status for selected ' + label + '?');
}
function bindSelectAll(sourceId, targetClass) {
  var source = document.getElementById(sourceId);
  if (!source) return;
  source.addEventListener('change', function(){
    document.querySelectorAll(targetClass).forEach(function(cb){
      cb.checked = source.checked;
    });
  });
}
bindSelectAll('selectAllStudents', '.student-checkbox');
bindSelectAll('selectAllStudentsHead', '.student-checkbox');
</script>
</body>

</html>
