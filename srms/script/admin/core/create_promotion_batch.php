<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');

if ($res !== '1' || !in_array((int)$level, [0, 1])) {
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

    // Get students in the class.
    $stmt = $conn->prepare('SELECT id FROM tbl_students WHERE class = ? AND status = \'active\'');
    $stmt->execute([(int)$classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        throw new RuntimeException('No active students found in this class.');
    }

    // Get promotion rule for this grade.
    $rule = [
        'min_score_for_promotion' => 40.0,
        'require_fees_clearance' => true,
        'require_report_finalization' => true,
    ];
    if (app_table_exists($conn, 'tbl_promotion_rules')) {
        $stmt = $conn->prepare('
            SELECT min_score_for_promotion, require_fees_clearance, require_report_finalization
            FROM tbl_promotion_rules
            WHERE school_id IS NULL AND grade_level = ?
            LIMIT 1
        ');
        $stmt->execute([$currentGrade]);
        $ruleRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ruleRow) {
            $rule['min_score_for_promotion'] = (float)$ruleRow['min_score_for_promotion'];
            $rule['require_fees_clearance'] = (bool)$ruleRow['require_fees_clearance'];
            $rule['require_report_finalization'] = (bool)$ruleRow['require_report_finalization'];
        }
    }

    // Find next class.
    $nextGrade = $currentGrade + 1;
    $stmt = $conn->prepare('SELECT id FROM tbl_classes WHERE grade = ? LIMIT 1');
    $stmt->execute([$nextGrade]);
    $nextClassRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextClassId = $nextClassRow ? (int)$nextClassRow['id'] : (int)$classId;

    // Create promotion batch.
    $stmt = $conn->prepare('
        INSERT INTO tbl_promotion_batches (class_id, academic_year, promotion_cycle, status, created_by, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([(int)$classId, $academicYear, $promotionCycle, 'pending', (int)$account_id, $notes]);
    $batchId = $conn->lastInsertId();

    // Generate students promotion records.
    $reportJoin = '';
    $reportFields = '0::DECIMAL(5,2) AS mean_score, FALSE AS report_finalized';
    if (app_table_exists($conn, 'tbl_report_cards')) {
        $reportJoin = '
        LEFT JOIN LATERAL (
            SELECT COALESCE(r.mean_score, 0) AS mean_score, COALESCE(r.finalized, FALSE) AS finalized
            FROM tbl_report_cards r
            WHERE r.student_id = st.id
            ORDER BY r.id DESC
            LIMIT 1
        ) rc ON TRUE';
        $reportFields = 'COALESCE(rc.mean_score, 0) AS mean_score, COALESCE(rc.finalized, FALSE) AS report_finalized';
    }

    $feesJoin = '';
    $feesField = '0::DECIMAL(10,2) AS fees_balance';
    if (app_table_exists($conn, 'tbl_fees_charged') && app_table_exists($conn, 'tbl_fees_paid')) {
        $feesJoin = '
        LEFT JOIN LATERAL (
            SELECT
                COALESCE((SELECT SUM(cf.amount) FROM tbl_fees_charged cf WHERE cf.student_id = st.id), 0)
                -
                COALESCE((SELECT SUM(cp.amount) FROM tbl_fees_paid cp WHERE cp.student_id = st.id), 0)
                AS balance
        ) fc ON TRUE';
        $feesField = 'COALESCE(fc.balance, 0) AS fees_balance';
    }

    $stmt = $conn->prepare('
        SELECT
            st.id, st.fname, st.mname, st.lname,
            ' . $reportFields . ',
            ' . $feesField . '
        FROM tbl_students st
        ' . $reportJoin . '
        ' . $feesJoin . '
        WHERE st.class = ? AND st.status = \'active\'
    ');
    $stmt->execute([(int)$classId]);
    $studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Insert promotion records.
    $totalFeesBalance = 0.0;
    foreach ($studentDetails as $student) {
        $meanScore = (float)($student['mean_score'] ?? 0);
        $feesBalance = (float)($student['fees_balance'] ?? 0);
        $reportFinalized = (bool)($student['report_finalized'] ?? false);
        $meritGrade = $meanScore > 0 ? app_merit_grade_from_score($meanScore) : null;
        $feesCleared = $feesBalance <= 0;
        $totalFeesBalance += max(0, $feesBalance);

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

        $notesLine = [];
        if ($status === 'repeated') {
            if ($meanScore < (float)$rule['min_score_for_promotion']) $notesLine[] = 'Below minimum score';
            if ((bool)$rule['require_fees_clearance'] && !$feesCleared) $notesLine[] = 'Fees not cleared';
            if ((bool)$rule['require_report_finalization'] && !$reportFinalized) $notesLine[] = 'Report not finalized';
        }

        $stmt = $conn->prepare('
            INSERT INTO tbl_student_promotions 
            (batch_id, student_id, from_class, to_class, status, mean_score, merit_grade, 
             fees_balance, fees_cleared, report_card_finalized, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            (int)$batchId,
            (string)$student['id'],
            (int)$classId,
            $status === 'promoted' ? $nextClassId : (int)$classId,
            $status,
            $meanScore,
            $meritGrade,
            $feesBalance,
            $feesCleared,
            $reportFinalized,
            implode('; ', $notesLine),
            (int)$account_id
        ]);
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
