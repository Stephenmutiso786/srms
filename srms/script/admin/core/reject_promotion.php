<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [0, 1])) {
    app_reply_redirect('danger', 'Unauthorized.', '../promotions');
}
app_require_permission('report.generate', '../promotions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request method.', '../promotions');
}

$batchId = trim((string)($_POST['batch_id'] ?? ''));
if ($batchId === '') {
    app_reply_redirect('danger', 'Missing batch ID.', '../promotions');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get batch details
    $stmt = $conn->prepare('SELECT * FROM tbl_promotion_batches WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        app_reply_redirect('danger', 'Promotion batch not found.', '../promotions');
    }

    if ($batch['status'] !== 'pending') {
        app_reply_redirect('warning', 'This batch cannot be rejected (already processed).', '../promotions?batch_id=' . $batchId);
    }

    // Update batch status to rejected
    $stmt = $conn->prepare('
        UPDATE tbl_promotion_batches 
        SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ');
    $stmt->execute(['rejected', (int)$account_id, (int)$batchId]);

    // Log action
    app_audit_log($conn, 'promotion.batch.reject', 'Rejected promotion batch ' . $batchId, 'tbl_promotion_batches');

    app_reply_redirect('success', 'Promotion batch rejected. No student classes were updated.', '../promotions');

} catch (Throwable $e) {
    error_log('Promotion rejection error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to reject promotion: ' . $e->getMessage(), '../promotions');
}
