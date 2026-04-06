<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
if ($res == "1" && $level == "0") {}else{header("location:../"); exit;}

$parents = [];
$links = [];
$students = [];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parents') || !app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException("Parent module is not installed. Run the Postgres migration 001_rbac_attendance.sql and 002_parent_sessions.sql.");
	}

	$stmt = $conn->prepare("SELECT id, fname, lname, phone, email, status FROM tbl_parents ORDER BY id DESC LIMIT 100");
	$stmt->execute();
	$parents = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT ps.parent_id, ps.student_id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name
		FROM tbl_parent_students ps
		JOIN tbl_students st ON st.id = ps.student_id
		ORDER BY ps.parent_id, ps.student_id");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
		$pid = (int)$r['parent_id'];
		if (!isset($links[$pid])) $links[$pid] = [];
		$links[$pid][] = ['id' => (string)$r['student_id'], 'name' => (string)$r['student_name']];
	}

	$stmt = $conn->prepare("SELECT id, concat_ws(' ', fname, mname, lname) AS name FROM tbl_students WHERE status = 1 ORDER BY id");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="content-type" content="text/html;charset=utf-8" />
<head>
<title><?php echo APP_NAME; ?> - Parents</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
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

<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user">
<div>
<p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p>
<p class="app-sidebar__user-designation">Administrator</p>
</div>
</div>
<ul class="app-menu">
<li><a class="app-menu__item" href="admin"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item active" href="admin/parents"><i class="app-menu__icon feather icon-user-plus"></i><span class="app-menu__label">Parents</span></a></li>
</ul>
</aside>

<main class="app-content">
<div class="app-title">
<div>
<h1>Parents</h1>
<p>Create parent accounts and link students.</p>
</div>
</div>

<?php if ($error !== '') { ?>
  <div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>

<div class="tile mb-3">
  <h3 class="tile-title">New Parent</h3>
  <form class="row g-3" method="POST" action="admin/core/new_parent" autocomplete="off">
	<div class="col-md-3">
	  <label class="form-label">First Name</label>
	  <input class="form-control" name="fname" required>
	</div>
	<div class="col-md-3">
	  <label class="form-label">Last Name</label>
	  <input class="form-control" name="lname" required>
	</div>
	<div class="col-md-3">
	  <label class="form-label">Phone</label>
	  <input class="form-control" name="phone" placeholder="+2547...">
	</div>
	<div class="col-md-3">
	  <label class="form-label">Email</label>
	  <input class="form-control" name="email" type="email" required>
	</div>
	<div class="col-md-4">
	  <label class="form-label">Password</label>
	  <input class="form-control" name="password" type="password" required>
	</div>
	<div class="col-md-2">
	  <label class="form-label">Status</label>
	  <select class="form-control" name="status">
		<option value="1" selected>Active</option>
		<option value="0">Blocked</option>
	  </select>
	</div>
	<div class="col-md-6 d-grid align-items-end">
	  <button class="btn btn-primary" type="submit"><i class="bi bi-plus-lg me-1"></i>Create Parent</button>
	</div>
  </form>
</div>

<div class="tile">
  <h3 class="tile-title">Manage Parents</h3>
  <div class="table-responsive">
	<table class="table table-hover table-striped">
	  <thead>
		<tr>
		  <th>ID</th>
		  <th>Name</th>
		  <th>Email</th>
		  <th>Phone</th>
		  <th>Status</th>
		  <th>Linked Students</th>
		  <th>Link Student</th>
		</tr>
	  </thead>
	  <tbody>
	  <?php foreach ($parents as $p) {
		$pid = (int)$p['id'];
		$linked = $links[$pid] ?? [];
	  ?>
		<tr>
		  <td><?php echo $pid; ?></td>
		  <td><?php echo htmlspecialchars((string)$p['fname'].' '.(string)$p['lname']); ?></td>
		  <td><?php echo htmlspecialchars((string)$p['email']); ?></td>
		  <td><?php echo htmlspecialchars((string)($p['phone'] ?? '')); ?></td>
		  <td><?php echo ((int)$p['status'] === 1) ? '<span class="badge bg-success">ACTIVE</span>' : '<span class="badge bg-danger">BLOCKED</span>'; ?></td>
		  <td>
			<?php if (count($linked) < 1) { ?>
			  <span class="text-muted">None</span>
			<?php } else { ?>
			  <?php foreach ($linked as $ls) { ?>
				<div class="d-flex justify-content-between align-items-center mb-1">
				  <span><?php echo htmlspecialchars($ls['id'].' — '.$ls['name']); ?></span>
				  <form method="POST" action="admin/core/unlink_parent_student" style="margin:0;">
					<input type="hidden" name="parent_id" value="<?php echo $pid; ?>">
					<input type="hidden" name="student_id" value="<?php echo htmlspecialchars($ls['id']); ?>">
					<button class="btn btn-sm btn-outline-danger" type="submit">Unlink</button>
				  </form>
				</div>
			  <?php } ?>
			<?php } ?>
		  </td>
		  <td>
			<form class="row g-2" method="POST" action="admin/core/link_parent_student" style="min-width:280px;">
			  <input type="hidden" name="parent_id" value="<?php echo $pid; ?>">
			  <div class="col-8">
				<select class="form-control" name="student_id" required>
				  <option value="" disabled selected>Select student</option>
				  <?php foreach ($students as $st) { ?>
					<option value="<?php echo htmlspecialchars((string)$st['id']); ?>"><?php echo htmlspecialchars((string)$st['id'].' — '.$st['name']); ?></option>
				  <?php } ?>
				</select>
			  </div>
			  <div class="col-4 d-grid">
				<button class="btn btn-sm btn-outline-primary" type="submit">Link</button>
			  </div>
			</form>
		  </td>
		</tr>
	  <?php } ?>
	  </tbody>
	</table>
  </div>
</div>

<?php } ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>

