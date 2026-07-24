<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');
require_once('const/notify.php');
require_once('const/report_engine.php');

$isSuperAdmin = !empty($super_admin);
$isLeadershipReviewer = !$isSuperAdmin && in_array((int)$level, [0, 1], true);
$isPromotionManager = $isSuperAdmin || $isLeadershipReviewer;

if ($res !== '1' || !$isPromotionManager) { 
    header('location:../'); exit; 
}
app_require_permission('report.generate', 'admin');

$action = trim((string)($_GET['action'] ?? ''));
$batchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

$promotion_batches = [];
$batch_details = [];
$students_in_batch = [];
$classes = [];
$classMeta = [];
$years = [];
$sourceClassStudentCount = null;
$targetClassStudentCount = null;
$targetClassHasOccupants = false;
$promotedStudentsCount = 0;
$repeatersRemainingCount = 0;
$alumniStudentsCount = 0;
$promotionQueue = [];
$autoPromotionRun = [];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_promotion_workflow_schema($conn);
    app_ensure_terms_academic_year_schema($conn);
    if (!empty($account_id)) {
        $autoPromotionRun = app_auto_prepare_year_end_promotions($conn, (int)$account_id);
    }
    $promotionQueue = app_promotion_queue_summary($conn);
    if (function_exists('app_ensure_class_cbe_level_schema')) {
        app_ensure_class_cbe_level_schema($conn);
    }

    // Get classes
    $stmt = $conn->prepare('SELECT id, name, grade FROM tbl_classes ORDER BY name');
    $stmt->execute();
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $classGradeMap = [];
    foreach ($classes as $classRow) {
        $effectiveGrade = app_effective_grade_level((string)($classRow['name'] ?? ''), $classRow['grade'] ?? null);
        if ($effectiveGrade > 0 && !isset($classGradeMap[$effectiveGrade])) {
            $classGradeMap[$effectiveGrade] = [
                'id' => (int)$classRow['id'],
                'name' => (string)$classRow['name'],
            ];
        }
    }
    usort($classes, static function (array $left, array $right): int {
        $leftGrade = app_effective_grade_level((string)($left['name'] ?? ''), $left['grade'] ?? null);
        $rightGrade = app_effective_grade_level((string)($right['name'] ?? ''), $right['grade'] ?? null);
        if ($leftGrade === $rightGrade) {
            return strcasecmp((string)($left['name'] ?? ''), (string)($right['name'] ?? ''));
        }
        if ($leftGrade === 0) {
            return -1;
        }
        if ($rightGrade === 0) {
            return 1;
        }
        return $leftGrade <=> $rightGrade;
    });
    foreach ($classes as $classRow) {
        $effectiveGrade = app_effective_grade_level((string)($classRow['name'] ?? ''), $classRow['grade'] ?? null);
        $nextClassName = '';
        if ($effectiveGrade >= app_promotion_terminal_grade_level()) {
            $nextClassName = 'Alumni';
        } elseif ($effectiveGrade > 0 && isset($classGradeMap[$effectiveGrade + 1])) {
            $nextClassName = (string)$classGradeMap[$effectiveGrade + 1]['name'];
        }
        $classMeta[(int)$classRow['id']] = [
            'effective_grade' => $effectiveGrade,
            'next_class_name' => $nextClassName,
        ];
    }

    // Get available academic years from Terms & Sessions first.
    if (app_table_exists($conn, 'tbl_terms')) {
        $selectYearSql = app_column_exists($conn, 'tbl_terms', 'academic_year')
            ? 'SELECT name, academic_year FROM tbl_terms ORDER BY id DESC'
            : 'SELECT name, NULL AS academic_year FROM tbl_terms ORDER BY id DESC';
        $stmt = $conn->prepare($selectYearSql);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $termRow) {
            $year = trim((string)($termRow['academic_year'] ?? ''));
            if ($year === '') {
                $year = app_extract_academic_year((string)($termRow['name'] ?? ''));
            }
            if ($year !== '') {
                $years[] = $year;
            }
        }
        $years = array_values(array_unique(array_filter(array_map('trim', $years))));
        rsort($years, SORT_STRING);
    }
    // Fallback to exams only if terms do not provide a year.
    if (empty($years) && app_table_exists($conn, 'tbl_exams') && app_column_exists($conn, 'tbl_exams', 'academic_year')) {
        $stmt = $conn->prepare("SELECT DISTINCT academic_year FROM tbl_exams WHERE academic_year IS NOT NULL AND TRIM(academic_year) <> '' ORDER BY academic_year DESC");
        $stmt->execute();
        $years = array_values(array_filter(array_map(static function ($value): string {
            return trim((string)$value);
        }, array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'academic_year'))));
    }
    if (empty($years)) {
        $currentYear = (int)date('Y');
        $years = [$currentYear . '/' . ($currentYear + 1)];
    }

    // Get promotion batches
    $stmt = $conn->prepare("
        SELECT pb.*, 
               c.name AS class_name,
               COALESCE(COUNT(sp.id), 0) as total_students,
               COALESCE(SUM(CASE WHEN COALESCE(sp.final_status, sp.status) = 'promoted' THEN 1 ELSE 0 END), 0) as promoted_count,
               COALESCE(SUM(CASE WHEN COALESCE(sp.suggested_status, sp.status) = 'conditional' THEN 1 ELSE 0 END), 0) as conditional_count,
               COALESCE(SUM(CASE WHEN COALESCE(sp.final_status, sp.status) = 'repeated' THEN 1 ELSE 0 END), 0) as repeated_count,
               COALESCE(SUM(CASE WHEN COALESCE(sp.final_status, sp.status) IN ('exited', 'suspended') THEN 1 ELSE 0 END), 0) as exited_count,
               COALESCE(SUM(CASE WHEN sp.fees_cleared = FALSE THEN 1 ELSE 0 END), 0) as not_cleared_count
        FROM tbl_promotion_batches pb
        LEFT JOIN tbl_classes c ON c.id = pb.class_id
        LEFT JOIN tbl_student_promotions sp ON sp.batch_id = pb.id
        GROUP BY pb.id
        ORDER BY pb.created_at DESC
        LIMIT 100
    ");
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
            $batchRule = app_promotion_rule_for_grade($conn, (int)($batch_details['class_level'] ?? 0));
            $reviewState = strtolower(trim((string)($batch_details['review_state'] ?? 'pending_review')));
            $requiresReview = (bool)($batchRule['require_headteacher_approval'] ?? true);
            $batchClassMeta = $classMeta[(int)($batch_details['class_id'] ?? 0)] ?? ['effective_grade' => 0, 'next_class_name' => ''];
            $promotionTargetName = (string)($batchClassMeta['next_class_name'] ?? '');

            // Get students in this batch
            $stmt = $conn->prepare("
                SELECT sp.*, 
                       st.school_id, st.fname, st.mname, st.lname,
                       concat_ws(' ', st.fname, st.mname, st.lname) as student_name,
                       c_from.name as from_class,
                       c_to.name as to_class,
                       c_to.name as to_class_name,
                       (SELECT r.grade FROM tbl_report_cards r WHERE r.student_id = st.id ORDER BY r.id DESC LIMIT 1) AS latest_report_grade,
                       COALESCE(sp.final_status, sp.status) AS final_status,
                       COALESCE(sp.suggested_status, sp.status) AS suggested_status
                FROM tbl_student_promotions sp
                JOIN tbl_students st ON st.id = sp.student_id
                LEFT JOIN tbl_classes c_from ON c_from.id = sp.from_class
                LEFT JOIN tbl_classes c_to ON c_to.id = sp.to_class
                WHERE sp.batch_id = ?
                ORDER BY st.fname, st.lname
            ");
            $stmt->execute([$batchId]);
            $students_in_batch = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $promotedStudentsCount = count(array_filter($students_in_batch, static function (array $student): bool {
                return strtolower(trim((string)($student['final_status'] ?? $student['status'] ?? ''))) === 'promoted';
            }));
            $repeatersRemainingCount = count(array_filter($students_in_batch, static function (array $student): bool {
                return strtolower(trim((string)($student['final_status'] ?? $student['status'] ?? ''))) === 'repeated';
            }));
            $alumniStudentsCount = count(array_filter($students_in_batch, static function (array $student): bool {
                return strtolower(trim((string)($student['final_status'] ?? $student['status'] ?? ''))) === 'alumni';
            }));

            $sourceClassId = (int)($batch_details['class_id'] ?? 0);
            if ($sourceClassId > 0) {
                $sourceClassStudentCount = app_active_student_count_in_class($conn, $sourceClassId);
            }

            $targetClassId = 0;
            foreach ($students_in_batch as $studentRow) {
                $candidateTargetClassId = (int)($studentRow['to_class'] ?? 0);
                if ($candidateTargetClassId > 0) {
                    $targetClassId = $candidateTargetClassId;
                    break;
                }
            }
            if ($targetClassId > 0) {
                $targetClassStudentCount = app_active_student_count_in_class($conn, $targetClassId);
                $targetClassHasOccupants = $targetClassStudentCount > 0;
            }

            $reviewMode = $isLeadershipReviewer && $batch_details['status'] === 'pending' && $reviewState === 'pending_review';
            $canFinalize = $isSuperAdmin && $batch_details['status'] === 'pending' && ($reviewState === 'reviewed' || !$requiresReview || $reviewState === 'ready_for_execution');
            $currentStepText = 'Create promotion batch';
            if ($batch_details['status'] === 'approved') {
                $currentStepText = 'Promotion completed';
            } elseif ($batch_details['status'] === 'rejected') {
                $currentStepText = 'Promotion rejected';
            } elseif ($reviewMode) {
                $currentStepText = 'Headteacher or Deputy review required';
            } elseif ($canFinalize) {
                $currentStepText = 'Ready for Super Admin completion';
            } elseif ($reviewState === 'reviewed') {
                $currentStepText = 'Waiting for Super Admin completion';
            } elseif ($reviewState === 'pending_review') {
                $currentStepText = 'Waiting for headteacher/deputy review';
            }
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

<div class="tile mb-3">
<div class="row g-3 align-items-center">
<div class="col-md-8">
<h3 class="tile-title mb-1">Promotion Workflow</h3>
<p class="mb-0">Create a promotion simulation, review suggested decisions, then execute the final class updates after approval.</p>
</div>
<div class="col-md-4 text-md-end">
<a class="btn btn-outline-primary" href="admin/promotions"><i class="bi bi-arrow-clockwise me-2"></i>Refresh</a>
</div>
</div>
</div>

<?php if ($batchId > 0 && !empty($batch_details)): ?>
<div class="alert <?php echo $canFinalize ? 'alert-success' : ($reviewMode ? 'alert-warning' : 'alert-info'); ?> mb-3">
<strong>Your promotion role:</strong>
<?php if ($isSuperAdmin): ?>
You are signed in as <strong>Super Admin</strong>. <?php echo $canFinalize ? 'This batch is ready. Use the complete button below to finish the promotion.' : 'You can only complete the batch after Headteacher or Deputy review is submitted.'; ?>
<?php elseif ($isLeadershipReviewer): ?>
You are signed in as <strong><?php echo htmlspecialchars((string)$designation); ?></strong>. Your role is to review and send the batch to Super Admin. You do not complete the final class move on this page.
<?php endif; ?>
</div>
<?php endif; ?>

<?php if (!empty($promotionQueue)): ?>
<div class="tile mb-3">
<div class="row g-2">
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Pending review</strong><br><?php echo (int)($promotionQueue['pending_review'] ?? 0); ?> batch(es)</div></div>
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Ready for Super Admin</strong><br><?php echo (int)($promotionQueue['ready_for_super_admin'] ?? 0); ?> batch(es)</div></div>
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Completed</strong><br><?php echo (int)($promotionQueue['completed'] ?? 0); ?> batch(es)</div></div>
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Auto promotion</strong><br><?php echo !empty($promotionQueue['auto_enabled']) ? 'Enabled' : 'Disabled'; ?></div></div>
</div>
</div>
<?php endif; ?>

<?php if ($batchId === 0): ?>
<!-- ===== CREATE NEW PROMOTION BATCH ===== -->
<div class="tile mb-3">
<h3 class="tile-title"><i class="bi bi-plus-circle"></i> Create New Promotion Batch</h3>
<?php if (!empty($promotionQueue['auto_enabled'])): ?>
<div class="alert alert-info">
<strong>Automatic year-end promotion is enabled.</strong>
When the academic year end date passes, the system prepares promotion batches automatically. You can still create one manually when needed for a special cycle or correction.
<?php if (!empty($autoPromotionRun['ran']) || !empty($autoPromotionRun['already_generated'])): ?>
<br><small><?php echo htmlspecialchars((string)($autoPromotionRun['message'] ?? '')); ?></small>
<?php endif; ?>
</div>
<?php endif; ?>
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
<?php
$meta = $classMeta[(int)$class['id']] ?? ['next_class_name' => '', 'effective_grade' => 0];
$nextClassName = (string)($meta['next_class_name'] ?? '');
?>
<option value="<?php echo htmlspecialchars((string)$class['id']); ?>" data-next-class="<?php echo htmlspecialchars($nextClassName); ?>">
<?php echo htmlspecialchars((string)$class['name']); ?>
</option>
<?php endforeach; ?>
</select>
<small class="form-text text-muted" id="promotionTargetHint">Select a class to see the next promotion target.</small>
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
<table class="table table-hover align-middle">
<thead><tr><th>#</th><th>Class</th><th>Academic Year</th><th>Cycle</th><th>Workflow</th><th>Students</th><th>Promoted</th><th>Conditional</th><th>Repeated</th><th>Not Cleared</th><th>Created</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($promotion_batches as $batch): ?>
<tr>
<td><?php echo (int)$batch['id']; ?></td>
<td><strong><?php echo htmlspecialchars((string)$batch['class_name']); ?></strong></td>
<td><?php echo htmlspecialchars((string)$batch['academic_year']); ?></td>
<td><?php echo htmlspecialchars((string)$batch['promotion_cycle']); ?></td>
<td>
<div class="d-flex flex-column gap-1">
<span class="badge bg-<?php 
switch($batch['status']) {
    case 'approved': echo 'success'; break;
    case 'rejected': echo 'danger'; break;
    case 'pending': echo 'warning'; break;
    default: echo 'secondary';
}
?>"><?php echo ucfirst(htmlspecialchars((string)$batch['status'])); ?></span>
<small class="text-muted">Review: <?php echo htmlspecialchars((string)($batch['review_state'] ?? 'pending_review')); ?></small>
</div>
</td>
<td><?php echo (int)$batch['total_students']; ?></td>
<td><span class="badge bg-success"><?php echo (int)$batch['promoted_count']; ?></span></td>
<td><span class="badge bg-info text-dark"><?php echo (int)$batch['conditional_count']; ?></span></td>
<td><span class="badge bg-warning text-dark"><?php echo (int)$batch['repeated_count']; ?></span></td>
<td><span class="badge bg-danger"><?php echo (int)$batch['not_cleared_count']; ?></span></td>
<td><small><?php echo htmlspecialchars((string)$batch['created_at']); ?></small></td>
<td>
<a class="btn btn-sm btn-primary" href="admin/promotions?batch_id=<?php echo (int)$batch['id']; ?>">Review</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$promotion_batches): ?>
<tr><td colspan="12" class="text-center text-muted">No promotion batches created yet.</td></tr>
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
<p class="mb-0"><strong>Target class:</strong> <?php echo htmlspecialchars($promotionTargetName !== '' ? $promotionTargetName : 'Not configured'); ?></p>
</div>
</div>
</div>
<div class="col-md-6">
<div class="tile">
<h3 class="tile-title">Promotion Summary</h3>
<p><strong>Current step:</strong> <?php echo htmlspecialchars($currentStepText); ?></p>
<p><strong><?php echo (int)count($students_in_batch); ?></strong> students in batch</p>
<p><strong class="text-success"><?php echo count(array_filter($students_in_batch, fn($s) => ($s['final_status'] ?? $s['status']) === 'promoted')); ?></strong> final promotions</p>
<p><strong class="text-dark"><?php echo count(array_filter($students_in_batch, fn($s) => ($s['final_status'] ?? $s['status']) === 'alumni')); ?></strong> moving to alumni</p>
<p><strong class="text-info"><?php echo count(array_filter($students_in_batch, fn($s) => ($s['status'] ?? '') === 'conditional')); ?></strong> awaiting review</p>
<p><strong class="text-warning"><?php echo count(array_filter($students_in_batch, fn($s) => ($s['final_status'] ?? $s['status']) === 'repeated')); ?></strong> to repeat</p>
<p><strong class="text-danger"><?php echo count(array_filter($students_in_batch, fn($s) => !$s['fees_cleared'])); ?></strong> not cleared fees</p>
</div>
</div>
</div>

<div class="alert alert-primary mb-3">
<strong>How this page works:</strong>
The system automatically sets the next class based on the class you created the batch from.
For this batch, students are moving from <strong><?php echo htmlspecialchars((string)$batch_details['class_name']); ?></strong>
to <strong><?php echo htmlspecialchars($promotionTargetName !== '' ? $promotionTargetName : 'the next configured class'); ?></strong>.
You do not select the destination class manually on this screen.
</div>

<?php if ($targetClassHasOccupants): ?>
<div class="alert alert-info mb-3">
<strong>Target class info:</strong>
<?php echo htmlspecialchars($promotionTargetName); ?> already has <strong><?php echo (int)$targetClassStudentCount; ?></strong> active student(s).
That is allowed. Promotion will add the newly promoted learners into the same class, while any repeaters can remain in the previous class.
</div>
<?php endif; ?>

<?php if ($batch_details['status'] === 'approved'): ?>
<div class="tile mb-3">
<h3 class="tile-title">After Promotion</h3>
<div class="row g-2">
<div class="col-md-4"><div class="alert alert-light mb-0"><strong>Students moved</strong><br><?php echo (int)$promotedStudentsCount; ?> promoted to <?php echo htmlspecialchars($promotionTargetName !== '' ? $promotionTargetName : 'the next class'); ?></div></div>
<div class="col-md-4"><div class="alert alert-light mb-0"><strong>Alumni</strong><br><?php echo (int)$alumniStudentsCount; ?> completed and moved to alumni</div></div>
<div class="col-md-4"><div class="alert alert-light mb-0"><strong><?php echo htmlspecialchars((string)$batch_details['class_name']); ?> now has</strong><br><?php echo $sourceClassStudentCount !== null ? (int)$sourceClassStudentCount : 0; ?> active students</div></div>
<?php if ($promotionTargetName !== 'Alumni'): ?>
<div class="col-md-4"><div class="alert alert-light mb-0"><strong><?php echo htmlspecialchars($promotionTargetName !== '' ? $promotionTargetName : 'Target class'); ?> now has</strong><br><?php echo $targetClassStudentCount !== null ? (int)$targetClassStudentCount : 0; ?> active students</div></div>
<?php endif; ?>
</div>
<div class="alert alert-info mt-3 mb-0">
<strong>What this means:</strong>
The previous class record stays in the system. It may become empty after promotion, and that is normal. If it now has <strong><?php echo $sourceClassStudentCount !== null ? (int)$sourceClassStudentCount : 0; ?></strong> students, it is ready for new intake or for repeaters to remain there.
<?php if ($repeatersRemainingCount > 0): ?>
This batch kept <strong><?php echo (int)$repeatersRemainingCount; ?></strong> repeater(s) in the previous class.
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php if (!empty($canFinalize)): ?>
<div class="tile mb-3 border border-success">
<div class="row g-3 align-items-center">
<div class="col-md-8">
<h3 class="tile-title text-success mb-1">Complete Promotion</h3>
<p class="mb-0">Headteacher or Deputy already reviewed this batch. The final step is now with Super Admin.</p>
<?php if ($targetClassHasOccupants): ?>
<p class="text-muted mb-0 mt-2"><?php echo htmlspecialchars($promotionTargetName); ?> already has learners, and this batch will merge the promoted learners into that class.</p>
<?php endif; ?>
</div>
<div class="col-md-4 d-grid">
<form method="POST" action="admin/core/approve_promotion">
<input type="hidden" name="batch_id" value="<?php echo (int)$batchId; ?>">
<button class="btn btn-success btn-lg" type="submit" onclick="return confirm('Complete this promotion batch? This will update student classes and generate certificates.')">
<i class="bi bi-check-circle me-2"></i>COMPLETE PROMOTION
</button>
</form>
</div>
</div>
</div>
<?php endif; ?>

<div class="tile mb-3">
<h3 class="tile-title">Rule Preview</h3>
<div class="row g-2">
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Minimum score</strong><br><?php echo number_format((float)($batchRule['min_score_for_promotion'] ?? 40), 2); ?></div></div>
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Fees clearance</strong><br><?php echo !empty($batchRule['require_fees_clearance']) ? 'Required' : 'Optional'; ?></div></div>
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Report finalization</strong><br><?php echo !empty($batchRule['require_report_finalization']) ? 'Required' : 'Optional'; ?></div></div>
<div class="col-md-3"><div class="alert alert-light mb-0"><strong>Leadership review</strong><br><?php echo !empty($batchRule['require_headteacher_approval']) ? 'Required (Headteacher/Deputy)' : 'Optional'; ?></div></div>
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
<?php if (!empty($reviewMode)): ?>
<form method="POST" action="admin/core/review_promotion_batch">
<input type="hidden" name="batch_id" value="<?php echo (int)$batchId; ?>">
<?php endif; ?>
<div class="table-responsive">
<table class="table table-sm table-hover align-middle">
<thead><tr><th>#</th><th>Name</th><th>Adm No</th><th>Mean</th><th>Report Grade</th><th>Promote To</th><th>Report</th><th>Fees</th><th>Suggested</th><th>Final</th><th>Review Notes</th></tr></thead>
<tbody>
<?php foreach ($students_in_batch as $idx => $student): ?>
<?php
$studentFinalStatus = strtolower(trim((string)($student['final_status'] ?? $student['status'] ?? '')));
$studentSuggestedStatus = strtolower(trim((string)($student['suggested_status'] ?? $student['status'] ?? '')));
$studentTargetLabel = $studentFinalStatus === 'alumni'
	? 'Alumni'
	: (($student['to_class_name'] ?? '') !== '' ? (string)$student['to_class_name'] : ($promotionTargetName !== '' ? $promotionTargetName : (string)($student['to_class'] ?? '')));
?>
<tr<?php echo !$student['fees_cleared'] || !$student['report_card_finalized'] ? ' class="table-warning"' : ''; ?>>
<td><?php echo $idx + 1; ?></td>
<td><?php echo htmlspecialchars((string)$student['student_name']); ?></td>
<td><?php echo htmlspecialchars((string)($student['school_id'] ?? '')); ?></td>
<td><?php echo $student['mean_score'] !== null ? number_format((float)$student['mean_score'], 2) : '—'; ?></td>
<td><?php echo ($student['latest_report_grade'] ?? $student['merit_grade']) ? '<strong>' . htmlspecialchars((string)($student['latest_report_grade'] ?: $student['merit_grade'])) . '</strong>' : '—'; ?></td>
<td><?php echo htmlspecialchars($studentTargetLabel); ?></td>
<td><?php echo $student['report_card_finalized'] ? '✓' : '<span class="badge bg-danger">✗</span>'; ?></td>
<td><?php echo $student['fees_cleared'] ? '✓' : '<span class="badge bg-danger">✗ Bal</span>'; ?></td>
<td>
<span class="badge bg-<?php echo $studentSuggestedStatus === 'promoted' ? 'success' : ($studentSuggestedStatus === 'conditional' ? 'info text-dark' : ($studentSuggestedStatus === 'repeated' ? 'warning' : ($studentSuggestedStatus === 'alumni' ? 'dark' : 'secondary'))); ?>">
<?php echo ucfirst(htmlspecialchars((string)$student['suggested_status'])); ?>
</span>
</td>
<td>
<?php if (!empty($reviewMode)): ?>
<select class="form-control form-control-sm" name="final_status[<?php echo (int)$student['id']; ?>]">
<option value="promoted"<?php echo ($studentFinalStatus === 'promoted') ? ' selected' : ''; ?>>Promoted</option>
<option value="repeated"<?php echo ($studentFinalStatus === 'repeated') ? ' selected' : ''; ?>>Repeat</option>
<option value="alumni"<?php echo ($studentFinalStatus === 'alumni') ? ' selected' : ''; ?>>Alumni</option>
<option value="exited"<?php echo ($studentFinalStatus === 'exited') ? ' selected' : ''; ?>>Exited</option>
<option value="suspended"<?php echo ($studentFinalStatus === 'suspended') ? ' selected' : ''; ?>>Suspended</option>
</select>
<?php else: ?>
<span class="badge bg-<?php echo $studentFinalStatus === 'promoted' ? 'success' : ($studentFinalStatus === 'repeated' ? 'warning' : ($studentFinalStatus === 'alumni' ? 'dark' : ($studentFinalStatus === 'exited' ? 'danger' : 'secondary'))); ?>">
<?php echo ucfirst(htmlspecialchars((string)$student['final_status'])); ?>
</span>
<?php endif; ?>
</td>
<td>
<?php if (!empty($reviewMode)): ?>
<input class="form-control form-control-sm" type="text" name="review_notes[<?php echo (int)$student['id']; ?>]" value="<?php echo htmlspecialchars((string)($student['override_reason'] ?? $student['review_comment'] ?? $student['notes'] ?? '')); ?>" placeholder="Review note or override reason">
<?php else: ?>
<small><?php echo htmlspecialchars((string)($student['override_reason'] ?? $student['review_comment'] ?? $student['notes'] ?? '')); ?></small>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php if (!empty($reviewMode)): ?>
<div class="d-grid mt-3">
<button class="btn btn-info btn-lg" type="submit"><i class="bi bi-clipboard-check me-2"></i>SEND TO SUPER ADMIN</button>
</div>
</form>
<?php endif; ?>
</div>

<!-- ===== PROMOTION ACTIONS ===== -->
<?php if ($batch_details['status'] === 'pending'): ?>
<div class="tile mt-3">
<h3 class="tile-title">Review Promotion</h3>
<div class="row g-2">
<div class="col-md-12">
<p class="alert alert-info">
<strong>Next Steps:</strong> Review the checklist above. First the Headteacher or Deputy Headteacher confirms or rejects the promotion, then the Super Admin completes the final move to <strong><?php echo htmlspecialchars($promotionTargetName !== '' ? $promotionTargetName : 'the next class'); ?></strong>.
</p>
</div>
<?php if (!empty($reviewMode)): ?>
<div class="col-md-12">
<p class="alert alert-warning mb-0"><strong>Your role on this page:</strong> You are at the review step. Review the promotion first, then send it back to the Super Admin for final completion.</p>
</div>
<?php elseif (!$canFinalize && $isLeadershipReviewer && $reviewState === 'reviewed'): ?>
<div class="col-md-12">
<p class="alert alert-warning mb-0"><strong>Next step:</strong> This batch has already been reviewed. It is now waiting for the Super Admin to complete the promotion.</p>
</div>
<?php elseif (!$canFinalize && $isSuperAdmin && $reviewState === 'pending_review'): ?>
<div class="col-md-12">
<p class="alert alert-warning mb-0"><strong>Why no complete button?</strong> The batch is still waiting for Headteacher or Deputy review. The final completion button will appear after that review is saved.</p>
</div>
<?php endif; ?>
<?php if (!empty($canFinalize)): ?>
<div class="col-md-6 d-grid">
<form method="POST" action="admin/core/approve_promotion" style="display:inline;">
<input type="hidden" name="batch_id" value="<?php echo (int)$batchId; ?>">
<button class="btn btn-success btn-lg" type="submit" onclick="return confirm('Complete this promotion batch? This will update student classes and generate certificates.')">
<i class="bi bi-check-circle me-2"></i>COMPLETE PROMOTION
</button>
</form>
</div>
<?php endif; ?>
<?php if ($isLeadershipReviewer && $batch_details['status'] === 'pending'): ?>
<div class="col-md-6 d-grid">
<form method="POST" action="admin/core/reject_promotion" style="display:inline;">
<input type="hidden" name="batch_id" value="<?php echo (int)$batchId; ?>">
<button class="btn btn-danger btn-lg" type="submit" onclick="return confirm('Reject this promotion batch during review? Students will not be promoted.')">
<i class="bi bi-x-circle me-2"></i>REJECT PROMOTION
</button>
</form>
</div>
<?php endif; ?>
</div>
</div>
<?php else: ?>
<div class="alert alert-info mt-3">
<strong>Status:</strong> This promotion batch is <?php echo htmlspecialchars((string)$batch_details['status']); ?> with review state <?php echo htmlspecialchars((string)($batch_details['review_state'] ?? 'pending_review')); ?>.
</div>
<?php endif; ?>

<?php endif; ?>
<?php endif; ?>

</main>

<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<script>
(function () {
  var classSelect = document.getElementById('promotionClass');
  var hint = document.getElementById('promotionTargetHint');
  if (!classSelect || !hint) {
    return;
  }

  function updatePromotionTarget() {
    var selected = classSelect.options[classSelect.selectedIndex];
    if (!selected || !selected.value) {
      hint.textContent = 'Select a class to see the next promotion target.';
      return;
    }

    var nextClass = selected.getAttribute('data-next-class') || '';
    if (nextClass) {
      hint.textContent = 'Students in this batch will be promoted to: ' + nextClass;
      return;
    }

    hint.textContent = 'No next class is configured for this class yet. Update your class setup first.';
  }

  classSelect.addEventListener('change', updatePromotionTarget);
  updatePromotionTarget();
})();
</script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
