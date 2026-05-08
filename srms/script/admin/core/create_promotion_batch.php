<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');
require_once('const/report_engine.php');

$isSuperAdmin = !empty($super_admin);
if ($res !== '1' || (!in_array((int)$level, [0, 1, 9], true) && !$isSuperAdmin)) {
    app_reply_redirect('danger', 'Unauthorized.', '../promotions');
}
app_require_permission('report.generate', '../promotions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request method.', '../promotions');
}

$classId = (int)trim((string)($_POST['class_id'] ?? '0'));
$academicYear = trim((string)($_POST['academic_year'] ?? ''));
$promotionCycle = trim((string)($_POST['promotion_cycle'] ?? 'year_end'));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($classId < 1 || $academicYear === '') {
    app_reply_redirect('danger', 'Missing required fields.', '../promotions');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->beginTransaction();

    $batchId = app_create_promotion_batch($conn, $classId, $academicYear, $promotionCycle, (int)$account_id, $notes);

    app_audit_log(
        $conn,
        'staff',
        (string)$account_id,
        'promotion.batch.create',
        'tbl_promotion_batches',
        (string)$batchId,
        ['class_id' => $classId, 'academic_year' => $academicYear, 'promotion_cycle' => $promotionCycle]
    );

    $studentCountStmt = $conn->prepare('SELECT COUNT(*) FROM tbl_student_promotions WHERE batch_id = ?');
    $studentCountStmt->execute([$batchId]);
    $studentCount = (int)$studentCountStmt->fetchColumn();

    $conn->commit();
    app_reply_redirect('success', 'Promotion batch created successfully with ' . $studentCount . ' students.', '../promotions?batch_id=' . $batchId);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Promotion batch creation error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to create promotion batch: ' . $e->getMessage(), '../promotions');
}
