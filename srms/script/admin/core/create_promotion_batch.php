<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');

if ($res !== '1' || !in_array((int)$level, [0, 1, 9], true)) {
    app_reply_redirect('danger', 'Unauthorized.', '../promotions');
}
app_require_permission('report.generate', '../promotions');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request method.', '../promotions');
}

$classId = trim((string)($_POST['class_id'] ?? ''));
$academicYear = trim((string)($_POST['academic_year'] ?? ''));
$promotionCycle = trim((string)($_POST['promotion_cycle'] ?? 'year_end'));
$notes = trim((string)($_POST['notes'] ?? ''));

if ($classId === '' || $academicYear === '') {
    app_reply_redirect('danger', 'Missing required fields.', '../promotions');
}

if (!preg_match('/^\d{4}(\/\d{4})?$/', $academicYear)) {
    app_reply_redirect('danger', 'Invalid academic year format.', '../promotions');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_promotion_workflow_schema($conn);
    $conn->beginTransaction();

    // Validate source class and get its grade.
    $stmt = $conn->prepare('SELECT id, grade FROM tbl_classes WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$classId]);
    $currentClass = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentClass) {
        throw new RuntimeException('Selected class was not found.');
    }
    $currentGrade = (int)($currentClass['grade'] ?? 0);

    // Check if batch already exists.
    $stmt = $conn->prepare('
        SELECT id FROM tbl_promotion_batches 
        WHERE class_id = ? AND academic_year = ? AND promotion_cycle = ?
        LIMIT 1
    ');
    $stmt->execute([(int)$classId, $academicYear, $promotionCycle]);
    if ($stmt->rowCount() > 0) {
        throw new RuntimeException('Promotion batch already exists for this class/year/cycle combination.');
    }

    // Get promotion rule for this grade.
    $rule = app_promotion_rule_for_grade($conn, $currentGrade);
    $needsHeadteacherReview = (bool)($rule['require_headteacher_approval'] ?? true);

    // Find next class.
    $nextGrade = $currentGrade + 1;
    $stmt = $conn->prepare('SELECT id FROM tbl_classes WHERE grade = ? LIMIT 1');
    $stmt->execute([$nextGrade]);
    $nextClassRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextClassId = $nextClassRow ? (int)$nextClassRow['id'] : (int)$classId;

    // Create promotion batch.
    $batchColumns = ['class_id', 'academic_year', 'promotion_cycle', 'status', 'created_by', 'notes'];
    $batchValues = [(int)$classId, $academicYear, $promotionCycle, 'pending', (int)$account_id, $notes];
    if (app_column_exists($conn, 'tbl_promotion_batches', 'review_state')) {
        $batchColumns[] = 'review_state';
        $batchValues[] = $needsHeadteacherReview ? 'pending_review' : 'ready_for_execution';
    }
    $stmt = $conn->prepare('INSERT INTO tbl_promotion_batches (' . implode(', ', $batchColumns) . ') VALUES (' . implode(', ', array_fill(0, count($batchColumns), '?')) . ')');
    $stmt->execute($batchValues);
    $batchId = $conn->lastInsertId();

    $studentStatusFilter = '';
    if (app_column_exists($conn, 'tbl_students', 'status')) {
        $statusType = '';
        if (DBDriver === 'pgsql') {
            $statusTypeStmt = $conn->prepare("SELECT data_type FROM information_schema.columns WHERE table_schema = 'public' AND table_name = 'tbl_students' AND column_name = 'status' LIMIT 1");
            $statusTypeStmt->execute();
            $statusType = strtolower(trim((string)$statusTypeStmt->fetchColumn()));
        } else {
            $statusTypeStmt = $conn->prepare("SELECT data_type FROM information_schema.columns WHERE table_schema = ? AND table_name = 'tbl_students' AND column_name = 'status' LIMIT 1");
            $statusTypeStmt->execute([DBName]);
            $statusType = strtolower(trim((string)$statusTypeStmt->fetchColumn()));
        }

        $studentStatusFilter = in_array($statusType, ['character varying', 'varchar', 'text', 'char'], true)
            ? " AND COALESCE(LOWER(TRIM(status)), '') IN ('1', 'active', 'enabled')"
            : ' AND COALESCE(status, 1) = 1';
    }

    // Generate student promotion records using schema-tolerant subqueries.
    $reportMeanExpr = '0';
    $reportFinalizedExpr = 'FALSE';
    if (app_table_exists($conn, 'tbl_report_cards')) {
        $reportMeanColumn = app_column_exists($conn, 'tbl_report_cards', 'mean_score') ? 'mean_score' : (app_column_exists($conn, 'tbl_report_cards', 'mean') ? 'mean' : '');
        if ($reportMeanColumn !== '') {
            $reportMeanExpr = 'COALESCE((SELECT r.' . $reportMeanColumn . ' FROM tbl_report_cards r WHERE r.student_id = st.id ORDER BY r.id DESC LIMIT 1), 0)';
        }

        if (app_column_exists($conn, 'tbl_report_cards', 'finalized')) {
            $reportFinalizedExpr = 'COALESCE((SELECT r.finalized FROM tbl_report_cards r WHERE r.student_id = st.id ORDER BY r.id DESC LIMIT 1), FALSE)';
        } else {
            $reportFinalizedExpr = 'CASE WHEN EXISTS (SELECT 1 FROM tbl_report_cards r WHERE r.student_id = st.id) THEN TRUE ELSE FALSE END';
        }
    }

    $feesBalanceExpr = '0';
    if (app_table_exists($conn, 'tbl_fees_charged') && app_table_exists($conn, 'tbl_fees_paid')) {
        $feesBalanceExpr = 'COALESCE((SELECT SUM(cf.amount) FROM tbl_fees_charged cf WHERE cf.student_id = st.id), 0) - COALESCE((SELECT SUM(cp.amount) FROM tbl_fees_paid cp WHERE cp.student_id = st.id), 0)';
    } elseif (app_table_exists($conn, 'tbl_invoices') && app_table_exists($conn, 'tbl_invoice_lines')) {
        $feesBalanceExpr = 'COALESCE((
            SELECT COALESCE(SUM(l.amount), 0)
            FROM tbl_invoices i
            INNER JOIN tbl_invoice_lines l ON l.invoice_id = i.id
            WHERE i.student_id = st.id AND i.status <> \'void\'
        ), 0)';

        if (app_table_exists($conn, 'tbl_payments')) {
            $feesBalanceExpr .= ' - COALESCE((
                SELECT COALESCE(SUM(p.amount), 0)
                FROM tbl_invoices i2
                INNER JOIN tbl_payments p ON p.invoice_id = i2.id
                WHERE i2.student_id = st.id AND i2.status <> \'void\'
            ), 0)';
        }
    }

    $stmt = $conn->prepare('
        SELECT
            st.id, st.fname, st.mname, st.lname,
            ' . $reportMeanExpr . ' AS mean_score,
            ' . $reportFinalizedExpr . ' AS report_finalized,
            ' . $feesBalanceExpr . ' AS fees_balance
        FROM tbl_students st
        WHERE st.class = ?' . $studentStatusFilter . '
    ');
    $stmt->execute([(int)$classId]);
    $studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($studentDetails)) {
        throw new RuntimeException('No active students found in this class.');
    }

    // Insert promotion records.
    $totalFeesBalance = 0.0;
    foreach ($studentDetails as $student) {
        $meanScore = (float)($student['mean_score'] ?? 0);
        $feesBalance = (float)($student['fees_balance'] ?? 0);
        $reportFinalized = (bool)($student['report_finalized'] ?? false);
        $meritGrade = $meanScore > 0 ? app_merit_grade_from_score($meanScore) : null;
        $feesCleared = $feesBalance <= 0;
        $totalFeesBalance += max(0, $feesBalance);
        $notesLine = [];

        // Determine promotion status using rule gates.
        $status = 'promoted';
        if ($meanScore < (float)$rule['min_score_for_promotion']) {
            $status = 'repeated';
        }
        if ((bool)$rule['require_fees_clearance'] && !$feesCleared) {
            $status = 'repeated';
        }
        if ((bool)$rule['require_report_finalization'] && !$reportFinalized) {
            $status = 'repeated';
        }

        if ($status === 'promoted' && $needsHeadteacherReview) {
            $status = 'conditional';
        }

        if ($status === 'repeated') {
            if ($meanScore < (float)$rule['min_score_for_promotion']) $notesLine[] = 'Below minimum score';
            if ((bool)$rule['require_fees_clearance'] && !$feesCleared) $notesLine[] = 'Fees not cleared';
            if ((bool)$rule['require_report_finalization'] && !$reportFinalized) $notesLine[] = 'Report not finalized';
        } elseif ($status === 'conditional') {
            $notesLine[] = 'Recommended for promotion pending review';
            if ($needsHeadteacherReview) {
                $notesLine[] = 'Awaiting headteacher review';
            }
        }

        $suggestedStatus = $status;
        $finalStatus = $status === 'conditional' ? 'promoted' : $status;

        $promotionColumns = [
            'batch_id', 'student_id', 'from_class', 'to_class', 'status', 'mean_score', 'merit_grade',
            'fees_balance', 'fees_cleared', 'report_card_finalized', 'notes', 'created_by'
        ];
        $promotionValues = [
            (int)$batchId,
            (string)$student['id'],
            (int)$classId,
            $status === 'promoted' || $status === 'conditional' ? $nextClassId : (int)$classId,
            $suggestedStatus,
            $meanScore,
            $meritGrade,
            $feesBalance,
            $feesCleared,
            $reportFinalized,
            implode('; ', $notesLine),
            (int)$account_id,
        ];

        if (app_column_exists($conn, 'tbl_student_promotions', 'suggested_status')) {
            $promotionColumns[] = 'suggested_status';
            $promotionValues[] = $suggestedStatus;
        }
        if (app_column_exists($conn, 'tbl_student_promotions', 'final_status')) {
            $promotionColumns[] = 'final_status';
            $promotionValues[] = $finalStatus;
        }

        $stmt = $conn->prepare('
            INSERT INTO tbl_student_promotions (' . implode(', ', $promotionColumns) . ')
            VALUES (' . implode(', ', array_fill(0, count($promotionColumns), '?')) . ')
        ');
        $stmt->execute($promotionValues);
    }

    $stmt = $conn->prepare('UPDATE tbl_promotion_batches SET total_fees_balance = ? WHERE id = ?');
    $stmt->execute([round($totalFeesBalance, 2), (int)$batchId]);

    // Log action.
    app_audit_log(
        $conn,
        'staff',
        (string)$account_id,
        'promotion.batch.create',
        'tbl_promotion_batches',
        (string)$batchId,
        ['class_id' => (int)$classId, 'academic_year' => $academicYear, 'promotion_cycle' => $promotionCycle]
    );

    $conn->commit();

    app_reply_redirect('success', 'Promotion batch created successfully with ' . count($studentDetails) . ' students.', '../promotions?batch_id=' . $batchId);

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Promotion batch creation error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to create promotion batch: ' . $e->getMessage(), '../promotions');
}
