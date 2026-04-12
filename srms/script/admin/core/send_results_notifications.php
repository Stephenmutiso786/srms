<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');

if ($res != '1' || $level != '0') { header('location:../'); exit; }
app_require_permission('results.approve', '../publish_results');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location:../publish_results');
    exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$channel = strtolower(trim((string)($_POST['channel'] ?? '')));
if ($examId < 1 || !in_array($channel, ['sms', 'email'], true)) {
    $_SESSION['reply'] = array(array('danger', 'Invalid notification request.'));
    header('location:../publish_results');
    exit;
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

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
    if ($classId < 1) {
        throw new RuntimeException('Exam class not found.');
    }

    $termName = (string)($exam['term_name'] ?? 'Current Term');
    $className = (string)($exam['class_name'] ?? 'Class');
    $examName = (string)($exam['name'] ?? 'Exam Results');
    $portalUrl = defined('APP_URL') && APP_URL !== '' ? rtrim((string)APP_URL, '/') : '';

    $messageText = 'Results published: ' . $examName . ' for ' . $className . ' (' . $termName . '). Check the portal for report card details.' . ($portalUrl !== '' ? ' ' . $portalUrl : '');
    $emailSubject = 'Published Results - ' . $examName . ' (' . $termName . ')';
    $emailHtml = '<p>Dear Parent/Student,</p>'
        . '<p>Results for <strong>' . htmlspecialchars($examName) . '</strong> have been published.</p>'
        . '<p><strong>Class:</strong> ' . htmlspecialchars($className) . '<br><strong>Term:</strong> ' . htmlspecialchars($termName) . '</p>'
        . '<p>Please log in to view the full report card and analysis.</p>'
        . ($portalUrl !== '' ? '<p><a href="' . htmlspecialchars($portalUrl) . '">' . htmlspecialchars($portalUrl) . '</a></p>' : '')
        . '<p>Regards,<br>' . htmlspecialchars((defined('WBName') ? WBName : APP_NAME)) . '</p>';

    $hasParentPhone = app_column_exists($conn, 'tbl_parents', 'phone');
    $hasParentEmail = app_column_exists($conn, 'tbl_parents', 'email');
    $hasStudentPhone = app_column_exists($conn, 'tbl_students', 'phone');
    $hasStudentEmail = app_column_exists($conn, 'tbl_students', 'email');

    $contacts = [];

    if (app_table_exists($conn, 'tbl_parent_students') && app_table_exists($conn, 'tbl_parents')) {
        $sql = 'SELECT DISTINCT p.id AS contact_id';
        if ($hasParentPhone) { $sql .= ', p.phone'; }
        if ($hasParentEmail) { $sql .= ', p.email'; }
        $sql .= ' FROM tbl_parent_students ps
            JOIN tbl_parents p ON p.id = ps.parent_id
            JOIN tbl_students s ON s.id = ps.student_id
            WHERE s.class = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contacts[] = [
                'phone' => trim((string)($row['phone'] ?? '')),
                'email' => trim((string)($row['email'] ?? '')),
            ];
        }
    }

    if (app_table_exists($conn, 'tbl_students')) {
        $sql = 'SELECT id';
        if ($hasStudentPhone) { $sql .= ', phone'; }
        if ($hasStudentEmail) { $sql .= ', email'; }
        $sql .= ' FROM tbl_students WHERE class = ?';
        $stmt = $conn->prepare($sql);
        $stmt->execute([$classId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $contacts[] = [
                'phone' => trim((string)($row['phone'] ?? '')),
                'email' => trim((string)($row['email'] ?? '')),
            ];
        }
    }

    $sent = 0;
    $failed = 0;
    $seen = [];

    foreach ($contacts as $contact) {
        if ($channel === 'sms') {
            $to = trim((string)($contact['phone'] ?? ''));
            if ($to === '') { continue; }
            $key = 'sms:' . $to;
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $result = app_send_sms($conn, $to, $messageText);
            if (!empty($result['ok'])) { $sent++; } else { $failed++; }
        } else {
            $to = trim((string)($contact['email'] ?? ''));
            if ($to === '') { continue; }
            $key = 'email:' . strtolower($to);
            if (isset($seen[$key])) { continue; }
            $seen[$key] = true;
            $result = app_send_email($conn, $to, $emailSubject, $emailHtml);
            if (!empty($result['ok'])) { $sent++; } else { $failed++; }
        }
    }

    app_audit_log($conn, 'staff', (string)$account_id, 'results.notify.' . $channel, 'exam', (string)$examId, [
        'sent' => $sent,
        'failed' => $failed,
        'class_id' => $classId,
        'term_id' => (int)($exam['term_id'] ?? 0),
    ]);

    if ($sent > 0) {
        $_SESSION['reply'] = array(array('success', strtoupper($channel) . ' sent successfully. Delivered: ' . $sent . ', Failed: ' . $failed));
    } else {
        $_SESSION['reply'] = array(array('danger', 'No ' . strtoupper($channel) . ' sent. Check contacts and ' . strtoupper($channel) . ' configuration.'));
    }

    header('location:../publish_results');
    exit;
} catch (Throwable $e) {
    $_SESSION['reply'] = array(array('danger', 'Failed to send notifications: ' . $e->getMessage()));
    header('location:../publish_results');
    exit;
}
