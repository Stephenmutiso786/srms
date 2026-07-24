<?php
require_once(__DIR__ . '/../db/config.php');
require_once(__DIR__ . '/report_engine.php');
require_once(__DIR__ . '/report_pdf_template.php');
require_once(__DIR__ . '/notify.php');
require_once(__DIR__ . '/../tcpdf/tcpdf.php');

function app_results_competency_summary(PDO $conn, string $studentId, int $classId, int $termId): array
{
    if (!app_table_exists($conn, 'tbl_cbe_assessments')) {
        return [];
    }

    $hasMarks = app_column_exists($conn, 'tbl_cbe_assessments', 'marks');
    $selectScore = $hasMarks ? 'AVG(COALESCE(marks,0))' : "AVG(CASE UPPER(level) WHEN 'EE' THEN 85 WHEN 'ME' THEN 70 WHEN 'AE' THEN 50 WHEN 'BE' THEN 30 ELSE 0 END)";

    $stmt = $conn->prepare("SELECT learning_area, $selectScore AS score
        FROM tbl_cbe_assessments
        WHERE student_id = ? AND class_id = ? AND term_id = ?
        GROUP BY learning_area
        ORDER BY learning_area ASC
        LIMIT 3");
    $stmt->execute([$studentId, $classId, $termId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $score = (float)($row['score'] ?? 0);
        $label = 'Needs Support';
        if ($score >= 80) {
            $label = 'Excellent';
        } elseif ($score >= 70) {
            $label = 'Very Good';
        } elseif ($score >= 60) {
            $label = 'Good';
        }
        $rows[] = [
            'name' => (string)($row['learning_area'] ?? 'Competency'),
            'label' => $label,
        ];
    }

    return $rows;
}

function app_results_status_from_mean(float $mean, string $className, string $termName = ''): array
{
    $normalizedTerm = strtoupper(trim($termName));
    $isFinalTerm = strpos($normalizedTerm, 'TERM THREE') !== false || strpos($normalizedTerm, 'TERM 3') !== false || strpos($normalizedTerm, 'THIRD TERM') !== false;

    if (!$isFinalTerm) {
        if ($mean >= 40.0) {
            return ['status' => 'RESULT RELEASED', 'recommendation' => ''];
        }
        return ['status' => 'RESULT RELEASED', 'recommendation' => ''];
    }

    if ($mean >= 40.0) {
        if (stripos($className, '6') !== false) {
            return ['status' => 'PROMOTED to JSS', 'recommendation' => ''];
        }
        return ['status' => 'PROMOTED', 'recommendation' => ''];
    }

    return ['status' => 'NOT PROMOTED', 'recommendation' => 'Recommendation: Repeat ' . $className];
}

function app_results_sms_message(array $ctx): string
{
    $lines = [];
    $lines[] = $ctx['school_name'];
    $lines[] = 'Student: ' . $ctx['student_name'];
    $lines[] = 'Class: ' . $ctx['class_name'];
    $lines[] = 'CBE Mean: ' . number_format((float)$ctx['cbe_mean'], 2);
    $lines[] = 'Overall Grade: ' . $ctx['grade'];

    if (!empty($ctx['competencies'])) {
        $lines[] = 'Competencies:';
        foreach ($ctx['competencies'] as $row) {
            $lines[] = $row['name'] . ': ' . $row['label'];
        }
    }

    $lines[] = 'Status: ' . $ctx['status'];
    if ($ctx['recommendation'] !== '') {
        $lines[] = $ctx['recommendation'];
    }
    $portalUrl = isset($ctx['portal_url']) ? (string)$ctx['portal_url'] : '';
    if ($portalUrl !== '') {
        $lines[] = 'Portal: ' . $portalUrl;
    }

    $msg = implode("\n", $lines);
    if (strlen($msg) > 320) {
        $msg = implode("\n", [
            $ctx['school_name'],
            'Student: ' . $ctx['student_name'],
            'Class: ' . $ctx['class_name'],
            'CBE Mean: ' . number_format((float)$ctx['cbe_mean'], 2),
            'Grade: ' . $ctx['grade'],
            'Status: ' . $ctx['status'],
            'Check portal/email for full details.'
        ]);
    }

    return $msg;
}

function app_results_whatsapp_message(array $ctx): string
{
    $headteacherTitle = trim((string)($ctx['headteacher_title'] ?? 'Headteacher'));
    $lines = [];
    $lines[] = $ctx['school_name'];
    $lines[] = 'Official Report Card Notice';
    $lines[] = 'Dear Parent,';
    $lines[] = 'Student: ' . $ctx['student_name'];
    $lines[] = 'Admission No: ' . $ctx['school_id'];
    $lines[] = 'Class: ' . $ctx['class_name'];
    $lines[] = 'Term: ' . $ctx['term_name'];
    $lines[] = 'CBE Mean: ' . number_format((float)$ctx['cbe_mean'], 2);
    $lines[] = 'Overall Grade: ' . $ctx['grade'];
    $lines[] = 'Status: ' . $ctx['status'];
    if ($ctx['recommendation'] !== '') {
        $lines[] = $ctx['recommendation'];
    }
    $lines[] = 'Please find the attached official report card PDF.';
    $lines[] = 'Regards,';
    $lines[] = $headteacherTitle;
    $lines[] = $ctx['school_name'];

    return implode("\n", $lines);
}

function app_results_email_html(array $ctx): string
{
    $headteacherTitle = trim((string)($ctx['headteacher_title'] ?? 'Headteacher'));
    $competencyHtml = '';
    if (!empty($ctx['competencies'])) {
        $competencyHtml .= '<p><strong>CBE Competencies:</strong></p><ul>';
        foreach ($ctx['competencies'] as $row) {
            $competencyHtml .= '<li>' . htmlspecialchars($row['name']) . ': ' . htmlspecialchars($row['label']) . '</li>';
        }
        $competencyHtml .= '</ul>';
    }

    $recommendationHtml = $ctx['recommendation'] !== ''
        ? '<p>' . htmlspecialchars($ctx['recommendation']) . '</p>'
        : '';

    $portalHtml = $ctx['portal_url'] !== ''
        ? '<p>Result link: <a href="' . htmlspecialchars($ctx['portal_url']) . '">' . htmlspecialchars($ctx['portal_url']) . '</a></p>'
        : '';

    return '<p>Dear Parent,</p>'
        . '<p>We are pleased to share the academic results for your child.</p>'
        . '<p><strong>Student Details:</strong><br>'
        . 'Name: ' . htmlspecialchars($ctx['student_name']) . '<br>'
        . 'Class: ' . htmlspecialchars($ctx['class_name']) . '<br>'
        . 'Admission No: ' . htmlspecialchars($ctx['school_id']) . '</p>'
        . '<p><strong>Academic Performance:</strong><br>'
        . 'CBE Mean Points: ' . number_format((float)$ctx['cbe_mean'], 2) . '<br>'
        . 'Overall Grade: ' . htmlspecialchars($ctx['grade']) . '</p>'
        . $competencyHtml
        . '<p><strong>Final Decision:</strong><br>' . htmlspecialchars($ctx['status']) . '</p>'
        . $recommendationHtml
        . $portalHtml
        . '<p>Attachments:<br>1. Report Card PDF<br>2. Progress Summary</p>'
        . '<p>Regards,<br>' . htmlspecialchars($headteacherTitle) . '<br>' . htmlspecialchars($ctx['school_name']) . '</p>';
}

function app_results_temp_report_pdf(PDO $conn, array $ctx): ?array
{
    try {
        $tmpFile = tempnam(sys_get_temp_dir(), 'srms_report_');
        if ($tmpFile === false) {
            return null;
        }
        $tmpPath = $tmpFile . '.pdf';
        @rename($tmpFile, $tmpPath);

        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        app_output_single_page_report_pdf($conn, $pdf, [
            'student_id' => $ctx['student_id'],
            'student_name' => $ctx['student_name'],
            'school_id' => $ctx['school_id'],
            'class_name' => $ctx['class_name'],
            'term_name' => $ctx['term_name'],
            'attendance' => $ctx['attendance'],
            'fees_balance' => $ctx['fees_balance'],
            'card' => $ctx['card'],
            'exam_summary' => is_array($ctx['exam_summary'] ?? null) ? $ctx['exam_summary'] : null,
        ]);
        $pdf->Output($tmpPath, 'F');

        $studentToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($ctx['student_name'] ?? $ctx['school_id']));
        $classToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($ctx['class_name'] ?? 'Class'));
        $termToken = preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)($ctx['term_name'] ?? 'Term'));
        $yearToken = preg_replace('/[^A-Za-z0-9_-]+/', '', (string)date('Y'));

        return ['path' => $tmpPath, 'name' => $studentToken . '_' . $classToken . '_' . $termToken . '_' . $yearToken . '_Report.pdf'];
    } catch (Throwable $e) {
        return null;
    }
}

