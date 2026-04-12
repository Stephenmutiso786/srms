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

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Check if batch already exists
    $stmt = $conn->prepare('
        SELECT id FROM tbl_promotion_batches 
        WHERE class_id = ? AND academic_year = ? 
        LIMIT 1
    ');
    $stmt->execute([(int)$classId, $academicYear]);
    if ($stmt->rowCount() > 0) {
        app_reply_redirect('warning', 'Promotion batch already exists for this class/year combination.', '../promotions');
    }

    // Get students in the class
    $stmt = $conn->prepare('SELECT id FROM tbl_students WHERE class = ? AND status = \'active\'');
    $stmt->execute([(int)$classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        app_reply_redirect('danger', 'No students found in this class.', '../promotions');
    }

    // Get next class level
    $stmt = $conn->prepare('SELECT grade FROM tbl_classes WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$classId]);
    $currentClass = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentGrade = (int)($currentClass['grade'] ?? 0);
    $nextGrade = $currentGrade + 1;

    // Get next class
    $stmt = $conn->prepare('SELECT id FROM tbl_classes WHERE grade = ? LIMIT 1');
    $stmt->execute([$nextGrade]);
    $nextClassRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextClassId = $nextClassRow ? (int)$nextClassRow['id'] : (int)$classId;

    // Create promotion batch
    $stmt = $conn->prepare('
        INSERT INTO tbl_promotion_batches (class_id, academic_year, promotion_cycle, status, created_by, notes)
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([(int)$classId, $academicYear, $promotionCycle, 'pending', (int)$account_id, $notes]);
    $batchId = $conn->lastInsertId();

    // Generate students promotion records
    $stmt = $conn->prepare('
        SELECT 
            st.id, st.fname, st.mname, st.lname,
            COALESCE(rc.mean_score, 0) as mean_score,
            COALESCE(fc.balance, 0) as fees_balance,
            COALESCE(rc.finalized, FALSE) as report_finalized
        FROM tbl_students st
        LEFT JOIN tbl_report_cards rc ON rc.student_id = st.id
        LEFT JOIN (
            SELECT student_id, 
                   (COALESCE(SUM(cf.amount), 0) - COALESCE(SUM(cp.amount), 0)) as balance
            FROM tbl_students
            LEFT JOIN tbl_fees_charged cf ON cf.student_id = id
            LEFT JOIN tbl_fees_paid cp ON cp.student_id = id
            GROUP BY student_id
        ) fc ON fc.student_id = st.id
        WHERE st.class = ? AND st.status = \'active\'
    ');
    $stmt->execute([(int)$classId]);
    $studentDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Insert promotion records
    foreach ($studentDetails as $student) {
        $meanScore = (float)($student['mean_score'] ?? 0);
        $feesBalance = (float)($student['fees_balance'] ?? 0);
        $reportFinalized = (bool)($student['report_finalized'] ?? false);
        $merit_grade = $meanScore > 0 ? app_merit_grade_from_score($meanScore) : null;

        // Determine promotion status based on rules
        $status = 'promoted';
        if ($meanScore < 40.0) {
            $status = 'repeated';
        }

        $stmt = $conn->prepare('
            INSERT INTO tbl_student_promotions 
            (batch_id, student_id, from_class, to_class, status, mean_score, merit_grade, 
             fees_balance, fees_cleared, report_card_finalized, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            (int)$batchId,
            (int)$student['id'],
            (int)$classId,
            $status === 'promoted' ? $nextClassId : (int)$classId,
            $status,
            $meanScore,
            $merit_grade,
            $feesBalance,
            $feesBalance <= 0,
            $reportFinalized,
            (int)$account_id
        ]);
    }

    // Log action
    app_audit_log($conn, 'promotion.batch.create', 'Created promotion batch ' . $batchId  . ' for class ' . $classId, 'tbl_promotion_batches');

    app_reply_redirect('success', 'Promotion batch created successfully with ' . count($studentDetails) . ' students.', '../promotions?batch_id=' . $batchId);

} catch (Throwable $e) {
    error_log('Promotion batch creation error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to create promotion batch: ' . $e->getMessage(), '../promotions');
}
