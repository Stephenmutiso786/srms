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
    app_ensure_certificates_table($conn);
    $conn->beginTransaction();

    // Lock batch row to avoid concurrent approvals.
    $stmt = $conn->prepare('SELECT * FROM tbl_promotion_batches WHERE id = ? LIMIT 1 FOR UPDATE');
    $stmt->execute([(int)$batchId]);
    $batch = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$batch) {
        throw new RuntimeException('Promotion batch not found.');
    }

    if ($batch['status'] !== 'pending') {
        throw new RuntimeException('This batch has already been processed.');
    }

    // Get students in batch.
    $stmt = $conn->prepare('
        SELECT sp.*, st.id, st.fname, st.mname, st.lname,
               concat_ws(\' \, st.fname, st.mname, st.lname) AS student_name,
               c_from.grade AS from_grade,
               c_to.grade AS to_grade
        FROM tbl_student_promotions sp
        JOIN tbl_students st ON st.id = sp.student_id
        LEFT JOIN tbl_classes c_from ON c_from.id = sp.from_class
        LEFT JOIN tbl_classes c_to ON c_to.id = sp.to_class
        WHERE sp.batch_id = ?
    ');
    $stmt->execute([(int)$batchId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        throw new RuntimeException('No students found in batch.');
    }

    $promoted = 0;
    $repeated = 0;
    $exited = 0;
    $certificates_generated = 0;

    $today = date('Y-m-d');

    // Process each student.
    foreach ($students as $student) {
        if ($student['status'] === 'promoted') {
            // Update student's class.
            $stmt = $conn->prepare('UPDATE tbl_students SET class = ? WHERE id = ?');
            $stmt->execute([(int)$student['to_class'], (string)$student['student_id']]);
            $promoted++;

            // Auto-generate completion certificates based on the completed class grade.
            $completedGrade = (int)($student['from_grade'] ?? 0);
            $certType = null;
            if ($completedGrade === 6) {
                $certType = 'primary_completion';
            } elseif ($completedGrade === 9) {
                $certType = 'junior_completion';
            }

            if ($certType) {
                $stmt = $conn->prepare('
                    SELECT id FROM tbl_certificates
                    WHERE student_id = ? AND certificate_type = ? AND class_id = ?
                    LIMIT 1
                ');
                $stmt->execute([(string)$student['student_id'], $certType, (int)$student['from_class']]);
                $existingCertId = (int)($stmt->fetchColumn() ?: 0);

                if ($existingCertId === 0) {
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
                        (int)$student['from_class'],
                        $certType,
                        $certType,
                        app_certificate_types()[$certType] ?? 'Certificate',
                        $serial,
                        $today,
                        'issued',
                        $student['mean_score'],
                        $student['merit_grade'],
                        (int)$account_id,
                        $code,
                        $hash
                    ]);
                    $certificates_generated++;
                }

                $stmt = $conn->prepare('
                    UPDATE tbl_student_promotions
                    SET certificate_generated = TRUE
                    WHERE id = ?
                ');
                $stmt->execute([(int)$student['id']]);
            }

            continue;
        }

        if ($student['status'] === 'repeated') {
            $repeated++;
            continue;
        }

        if ($student['status'] === 'exited') {
            $exited++;
            continue;
        }
    }

    // Update batch status.
    $stmt = $conn->prepare('
        UPDATE tbl_promotion_batches 
        SET status = ?, approved_by = ?, approved_at = CURRENT_TIMESTAMP,
            students_promoted = ?, students_repeated = ?, students_exited = ?
        WHERE id = ?
    ');
    $stmt->execute(['approved', (int)$account_id, $promoted, $repeated, $exited, (int)$batchId]);

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

    // Log action.
    app_audit_log(
        $conn,
        'staff',
        (string)$account_id,
        'promotion.batch.approve',
        'tbl_promotion_batches',
        (string)$batchId,
        ['promoted' => $promoted, 'repeated' => $repeated, 'exited' => $exited, 'certificates_generated' => $certificates_generated]
    );

    $conn->commit();

    $msg = 'Promotion approved successfully! ' . $promoted . ' students promoted, ' . $repeated . ' will repeat. ' . $certificates_generated . ' certificates generated.';
    app_reply_redirect('success', $msg, '../promotions');

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Promotion approval error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to approve promotion: ' . $e->getMessage(), '../promotions');
}
