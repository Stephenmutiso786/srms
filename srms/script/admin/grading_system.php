<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
require_once('const/rbac.php');

if ($res !== "1" || $level !== "0") { header("location:../"); exit; }
app_require_permission('exams.manage', '../admin');

$gradingSystems = [];
$gradingScalesBySystem = [];
$classUsageBySystem = [];
$examUsageBySystem = [];
$activeClassCount = 0;
$activeExamCount = 0;

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_grading_schema($conn);
	app_ensure_overall_grading_defaults($conn);

	if (app_table_exists($conn, 'tbl_grading_systems')) {
		$stmt = $conn->prepare("SELECT * FROM tbl_grading_systems ORDER BY is_default DESC, is_active DESC, name ASC");
		$stmt->execute();
		$gradingSystems = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}

	foreach ($gradingSystems as $system) {
		$systemId = (int)$system['id'];
		$gradingScalesBySystem[$systemId] = report_grading_scales($conn, $systemId);

		$classRows = [];
		if (app_table_exists($conn, 'tbl_classes') && app_column_exists($conn, 'tbl_classes', 'grading_system_id')) {
			$stmt = $conn->prepare("SELECT id, name FROM tbl_classes WHERE grading_system_id = ? ORDER BY name ASC");
			$stmt->execute([$systemId]);
			$classRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$activeClassCount += count($classRows);
		}
		$classUsageBySystem[$systemId] = $classRows;

		$examRows = [];
		if (app_table_exists($conn, 'tbl_exams') && app_column_exists($conn, 'tbl_exams', 'grading_system_id')) {
			$stmt = $conn->prepare("SELECT e.id, e.name, e.status, c.name AS class_name, t.name AS term_name
				FROM tbl_exams e
				LEFT JOIN tbl_classes c ON c.id = e.class_id
				LEFT JOIN tbl_terms t ON t.id = e.term_id
				WHERE e.grading_system_id = ?
				ORDER BY e.id DESC");
			$stmt->execute([$systemId]);
			$examRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$activeExamCount += count($examRows);
		}
		$examUsageBySystem[$systemId] = $examRows;
	}
} catch (Throwable $e) {
	$_SESSION['reply'] = array(array("danger", "Failed to load grading management."));
}

