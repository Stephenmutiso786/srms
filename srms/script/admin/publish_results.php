<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/report_engine.php');
if ($res != "1" || $level != "0") { header("location:../"); exit; }
app_require_permission('results.approve', 'admin');

$classes = [];
$terms = [];
$classId = (int)($_GET['class_id'] ?? 0);
$termId = (int)($_GET['term_id'] ?? 0);
$rows = [];
$summary = ['draft' => 0, 'active' => 0, 'reviewed' => 0, 'finalized' => 0, 'published' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes ORDER BY id");
	$stmt->execute();
	$classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT id, name FROM tbl_terms ORDER BY id DESC");
	$stmt->execute();
	$terms = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$sql = "SELECT e.id, e.name, e.status, e.created_at, e.class_id, e.term_id, c.name AS class_name, t.name AS term_name, et.name AS type_name,
		COALESCE((SELECT COUNT(*) FROM tbl_exam_mark_submissions ms WHERE ms.exam_id = e.id AND ms.status = 'reviewed'), 0) AS reviewed_count,
		COALESCE((SELECT COUNT(*) FROM tbl_exam_mark_submissions ms WHERE ms.exam_id = e.id AND ms.status = 'submitted'), 0) AS submitted_count,
		COALESCE((SELECT COUNT(*) FROM tbl_exam_mark_submissions ms WHERE ms.exam_id = e.id AND ms.status = 'rejected'), 0) AS rejected_count,
		COALESCE((SELECT COUNT(*) FROM tbl_exam_mark_submissions ms WHERE ms.exam_id = e.id), 0) AS total_submissions
		FROM tbl_exams e
		LEFT JOIN tbl_classes c ON c.id = e.class_id
		LEFT JOIN tbl_terms t ON t.id = e.term_id
		LEFT JOIN tbl_exam_types et ON et.id = e.exam_type_id";
	$params = [];
	$where = [];
	if ($classId > 0) {
		$where[] = "e.class_id = ?";
		$params[] = $classId;
	}
	if ($termId > 0) {
		$where[] = "e.term_id = ?";
		$params[] = $termId;
	}
	if ($where) {
		$sql .= " WHERE " . implode(' AND ', $where);
	}
	$sql .= " ORDER BY e.created_at DESC";
	$stmt = $conn->prepare($sql);
	$stmt->execute($params);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	foreach ($rows as $row) {
		$status = (string)($row['status'] ?? 'draft');
		if (isset($summary[$status])) {
			$summary[$status]++;
		}
	}
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__." Throwable] " . $e->getMessage());
	$error = "An internal error occurred.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Publish Results</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.publish-hero{background:linear-gradient(135deg,#0d3b66,#12708f 55%,#33b679);border-radius:20px;padding:28px;color:#fff;box-shadow:0 18px 45px rgba(13,59,102,.18)}
