<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');
require_once('const/notify.php');

$isSuperAdmin = !empty($super_admin);

if ($res !== '1' || (!in_array((int)$level, [0, 1, 9], true) && !$isSuperAdmin)) {
    app_reply_redirect('danger', 'Unauthorized.', '../promotions');
}
if (!$isSuperAdmin) {
    app_reply_redirect('danger', 'Only the Super Admin can complete the final promotion step.', '../promotions');
}

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
    app_ensure_promotion_workflow_schema($conn);
    app_ensure_student_alumni_schema($conn);
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

    $reviewState = strtolower(trim((string)($batch['review_state'] ?? 'pending_review')));
    if ($reviewState === 'pending_review') {
        throw new RuntimeException('This batch must be reviewed before final execution.');
    }

    // Get students in batch.
    $stmt = $conn->prepare('
        SELECT sp.*, st.id, st.fname, st.mname, st.lname,
               concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name,
               c_from.name AS from_class_name,
               c_to.name AS to_class_name,
               c_from.grade AS from_grade,
               c_to.grade AS to_grade,
               (SELECT r.grade FROM tbl_report_cards r WHERE r.student_id = st.id ORDER BY r.id DESC LIMIT 1) AS latest_report_grade
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
    $alumni = 0;
    $certificates_generated = 0;

    $today = date('Y-m-d');
    $historyColumns = ['student_id', 'batch_id', 'from_class', 'to_class', 'academic_year', 'promotion_cycle', 'decision_status', 'mean_score', 'merit_grade', 'decision_role', 'decided_by', 'notes'];

    // Process each student.
    foreach ($students as $student) {
        $effectiveMeritGrade = trim((string)($student['latest_report_grade'] ?? ''));
        if ($effectiveMeritGrade === '') {
            $effectiveMeritGrade = trim((string)($student['merit_grade'] ?? ''));
        }
        $decisionStatus = strtolower(trim((string)($student['final_status'] ?? $student['status'])));
        if (!in_array($decisionStatus, ['promoted', 'repeated', 'alumni', 'exited', 'suspended'], true)) {
            throw new RuntimeException('Student #'.(string)$student['student_id'].' still has an unresolved promotion decision.');
        }

        if ($decisionStatus === 'promoted') {
            $targetClassId = (int)($student['to_class'] ?? 0);
            if ($targetClassId < 1) {
                throw new RuntimeException('Student #'.(string)$student['student_id'].' has no valid promotion target class.');
            }
            $targetOccupancy = app_active_student_count_in_class($conn, $targetClassId);
            if ($targetOccupancy > 0) {
                throw new RuntimeException('Cannot promote into ' . (string)($student['to_class_name'] ?? 'the target class') . ' because it already has ' . $targetOccupancy . ' active student(s). Clear that class first or mark the learner as a repeater.');
            }
        }

        if ($decisionStatus === 'promoted') {
            // Update student's class.
            if (app_column_exists($conn, 'tbl_students', 'class_id')) {
                $stmt = $conn->prepare('UPDATE tbl_students SET class = ?, class_id = ? WHERE id = ?');
                $stmt->execute([(int)$student['to_class'], (int)$student['to_class'], (string)$student['student_id']]);
            } else {
                $stmt = $conn->prepare('UPDATE tbl_students SET class = ? WHERE id = ?');
                $stmt->execute([(int)$student['to_class'], (string)$student['student_id']]);
            }
            app_sync_student_finance_class_links($conn, (string)$student['student_id']);
            $promoted++;

            // Auto-generate completion certificates based on the completed class grade.
            $completedGrade = app_effective_grade_level((string)($student['from_class_name'] ?? ''), $student['from_grade'] ?? null);
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
                        $effectiveMeritGrade !== '' ? $effectiveMeritGrade : null,
                        (int)$account_id,
                        $code,
                        $hash
                    ]);
                    $newCertificateId = (int)$conn->lastInsertId();
                    app_data_camp_store_record($conn, [
                        'module_key' => 'certificates',
                        'record_type' => 'certificate',
                        'entity_table' => 'tbl_certificates',
                        'entity_id' => (string)$newCertificateId,
                        'title' => app_certificate_types()[$certType] ?? 'Certificate',
                        'description' => 'Auto-generated promotion certificate retained for future reference',
                        'academic_year' => (string)($batch['academic_year'] ?? ''),
                        'class_id' => (int)$student['from_class'],
                        'student_id' => (string)$student['student_id'],
                        'owner_portal' => 'student,parent,teacher,admin',
                        'source_url' => 'verify_certificate?code=' . $code,
                        'mime_type' => 'application/pdf',
                        'status' => 'retained',
                        'source_key' => 'certificate:' . $newCertificateId,
                        'created_by' => (int)$account_id,
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

            $historyValues = [
                (string)$student['student_id'],
                (int)$batchId,
                (int)$student['from_class'],
                (int)$student['to_class'],
                (string)($batch['academic_year'] ?? ''),
                (string)($batch['promotion_cycle'] ?? ''),
                'promoted',
                $student['mean_score'],
                $effectiveMeritGrade !== '' ? $effectiveMeritGrade : null,
                'admin_execution',
                (int)$account_id,
                $student['review_comment'] ?? $student['notes'] ?? null,
            ];
            $stmt = $conn->prepare('INSERT INTO tbl_student_class_history (' . implode(', ', $historyColumns) . ') VALUES (' . implode(', ', array_fill(0, count($historyColumns), '?')) . ')');
            $stmt->execute($historyValues);
            continue;
        }

        if ($decisionStatus === 'alumni') {
            $alumniNotes = $student['review_comment'] ?? $student['notes'] ?? 'Completed Grade ' . app_promotion_terminal_grade_level();
            $stmt = $conn->prepare('UPDATE tbl_students SET is_alumni = 1, alumni_year = ?, alumni_at = CURRENT_TIMESTAMP, alumni_notes = ?, status = 0 WHERE id = ?');
            $stmt->execute([
                (string)($batch['academic_year'] ?? ''),
                $alumniNotes,
                (string)$student['student_id'],
            ]);
            app_sync_student_finance_class_links($conn, (string)$student['student_id']);
            $alumni++;

            try {
                app_data_camp_store_record($conn, [
                    'module_key' => 'alumni',
                    'record_type' => 'alumni_student',
                    'entity_table' => 'tbl_students',
                    'entity_id' => (string)$student['student_id'],
                    'title' => trim((string)($student['student_name'] ?? ('Student ' . $student['student_id']))) . ' - Alumni',
                    'description' => 'Learner completed school and was retained in alumni records',
                    'academic_year' => (string)($batch['academic_year'] ?? ''),
                    'class_id' => (int)$student['from_class'],
                    'student_id' => (string)$student['student_id'],
                    'owner_portal' => 'admin,headteacher,deputy_headteacher',
                    'status' => 'retained',
                    'source_key' => 'alumni:' . (string)$student['student_id'],
                    'created_by' => (int)$account_id,
                    'payload_json' => [
                        'student_id' => (string)$student['student_id'],
                        'student_name' => (string)($student['student_name'] ?? ''),
                        'from_class' => (int)$student['from_class'],
                        'from_class_name' => (string)($student['from_class_name'] ?? ''),
                        'academic_year' => (string)($batch['academic_year'] ?? ''),
                        'decision_status' => 'alumni',
                        'mean_score' => $student['mean_score'],
                        'grade' => $effectiveMeritGrade,
                        'notes' => $alumniNotes,
                    ],
                ]);
            } catch (Throwable $archiveError) {
                error_log('[approve_promotion/alumni_data_camp] ' . $archiveError->getMessage());
            }

            $completedGrade = app_effective_grade_level((string)($student['from_class_name'] ?? ''), $student['from_grade'] ?? null);
            if ($completedGrade === app_promotion_terminal_grade_level()) {
                $certType = 'junior_completion';
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
                        $effectiveMeritGrade !== '' ? $effectiveMeritGrade : null,
                        (int)$account_id,
                        $code,
                        $hash
                    ]);
                    $newCertificateId = (int)$conn->lastInsertId();
                    app_data_camp_store_record($conn, [
                        'module_key' => 'certificates',
                        'record_type' => 'certificate',
                        'entity_table' => 'tbl_certificates',
                        'entity_id' => (string)$newCertificateId,
                        'title' => app_certificate_types()[$certType] ?? 'Certificate',
                        'description' => 'Auto-generated alumni completion certificate retained for future reference',
                        'academic_year' => (string)($batch['academic_year'] ?? ''),
                        'class_id' => (int)$student['from_class'],
                        'student_id' => (string)$student['student_id'],
                        'owner_portal' => 'student,parent,teacher,admin',
                        'source_url' => 'verify_certificate?code=' . $code,
                        'mime_type' => 'application/pdf',
                        'status' => 'retained',
                        'source_key' => 'certificate:' . $newCertificateId,
                        'created_by' => (int)$account_id,
                    ]);
                    $certificates_generated++;
                }

                $stmt = $conn->prepare('UPDATE tbl_student_promotions SET certificate_generated = TRUE WHERE id = ?');
                $stmt->execute([(int)$student['id']]);
            }

            $historyValues = [
                (string)$student['student_id'],
                (int)$batchId,
                (int)$student['from_class'],
                (int)$student['from_class'],
                (string)($batch['academic_year'] ?? ''),
                (string)($batch['promotion_cycle'] ?? ''),
                'alumni',
                $student['mean_score'],
                $effectiveMeritGrade !== '' ? $effectiveMeritGrade : null,
                'admin_execution',
                (int)$account_id,
                $student['review_comment'] ?? $student['notes'] ?? null,
            ];
            $stmt = $conn->prepare('INSERT INTO tbl_student_class_history (' . implode(', ', $historyColumns) . ') VALUES (' . implode(', ', array_fill(0, count($historyColumns), '?')) . ')');
            $stmt->execute($historyValues);
            continue;
        }

        if ($decisionStatus === 'repeated' || $decisionStatus === 'exited' || $decisionStatus === 'suspended') {
            if ($decisionStatus === 'repeated') {
                $repeated++;
            } else {
                $exited++;
            }
            $historyValues = [
                (string)$student['student_id'],
                (int)$batchId,
                (int)$student['from_class'],
                (int)$student['from_class'],
                (string)($batch['academic_year'] ?? ''),
                (string)($batch['promotion_cycle'] ?? ''),
                $decisionStatus,
                $student['mean_score'],
                $effectiveMeritGrade !== '' ? $effectiveMeritGrade : null,
                'admin_execution',
                (int)$account_id,
                $student['review_comment'] ?? $student['notes'] ?? null,
            ];
            $stmt = $conn->prepare('INSERT INTO tbl_student_class_history (' . implode(', ', $historyColumns) . ') VALUES (' . implode(', ', array_fill(0, count($historyColumns), '?')) . ')');
            $stmt->execute($historyValues);
            continue;
        }
    }

    // Update batch status.
    $stmt = $conn->prepare('
        UPDATE tbl_promotion_batches 
        SET status = ?, review_state = ?, reviewed_by = COALESCE(reviewed_by, ?), reviewed_at = COALESCE(reviewed_at, CURRENT_TIMESTAMP),
            executed_by = ?, executed_at = CURRENT_TIMESTAMP, approved_by = ?, approved_at = CURRENT_TIMESTAMP,
            students_promoted = ?, students_repeated = ?, students_exited = ?
        WHERE id = ?
    ');
    $stmt->execute(['approved', 'executed', (int)$account_id, (int)$account_id, (int)$account_id, $promoted, $repeated, $exited + $alumni, (int)$batchId]);

    // Send SMS to parents about promotion (if SMS wallet exists)
    if (app_table_exists($conn, 'tbl_sms_wallets')) {
        $promoted_students = array_filter($students, fn($s) => strtolower(trim((string)($s['final_status'] ?? $s['status']))) === 'promoted');
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
        ['promoted' => $promoted, 'repeated' => $repeated, 'alumni' => $alumni, 'exited' => $exited, 'certificates_generated' => $certificates_generated, 'review_state' => $reviewState]
    );

    $conn->commit();

    $msg = 'Promotion approved successfully! ' . $promoted . ' students promoted, ' . $repeated . ' will repeat, ' . $alumni . ' moved to alumni, and ' . $exited . ' were marked as exited/suspended. ' . $certificates_generated . ' certificates generated.';
    app_reply_redirect('success', $msg, '../promotions');

} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('Promotion approval error: ' . $e->getMessage());
    app_reply_redirect('danger', 'Failed to approve promotion: ' . $e->getMessage(), '../promotions');
}
