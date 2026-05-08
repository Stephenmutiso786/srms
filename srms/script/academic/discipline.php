<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');

app_require_discipline_access();

$cases = [];
$categories = [];
$students = [];
$summary = ['reported' => 0, 'investigation' => 0, 'resolved' => 0, 'escalated' => 0];
$error = '';

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_discipline_management_schema($conn);

	$stmt = $conn->prepare("SELECT id, name, examples, suggested_action FROM tbl_offense_categories ORDER BY severity_level ASC");
	$stmt->execute();
	$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT st.id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name, st.school_id AS admission_no, c.name AS class_name
		FROM tbl_students st
		LEFT JOIN tbl_classes c ON c.id = st.class
		ORDER BY c.name, st.fname, st.lname
		LIMIT 500");
	$stmt->execute();
	$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$stmt = $conn->prepare("SELECT
			d.id,
			d.student_id,
			d.incident_type,
			d.description,
			d.category,
			d.location,
			d.case_status,
			d.status,
			d.action_taken,
			d.action_recommended,
			d.parent_visit_status,
			d.date_reported,
			d.created_at,
			concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
			st.school_id AS admission_no,
			c.name AS class_name
		FROM tbl_discipline_cases d
		JOIN tbl_students st ON st.id = d.student_id
		LEFT JOIN tbl_classes c ON c.id = d.class_id
		ORDER BY COALESCE(d.date_reported, d.created_at) DESC, d.id DESC
		LIMIT 200");
	$stmt->execute();
	$cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

	foreach ($cases as $case) {
		switch ((string)$case['case_status']) {
			case 'Reported': $summary['reported']++; break;
			case 'Under Investigation': $summary['investigation']++; break;
			case 'Resolved': $summary['resolved']++; break;
			case 'Escalated': $summary['escalated']++; break;
		}
	}
} catch (Throwable $e) {
	error_log('['.__FILE__.':'.__LINE__.'] '.$e->getMessage());
	$error = 'Failed to load the discipline manager.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Deputy Discipline Manager</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.discipline-wrap{display:grid;grid-template-columns:minmax(320px,400px) minmax(0,1fr);gap:18px}
.discipline-card{background:#fff;border:1px solid #e7edf5;border-radius:16px;box-shadow:0 14px 40px rgba(15,95,168,.08);padding:18px}
.discipline-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:16px}
.discipline-stat{background:#fff;border:1px solid #e7edf5;border-radius:16px;padding:14px}
.discipline-stat .label{font-size:.75rem;text-transform:uppercase;color:#6f7e8f}
.discipline-stat .value{font-size:1.4rem;font-weight:900}
.status-pill,.category-pill{display:inline-flex;align-items:center;border-radius:999px;padding:4px 9px;font-size:.76rem;font-weight:800}
.status-Reported{background:#fff3cd;color:#8a6d03}
.status-Under-Investigation{background:#dbeafe;color:#1d4ed8}
.status-Resolved{background:#dcfce7;color:#166534}
.status-Escalated{background:#fee2e2;color:#b91c1c}
.category-Minor{background:#dcfce7;color:#166534}
.category-Moderate{background:#dbeafe;color:#1d4ed8}
.category-Major{background:#ffedd5;color:#c2410c}
.category-Severe{background:#fee2e2;color:#b91c1c}
.discipline-table td,.discipline-table th{vertical-align:top}
.mini-form .form-control{min-width:120px}
@media (max-width:1100px){
	.discipline-wrap{grid-template-columns:1fr}
	.discipline-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('academic/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title">
	<div>
		<h1>Discipline Cases</h1>
		<p>Record, review, print, and follow up discipline cases using a simpler stable workflow.</p>
	</div>
</div>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="discipline-stats">
	<div class="discipline-stat"><div class="label">Reported</div><div class="value"><?php echo (int)$summary['reported']; ?></div></div>
	<div class="discipline-stat"><div class="label">Under Investigation</div><div class="value"><?php echo (int)$summary['investigation']; ?></div></div>
	<div class="discipline-stat"><div class="label">Resolved</div><div class="value"><?php echo (int)$summary['resolved']; ?></div></div>
	<div class="discipline-stat"><div class="label">Escalated</div><div class="value"><?php echo (int)$summary['escalated']; ?></div></div>
</div>

<div class="discipline-wrap">
	<div class="discipline-card">
		<h3 class="tile-title">Record New Case</h3>
		<form method="POST" action="teacher/core/submit_discipline_case.php">
			<input type="hidden" name="return_to" value="../../academic/discipline.php">
			<div class="mb-3">
				<label class="form-label">Student</label>
				<select class="form-control" name="student_id" required>
					<option value="">Select student</option>
					<?php foreach ($students as $student): ?>
					<option value="<?php echo htmlspecialchars((string)$student['id']); ?>"><?php echo htmlspecialchars((string)$student['student_name'].' - '.(string)($student['class_name'] ?? '').' - '.(string)($student['admission_no'] ?? '')); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="mb-3">
				<label class="form-label">Incident Type</label>
				<input class="form-control" name="incident_type" placeholder="Fighting, lateness, bullying..." required>
			</div>
			<div class="row">
				<div class="col-md-6 mb-3">
					<label class="form-label">Category</label>
					<select class="form-control" name="category" required onchange="disciplineSuggestAction(this.value)">
						<option value="Minor">Minor</option>
						<option value="Moderate" selected>Moderate</option>
						<option value="Major">Major</option>
						<option value="Severe">Severe</option>
					</select>
				</div>
				<div class="col-md-6 mb-3">
					<label class="form-label">Date & Time</label>
					<input class="form-control" type="datetime-local" name="date_reported" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
				</div>
			</div>
			<div class="mb-3">
				<label class="form-label">Location</label>
				<input class="form-control" name="location" placeholder="Classroom, field, dorm..." required>
			</div>
			<div class="mb-3">
				<label class="form-label">Description</label>
				<textarea class="form-control" name="description" rows="4" required></textarea>
			</div>
			<div class="mb-3">
				<label class="form-label">Suggested Action</label>
				<input class="form-control" id="discipline-suggested-action" value="Detention" readonly>
			</div>
			<button class="btn btn-primary btn-sm" type="submit">Save Case</button>
		</form>

		<?php if ($categories): ?>
		<hr>
		<h4 class="tile-title">Category Guide</h4>
		<?php foreach ($categories as $category): ?>
		<div class="mb-3">
			<div class="category-pill category-<?php echo htmlspecialchars((string)$category['name']); ?>"><?php echo htmlspecialchars((string)$category['name']); ?></div>
			<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)$category['examples']); ?></div>
			<div class="small"><strong>Suggested action:</strong> <?php echo htmlspecialchars((string)$category['suggested_action']); ?></div>
		</div>
		<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<div class="discipline-card">
		<h3 class="tile-title">Recent Cases</h3>
		<div class="table-responsive">
			<table class="table table-bordered table-hover discipline-table">
				<thead>
					<tr>
						<th>Student</th>
						<th>Incident</th>
						<th>Status</th>
						<th>Action</th>
						<th width="280">Update</th>
					</tr>
				</thead>
				<tbody>
				<?php if (!$cases): ?>
					<tr><td colspan="5" class="text-center text-muted">No discipline cases recorded yet.</td></tr>
				<?php else: ?>
					<?php foreach ($cases as $case): ?>
					<tr>
						<td>
							<strong><?php echo htmlspecialchars((string)$case['student_name']); ?></strong><br>
							<span class="small text-muted"><?php echo htmlspecialchars((string)($case['admission_no'] ?? '')); ?> | <?php echo htmlspecialchars((string)($case['class_name'] ?? '')); ?></span>
						</td>
						<td>
							<strong><?php echo htmlspecialchars((string)$case['incident_type']); ?></strong><br>
							<span class="category-pill category-<?php echo htmlspecialchars((string)($case['category'] ?? 'Moderate')); ?>"><?php echo htmlspecialchars((string)($case['category'] ?? 'Moderate')); ?></span>
							<div class="small mt-1"><?php echo nl2br(htmlspecialchars((string)$case['description'])); ?></div>
							<div class="small text-muted mt-1"><?php echo htmlspecialchars((string)($case['location'] ?? '')); ?> | <?php echo htmlspecialchars((string)($case['date_reported'] ?? $case['created_at'])); ?></div>
						</td>
						<td>
							<span class="status-pill status-<?php echo htmlspecialchars(str_replace(' ', '-', (string)($case['case_status'] ?? 'Reported'))); ?>"><?php echo htmlspecialchars((string)($case['case_status'] ?? 'Reported')); ?></span>
							<div class="small text-muted mt-2">Parent visit: <?php echo htmlspecialchars((string)($case['parent_visit_status'] ?? 'Pending')); ?></div>
						</td>
						<td>
							<?php echo htmlspecialchars((string)($case['action_taken'] ?: $case['action_recommended'] ?: 'Pending review')); ?><br>
							<div class="mt-2 d-flex flex-wrap gap-2">
								<a class="btn btn-outline-secondary btn-sm" href="academic/discipline_letter.php?id=<?php echo (int)$case['id']; ?>" target="_blank">Print Letter</a>
								<form method="POST" action="admin/core/send_discipline_notice.php" class="d-inline">
									<input type="hidden" name="case_id" value="<?php echo (int)$case['id']; ?>">
									<input type="hidden" name="return_to" value="../../academic/discipline.php">
									<button class="btn btn-outline-primary btn-sm" type="submit">Send Notice</button>
								</form>
								<form method="POST" action="admin/core/delete_discipline_case.php" class="d-inline" onsubmit="return confirm('Delete this discipline case?');">
									<input type="hidden" name="id" value="<?php echo (int)$case['id']; ?>">
									<input type="hidden" name="return_to" value="../../academic/discipline.php">
									<button class="btn btn-outline-danger btn-sm" type="submit">Delete</button>
								</form>
							</div>
						</td>
						<td>
							<form method="POST" action="admin/core/update_discipline_case.php" class="mini-form">
								<input type="hidden" name="id" value="<?php echo (int)$case['id']; ?>">
								<input type="hidden" name="return_to" value="../../academic/discipline.php">
								<div class="mb-2">
									<select class="form-control form-control-sm" name="case_status">
										<?php foreach (['Reported','Under Investigation','Resolved','Escalated'] as $status): ?>
										<option value="<?php echo htmlspecialchars($status); ?>" <?php echo $status === (string)$case['case_status'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($status); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="mb-2">
									<input class="form-control form-control-sm" name="action_taken" value="<?php echo htmlspecialchars((string)($case['action_taken'] ?: $case['action_recommended'])); ?>" placeholder="Action taken">
								</div>
								<div class="mb-2">
									<select class="form-control form-control-sm" name="parent_visit_status">
										<?php foreach (['Pending','Visited','Follow Up Required'] as $visitStatus): ?>
										<option value="<?php echo htmlspecialchars($visitStatus); ?>" <?php echo $visitStatus === (string)($case['parent_visit_status'] ?? 'Pending') ? 'selected' : ''; ?>><?php echo htmlspecialchars($visitStatus); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<input type="hidden" name="status" value="<?php echo ((string)$case['case_status'] === 'Resolved') ? 'resolved' : 'reviewed'; ?>">
								<input type="hidden" name="category" value="<?php echo htmlspecialchars((string)($case['category'] ?? 'Moderate')); ?>">
								<textarea class="form-control form-control-sm mb-2" name="review_notes" rows="2" placeholder="Optional deputy note"></textarea>
								<button class="btn btn-primary btn-sm" type="submit">Update</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
<script>
function disciplineSuggestAction(category){
	let action = 'Review Required';
	switch ((category || '').toLowerCase()) {
		case 'minor': action = 'Warning'; break;
		case 'moderate': action = 'Detention'; break;
		case 'major': action = 'Suspension'; break;
		case 'severe': action = 'Expulsion / BOM Hearing'; break;
	}
	var field = document.getElementById('discipline-suggested-action');
	if (field) field.value = action;
}
</script>
</body>
</html>
