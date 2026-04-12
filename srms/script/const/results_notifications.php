<?php
require_once('db/config.php');
require_once('const/report_engine.php');
require_once('const/report_pdf_template.php');
require_once('const/notify.php');
require_once('tcpdf/tcpdf.php');

function app_results_competency_summary(PDO $conn, string $studentId, int $classId, int $termId): array
{
    if (!app_table_exists($conn, 'tbl_cbc_assessments')) {
        return [];
    }

    $hasMarks = app_column_exists($conn, 'tbl_cbc_assessments', 'marks');
    $selectScore = $hasMarks ? 'AVG(COALESCE(marks,0))' : "AVG(CASE UPPER(level) WHEN 'EE' THEN 85 WHEN 'ME' THEN 70 WHEN 'AE' THEN 50 WHEN 'BE' THEN 30 ELSE 0 END)";

    $stmt = $conn->prepare("SELECT learning_area, $selectScore AS score
        FROM tbl_cbc_assessments
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

function app_results_status_from_mean(float $mean, string $className): array
{
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
    $lines[] = 'Mean Score: ' . number_format((float)$ctx['mean'], 0) . '% (' . $ctx['grade'] . ')';
    $lines[] = 'Position: ' . $ctx['position'] . '/' . $ctx['total_students'];

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
    if ($ctx['portal_url'] !== '') {
        $lines[] = 'Portal: ' . $ctx['portal_url'];
    }

    $msg = implode("\n", $lines);
    if (strlen($msg) > 320) {
        $msg = implode("\n", [
            $ctx['school_name'],
            'Student: ' . $ctx['student_name'],
            'Class: ' . $ctx['class_name'],
            'Mean: ' . number_format((float)$ctx['mean'], 0) . '% (' . $ctx['grade'] . ')',
            'Position: ' . $ctx['position'] . '/' . $ctx['total_students'],
            'Status: ' . $ctx['status'],
            'Check portal/email for full details.'
        ]);
    }

    return $msg;
}

function app_results_email_html(array $ctx): string
{
    $competencyHtml = '';
    if (!empty($ctx['competencies'])) {
        $competencyHtml .= '<p><strong>CBC Competencies:</strong></p><ul>';
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
        . 'Mean Score: ' . number_format((float)$ctx['mean'], 2) . '% (' . htmlspecialchars($ctx['grade']) . ')<br>'
        . 'Position: ' . (int)$ctx['position'] . ' out of ' . (int)$ctx['total_students'] . ' students</p>'
        . $competencyHtml
        . '<p><strong>Final Decision:</strong><br>' . htmlspecialchars($ctx['status']) . '</p>'
        . $recommendationHtml
        . $portalHtml
        . '<p>Attachments:<br>1. Report Card PDF<br>2. Progress Summary</p>'
        . '<p>Regards,<br>Headteacher<br>' . htmlspecialchars($ctx['school_name']) . '</p>';
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
        ]);
        $pdf->Output($tmpPath, 'F');

        return ['path' => $tmpPath, 'name' => 'ReportCard-' . preg_replace('/[^A-Za-z0-9_-]/', '', $ctx['school_id']) . '.pdf'];
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
    if (!in_array($channel, ['sms', 'email', 'both'], true)) {
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

    $portalBase = defined('APP_URL') && APP_URL !== '' ? rtrim((string)APP_URL, '/') : '';
    $schoolName = defined('WBName') ? (string)WBName : (defined('APP_NAME') ? (string)APP_NAME : 'School');

    $sentSms = 0;
    $failedSms = 0;
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
        $statusPack = app_results_status_from_mean((float)($card['mean'] ?? 0), (string)($exam['class_name'] ?? 'Class'));

        $resultUrl = $portalBase !== '' ? ($portalBase . '/verify_report?code=' . urlencode((string)($card['verification_code'] ?? ''))) : '';

        $ctx = [
            'school_name' => $schoolName,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'school_id' => $schoolId,
            'class_name' => (string)($exam['class_name'] ?? 'Class'),
            'term_name' => (string)($exam['term_name'] ?? 'Term'),
            'mean' => (float)($card['mean'] ?? 0),
            'grade' => (string)($card['grade'] ?? 'N/A'),
            'position' => (int)($card['position'] ?? 0),
            'total_students' => (int)($card['total_students'] ?? 0),
            'status' => $statusPack['status'],
            'recommendation' => $statusPack['recommendation'],
            'portal_url' => $resultUrl,
            'competencies' => $competencies,
            'attendance' => $attendance,
            'fees_balance' => $feesBalance,
            'card' => $card,
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

        if (($channel === 'sms' || $channel === 'both') && empty($smsTargets) && ($channel !== 'email')) {
            $missingContacts++;
            app_results_record_delivery($failed, 'sms', 'N/A', $studentName, 'failed', 'No SMS contact found for parent or student');
        }
        if (($channel === 'email' || $channel === 'both') && empty($emailTargets) && ($channel !== 'sms')) {
            $missingContacts++;
            app_results_record_delivery($failed, 'email', 'N/A', $studentName, 'failed', 'No email contact found for parent or student');
        }

        if ($channel === 'sms' || $channel === 'both') {
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

        if ($channel === 'email' || $channel === 'both') {
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
        'sent_email' => $sentEmail,
        'failed_email' => $failedEmail,
        'missing_contacts' => $missingContacts,
        'skipped_fees' => $skippedFees,
        'students' => count($students),
        'delivered' => $delivered,
        'failed' => $failed,
    ];
}