.publish-chip{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;background:rgba(255,255,255,.14);font-size:.92rem}
.publish-stat{border-radius:18px;padding:18px;background:#fff;border:1px solid #e7edf3;box-shadow:0 8px 24px rgba(15,95,168,.08)}
.publish-stat .value{font-size:1.7rem;font-weight:800;color:#123}
.publish-table td,.publish-table th{vertical-align:middle}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav"><li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown"><i class="bi bi-person fs-4"></i></a><ul class="dropdown-menu settings-menu dropdown-menu-right"><li><a class="dropdown-item" href="admin/profile"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li><li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li></ul></li></ul>
</header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>Publish Results</h1><p>Release only fully finalized exam results to students and parents.</p></div></div>

<?php if ($error !== '') { ?>
<div class="tile"><div class="alert alert-danger mb-0"><?php echo htmlspecialchars($error); ?></div></div>
<?php } else { ?>
<div class="publish-hero mb-4">
	<div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
		<div>
			<h2 class="mb-2">Professional release control</h2>
			<p class="mb-0">Students and parents will only see report cards and result insights after you publish the relevant exam structures.</p>
		</div>
		<div class="d-flex flex-wrap gap-2">
			<span class="publish-chip"><i class="bi bi-lock"></i> Review before release</span>
			<span class="publish-chip"><i class="bi bi-broadcast"></i> Publish when ready</span>
		</div>
	</div>
</div>

<div class="tile mb-4">
	<form class="row g-3" method="GET" action="admin/publish_results">
		<div class="col-md-5">
			<label class="form-label">Class</label>
			<select class="form-control" name="class_id">
				<option value="">All classes</option>
				<?php foreach ($classes as $class): ?>
				<option value="<?php echo (int)$class['id']; ?>" <?php echo ((int)$class['id'] === $classId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($class['name']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-5">
			<label class="form-label">Term</label>
			<select class="form-control" name="term_id">
				<option value="">All terms</option>
				<?php foreach ($terms as $term): ?>
				<option value="<?php echo (int)$term['id']; ?>" <?php echo ((int)$term['id'] === $termId) ? 'selected' : ''; ?>><?php echo htmlspecialchars($term['name']); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
		<div class="col-md-2 d-grid align-items-end">
			<button class="btn btn-primary">Filter</button>
		</div>
	</form>
</div>

<div class="row mb-4">
	<?php foreach ($summary as $key => $value): ?>
	<div class="col-md-6 col-lg-2 mb-3">
		<div class="publish-stat">
			<div class="text-muted text-uppercase small"><?php echo htmlspecialchars($key); ?></div>
			<div class="value"><?php echo (int)$value; ?></div>
		</div>
	</div>
	<?php endforeach; ?>
</div>

<div class="tile">
	<h3 class="tile-title">Release Queue</h3>
	<div class="table-responsive">
		<table class="table table-hover publish-table">
			<thead>
				<tr>
					<th>Exam</th>
					<th>Class</th>
					<th>Term</th>
					<th>Status</th>
					<th>Moderation</th>
					<th>Action</th>
				</tr>
			</thead>
			<tbody>
			<?php if (!$rows) { ?>
				<tr><td colspan="6" class="text-muted">No exams found for the selected filter.</td></tr>
			<?php } ?>
			<?php foreach ($rows as $row): ?>
				<tr>
					<td>
						<div class="fw-semibold"><?php echo htmlspecialchars($row['name']); ?></div>
						<div class="small text-muted"><?php echo htmlspecialchars((string)($row['type_name'] ?? 'General Exam')); ?></div>
					</td>
					<td><?php echo htmlspecialchars((string)($row['class_name'] ?? '')); ?></td>
					<td><?php echo htmlspecialchars((string)($row['term_name'] ?? '')); ?></td>
					<td><span class="badge bg-<?php echo htmlspecialchars(app_exam_status_badge((string)$row['status'])); ?>"><?php echo htmlspecialchars(ucfirst((string)$row['status'])); ?></span></td>
					<td>
						<div class="small">Reviewed: <?php echo (int)$row['reviewed_count']; ?></div>
						<div class="small">Pending: <?php echo (int)$row['submitted_count']; ?></div>
						<div class="small">Returned: <?php echo (int)$row['rejected_count']; ?></div>
					</td>
					<td>
						<?php if ((string)$row['status'] === 'finalized') { ?>
						<form method="POST" action="admin/core/update_exam_status" class="d-inline">
							<input type="hidden" name="exam_id" value="<?php echo (int)$row['id']; ?>">
							<button class="btn btn-sm btn-dark" name="status" value="published"><i class="bi bi-broadcast me-1"></i>Publish</button>
						</form>
						<?php } elseif ((string)$row['status'] === 'published') { ?>
						<form method="POST" action="admin/core/update_exam_status" class="d-inline">
							<input type="hidden" name="exam_id" value="<?php echo (int)$row['id']; ?>">
							<button class="btn btn-sm btn-outline-warning" name="status" value="finalized"><i class="bi bi-eye-slash me-1"></i>Unpublish</button>
						</form>
						<form method="POST" action="admin/core/send_results_notifications" class="d-inline">
							<input type="hidden" name="exam_id" value="<?php echo (int)$row['id']; ?>">
							<input type="hidden" name="channel" value="both">
							<button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-send-check me-1"></i>Send Both</button>
						</form>
						<form method="POST" action="admin/core/send_results_notifications" class="d-inline">
							<input type="hidden" name="exam_id" value="<?php echo (int)$row['id']; ?>">
							<input type="hidden" name="channel" value="sms">
							<button class="btn btn-sm btn-outline-primary" type="submit"><i class="bi bi-chat-dots me-1"></i>Send SMS</button>
						</form>
						<form method="POST" action="admin/core/send_results_notifications" class="d-inline">
							<input type="hidden" name="exam_id" value="<?php echo (int)$row['id']; ?>">
							<input type="hidden" name="channel" value="email">
							<button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-envelope me-1"></i>Send Email</button>
						</form>
						<?php } else { ?>
						<span class="text-muted small">Move this exam to finalized before publishing.</span>
						<?php } ?>
					</td>
				</tr>
			<?php endforeach; ?>
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
