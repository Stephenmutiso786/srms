<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('students.manage', '../admin');
$teachers = [];
$subjects = [];
$classSubjectMap = [];
$classTeacherMap = [];
$streamGroups = [];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_class_teachers_table($conn);
	$stmt = $conn->prepare("SELECT id, fname, lname FROM tbl_staff WHERE level = 2 AND status = 1 ORDER BY fname, lname");
	$stmt->execute();
	$teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt = $conn->prepare("SELECT id, name FROM tbl_subjects ORDER BY name");
	$stmt->execute();
	$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY name");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $classRow) {
		$classId = (int)$classRow['id'];
		$classSubjectMap[$classId] = app_class_subject_ids($conn, $classId);
		$classTeacherMap[$classId] = app_class_subject_teacher_rows($conn, $classId);
		$parts = app_class_name_parts((string)$classRow['name']);
		$gradeKey = $parts['grade'] !== '' ? $parts['grade'] : (string)$classRow['name'];
		$streamGroups[$gradeKey][] = [
			'id' => $classId,
			'stream' => $parts['stream'] !== '' ? $parts['stream'] : 'Main',
			'name' => (string)$classRow['name'],
		];
	}
} catch (Throwable $e) {
	$teachers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Class Management</title>
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
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Class Management</h1>
<p>Create grades, add streams under them, then keep class teachers and subjects ready for teacher allocation, exams, and report cards.</p>
</div>
<ul class="app-breadcrumb breadcrumb">
<li class="breadcrumb-item">
<form method="POST" action="admin/core/apply_cbc_structure" onsubmit="return confirm('Apply the Kenya CBC primary + junior class structure and replace unused extra classes/subjects?');">
<button class="btn btn-outline-success btn-sm" type="submit">Apply Kenya CBC Defaults</button>
</form>
</li>
<li class="breadcrumb-item"><button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addModal">Add Grade / Stream</button></li>
<li class="breadcrumb-item"><button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addStreamModal">Add Stream to Existing Grade</button></li>
</ul>
</div>

<div class="tile mb-3">
<div class="tile-body">
<div class="alert alert-info mb-0">
<strong>Class management is now the main setup point:</strong> create the grade, add streams under it, set the class teacher, and choose the subjects here. Subject teachers are listed per class below for easier management, while <a href="admin/teacher_allocation">Teacher Allocation</a> still handles the actual teacher-to-subject assignment records.
<hr class="my-2">
<span class="small">Need the Kenya CBC primary + junior setup quickly? Use <strong>Apply Kenya CBC Defaults</strong> above to load PP1 to Grade 9, attach the recommended subjects per level, and clear unused extras that are not already in active use.</span>
</div>
</div>
</div>

<div class="row mb-3">
<div class="col-md-12">
<div class="tile">
<div class="tile-body">
<h3 class="tile-title">Class → Streams Overview</h3>
<div class="row">
<?php foreach ($streamGroups as $gradeName => $streams): ?>
<div class="col-md-4 mb-3">
<div class="border rounded p-3 h-100">
<div class="d-flex justify-content-between align-items-start mb-2">
<div>
<div class="fw-bold"><?php echo htmlspecialchars($gradeName); ?></div>
<div class="small text-muted"><?php echo count($streams); ?> stream(s)</div>
</div>
<button
	type="button"
	class="btn btn-outline-primary btn-sm add-stream-btn"
	data-grade="<?php echo htmlspecialchars($gradeName); ?>"
	data-bs-toggle="modal"
	data-bs-target="#addStreamModal">Add Stream</button>
</div>
<ul class="mb-0 ps-3">
<?php foreach ($streams as $stream): ?>
<li><?php echo htmlspecialchars($stream['name']); ?></li>
<?php endforeach; ?>
</ul>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>
</div>

<div class="modal fade" id="addModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Grade / Stream</h5></div><div class="modal-body">
<form class="app_frm" method="POST" autocomplete="off" action="admin/core/new_class">
<div class="mb-3"><label class="form-label">Grade / Class</label><input required name="grade_name" class="form-control" type="text" placeholder="e.g. Grade 8"></div>
<div class="mb-3"><label class="form-label">Stream / Section</label><input name="stream_name" class="form-control" type="text" placeholder="e.g. A"></div>
<div class="mb-3"><label class="form-label">Class Teacher</label><select name="class_teacher_id" class="form-control"><option value="">Select class teacher (optional)</option><?php foreach ($teachers as $teacher) { ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo htmlspecialchars(trim(($teacher['fname'] ?? '').' '.($teacher['lname'] ?? ''))); ?></option><?php } ?></select></div>
<div class="mb-3"><label class="form-label">Subjects for this Class / Stream</label><select name="subject_ids[]" class="form-control" multiple size="8"><?php foreach ($subjects as $subject) { ?><option value="<?php echo (int)$subject['id']; ?>"><?php echo htmlspecialchars((string)$subject['name']); ?></option><?php } ?></select><div class="form-text">Select the subjects this class or stream will do. This becomes the source of truth for exams and teacher allocation.</div></div>
<div class="form-text mb-3">The system will save this as one class name, for example <strong>Grade 8 A</strong>.</div>
<input type="hidden" name="name" value="">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div></div></div>
</div>

<div class="modal fade" id="addStreamModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Add Stream to Existing Grade</h5></div><div class="modal-body">
<form class="app_frm" method="POST" autocomplete="off" action="admin/core/new_class">
<div class="mb-3">
<label class="form-label">Grade / Class</label>
<select id="stream_grade_name" required name="grade_name" class="form-control">
<option value="">Select existing grade</option>
<?php foreach (array_keys($streamGroups) as $gradeName) { ?>
<option value="<?php echo htmlspecialchars($gradeName); ?>"><?php echo htmlspecialchars($gradeName); ?></option>
<?php } ?>
</select>
</div>
<div class="mb-3"><label class="form-label">New Stream / Section</label><input required name="stream_name" class="form-control" type="text" placeholder="e.g. East"></div>
<div class="mb-3"><label class="form-label">Class Teacher</label><select name="class_teacher_id" class="form-control"><option value="">Select class teacher (optional)</option><?php foreach ($teachers as $teacher) { ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo htmlspecialchars(trim(($teacher['fname'] ?? '').' '.($teacher['lname'] ?? ''))); ?></option><?php } ?></select></div>
<div class="mb-3"><label class="form-label">Subjects for this Stream</label><select name="subject_ids[]" class="form-control" multiple size="8"><?php foreach ($subjects as $subject) { ?><option value="<?php echo (int)$subject['id']; ?>"><?php echo htmlspecialchars((string)$subject['name']); ?></option><?php } ?></select></div>
<div class="form-text mb-3">Use this when the grade already exists and you only want to add another stream under it.</div>
<input type="hidden" name="name" value="">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Add Stream</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div></div></div>
</div>

<div class="modal fade" id="editModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
<div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Class</h5></div><div class="modal-body">
<form class="app_frm" method="POST" autocomplete="off" action="admin/core/update_class">
<div class="mb-3"><label class="form-label">Grade / Class</label><input id="grade_name" required name="grade_name" class="form-control" type="text" placeholder="e.g. Grade 8"></div>
<div class="mb-3"><label class="form-label">Stream / Section</label><input id="stream_name" name="stream_name" class="form-control" type="text" placeholder="e.g. A"></div>
<div class="mb-3"><label class="form-label">Class Teacher</label><select id="class_teacher_id" name="class_teacher_id" class="form-control"><option value="">Select class teacher (optional)</option><?php foreach ($teachers as $teacher) { ?><option value="<?php echo (int)$teacher['id']; ?>"><?php echo htmlspecialchars(trim(($teacher['fname'] ?? '').' '.($teacher['lname'] ?? ''))); ?></option><?php } ?></select></div>
<div class="mb-3"><label class="form-label">Subjects for this Class / Stream</label><select id="edit_subject_ids" name="subject_ids[]" class="form-control" multiple size="8"><?php foreach ($subjects as $subject) { ?><option value="<?php echo (int)$subject['id']; ?>"><?php echo htmlspecialchars((string)$subject['name']); ?></option><?php } ?></select></div>
<div class="form-text mb-3">Edit grade and stream separately; the system combines them into one class name.</div>
<input id="name" name="name" type="hidden">
<input type="hidden" name="id" id="id">
<button type="submit" name="submit" value="1" class="btn btn-primary app_btn">Save</button>
<button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
</form>
</div></div></div>
</div>

<div class="row"><div class="col-md-12"><div class="tile"><div class="tile-body"><div class="table-responsive">
<h3 class="tile-title">Classes, Subjects, and Teachers</h3>
<p class="text-muted">Every row below is one stream. That means `Grade 6 East` and `Grade 6 West` are managed independently while still rolling up under the same grade in the overview above.</p>
<table class="table table-hover table-bordered" id="srmsTable">
<thead><tr><th>Grade</th><th>Stream</th><th>Saved Class Name</th><th>Class Teacher</th><th>Subjects</th><th>Subject Teachers</th><th>Added On</th><th width="140"></th></tr></thead>
<tbody>
<?php
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT c.*, ct.teacher_id, st.fname AS teacher_fname, st.lname AS teacher_lname
		FROM tbl_classes c
		LEFT JOIN tbl_class_teachers ct ON ct.class_id = c.id AND ct.active = 1
		LEFT JOIN tbl_staff st ON st.id = ct.teacher_id
		ORDER BY c.name");
	$stmt->execute();
	foreach($stmt->fetchAll() as $row) {
		$parts = app_class_name_parts((string)$row[1]);
		$subjectIds = $classSubjectMap[(int)$row[0]] ?? [];
		$subjectNames = [];
		foreach ($subjects as $subject) {
			if (in_array((int)$subject['id'], $subjectIds, true)) {
				$subjectNames[] = (string)$subject['name'];
			}
		}
		$teacherRows = $classTeacherMap[(int)$row[0]] ?? [];
		$teacherLines = [];
		foreach ($teacherRows as $teacherRow) {
			$teacherLines[] = $teacherRow['subject_name'] . ': ' . (!empty($teacherRow['teachers']) ? implode(', ', $teacherRow['teachers']) : 'Not assigned');
		}
?>
<tr>
<td><?php echo htmlspecialchars($parts['grade']); ?></td>
<td><?php echo htmlspecialchars($parts['stream'] !== '' ? $parts['stream'] : '—'); ?></td>
<td><?php echo htmlspecialchars($row[1]); ?></td>
<td><?php echo htmlspecialchars(trim(($row['teacher_fname'] ?? '').' '.($row['teacher_lname'] ?? '')) ?: '—'); ?></td>
<td><?php echo htmlspecialchars(!empty($subjectNames) ? implode(', ', $subjectNames) : 'No subjects set'); ?></td>
<td><?php echo htmlspecialchars(!empty($teacherLines) ? implode(' | ', $teacherLines) : 'No subject teachers assigned'); ?></td>
<td><?php echo htmlspecialchars($row[2] ?? ''); ?></td>
<td align="center">
<button
	type="button"
	class="btn btn-primary btn-sm edit-class"
	data-id="<?php echo $row[0]; ?>"
	data-name="<?php echo htmlspecialchars($row[1]); ?>"
	data-grade="<?php echo htmlspecialchars($parts['grade']); ?>"
	data-stream="<?php echo htmlspecialchars($parts['stream']); ?>"
	data-class-teacher-id="<?php echo (int)($row['teacher_id'] ?? 0); ?>"
	data-subject-ids="<?php echo htmlspecialchars(json_encode($subjectIds)); ?>"
	data-bs-toggle="modal"
	data-bs-target="#editModal">Edit</button>
<a onclick="del('admin/core/drop_class?id=<?php echo $row[0]; ?>', 'Delete Class?');" class="btn btn-danger btn-sm" href="javascript:void(0);">Delete</a>
</td>
</tr>
<?php
	}
} catch (Throwable $e) {
	echo '<tr><td colspan="8">Failed to load classes.</td></tr>';
}
?>
</tbody>
</table>
</div></div></div></div></div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<script type="text/javascript" src="js/plugins/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="js/plugins/dataTables.bootstrap.min.html"></script>
<script type="text/javascript">$('#srmsTable').DataTable({"sort":false});</script>
<script>
$('.edit-class').on('click', function () {
	$('#id').val($(this).data('id'));
	$('#name').val($(this).data('name'));
	$('#grade_name').val($(this).data('grade'));
	$('#stream_name').val($(this).data('stream'));
	$('#class_teacher_id').val($(this).data('class-teacher-id'));
	$('#edit_subject_ids option').prop('selected', false);
	const selectedSubjects = $(this).data('subject-ids');
	if (Array.isArray(selectedSubjects)) {
		$('#edit_subject_ids option').each(function () {
			if (selectedSubjects.includes(parseInt($(this).val(), 10))) {
				$(this).prop('selected', true);
			}
		});
	}
});
$('.add-stream-btn').on('click', function () {
	$('#stream_grade_name').val($(this).data('grade'));
});
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
