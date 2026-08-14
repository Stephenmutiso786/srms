<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

$_appBaseUrl = app_base_url();
$superAdminBase = $_appBaseUrl !== '' ? $_appBaseUrl . '/super_admin' : '';
$superAdminPath = $superAdminBase !== '' ? $superAdminBase : '';
$isSuperAdmin = !empty($super_admin) || (string)($level ?? '') === '9';
if ($res !== '1' || !$isSuperAdmin) {
	header('location:../');
	exit;
}

$conn = app_db();
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if (function_exists('app_ensure_school_subscription_schema')) {
  app_ensure_school_subscription_schema($conn);
}
$selectedSchoolId = app_current_school_id();
$schoolFilter = trim((string)($_GET['q'] ?? ''));
$stats = ['schools' => 0, 'owners' => 0, 'active_schools' => 0, 'pending' => 0, 'teachers' => 0, 'students' => 0, 'parents' => 0];
$owners = [];
$schools = [];
$pendingSchools = [];
$selectedSchool = [];
try {
	if (app_table_exists($conn, 'tbl_school')) {
		$stats['schools'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_school")->fetchColumn();
		$stats['active_schools'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_school WHERE allow_results = 1")->fetchColumn();
		$stats['pending'] = (int)$conn->query("SELECT COUNT(*) FROM tbl_school WHERE approval_status = 'pending'")->fetchColumn();
    $stmt = $conn->query("SELECT s.id, s.name, s.approval_status, s.application_email_sent_at, s.created_at, s.is_locked, s.is_suspended, s.package_tier, s.allow_results, COALESCE(st.email, '') AS admin_email
			FROM tbl_school s
			LEFT JOIN tbl_staff st ON st.school_id = s.id AND st.level = 0
			WHERE s.approval_status = 'pending'
			ORDER BY s.id DESC");
		$pendingSchools = $stmt->fetchAll(PDO::FETCH_ASSOC);
		if ($schoolFilter !== '') {
			$stmt = $conn->prepare("SELECT id, name, logo, result_system, allow_results, package_tier, support_plan, term_start_date, term_end_date, is_locked, is_suspended, approval_status, mpesa_enabled, sms_balance FROM tbl_school WHERE name LIKE ? ORDER BY id DESC");
			$stmt->execute(['%' . $schoolFilter . '%']);
		} else {
			$stmt = $conn->query("SELECT id, name, logo, result_system, allow_results, package_tier, support_plan, term_start_date, term_end_date, is_locked, is_suspended, approval_status, mpesa_enabled, sms_balance FROM tbl_school ORDER BY id DESC");
		}
		$schools = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (app_table_exists($conn, 'tbl_staff')) {
		$stmt = $conn->prepare("SELECT id, fname, lname, email, level, status FROM tbl_staff WHERE level = 9 ORDER BY id DESC");
		$stmt->execute();
		$owners = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$stats['owners'] = count($owners);
	}
	$schoolIdForCounts = $selectedSchoolId > 0 ? $selectedSchoolId : 0;
	if ($schoolIdForCounts > 0) {
		if (app_table_exists($conn, 'tbl_staff')) {
			$countSql = app_column_exists($conn, 'tbl_staff', 'school_id')
				? "SELECT COUNT(*) FROM tbl_staff WHERE school_id = ? AND level = 2"
				: "SELECT COUNT(*) FROM tbl_staff WHERE level = 2";
			$stmt = $conn->prepare($countSql);
			$stmt->execute(app_column_exists($conn, 'tbl_staff', 'school_id') ? [$schoolIdForCounts] : []);
			$stats['teachers'] = (int)$stmt->fetchColumn();
		}
		if (app_table_exists($conn, 'tbl_students')) {
			$countSql = app_column_exists($conn, 'tbl_students', 'school_id')
				? "SELECT COUNT(*) FROM tbl_students WHERE school_id = ?"
				: "SELECT COUNT(*) FROM tbl_students";
			$stmt = $conn->prepare($countSql);
			$stmt->execute(app_column_exists($conn, 'tbl_students', 'school_id') ? [$schoolIdForCounts] : []);
			$stats['students'] = (int)$stmt->fetchColumn();
		}
		if (app_table_exists($conn, 'tbl_parents')) {
			$countSql = app_column_exists($conn, 'tbl_parents', 'school_id')
				? "SELECT COUNT(*) FROM tbl_parents WHERE school_id = ?"
				: "SELECT COUNT(*) FROM tbl_parents";
			$stmt = $conn->prepare($countSql);
			$stmt->execute(app_column_exists($conn, 'tbl_parents', 'school_id') ? [$schoolIdForCounts] : []);
			$stats['parents'] = (int)$stmt->fetchColumn();
		}
		$selectedSchool = app_school_row($conn, $schoolIdForCounts);
	}
} catch (Throwable $e) {
	$schools = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo APP_NAME; ?> - Super Admin</title>
<link rel="stylesheet" href="../css/main.css">
<link rel="icon" href="../images/icon.ico">
</head>
<body class="app sidebar-mini">
<main class="app-content">
<h1 class="mb-4">Super Admin Console</h1>
<div class="card mb-4">
  <div class="card-body">
    <p class="mb-0">Use this area to manage schools at the platform level.</p>
    <?php if ($selectedSchoolId > 0): ?>
      <div class="alert alert-info mt-3 mb-0">Current school: <?php echo htmlspecialchars((string)app_school_row($conn, $selectedSchoolId)['name']); ?></div>
    <?php endif; ?>
  </div>
</div>
<div class="row mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Schools</small><div class="h3 mb-0"><?php echo number_format($stats['schools']); ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Active Schools</small><div class="h3 mb-0"><?php echo number_format($stats['active_schools']); ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Owner Accounts</small><div class="h3 mb-0"><?php echo number_format($stats['owners']); ?></div></div></div></div>
</div>
<div class="row mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Pending Schools</small><div class="h3 mb-0"><?php echo number_format($stats['pending']); ?></div></div></div></div>
</div>
<div class="card mb-4 border-warning">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Pending Applications</strong>
    <span class="badge bg-warning text-dark"><?php echo number_format($stats['pending']); ?> waiting</span>
  </div>
  <div class="card-body">
    <?php if (empty($pendingSchools)): ?>
      <div class="text-muted">No pending applications right now.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>School</th>
              <th>Applicant Email</th>
              <th>Package</th>
              <th>Submitted</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pendingSchools as $pending): ?>
              <tr>
                <td>
                  <div class="fw-bold"><?php echo htmlspecialchars((string)$pending['name']); ?></div>
                  <div class="small text-muted">ID: <?php echo (int)$pending['id']; ?></div>
                </td>
                <td><?php echo htmlspecialchars((string)($pending['admin_email'] ?: 'Not captured')); ?></td>
                <td><?php echo htmlspecialchars(strtoupper((string)($pending['package_tier'] ?? 'elimu_hub'))); ?></td>
                <td><?php echo htmlspecialchars(trim((string)($pending['created_at'] ?? '') ?: (string)($pending['application_email_sent_at'] ?? '') ?: 'Unknown')); ?></td>
                <td>
                  <?php if ((int)($pending['is_locked'] ?? 0) === 1): ?>
                    <span class="badge bg-warning text-dark">Locked</span>
                  <?php endif; ?>
                  <?php if ((int)($pending['is_suspended'] ?? 0) === 1): ?>
                    <span class="badge bg-secondary">Suspended</span>
                  <?php endif; ?>
                  <span class="badge bg-info text-dark">Pending</span>
                </td>
                <td>
                  <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/approve_school.php'); ?>" method="post" class="d-inline">
                    <input type="hidden" name="school_id" value="<?php echo (int)$pending['id']; ?>">
                    <button class="btn btn-success btn-sm" type="submit">Approve</button>
                  </form>
                  <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/reject_school.php'); ?>" method="post" class="d-inline">
                    <input type="hidden" name="school_id" value="<?php echo (int)$pending['id']; ?>">
                    <input type="hidden" name="reject_reason" value="Pending application rejected from dashboard">
                    <button class="btn btn-outline-dark btn-sm" type="submit">Reject</button>
                  </form>
                  <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/resend_pending_school_email.php'); ?>" method="post" class="d-inline">
                    <input type="hidden" name="school_id" value="<?php echo (int)$pending['id']; ?>">
                    <button class="btn btn-outline-primary btn-sm" type="submit">Resend Email</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($selectedSchoolId > 0): ?>
<div class="row mb-3">
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Teachers</small><div class="h3 mb-0"><?php echo number_format($stats['teachers']); ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Students</small><div class="h3 mb-0"><?php echo number_format($stats['students']); ?></div></div></div></div>
  <div class="col-md-4"><div class="card"><div class="card-body"><small class="text-muted text-uppercase">Parents</small><div class="h3 mb-0"><?php echo number_format($stats['parents']); ?></div></div></div></div>
</div>
<?php if (!empty($selectedSchool['id'])): ?>
<div class="card mb-4 border-info">
  <div class="card-body">
    <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
      <div>
        <h5 class="mb-1">Selected School Overview</h5>
        <div class="text-muted"><?php echo htmlspecialchars((string)$selectedSchool['name']); ?></div>
        <div class="small text-muted">Application status: <?php echo htmlspecialchars((string)($selectedSchool['approval_status'] ?? 'approved')); ?></div>
      </div>
      <div class="text-end">
        <div><strong>Package:</strong> <?php echo htmlspecialchars(strtoupper((string)($selectedSchool['package_tier'] ?? 'elimu_hub'))); ?></div>
        <div><strong>Support:</strong> <?php echo htmlspecialchars((string)($selectedSchool['support_plan'] ?? 'basic')); ?></div>
        <div><strong>M-Pesa:</strong> <?php echo ((int)($selectedSchool['mpesa_enabled'] ?? 1) === 1 ? 'Enabled' : 'Disabled'); ?></div>
        <div><strong>SMS Balance:</strong> <?php echo number_format((int)($selectedSchool['sms_balance'] ?? 0)); ?></div>
        <div><strong>Access:</strong> <?php echo htmlspecialchars(strtolower((string)($selectedSchool['approval_status'] ?? 'approved')) === 'pending' ? 'Pending approval' : ((int)($selectedSchool['is_suspended'] ?? 0) === 1 ? 'Suspended' : ((int)($selectedSchool['is_locked'] ?? 0) === 1 ? 'Locked' : 'Active'))); ?></div>
      </div>
    </div>
    <div class="mt-3">
      <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#schoolDetailModal">Open School Details</button>
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/backup_restore.php'); ?>">Backup / Restore</a>
    </div>
  </div>
</div>
<div class="modal fade" id="schoolDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">School Overview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="border rounded p-3">
              <div class="text-muted small text-uppercase">School</div>
              <div class="fw-bold"><?php echo htmlspecialchars((string)$selectedSchool['name']); ?></div>
              <div class="small text-muted">ID: <?php echo (int)($selectedSchool['id'] ?? 0); ?></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="border rounded p-3">
              <div class="text-muted small text-uppercase">Access</div>
              <div class="fw-bold">
                <?php echo htmlspecialchars(strtolower((string)($selectedSchool['approval_status'] ?? 'approved')) === 'pending' ? 'Pending approval' : ((int)($selectedSchool['is_suspended'] ?? 0) === 1 ? 'Suspended' : ((int)($selectedSchool['is_locked'] ?? 0) === 1 ? 'Locked' : 'Active'))); ?>
              </div>
              <div class="small text-muted">
                Term: <?php echo htmlspecialchars(trim((string)($selectedSchool['term_start_date'] ?? '') . ' to ' . (string)($selectedSchool['term_end_date'] ?? ''), ' to')); ?>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3">
              <div class="text-muted small text-uppercase">Package</div>
              <div class="fw-bold"><?php echo htmlspecialchars(strtoupper((string)($selectedSchool['package_tier'] ?? 'elimu_hub'))); ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3">
              <div class="text-muted small text-uppercase">Support</div>
              <div class="fw-bold"><?php echo htmlspecialchars((string)($selectedSchool['support_plan'] ?? 'basic')); ?></div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="border rounded p-3">
              <div class="text-muted small text-uppercase">M-Pesa / SMS</div>
              <div class="fw-bold"><?php echo ((int)($selectedSchool['mpesa_enabled'] ?? 1) === 1 ? 'M-Pesa Enabled' : 'M-Pesa Disabled'); ?></div>
              <div class="small text-muted">SMS balance: <?php echo number_format((int)($selectedSchool['sms_balance'] ?? 0)); ?></div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a class="btn btn-outline-primary" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/edit_school.php?id=' . (int)($selectedSchool['id'] ?? 0)); ?>">Edit School</a>
        <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/switch_school.php'); ?>" method="post" class="d-inline">
          <input type="hidden" name="school_id" value="<?php echo (int)($selectedSchool['id'] ?? 0); ?>">
          <button class="btn btn-success" type="submit">Enter School</button>
        </form>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Schools</strong>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/export_schools.php'); ?>">Export Schools</a>
      <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/backup_restore.php'); ?>">Backup / Restore</a>
      <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/new_school.php'); ?>">Register School</a>
    </div>
  </div>
  <div class="card-body">
    <form class="row g-2 mb-3" method="get" action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/index.php'); ?>">
      <div class="col-md-8">
        <input class="form-control" type="search" name="q" value="<?php echo htmlspecialchars($schoolFilter); ?>" placeholder="Filter schools by name">
      </div>
      <div class="col-md-4">
        <button class="btn btn-outline-primary" type="submit">Filter</button>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/index.php'); ?>">Reset</a>
      </div>
    </form>
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>ID</th><th>Name</th><th>Package</th><th>Term</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($schools as $school): ?>
          <tr>
            <td><?php echo (int)$school['id']; ?></td>
            <td><?php echo htmlspecialchars((string)$school['name']); ?></td>
            <td>
              <?php echo htmlspecialchars(strtoupper((string)($school['package_tier'] ?? 'elimu_hub'))); ?><br>
              <small class="text-muted"><?php echo htmlspecialchars((string)($school['approval_status'] ?? 'approved')); ?></small><br>
              <small class="text-muted"><?php echo ((int)($school['mpesa_enabled'] ?? 1) === 1 ? 'M-Pesa ON' : 'M-Pesa OFF'); ?>, <?php echo htmlspecialchars((string)($school['support_plan'] ?? 'basic')); ?></small>
            </td>
            <td>
              <?php
                $termStart = trim((string)($school['term_start_date'] ?? ''));
                $termEnd = trim((string)($school['term_end_date'] ?? ''));
                echo $termStart !== '' || $termEnd !== '' ? htmlspecialchars(trim($termStart . ' to ' . $termEnd, ' to')) : 'Not set';
              ?>
            </td>
            <td>
              <?php if (strtolower((string)($school['approval_status'] ?? 'approved')) === 'pending'): ?>
                <span class="badge bg-info text-dark">Pending</span>
              <?php elseif (strtolower((string)($school['approval_status'] ?? 'approved')) === 'rejected'): ?>
                <span class="badge bg-dark">Rejected</span>
              <?php elseif ((int)($school['is_suspended'] ?? 0) === 1): ?>
                <span class="badge bg-warning text-dark">Suspended</span>
              <?php elseif ((int)($school['is_locked'] ?? 0) === 1): ?>
                <span class="badge bg-danger">Locked</span>
              <?php else: ?>
                <span class="badge bg-success">Active</span>
              <?php endif; ?>
              <div class="small text-muted mt-1"><?php echo ((int)($school['allow_results'] ?? 0) === 1 ? 'Results enabled' : 'Results hidden'); ?></div>
            </td>
            <td>
              <?php if (strtolower((string)($school['approval_status'] ?? 'approved')) === 'pending'): ?>
                <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/approve_school.php'); ?>" method="post" class="d-inline">
                  <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                  <button class="btn btn-success btn-sm" type="submit">Approve</button>
                </form>
                <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/reject_school.php'); ?>" method="post" class="d-inline">
                  <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                  <button class="btn btn-outline-dark btn-sm" type="submit">Reject</button>
                </form>
              <?php endif; ?>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/switch_school.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <button class="btn btn-success btn-sm" type="submit"><?php echo $selectedSchoolId === (int)$school['id'] ? 'Selected' : 'Switch'; ?></button>
              </form>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/switch_school.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <input type="hidden" name="redirect_to" value="admin/teachers.php">
                <button class="btn btn-outline-primary btn-sm" type="submit">Teachers</button>
              </form>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/switch_school.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <input type="hidden" name="redirect_to" value="admin/students.php">
                <button class="btn btn-outline-primary btn-sm" type="submit">Students</button>
              </form>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/switch_school.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <input type="hidden" name="redirect_to" value="admin/parents.php">
                <button class="btn btn-outline-primary btn-sm" type="submit">Parents</button>
              </form>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/impersonate_school_admin.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <button class="btn btn-primary btn-sm" type="submit">Open Admin</button>
              </form>
              <a class="btn btn-secondary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/edit_school.php?id=' . (int)$school['id']); ?>">Edit</a>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/toggle_school_status.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <button class="btn btn-warning btn-sm" type="submit"><?php echo (int)($school['is_locked'] ?? 0) === 1 ? 'Unlock' : 'Lock'; ?></button>
              </form>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/toggle_school_suspension.php'); ?>" method="post" class="d-inline">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <button class="btn btn-outline-warning btn-sm" type="submit"><?php echo (int)($school['is_suspended'] ?? 0) === 1 ? 'Unsuspend' : 'Suspend'; ?></button>
              </form>
              <a class="btn btn-outline-primary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/index.php?school_id=' . (int)$school['id']); ?>">Refresh</a>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/delete_school.php'); ?>" method="post" class="d-inline" onsubmit="return confirm('Delete this school? This cannot be undone.');">
                <input type="hidden" name="school_id" value="<?php echo (int)$school['id']; ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<div class="card mt-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Owner Accounts</strong>
    <a class="btn btn-danger btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/owner_account.php'); ?>">Create Owner</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($owners as $owner): ?>
          <tr>
            <td><?php echo (int)$owner['id']; ?></td>
            <td><?php echo htmlspecialchars(trim((string)$owner['fname'] . ' ' . (string)$owner['lname'])); ?></td>
            <td><?php echo htmlspecialchars((string)$owner['email']); ?></td>
            <td><?php echo (string)$owner['status'] === '1' ? 'Active' : 'Blocked'; ?></td>
            <td class="d-flex gap-2">
              <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/owner_account.php?id=' . (int)$owner['id']); ?>">Edit</a>
              <form action="<?php echo htmlspecialchars(($superAdminPath !== '' ? $superAdminPath : '') . '/core/toggle_owner_status.php'); ?>" method="post">
                <input type="hidden" name="owner_id" value="<?php echo (int)$owner['id']; ?>">
                <button class="btn btn-warning btn-sm" type="submit"><?php echo (string)$owner['status'] === '1' ? 'Block' : 'Unblock'; ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</main>
<script src="../js/bootstrap.min.js"></script>
<script>
(function () {
  var el = document.getElementById('schoolDetailModal');
  if (!el || typeof bootstrap === 'undefined') {
    return;
  }
  var trigger = document.querySelector('[data-bs-target="#schoolDetailModal"]');
  if (!trigger) {
    return;
  }
})();
</script>
</body>
</html>