function app_results_record_delivery(array &$details, string $channel, string $recipient, string $studentName, string $status, string $reason): void
{
    $details[] = [
        'channel' => strtoupper($channel),
        'recipient' => $recipient,
        'student' => $studentName,
        'status' => $status,
        'reason' => $reason,
    ];
}

function app_results_delivery_report_html(array $stats): string
{
    $delivered = is_array($stats['delivered'] ?? null) ? $stats['delivered'] : [];
    $failed = is_array($stats['failed'] ?? null) ? $stats['failed'] : [];

    $html = '<div style="text-align:left; line-height:1.5">';
    $html .= '<p><strong>Delivery Summary</strong><br>'
        . 'SMS Sent: ' . (int)($stats['sent_sms'] ?? 0) . ', SMS Failed: ' . (int)($stats['failed_sms'] ?? 0) . '<br>'
        . 'WhatsApp Sent: ' . (int)($stats['sent_whatsapp'] ?? 0) . ', WhatsApp Failed: ' . (int)($stats['failed_whatsapp'] ?? 0) . '<br>'
        . 'Email Sent: ' . (int)($stats['sent_email'] ?? 0) . ', Email Failed: ' . (int)($stats['failed_email'] ?? 0) . '<br>'
        . 'Missing Contacts: ' . (int)($stats['missing_contacts'] ?? 0) . '<br>'
        . 'Fees Not Cleared: ' . (int)($stats['skipped_fees'] ?? 0) . '</p>';

    $html .= '<p><strong>Delivered</strong></p>';
    if (!$delivered) {
        $html .= '<p>None</p>';
    } else {
        $html .= '<ul style="margin:0 0 1rem 1.25rem; padding:0;">';
        foreach (array_slice($delivered, 0, 40) as $row) {
            $html .= '<li>'
                . htmlspecialchars((string)($row['channel'] ?? '')) . ': '
                . htmlspecialchars((string)($row['recipient'] ?? ''))
                . ' - ' . htmlspecialchars((string)($row['student'] ?? ''))
                . ' (' . htmlspecialchars((string)($row['status'] ?? 'delivered')) . ')'
                . '</li>';
        }
        if (count($delivered) > 40) {
            $html .= '<li>And ' . (count($delivered) - 40) . ' more delivered messages.</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<p><strong>Not Delivered</strong></p>';
    if (!$failed) {
        $html .= '<p>None</p>';
    } else {
        $html .= '<ul style="margin:0 0 1rem 1.25rem; padding:0;">';
        foreach (array_slice($failed, 0, 40) as $row) {
            $html .= '<li>'
                . htmlspecialchars((string)($row['channel'] ?? '')) . ': '
                . htmlspecialchars((string)($row['recipient'] ?? ''))
                . ' - ' . htmlspecialchars((string)($row['student'] ?? ''))
                . ' | Reason: ' . htmlspecialchars((string)($row['reason'] ?? 'Unknown error'))
                . '</li>';
        }
        if (count($failed) > 40) {
            $html .= '<li>And ' . (count($failed) - 40) . ' more failed messages.</li>';
        }
        $html .= '</ul>';
    }

    $html .= '</div>';

    return $html;
}

function app_results_send_notifications(PDO $conn, int $examId, string $channel = 'both'): array
{
    $channel = strtolower(trim($channel));
    if (!in_array($channel, ['sms', 'email', 'whatsapp', 'both', 'all'], true)) {
        $channel = 'both';
    }

    $stmt = $conn->prepare('SELECT e.id, e.status, e.class_id, e.term_id, e.name, c.name AS class_name, t.name AS term_name
        FROM tbl_exams e
        LEFT JOIN tbl_classes c ON c.id = e.class_id
        LEFT JOIN tbl_terms t ON t.id = e.term_id
        WHERE e.id = ? LIMIT 1');
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exam) {
        throw new RuntimeException('Exam not found.');
    }
    if ((string)($exam['status'] ?? '') !== 'published') {
        throw new RuntimeException('Only published exams can be sent to parents/students.');
    }

    $classId = (int)($exam['class_id'] ?? 0);
    $termId = (int)($exam['term_id'] ?? 0);
    if ($classId < 1 || $termId < 1) {
        throw new RuntimeException('Exam class/term missing.');
    }

    $hasParentPhone = app_column_exists($conn, 'tbl_parents', 'phone');
    $hasParentEmail = app_column_exists($conn, 'tbl_parents', 'email');
    $hasStudentPhone = app_column_exists($conn, 'tbl_students', 'phone');
    $hasStudentEmail = app_column_exists($conn, 'tbl_students', 'email');

    $sql = 'SELECT s.id, s.school_id, s.fname, s.mname, s.lname';
    if ($hasStudentPhone) { $sql .= ', s.phone AS student_phone'; }
    if ($hasStudentEmail) { $sql .= ', s.email AS student_email'; }
    if (app_table_exists($conn, 'tbl_parent_students') && app_table_exists($conn, 'tbl_parents')) {
        if ($hasParentPhone) {
            $sql .= ', (SELECT p.phone FROM tbl_parent_students ps JOIN tbl_parents p ON p.id = ps.parent_id WHERE ps.student_id = s.id LIMIT 1) AS parent_phone';
        }
        if ($hasParentEmail) {
            $sql .= ', (SELECT p.email FROM tbl_parent_students ps JOIN tbl_parents p ON p.id = ps.parent_id WHERE ps.student_id = s.id LIMIT 1) AS parent_email';
        }
    }
    $sql .= ' FROM tbl_students s WHERE s.class = ? ORDER BY s.fname, s.lname';

    $stmt = $conn->prepare($sql);
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $settings = report_get_settings($conn);
    $requireFeesClear = ((int)($settings['require_fees_clear'] ?? 0) === 1);

    $portalBase = rtrim(app_base_url(), '/');
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $headteacherTitle = defined('WBHeadteacherTitle') ? trim((string)WBHeadteacherTitle) : 'Headteacher';

    $sentSms = 0;
    $failedSms = 0;
    $sentWhatsapp = 0;
    $failedWhatsapp = 0;
    $sentEmail = 0;
    $failedEmail = 0;
    $missingContacts = 0;
    $skippedFees = 0;
    $delivered = [];
    $failed = [];

    foreach ($students as $student) {
        $studentId = (string)($student['id'] ?? '');
        if ($studentId === '') {
            continue;
        }

        $studentName = trim((string)($student['fname'] ?? '') . ' ' . (string)($student['mname'] ?? '') . ' ' . (string)($student['lname'] ?? ''));
        $schoolId = trim((string)($student['school_id'] ?? ''));
        if ($schoolId === '') {
            $schoolId = $studentId;
        }

        $card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
        if (!$card) {
            continue;
        }

        $feesBalance = report_fees_balance($conn, $studentId, $termId);
        if ($requireFeesClear && $feesBalance > 0) {
            $skippedFees++;
            continue;
        }

        $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
        $competencies = app_results_competency_summary($conn, $studentId, $classId, $termId);
        $statusPack = app_results_status_from_mean(
            (float)($card['mean'] ?? 0),
            (string)($exam['class_name'] ?? 'Class'),
            (string)($exam['term_name'] ?? '')
        );

        $resultUrl = $portalBase !== '' ? ($portalBase . '/verify_report?code=' . urlencode((string)($card['verification_code'] ?? ''))) : '';
        $pdfUrl = $portalBase !== '' ? ($portalBase . '/verify_report_pdf?code=' . urlencode((string)($card['verification_code'] ?? ''))) : '';

        $ctx = [
            'school_name' => $schoolName,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'school_id' => $schoolId,
            'class_name' => (string)($exam['class_name'] ?? 'Class'),
            'term_name' => (string)($exam['term_name'] ?? 'Term'),
            'mean' => (float)($card['mean'] ?? 0),
            'cbe_mean' => isset($card['mean_points']) ? (float)$card['mean_points'] : (float)($card['mean'] ?? 0),
            'grade' => (string)($card['grade'] ?? 'N/A'),
            'position' => (int)($card['position'] ?? 0),
            'total_students' => (int)($card['total_students'] ?? 0),
            'status' => $statusPack['status'],
            'recommendation' => $statusPack['recommendation'],
            'portal_url' => $resultUrl,
            'pdf_url' => $pdfUrl,
            'headteacher_title' => $headteacherTitle,
            'competencies' => $competencies,
            'attendance' => $attendance,
            'fees_balance' => $feesBalance,
            'card' => $card,
            'exam_summary' => [
                'exam_id' => (int)($exam['id'] ?? 0),
                'exam_name' => (string)($exam['name'] ?? ''),
                'assessment_mode' => (string)($exam['assessment_mode'] ?? 'normal'),
                'status' => (string)($exam['status'] ?? ''),
            ],
        ];


        $smsTargets = [];
        $emailTargets = [];

        $parentPhone = trim((string)($student['parent_phone'] ?? ''));
        $studentPhone = trim((string)($student['student_phone'] ?? ''));
        $parentEmail = trim((string)($student['parent_email'] ?? ''));
        $studentEmail = trim((string)($student['student_email'] ?? ''));

        if ($parentPhone !== '') { $smsTargets[] = $parentPhone; }
        if ($studentPhone !== '') { $smsTargets[] = $studentPhone; }
        if (empty($smsTargets) && $studentPhone !== '') { $smsTargets[] = $studentPhone; }

        if ($parentEmail !== '') { $emailTargets[] = $parentEmail; }
        if ($studentEmail !== '') { $emailTargets[] = $studentEmail; }
        if (empty($emailTargets) && $studentEmail !== '') { $emailTargets[] = $studentEmail; }

        if (($channel === 'sms') && empty($smsTargets)) {
            $missingContacts++;
            app_results_record_delivery($failed, 'sms', 'N/A', $studentName, 'failed', 'No SMS contact found for parent or student');
        }
        if (($channel === 'whatsapp' || $channel === 'both' || $channel === 'all') && empty($smsTargets)) {
            $missingContacts++;
            app_results_record_delivery($failed, 'whatsapp', 'N/A', $studentName, 'failed', 'No WhatsApp contact found for parent or student');
        }
        if (($channel === 'email' || $channel === 'both' || $channel === 'all') && empty($emailTargets)) {
            $missingContacts++;
            app_results_record_delivery($failed, 'email', 'N/A', $studentName, 'failed', 'No email contact found for parent or student');
        }

        if ($channel === 'sms' || $channel === 'all') {
            $smsMessage = app_results_sms_message($ctx);
            $sentMap = [];
            foreach ($smsTargets as $to) {
                $key = 'sms:' . $to;
                if (isset($sentMap[$key])) { continue; }
                $sentMap[$key] = true;
                $result = app_send_sms($conn, $to, $smsMessage);
                if (!empty($result['ok'])) {
                    $sentSms++;
                    app_results_record_delivery($delivered, 'sms', $to, $studentName, 'delivered', 'Sent successfully');
                } else {
                    $failedSms++;
                    app_results_record_delivery($failed, 'sms', $to, $studentName, 'failed', (string)($result['error'] ?? 'SMS send failed'));
                }
            }
        }

        if ($channel === 'whatsapp' || $channel === 'both' || $channel === 'all') {
            $whatsAppMessage = app_results_whatsapp_message($ctx);
            $sentMap = [];
            $attachment = app_results_temp_report_pdf($conn, $ctx);
            if (!$attachment || !isset($attachment['path']) || !is_file((string)$attachment['path'])) {
                foreach ($smsTargets as $to) {
                    $key = 'whatsapp:' . $to;
                    if (isset($sentMap[$key])) { continue; }
                    $sentMap[$key] = true;
                    $failedWhatsapp++;
                    app_results_record_delivery($failed, 'whatsapp', $to, $studentName, 'failed', 'Unable to generate the report PDF attachment');
                }
            } else {
                foreach ($smsTargets as $to) {
                    $key = 'whatsapp:' . $to;
                    if (isset($sentMap[$key])) { continue; }
                    $sentMap[$key] = true;
                    $targetRole = ($parentPhone !== '' && app_normalize_phone_number($to, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254')) === app_normalize_phone_number($parentPhone, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254')))
                        ? 'parent'
                        : 'student';
                    $result = app_send_whatsapp_document(
                        $conn,
                        $to,
                        $whatsAppMessage,
                        (string)$attachment['path'],
                        (string)($attachment['name'] ?? ''),
                        [
                            'entity_type' => 'result_notification',
                            'exam_id' => (int)($exam['id'] ?? 0),
                            'student_id' => $studentId,
                            'student_name' => $studentName,
                            'school_id' => $schoolId,
                            'class_id' => $classId,
                            'term_id' => $termId,
                            'term_name' => (string)($exam['term_name'] ?? ''),
                            'channel' => 'whatsapp',
                            'target_role' => $targetRole,
                            'cbe_mean' => isset($ctx['cbe_mean']) ? (float)$ctx['cbe_mean'] : null,
                            'grade' => (string)($ctx['grade'] ?? ''),
                        ]
                    );
                    if (!empty($result['ok'])) {
                        $sentWhatsapp++;
                        app_results_record_delivery($delivered, 'whatsapp', $to, $studentName, 'delivered', 'Sent successfully');
                    } else {
                        $failedWhatsapp++;
                        app_results_record_delivery($failed, 'whatsapp', $to, $studentName, 'failed', (string)($result['error'] ?? 'WhatsApp send failed'));
                    }
                }
            }
            if ($attachment && isset($attachment['path']) && is_file((string)$attachment['path'])) {
                @unlink((string)$attachment['path']);
            }
        }

        if ($channel === 'email' || $channel === 'both' || $channel === 'all') {
            $emailSubject = 'Academic Results - ' . $schoolName;
            $emailHtml = app_results_email_html($ctx);
            $attachment = app_results_temp_report_pdf($conn, $ctx);
            $attachments = $attachment ? [$attachment] : [];

            $sentMap = [];
            foreach ($emailTargets as $to) {
                $key = 'email:' . strtolower($to);
                if (isset($sentMap[$key])) { continue; }
                $sentMap[$key] = true;
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $failedEmail++;
                    app_results_record_delivery($failed, 'email', $to, $studentName, 'failed', 'Invalid email address');
                    continue;
                }
                $result = app_send_email($conn, $to, $emailSubject, $emailHtml, $attachments);
                if (!empty($result['ok'])) {
                    $sentEmail++;
                    app_results_record_delivery($delivered, 'email', $to, $studentName, 'delivered', 'Sent successfully');
                } else {
                    $failedEmail++;
                    app_results_record_delivery($failed, 'email', $to, $studentName, 'failed', (string)($result['error'] ?? 'Email send failed'));
                }
            }

            if ($attachment && isset($attachment['path']) && is_file((string)$attachment['path'])) {
                @unlink((string)$attachment['path']);
            }
        }
    }

    return [
        'sent_sms' => $sentSms,
        'failed_sms' => $failedSms,
        'sent_whatsapp' => $sentWhatsapp,
        'failed_whatsapp' => $failedWhatsapp,
        'sent_email' => $sentEmail,
        'failed_email' => $failedEmail,
        'missing_contacts' => $missingContacts,
        'skipped_fees' => $skippedFees,
        'students' => count($students),
        'delivered' => $delivered,
        'failed' => $failed,
    ];
}

