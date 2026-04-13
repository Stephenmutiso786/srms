<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');
require_once('const/notify.php');

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
        app_reply_redirect('warning', 'This batch has already been processed.', '../promotions?batch_id=' . $batchId);
    }

    // Get students in batch
    $stmt = $conn->prepare('
        SELECT sp.*, st.id, st.fname, st.mname, st.lname, 
               concat_ws(\' \', st.fname, st.mname, st.lname) as student_name
        FROM tbl_student_promotions sp
        JOIN tbl_students st ON st.id = sp.student_id
        WHERE sp.batch_id = ?
    ');
    $stmt->execute([(int)$batchId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        app_reply_redirect('danger', 'No students found in batch.', '../promotions');
    }

    $promoted = 0;
    $repeated = 0;
    $certificates_generated = 0;

    // Process each student
    foreach ($students as $student) {
        if ($student['status'] === 'promoted') {
            // Update student's class
            $stmt = $conn->prepare('UPDATE tbl_students SET class = ? WHERE id = ?');
            $stmt->execute([(int)$student['to_class'], (string)$student['student_id']]);
            $promoted++;

            // Auto-generate certificate if this is a completion class (Grade 6 or 9)
            if ($student['mean_score'] >= 40.0) {
                $stmt = $conn->prepare('
                    SELECT grade FROM tbl_classes WHERE id = ? LIMIT 1
                ');
                $stmt->execute([(int)$student['to_class']]);
                $classData = $stmt->fetch(PDO::FETCH_ASSOC);
                $classGrade = (int)($classData['grade'] ?? 0);

                $certType = null;
                if ($classGrade === 6) {
                    $certType = 'primary_completion';
                } elseif ($classGrade === 9) {
                    $certType = 'junior_completion';
                }

                if ($certType) {
                    $serial = app_certificate_serial($certType, (string)$student['student_id']);
                    $code = app_certificate_code((string)$student['student_id']);
                    $payload = [
                        'student_id' => $student['student_id'],
                        'certificate_type' => $certType,
                        'serial' => $serial,
                    ];
                    $hash = app_certificate_hash($payload);

                    $stmt = $conn->prepare('
                        INSERT INTO tbl_certificates 
                        (student_id, class_id, certificate_type, certificate_category, title, serial_no, 
                         issue_date, status, mean_score, merit_grade, issued_by, verification_code, cert_hash)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        (string)$student['student_id'],
                        (int)$student['to_class'],
                        $certType,
                        $certType,
                        app_certificate_types()[$certType] ?? 'Certificate',
                        $serial,
                        date('Y-m-d'),
                        'issued',
                        $student['mean_score'],
                        $student['merit_grade'],
                        (int)$account_id,
                        $code,
                        $hash
                    ]);
                    $certificates_generated++;

                    // Mark as certificate generated
                    $stmt = $conn->prepare('
                        UPDATE tbl_student_promotions 
                        SET certificate_generated = TRUE 
                        WHERE id = ?
                    ');
                    $stmt->execute([(int)$student['id']]);
                }
            }
        } else {
            $repeated++;
        }
    }

    // Update batch status
    $stmt = $conn->prepare('
        UPDATE tbl_promotion_batches 
        SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP,
            students_promoted = ?, students_repeated = ?
        WHERE id = ?
    ');
    $stmt->execute(['approved', (int)$account_id, $promoted, $repeated, (int)$batchId]);

    // Send SMS to parents about promotion (if SMS wallet exists)
    if (app_table_exists($conn, 'tbl_sms_wallets')) {
        $promoted_students = array_filter($students, fn($s) => $s['status'] === 'promoted');
        foreach ($promoted_students as $student) {
            $stmt = $conn->prepare('
                SELECT phone FROM tbl_parents 
                WHERE id IN (SELECT parent_id FROM tbl_parent_students WHERE student_id = ?)
                LIMIT 1
            ');
            $stmt->execute([(string)$student['student_id']]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parent && !empty($parent['phone'])) {
                $message = 'Dear Parent, ' . $student['student_name'] . ' has been promoted to their next class. Congratulations! - ' . WBName;
                app_send_sms($conn, $parent['phone'], $message);
            }
        }
    }

    // Log action
    app_audit_log($conn, 'promotion.batch.approve', 'Approved promotion batch ' . $batchId . ': ' . $promoted . ' promoted, ' . $repeated . ' repeated', 'tbl_promotion_batches');

    $msg = 'Promotion approved successfully! ' . $promoted . ' students promoted, ' . $repeated . ' will repeat. ' . $certificates_generated . ' certificates generated.';
    app_reply_redirect('success', $msg, '../promotions');

} catch (Throwable $e) {
    error_log('Promotion approval error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to approve promotion: ' . $e->getMessage(), '../promotions');
}
