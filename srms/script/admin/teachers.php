<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../");}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Staff</title>
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
<h1>Staff</h1>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add</button></li>
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#importModal">Import</button></li>
</ul>
</div>

<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="addModalLabel">Add Staff</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/new_user2">
<div class="mb-2">
<label class="form-label">First Name</label>
<input required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input required name="lname" class="form-control" type="text"  onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input required name="email" class="form-control" type="email" placeholder="Enter email address">
</div>
<div class="mb-2">
<label class="form-label">Password</label>
<input type="password" class="form-control" id="npass" name="password" placeholder="***************">
</div>
<div class="mb-2">
<label class="form-label">Confirm Password</label>
<input type="password" class="form-control" id="cnpass" placeholder="***************">
</div>
<div class="mb-2">
<label class="form-label">Gender</label>
<select class="form-control" name="gender" required>
<option selected disabled value="">Select gender</option>
<option value="Male">Male</option>
<option value="Female">Female</option>
</select>
</div>

<div class="mb-2">
<label class="form-label">Role</label>
<select class="form-control" name="role" required>
<option value="2" selected>Teacher</option>
<option value="5">Accountant</option>
</select>
</div>


<div class="mb-3">
<label class="form-label">Status</label>
<select class="form-control" name="status" required>
<option selected disabled value="">Select status</option>
<option value="1">Active</option>
<option value="0">Blocked</option>
</select>
</div>

<button id="sub_btnp2" type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="editModalLabel">Edit Staff</h5>
</div>
<div class="modal-body">
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/update_user2">
<div class="mb-2">
<label class="form-label">First Name</label>
<input id="fname" required name="fname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter first name">
</div>
<div class="mb-2">
<label class="form-label">Last Name</label>
<input id="lname" required name="lname" class="form-control" type="text" onkeypress="return lettersOnly(event)" placeholder="Enter last name">
</div>
<div class="mb-2">
<label class="form-label">Email Address</label>
<input id="email" required name="email" class="form-control" type="email" placeholder="Enter email address">
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
<label class="form-label">Role</label>
<select id="role" class="form-control" name="role" required>
<option value="2">Teacher</option>
<option value="5">Accountant</option>
</select>
</div>


<div class="mb-3">
<label class="form-label">Status</label>
<select id="status" class="form-control" name="status" required>
<option selected disabled value="">Select status</option>
<option value="1">Active</option>
<option value="0">Blocked</option>
</select>
</div>
<input type="hidden" name="id" id="id">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Save</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="modal fade" id="importModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header">
<h5 class="modal-title" id="importModalLabel">Import Teachers</h5>
</div>
<div class="modal-body">
<form enctype="multipart/form-data" class="app_frm" method="POST" autocomplete="OFF" action="admin/core/import_users">
<div class="mb-3">
<label class="form-label">Excel File</label>
<input required accept=".xlsx" type="file" name="file" class="form-control" accept="application/msexcel">
</div>


<div class="alert alert-info">
Download excel template from <a download href="templates/import_teachers.xlsx" class="alert-link">here</a>
</div>
<button type="submit" name="submit" value="1" class="btn btn-primary">Import</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div>

</div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Staff</h3>
<form id="bulkStaffForm" method="POST" action="admin/core/bulk_delete_staff" onsubmit="return confirmBulkDelete('staff');">
<div class="d-flex flex-wrap align-items-center gap-2 mb-2">
<select class="form-control form-control-sm" name="bulk_action" style="max-width:200px;">
<option value="delete" selected>Delete selected</option>
<option value="set_active">Set status: Active</option>
<option value="set_blocked">Set status: Blocked</option>
</select>
<button type="submit" class="btn btn-primary btn-sm">Apply</button>
<div class="form-check ms-2">
<input class="form-check-input" type="checkbox" id="selectAllStaff">
<label class="form-check-label" for="selectAllStaff">Select all</label>
</div>
</div>
<table class="table table-hover table-bordered" id="srmsTable">
<thead>
<tr>
<th width="40"><input class="form-check-input" type="checkbox" id="selectAllStaffHead"></th>
<th>First Name</th>
<th>Last Name</th>
<th>Email</th>
<th>Gender</th>
<th>Role</th>
<th width="120" align="center">Status</th>
<th width="120" align="center"></th>
</tr>
</thead>
<tbody>

<?php
try {
$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$stmt = $conn->prepare("SELECT * FROM tbl_staff WHERE level IN (2,5)");
$stmt->execute();
$result = $stmt->fetchAll();

foreach($result as $row)
{
if ($row[7] == "1") {
$st = '<span class="me-1 badge badge-pill bg-success">Active</span>';
}else{
$st = '<span class="me-1 badge badge-pill bg-danger">Blocked</span>';
}

?>
<tr>
<td>
<input class="form-check-input staff-checkbox" type="checkbox" name="staff_ids[]" value="<?php echo $row[0]; ?>">
</td>
<td><?php echo $row[1];?></td>
<td><?php echo $row[2];?></td>
<td><?php echo $row[4];?></td>
<td><?php echo $row[3];?></td>
<td><?php echo ((int)$row[6] === 5) ? 'Accountant' : 'Teacher'; ?></td>
<td width="100" align="center"><?php echo $st;?></td>
<td width="120" align="center">
<textarea style="display:none;" id="fname_<?php echo $row[0]; ?>"><?php echo $row[1]; ?></textarea>
<textarea style="display:none;" id="lname_<?php echo $row[0]; ?>"><?php echo $row[2]; ?></textarea>
<textarea style="display:none;" id="email_<?php echo $row[0]; ?>"><?php echo $row[4]; ?></textarea>
<textarea style="display:none;" id="role_<?php echo $row[0]; ?>"><?php echo $row[6]; ?></textarea>
<button onclick="set_user('<?php echo $row[0]; ?>', '<?php echo $row[3]; ?>', '<?php echo $row[7]; ?>', '<?php echo $row[6]; ?>');" data-bs-toggle="modal" data-bs-target="#editModal" class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#editModal">Edit</button>
<a onclick="del('admin/core/drop_user2?id=<?php echo $row[0]; ?>', 'Delete Teacher?');" href="javascript:void(0);" class="btn btn-danger btn-sm">Delete</a>
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
<script type="text/javascript">
function set_user(id, gender, status, role){
	document.getElementById("id").value = id;
	document.getElementById("fname").value = document.getElementById("fname_"+id).value;
	document.getElementById("lname").value = document.getElementById("lname_"+id).value;
	document.getElementById("email").value = document.getElementById("email_"+id).value;
	document.getElementById("gender").value = gender;
	document.getElementById("status").value = status;
	document.getElementById("role").value = role;
}
function confirmBulkDelete(label){
  var checked = document.querySelectorAll('.staff-checkbox:checked');
  if (!checked.length) {
    alert('Please select at least one staff member.');
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
bindSelectAll('selectAllStaff', '.staff-checkbox');
bindSelectAll('selectAllStaffHead', '.staff-checkbox');
</script>
<?php require_once('const/check-reply.php'); ?>
</body>

</html>