function app_results_resend_single_whatsapp(PDO $conn, int $examId, string $studentId, string $recipient = ''): array
{
    $stmt = $conn->prepare('SELECT e.id, e.status, e.class_id, e.term_id, e.name, e.assessment_mode, c.name AS class_name, t.name AS term_name
        FROM tbl_exams e
        LEFT JOIN tbl_classes c ON c.id = e.class_id
        LEFT JOIN tbl_terms t ON t.id = e.term_id
        WHERE e.id = ? LIMIT 1');
    $stmt->execute([$examId]);
    $exam = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$exam) {
        return ['ok' => false, 'error' => 'Exam not found.'];
    }

    $classId = (int)($exam['class_id'] ?? 0);
    $termId = (int)($exam['term_id'] ?? 0);
    if ($classId < 1 || $termId < 1) {
        return ['ok' => false, 'error' => 'Exam class/term missing.'];
    }

    $sql = 'SELECT s.id, s.school_id, s.fname, s.mname, s.lname';
    if (app_column_exists($conn, 'tbl_students', 'phone')) { $sql .= ', s.phone AS student_phone'; }
    if (app_column_exists($conn, 'tbl_students', 'email')) { $sql .= ', s.email AS student_email'; }
    if (app_table_exists($conn, 'tbl_parent_students') && app_table_exists($conn, 'tbl_parents')) {
        if (app_column_exists($conn, 'tbl_parents', 'phone')) {
            $sql .= ', (SELECT p.phone FROM tbl_parent_students ps JOIN tbl_parents p ON p.id = ps.parent_id WHERE ps.student_id = s.id LIMIT 1) AS parent_phone';
        }
        if (app_column_exists($conn, 'tbl_parents', 'email')) {
            $sql .= ', (SELECT p.email FROM tbl_parent_students ps JOIN tbl_parents p ON p.id = ps.parent_id WHERE ps.student_id = s.id LIMIT 1) AS parent_email';
        }
    }
    $sql .= ' FROM tbl_students s WHERE s.class = ? AND s.id = ? LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->execute([$classId, $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        return ['ok' => false, 'error' => 'Student not found for this exam class.'];
    }

    $settings = report_get_settings($conn);
    $requireFeesClear = ((int)($settings['require_fees_clear'] ?? 0) === 1);

    $card = report_ensure_card_generated($conn, $studentId, $classId, $termId);
    if (!$card) {
        return ['ok' => false, 'error' => 'Report card is not available yet.'];
    }

    $feesBalance = report_fees_balance($conn, $studentId, $termId);
    if ($requireFeesClear && $feesBalance > 0) {
        return ['ok' => false, 'error' => 'Fees clearance is required before sending this result.'];
    }

    $studentName = trim((string)($student['fname'] ?? '') . ' ' . (string)($student['mname'] ?? '') . ' ' . (string)($student['lname'] ?? ''));
    $schoolId = trim((string)($student['school_id'] ?? ''));
    if ($schoolId === '') {
        $schoolId = $studentId;
    }

    $attendance = report_attendance_summary($conn, $studentId, $classId, $termId);
    $competencies = app_results_competency_summary($conn, $studentId, $classId, $termId);
    $statusPack = app_results_status_from_mean(
        (float)($card['mean'] ?? 0),
        (string)($exam['class_name'] ?? 'Class'),
        (string)($exam['term_name'] ?? '')
    );

    $portalBase = rtrim(app_base_url(), '/');
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');
    $headteacherTitle = defined('WBHeadteacherTitle') ? trim((string)WBHeadteacherTitle) : 'Headteacher';

    $ctx = [
        'school_name' => $schoolName,
        'student_id' => $studentId,
        'student_name' => $studentName,
        'school_id' => $schoolId,
        'class_name' => (string)($exam['class_name'] ?? 'Class'),
        'term_name' => (string)($exam['term_name'] ?? 'Term'),
        'mean' => (float)($card['mean'] ?? 0),
        'cbe_mean' => isset($card['mean_points']) ? (float)$card['mean_points'] : (float)($card['mean'] ?? 0),
        'grade' => (string)($card['grade'] ?? 'N/A'),
        'status' => $statusPack['status'],
        'recommendation' => $statusPack['recommendation'],
        'portal_url' => $portalBase !== '' ? ($portalBase . '/verify_report?code=' . urlencode((string)($card['verification_code'] ?? ''))) : '',
        'pdf_url' => $portalBase !== '' ? ($portalBase . '/verify_report_pdf?code=' . urlencode((string)($card['verification_code'] ?? ''))) : '',
        'headteacher_title' => $headteacherTitle,
        'competencies' => $competencies,
        'attendance' => $attendance,
        'fees_balance' => $feesBalance,
        'card' => $card,
        'exam_summary' => [
            'exam_id' => (int)($exam['id'] ?? 0),
            'exam_name' => (string)($exam['name'] ?? ''),
            'assessment_mode' => (string)($exam['assessment_mode'] ?? 'normal'),
            'status' => (string)($exam['status'] ?? ''),
        ],
    ];

    $targets = [];
    $parentPhone = trim((string)($student['parent_phone'] ?? ''));
    $studentPhone = trim((string)($student['student_phone'] ?? ''));
    if ($recipient !== '') {
        $targets[] = $recipient;
    } else {
        if ($parentPhone !== '') { $targets[] = $parentPhone; }
        if ($studentPhone !== '') { $targets[] = $studentPhone; }
    }
    $targets = array_values(array_unique(array_filter($targets)));
    if (empty($targets)) {
        return ['ok' => false, 'error' => 'No WhatsApp contact found for this student.'];
    }

    $attachment = app_results_temp_report_pdf($conn, $ctx);
    if (!$attachment || !isset($attachment['path']) || !is_file((string)$attachment['path'])) {
        return ['ok' => false, 'error' => 'Unable to generate the report PDF attachment.'];
    }

    $message = app_results_whatsapp_message($ctx);
    $lastResult = ['ok' => false, 'error' => 'No send attempted.'];
    foreach ($targets as $to) {
        $targetRole = ($parentPhone !== '' && app_normalize_phone_number($to, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254')) === app_normalize_phone_number($parentPhone, (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254')))
            ? 'parent'
            : 'student';
        $lastResult = app_send_whatsapp_document(
            $conn,
            $to,
            $message,
            (string)$attachment['path'],
            (string)($attachment['name'] ?? ''),
            [
                'entity_type' => 'result_notification',
                'exam_id' => (int)($exam['id'] ?? 0),
                'student_id' => $studentId,
                'student_name' => $studentName,
                'school_id' => $schoolId,
                'class_id' => $classId,
                'term_id' => $termId,
                'term_name' => (string)($exam['term_name'] ?? ''),
                'channel' => 'whatsapp',
                'target_role' => $targetRole,
                'retry' => true,
                'cbe_mean' => isset($ctx['cbe_mean']) ? (float)$ctx['cbe_mean'] : null,
                'grade' => (string)($ctx['grade'] ?? ''),
            ]
        );
        if (!empty($lastResult['ok'])) {
            break;
        }
    }

    if (isset($attachment['path']) && is_file((string)$attachment['path'])) {
        @unlink((string)$attachment['path']);
    }

    return $lastResult;
}
