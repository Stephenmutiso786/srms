<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');

if ($res == "1" && $level == "0") {}else{header("location:../");}
$selectedClasses = isset($_SESSION['student_list']) && is_array($_SESSION['student_list']) ? $_SESSION['student_list'] : [];
$selectedClasses = array_values(array_filter($selectedClasses, static function ($value) {
	return $value !== '' && $value !== null;
}));
$schoolId = function_exists('app_current_school_id') ? app_current_school_id() : 0;
$classes = [];
$studentsByClass = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$classSql = "SELECT id, name FROM tbl_classes";
	$classParams = [];
	if (app_column_exists($conn, 'tbl_classes', 'school_id') && $schoolId > 0) {
		$classSql .= " WHERE school_id IS NULL OR school_id = ?";
		$classParams[] = $schoolId;
	}
	$stmt = $conn->prepare($classSql . " ORDER BY name ASC");
	$stmt->execute($classParams);
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$classNames = [];
	foreach ($classes as $classRow) {
		$classNames[(string)$classRow['id']] = (string)$classRow['name'];
	}

	if (!empty($selectedClasses)) {
		$placeholders = implode(',', array_fill(0, count($selectedClasses), '?'));
		$studentSql = "SELECT st.id, st.fname, st.mname, st.lname, st.gender, st.class, st.display_image
			FROM tbl_students st";
		$studentParams = [];
		if (app_column_exists($conn, 'tbl_students', 'tenant_school_id') && $schoolId > 0) {
			$studentSql .= " WHERE (st.tenant_school_id IS NULL OR st.tenant_school_id = ?)";
			$studentParams[] = $schoolId;
		} elseif (app_column_exists($conn, 'tbl_students', 'school_id') && $schoolId > 0) {
			$studentSql .= " WHERE (st.school_id IS NULL OR st.school_id = ?)";
			$studentParams[] = $schoolId;
		}
		$studentSql .= (strpos($studentSql, ' WHERE ') === false ? ' WHERE ' : ' AND ') . "st.class IN ($placeholders)";
		$studentParams = array_merge($studentParams, $selectedClasses);
		$studentSql .= " ORDER BY st.class ASC, st.fname ASC, st.mname ASC, st.lname ASC, st.id ASC";
		$stmt = $conn->prepare($studentSql);
		$stmt->execute($studentParams);
		foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
			$className = (string)($classNames[(string)($row['class'] ?? '')] ?? 'Unassigned');
			$studentsByClass[$className][] = $row;
		}
	}
} catch (Throwable $e) {
	$classes = [];
	$studentsByClass = [];
}
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
<div class="col-md-4 center_form">
<div class="tile">
<div class="tile-body">
<div class="table-responsive">
<h3 class="tile-title">Manage Students</h3>
<form class="app_frm" method="POST" autocomplete="OFF" action="admin/core/list_students">

<div class="mb-2">
<label class="form-label">Select Class</label>
<select multiple="true" class="form-control select2" name="class[]" required style="width: 100%;">
<?php
foreach($classes as $row)
{
?>
<option value="<?php echo (int)$row['id']; ?>"><?php echo htmlspecialchars((string)$row['name']); ?> </option>
<?php
}
?>
</select>
</div>


<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Manage Students</button>
</form>
</div>
</div>
</div>
</div>
</div>

<?php if (!empty($selectedClasses)): ?>
<div class="row mt-3">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
<h3 class="tile-title mb-0">Selected Class Students</h3>
<form method="POST" action="admin/core/export_students_text" class="d-inline-block">
<?php foreach ($selectedClasses as $classId): ?>
<input type="hidden" name="class[]" value="<?php echo htmlspecialchars((string)$classId); ?>">
<?php endforeach; ?>
<button type="submit" class="btn btn-outline-primary">Export Text</button>
</form>
</div>
<?php foreach ($studentsByClass as $className => $students): ?>
<div class="mb-4">
<h5 class="mb-2"><?php echo htmlspecialchars($className); ?> <small class="text-muted">(<?php echo count($students); ?> students)</small></h5>
<textarea class="form-control" rows="<?php echo max(4, count($students) + 1); ?>" readonly><?php
foreach ($students as $student) {
	echo trim((string)($student['fname'] ?? '') . ' ' . (string)($student['mname'] ?? '') . ' ' . (string)($student['lname'] ?? '')) . PHP_EOL;
}
?></textarea>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
<?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
<div class="row mt-3">
<div class="col-md-12">
<div class="alert alert-warning">No students found for the selected class list.</div>
</div>
</div>
<?php endif; ?>


</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/forms.js"></script>
<script src="js/sweetalert2@11.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script src="select2/dist/js/select2.full.min.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
$('.select2').select2()
</script>
</body>

</html>