$defaultOverallRows = app_default_overall_grading_rows();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Grading Management</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.grading-hero{background:linear-gradient(135deg,#0b5b4f 0%,#117a65 52%,#1b9aaa 100%);color:#fff;border-radius:22px;padding:24px;box-shadow:0 18px 38px rgba(8,56,48,.16)}
.grading-stat{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:16px;padding:14px 16px}
.grading-stat .label{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;opacity:.82}
.grading-stat .value{font-size:1.45rem;font-weight:800}
.grading-card{border:1px solid #e4ece9;border-radius:20px;background:#fff;box-shadow:0 12px 28px rgba(8,56,48,.06)}
.grading-pill{display:inline-flex;align-items:center;border-radius:999px;padding:6px 10px;font-size:.78rem;font-weight:700}
.grading-pill.default{background:#d6f5ea;color:#0b6b46}
.grading-pill.active{background:#d9ecff;color:#0d5cab}
.grading-pill.inactive{background:#f3e1e1;color:#a63d3d}
.usage-chip{display:inline-block;border-radius:999px;padding:4px 10px;background:#eef7f4;color:#185648;font-size:.8rem;margin:0 6px 6px 0}
.scale-table input,.scale-table select{min-width:110px}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a><ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
<div>
<h1>Grading Management</h1>
<p>Admin controls grading systems here, assigns them to classes through Class Management, and the exam/report engine inherits them automatically.</p>
</div>
</div>

<div class="grading-hero mb-4">
<div class="row g-3 align-items-end">
<div class="col-md-6">
<h2 class="mb-2">Class-based grading now runs from one place</h2>
<p class="mb-0">Create, update, activate, deactivate, or delete grading systems here. Teachers and academic staff only consume the grading setup through marks entry, exams, and report cards.</p>
</div>
<div class="col-md-2"><div class="grading-stat"><div class="label">Systems</div><div class="value"><?php echo count($gradingSystems); ?></div></div></div>
<div class="col-md-2"><div class="grading-stat"><div class="label">Assigned Classes</div><div class="value"><?php echo (int)$activeClassCount; ?></div></div></div>
<div class="col-md-2"><div class="grading-stat"><div class="label">Linked Exams</div><div class="value"><?php echo (int)$activeExamCount; ?></div></div></div>
</div>
</div>

<div class="row">
<div class="col-md-12">
<div class="tile grading-card">
<div class="tile-body">
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
<div>
<h3 class="tile-title mb-1">Create Grading System</h3>
<p class="text-muted mb-0">Build the grading scale once, then assign it per class under <a href="admin/classes">Class Management</a>.</p>
</div>
</div>
<form class="app_frm" action="admin/core/save_grading_system" method="POST">
<input type="hidden" name="grading_system_id" value="0">
<input type="hidden" name="return" value="grading_system">
<div class="row">
<div class="col-md-4 mb-3"><label class="form-label">System Name</label><input class="form-control" name="name" required placeholder="e.g. Grade 7 CBE Term System" value="Overall Grading System"></div>
<div class="col-md-2 mb-3"><label class="form-label">Type</label><select class="form-control" name="type"><option value="cbe">CBE</option><option value="marks">Marks</option></select></div>
<div class="col-md-4 mb-3"><label class="form-label">Description</label><input class="form-control" name="description" placeholder="Describe where this grading applies"></div>
<div class="col-md-2 mb-3"><label class="form-label">Default</label><select class="form-control" name="is_default"><option value="0">No</option><option value="1">Yes</option></select></div>
<div class="col-md-12">
<div class="table-responsive scale-table">
<table class="table table-hover">
<thead><tr><th>Grade</th><th>Min</th><th>Max</th><th>Points</th><th>Remark</th><th>Order</th><th>Active</th></tr></thead>
<tbody>
<?php foreach ($defaultOverallRows as $row): ?>
<tr>
<td><input class="form-control" name="scale_grade[]" value="<?php echo htmlspecialchars($row['grade']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_min[]" value="<?php echo htmlspecialchars((string)$row['min']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_max[]" value="<?php echo htmlspecialchars((string)$row['max']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_points[]" value="<?php echo htmlspecialchars((string)$row['points']); ?>"></td>
<td><input class="form-control" name="scale_remark[]" value="<?php echo htmlspecialchars($row['remark']); ?>"></td>
<td><input class="form-control" type="number" name="scale_order[]" value="<?php echo htmlspecialchars((string)$row['order']); ?>"></td>
<td><select class="form-control" name="scale_active[]"><option value="1" selected>Yes</option><option value="0">No</option></select></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<button class="btn btn-primary app_btn">Save Grading System</button>
</form>
</div>
</div>
</div>
</div>

<div class="row mt-4">
<div class="col-md-12">
<?php foreach ($gradingSystems as $system): ?>
<?php
	$systemId = (int)$system['id'];
	$classRows = $classUsageBySystem[$systemId] ?? [];
	$examRows = $examUsageBySystem[$systemId] ?? [];
	$scaleRows = $gradingScalesBySystem[$systemId] ?? [];
?>
<div class="tile grading-card mb-4">
<div class="tile-body">
<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
<div>
<h3 class="tile-title mb-1"><?php echo htmlspecialchars((string)$system['name']); ?></h3>
<div class="d-flex flex-wrap gap-2">
<?php if ((int)$system['is_default'] === 1): ?><span class="grading-pill default">Default</span><?php endif; ?>
<span class="grading-pill <?php echo (int)$system['is_active'] === 1 ? 'active' : 'inactive'; ?>"><?php echo (int)$system['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span>
<span class="grading-pill active"><?php echo strtoupper(htmlspecialchars((string)$system['type'])); ?></span>
</div>
<p class="text-muted mb-0 mt-2"><?php echo htmlspecialchars((string)($system['description'] ?? 'No description')); ?></p>
</div>
<div class="text-end">
<div class="small text-muted"><?php echo count($classRows); ?> class(es) assigned</div>
<div class="small text-muted"><?php echo count($examRows); ?> exam(s) linked</div>
</div>
</div>

<div class="row mb-3">
<div class="col-md-7">
<div class="border rounded p-3 h-100">
<div class="fw-bold mb-2">Classes Using This Grading</div>
<?php if (!empty($classRows)): ?>
<?php foreach ($classRows as $classRow): ?>
<span class="usage-chip"><?php echo htmlspecialchars((string)$classRow['name']); ?></span>
<?php endforeach; ?>
<?php else: ?>
<div class="text-muted">No class has been assigned this grading system yet.</div>
<?php endif; ?>
</div>
</div>
<div class="col-md-5">
<div class="border rounded p-3 h-100">
<div class="fw-bold mb-2">Exams Currently Linked</div>
<?php if (!empty($examRows)): ?>
<?php foreach (array_slice($examRows, 0, 6) as $examRow): ?>
<div class="small mb-1"><?php echo htmlspecialchars((string)$examRow['name']); ?><?php echo !empty($examRow['class_name']) ? ' - ' . htmlspecialchars((string)$examRow['class_name']) : ''; ?><?php echo !empty($examRow['term_name']) ? ' / ' . htmlspecialchars((string)$examRow['term_name']) : ''; ?><?php echo !empty($examRow['status']) ? ' [' . htmlspecialchars((string)$examRow['status']) . ']' : ''; ?></div>
<?php endforeach; ?>
<?php if (count($examRows) > 6): ?><div class="small text-muted">+<?php echo count($examRows) - 6; ?> more linked exams</div><?php endif; ?>
<?php else: ?>
<div class="text-muted">No exams are linked yet. New exams can inherit this from the class setup.</div>
<?php endif; ?>
</div>
</div>
</div>

<form class="app_frm" action="admin/core/save_grading_system" method="POST">
<input type="hidden" name="grading_system_id" value="<?php echo $systemId; ?>">
<input type="hidden" name="return" value="grading_system">
<div class="row">
<div class="col-md-3 mb-3"><label class="form-label">System Name</label><input class="form-control" name="name" value="<?php echo htmlspecialchars((string)$system['name']); ?>" required></div>
<div class="col-md-2 mb-3"><label class="form-label">Type</label><select class="form-control" name="type"><option value="marks" <?php echo $system['type'] === 'marks' ? 'selected' : ''; ?>>Marks</option><option value="cbe" <?php echo $system['type'] === 'cbe' ? 'selected' : ''; ?>>CBE</option></select></div>
<div class="col-md-4 mb-3"><label class="form-label">Description</label><input class="form-control" name="description" value="<?php echo htmlspecialchars((string)($system['description'] ?? '')); ?>"></div>
<div class="col-md-3 mb-3"><label class="form-label">Status</label><div class="d-flex gap-2"><select class="form-control" name="is_active"><option value="1" <?php echo (int)$system['is_active'] === 1 ? 'selected' : ''; ?>>Active</option><option value="0" <?php echo (int)$system['is_active'] === 0 ? 'selected' : ''; ?>>Inactive</option></select><select class="form-control" name="is_default"><option value="0" <?php echo (int)$system['is_default'] === 0 ? 'selected' : ''; ?>>Normal</option><option value="1" <?php echo (int)$system['is_default'] === 1 ? 'selected' : ''; ?>>Default</option></select></div></div>
<div class="col-md-12">
<div class="table-responsive scale-table">
<table class="table table-sm table-hover">
<thead><tr><th>Grade</th><th>Min</th><th>Max</th><th>Points</th><th>Remark</th><th>Order</th><th>Active</th></tr></thead>
<tbody>
<?php foreach ($scaleRows as $scale): ?>
<tr>
<td><input class="form-control" name="scale_grade[]" value="<?php echo htmlspecialchars((string)$scale['name']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_min[]" value="<?php echo htmlspecialchars((string)$scale['min']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_max[]" value="<?php echo htmlspecialchars((string)$scale['max']); ?>" required></td>
<td><input class="form-control" type="number" step="0.01" name="scale_points[]" value="<?php echo htmlspecialchars((string)($scale['points'] ?? 0)); ?>"></td>
<td><input class="form-control" name="scale_remark[]" value="<?php echo htmlspecialchars((string)($scale['remark'] ?? '')); ?>"></td>
<td><input class="form-control" type="number" name="scale_order[]" value="<?php echo htmlspecialchars((string)($scale['sort_order'] ?? 0)); ?>"></td>
<td><select class="form-control" name="scale_active[]"><option value="1" <?php echo ((int)($scale['is_active'] ?? 1) === 1) ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo ((int)($scale['is_active'] ?? 1) === 0) ? 'selected' : ''; ?>>No</option></select></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
<div class="d-flex gap-2 flex-wrap">
<button class="btn btn-outline-primary">Save Changes</button>
<a href="admin/classes" class="btn btn-outline-secondary">Assign to Classes</a>
<a onclick="del('admin/core/delete_grading_system?id=<?php echo $systemId; ?>&return=grading_system', 'Delete this grading system? This only works when no class or exam is linked to it.');" href="javascript:void(0);" class="btn btn-outline-danger">Delete</a>
</div>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script src="loader/waitMe.js"></script>
<script src="js/sweetalert2@11.js"></script>
<script src="js/forms.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
