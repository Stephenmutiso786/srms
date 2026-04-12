<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');
require_once('const/notify.php');

if ($res !== '1' || !in_array((int)$level, [0, 1])) { 
    header('location:../'); exit; 
}
app_require_permission('report.generate', 'admin');

$action = trim((string)($_GET['action'] ?? ''));
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

$promotion_batches = [];
$batch_details = [];
$students_in_batch = [];
$classes = [];
$years = [];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get classes
    $stmt = $conn->prepare('SELECT id, name FROM tbl_classes ORDER BY grade, name');
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get available academic years
    $stmt = $conn->prepare('SELECT DISTINCT academic_year FROM tbl_exams ORDER BY academic_year DESC');
    $stmt->execute();
    $years = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'academic_year');

    // Get promotion batches
    $stmt = $conn->prepare('
        SELECT pb.*, 
               c.name AS class_name,
               COALESCE(COUNT(sp.id), 0) as total_students,
               COALESCE(SUM(CASE WHEN sp.status = \'promoted\' THEN 1 ELSE 0 END), 0) as promoted_count,
               COALESCE(SUM(CASE WHEN sp.status = \'repeated\' THEN 1 ELSE 0 END), 0) as repeated_count,
               COALESCE(SUM(CASE WHEN sp.fees_cleared = FALSE THEN 1 ELSE 0 END), 0) as not_cleared_count
        FROM tbl_promotion_batches pb
        LEFT JOIN tbl_classes c ON c.id = pb.class_id
        LEFT JOIN tbl_student_promotions sp ON sp.batch_id = pb.id
        GROUP BY pb.id
        ORDER BY pb.created_at DESC
        LIMIT 100
    ');
    $stmt->execute();
    $promotion_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get batch details if viewing specific batch
    if ($batchId > 0) {
        $stmt = $conn->prepare('
            SELECT pb.*, c.name AS class_name, c.grade AS class_level
            FROM tbl_promotion_batches pb
            LEFT JOIN tbl_classes c ON c.id = pb.class_id
            WHERE pb.id = ?
        ');
        $stmt->execute([$batchId]);
        $batch_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($batch_details) {
            // Get students in this batch
            $stmt = $conn->prepare('
                SELECT sp.*, 
                       st.school_id, st.fname, st.mname, st.lname,
                       concat_ws(\' \', st.fname, st.mname, st.lname) as student_name,
                       c_from.name as from_class,
                       c_to.name as to_class
                FROM tbl_student_promotions sp
                JOIN tbl_students st ON st.id = sp.student_id
                LEFT JOIN tbl_classes c_from ON c_from.id = sp.from_class
                LEFT JOIN tbl_classes c_to ON c_to.id = sp.to_class
                WHERE sp.batch_id = ?
                ORDER BY st.fname, st.lname
            ');
            $stmt->execute([$batchId]);
            $students_in_batch = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $_SESSION['reply'] = array(array('danger', 'Database error: ' . $e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Promotions</title>
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
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>🎓 Student Promotions</h1><p>Manage student class promotions with approval workflow and fees clearance integration.</p></div></div>

<?php if ($batchId === 0): ?>
<!-- ===== CREATE NEW PROMOTION BATCH ===== -->
<div class="tile mb-3">
<h3 class="tile-title"><i class="bi bi-plus-circle"></i> Create New Promotion Batch</h3>
<form class="row g-3" method="POST" action="admin/core/create_promotion_batch">

<div class="col-md-3">
<label class="form-label">Academic Year *</label>
<select class="form-control" name="academic_year" required>
<option value="" disabled selected>-- Select Year --</option>
<?php foreach ($years as $year): ?>
<option value="<?php echo htmlspecialchars($year); ?>"><?php echo htmlspecialchars($year); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Class to Promote *</label>
<select class="form-control" name="class_id" required id="promotionClass">
<option value="" disabled selected>-- Select Class --</option>
<?php foreach ($classes as $class): ?>
<option value="<?php echo htmlspecialchars((string)$class['id']); ?>">
<?php echo htmlspecialchars((string)$class['name']); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-md-3">
<label class="form-label">Promotion Cycle</label>
<select class="form-control" name="promotion_cycle">
<option value="year_end" selected>Year End (Standard)</option>
<option value="mid_year">Mid-Year</option>
<option value="special">Special Promotion</option>
</select>
</div>

<div class="col-md-12">
<label class="form-label">Notes (Optional)</label>
<textarea class="form-control" name="notes" rows="2" placeholder="Any special notes for this promotion batch"></textarea>
</div>

<div class="col-md-12 d-grid">
<button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-arrow-up me-2"></i>Create Promotion Batch</button>
</div>
</form>
</div>

<!-- ===== EXISTING PROMOTION BATCHES ===== -->
<div class="tile">
<h3 class="tile-title">📋 Promotion Batches</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>#</th><th>Class</th><th>Academic Year</th><th>Cycle</th><th>Status</th><th>Students</th><th>Promoted</th><th>Repeated</th><th>Not Cleared</th><th>Created</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($promotion_batches as $batch): ?>
<tr>
<td><?php echo (int)$batch['id']; ?></td>
<td><strong><?php echo htmlspecialchars((string)$batch['class_name']); ?></strong></td>
<td><?php echo htmlspecialchars((string)$batch['academic_year']); ?></td>
<td><?php echo htmlspecialchars((string)$batch['promotion_cycle']); ?></td>
<td>
<span class="badge bg-<?php 
switch($batch['status']) {
    case 'approved': echo 'success'; break;
    case 'rejected': echo 'danger'; break;
    case 'pending': echo 'warning'; break;
    default: echo 'secondary';
}
?>">
<?php echo ucfirst(htmlspecialchars((string)$batch['status'])); ?>
</span>
</td>
<td><?php echo (int)$batch['total_students']; ?></td>
<td><span class="badge bg-success"><?php echo (int)$batch['promoted_count']; ?></span></td>
<td><span class="badge bg-warning text-dark"><?php echo (int)$batch['repeated_count']; ?></span></td>
<td><span class="badge bg-danger"><?php echo (int)$batch['not_cleared_count']; ?></span></td>
<td><small><?php echo htmlspecialchars((string)$batch['created_at']); ?></small></td>
<td>
<a class="btn btn-sm btn-primary" href="admin/promotions?batch_id=<?php echo (int)$batch['id']; ?>">Review</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$promotion_batches): ?>
<tr><td colspan="11" class="text-center text-muted">No promotion batches created yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>

<?php else: ?>
<!-- ===== BATCH REVIEW & APPROVAL VIEW ===== -->
<?php if ($batch_details): ?>

<div class="row mb-3">
<div class="col-md-6">
<div class="tile tile-colored bg-primary">
<div class="tile-body">
<h1><?php echo htmlspecialchars((string)$batch_details['class_name']); ?></h1>
<p>Year: <?php echo htmlspecialchars((string)$batch_details['academic_year']); ?> | Cycle: <?php echo htmlspecialchars((string)$batch_details['promotion_cycle']); ?></p>
</div>
</div>
</div>
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Promotion Summary</h3>
<p><strong><?php echo (int)count($students_in_batch); ?></strong> students in batch</p>
<p><strong class="text-success"><?php echo count(array_filter($students_in_batch, fn($s) => $s['status'] === 'promoted')); ?></strong> recommended for promotion</p>
<p><strong class="text-warning"><?php echo count(array_filter($students_in_batch, fn($s) => $s['status'] === 'repeated')); ?></strong> to repeat</p>
<p><strong class="text-danger"><?php echo count(array_filter($students_in_batch, fn($s) => !$s['fees_cleared'])); ?></strong> not cleared fees</p>
</div>
</div>
</div>

<div class="tile mb-3">
<h3 class="tile-title">⚠️ Pre-Approval Checklist</h3>
<div class="row g-2">
<div class="col-md-4">
<div class="alert alert-info mb-0">
<strong>📋 Report Cards</strong><br>
<?php $finalized = count(array_filter($students_in_batch, fn($s) => $s['report_card_finalized'])); ?>
<?php echo $finalized; ?> / <?php echo count($students_in_batch); ?> finalized
</div>
</div>
<div class="col-md-4">
<div class="alert alert-info mb-0">
<strong>💰 Fees Clearance</strong><br>
<?php $cleared = count(array_filter($students_in_batch, fn($s) => $s['fees_cleared'])); ?>
<?php echo $cleared; ?> / <?php echo count($students_in_batch); ?> cleared
</div>
</div>
<div class="col-md-4">
<div class="alert alert-info mb-0">
<strong>✅ Status</strong><br>
<span class="badge bg-<?php echo $batch_details['status'] === 'pending' ? 'warning' : 'success'; ?>">
<?php echo ucfirst(htmlspecialchars((string)$batch_details['status'])); ?>
</span>
</div>
</div>
</div>
</div>

<!-- ===== STUDENTS IN BATCH ===== -->
<div class="tile">
<h3 class="tile-title">👥 Students in Promotion Batch</h3>
<div class="table-responsive">
<table class="table table-sm table-hover">
<thead><tr><th>#</th><th>Name</th><th>Adm No</th><th>Mean</th><th>Grade</th><th>Report</th><th>Fees</th><th>Status</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach ($students_in_batch as $idx => $student): ?>
<tr<?php echo !$student['fees_cleared'] || !$student['report_card_finalized'] ? ' class="table-warning"' : ''; ?>>
<td><?php echo $idx + 1; ?></td>
<td><?php echo htmlspecialchars((string)$student['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)($student['school_id'] ?? '')); ?></td>
<td><?php echo $student['mean_score'] !== null ? number_format((float)$student['mean_score'], 2) : '—'; ?></td>
<td><?php echo $student['merit_grade'] ? '<strong>' . htmlspecialchars($student['merit_grade']) . '</strong>' : '—'; ?></td>
<td><?php echo $student['report_card_finalized'] ? '✓' : '<span class="badge bg-danger">✗</span>'; ?></td>
<td><?php echo $student['fees_cleared'] ? '✓' : '<span class="badge bg-danger">✗ Bal</span>'; ?></td>
<td>
<span class="badge bg-<?php echo $student['status'] === 'promoted' ? 'success' : ($student['status'] === 'repeated' ? 'warning' : 'secondary'); ?>">
<?php echo ucfirst(htmlspecialchars((string)$student['status'])); ?>
</span>
</td>
<td><small><?php echo htmlspecialchars((string)($student['notes'] ?? '')); ?></small></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- ===== PROMOTION ACTIONS ===== -->
<?php if ($batch_details['status'] === 'pending'): ?>
<div class="tile mt-3">
<h3 class="tile-title">Actions</h3>
<div class="row g-2">
<div class="col-md-12">
<p class="alert alert-info">
<strong>Next Steps:</strong> Review the checklist above. Once all students have finalized report cards and cleared fees, you can approve this promotion batch.
</p>
</div>
<div class="col-md-6 d-grid">
<form method="POST" action="admin/core/approve_promotion" style="display:inline;">
<input type="hidden" name="batch_id" value="<?php echo (int)$batchId; ?>">
<button class="btn btn-success btn-lg" type="submit" onclick="return confirm('Approve this promotion batch? This will update student classes and generate certificates.')">
<i class="bi bi-check-circle me-2"></i>APPROVE PROMOTION
</button>
</form>
</div>
<div class="col-md-6 d-grid">
<form method="POST" action="admin/core/reject_promotion" style="display:inline;">
<input type="hidden" name="batch_id" value="<?php echo (int)$batchId; ?>">
<button class="btn btn-danger btn-lg" type="submit" onclick="return confirm('Reject this promotion batch? Students will not be promoted.')">
<i class="bi bi-x-circle me-2"></i>REJECT
</button>
</form>
</div>
</div>
</div>
<?php else: ?>
<div class="alert alert-info mt-3">
<strong>Status:</strong> This promotion batch has been <?php echo htmlspecialchars((string)$batch_details['status']); ?> on <?php echo htmlspecialchars((string)$batch_details['approved_at']); ?>
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
