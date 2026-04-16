<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || !in_array((int)$level, [1, 9], true)) {
    app_reply_redirect('danger', 'Only the headteacher review step can update promotion decisions.', '../promotions');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request method.', '../promotions');
}

$batchId = trim((string)($_POST['batch_id'] ?? ''));
if ($batchId === '') {
    app_reply_redirect('danger', 'Missing batch ID.', '../promotions');
}

$finalStatuses = $_POST['final_status'] ?? [];
$reviewNotes = $_POST['review_notes'] ?? [];

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_promotion_workflow_schema($conn);
    $conn->beginTransaction();

    $stmt = $conn->prepare('SELECT * FROM tbl_promotion_batches WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new RuntimeException('Promotion batch not found.');
    }

    if ((string)($batch['status'] ?? 'pending') !== 'pending') {
        throw new RuntimeException('This promotion batch cannot be reviewed anymore.');
    }

    $stmt = $conn->prepare('SELECT * FROM tbl_student_promotions WHERE batch_id = ? ORDER BY id ASC');
    $stmt->execute([(int)$batchId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($students)) {
        throw new RuntimeException('No students found in the selected promotion batch.');
    }

    $allowedStatuses = ['promoted', 'repeated', 'exited', 'suspended'];
    $reviewedCount = 0;
    $overrideCount = 0;

    $updateStmt = $conn->prepare('UPDATE tbl_student_promotions
        SET final_status = ?, review_comment = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP, override_reason = ?
        WHERE id = ?');

    foreach ($students as $student) {
        $rowId = (int)$student['id'];
        $suggested = strtolower(trim((string)($student['suggested_status'] ?? $student['status'] ?? 'promoted')));
        $postedStatus = strtolower(trim((string)($finalStatuses[$rowId] ?? '')));
        if (!in_array($postedStatus, $allowedStatuses, true)) {
            $postedStatus = $suggested === 'conditional' ? 'promoted' : ($suggested !== '' ? $suggested : 'promoted');
        }
        if ($postedStatus === 'conditional') {
            $postedStatus = 'promoted';
        }

        $reviewComment = trim((string)($reviewNotes[$rowId] ?? ''));
        $overrideReason = '';
        if ($postedStatus !== $suggested) {
            $overrideCount++;
            $overrideReason = 'Overridden from ' . $suggested . ' to ' . $postedStatus;
            if ($reviewComment !== '') {
                $overrideReason .= '. ' . $reviewComment;
            }
        } elseif ($reviewComment !== '') {
            $overrideReason = $reviewComment;
        }

        $updateStmt->execute([
            $postedStatus,
            $reviewComment !== '' ? $reviewComment : null,
            (int)$account_id,
            $overrideReason !== '' ? $overrideReason : null,
            $rowId,
        ]);
        $reviewedCount++;
    }

    $stmt = $conn->prepare('UPDATE tbl_promotion_batches
        SET review_state = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP
        WHERE id = ?');
    $stmt->execute(['reviewed', (int)$account_id, (int)$batchId]);

    app_audit_log(
        $conn,
        'staff',
        (string)$account_id,
        'promotion.batch.review',
        'tbl_promotion_batches',
        (string)$batchId,
        ['reviewed_students' => $reviewedCount, 'overrides' => $overrideCount]
    );

    $conn->commit();

    app_reply_redirect('success', 'Promotion review saved. ' . $reviewedCount . ' student decisions recorded.', '../promotions?batch_id=' . $batchId);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Promotion review error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to save promotion review: ' . $e->getMessage(), '../promotions?batch_id=' . $batchId);
}
