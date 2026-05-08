<?php
chdir(__DIR__ . '/../..');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/notify.php');
require_once('const/rbac.php');

if ($res !== "1" || (int)$level !== 5) {
    app_reply_redirect('danger', 'Unauthorized.', '../fees');
}
app_require_permission('finance.manage', '../fees');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_reply_redirect('danger', 'Invalid request.', '../fees');
}

$studentId = trim((string)($_POST['student_id'] ?? ''));
$actionType = trim((string)($_POST['action_type'] ?? 'reminder'));
$channel = trim((string)($_POST['channel'] ?? 'sms'));

if ($studentId === '' || !in_array($actionType, ['reminder', 'statement'], true) || !in_array($channel, ['sms', 'email', 'both'], true)) {
    app_reply_redirect('danger', 'Invalid fee communication request.', '../fees');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_finance_tables($conn);
    app_sync_student_finance_class_links($conn, $studentId);

    $stmt = $conn->prepare("SELECT st.id, st.school_id, concat_ws(' ', st.fname, st.mname, st.lname) AS student_name,
        c.name AS class_name,
        COALESCE(inv.total_invoiced, 0) AS total_invoiced,
        COALESCE(pay.total_paid, 0) AS paid_amount
        FROM tbl_students st
        LEFT JOIN tbl_classes c ON c.id = st.class
        LEFT JOIN (
            SELECT i.student_id, SUM(line_totals.total_amount) AS total_invoiced
            FROM tbl_invoices i
            LEFT JOIN (
                SELECT invoice_id, SUM(amount) AS total_amount
                FROM tbl_invoice_lines
                GROUP BY invoice_id
            ) AS line_totals ON line_totals.invoice_id = i.id
            WHERE i.status <> 'void'
            GROUP BY i.student_id
        ) inv ON inv.student_id = st.id
        LEFT JOIN (
            SELECT i.student_id, SUM(p.amount) AS total_paid
            FROM tbl_invoices i
            JOIN tbl_payments p ON p.invoice_id = i.id
            WHERE i.status <> 'void'
            GROUP BY i.student_id
        ) pay ON pay.student_id = st.id
        WHERE st.id = ?
        LIMIT 1");
    $stmt->execute([$studentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$row) {
        throw new RuntimeException('Student finance account not found.');
    }
    $studentName = (string)($row['student_name'] ?? 'Student');
    $schoolId = (string)($row['school_id'] ?? $studentId);
    $className = (string)($row['class_name'] ?? '');
    $totalInvoiced = (float)($row['total_invoiced'] ?? 0);
    $totalPaid = (float)($row['paid_amount'] ?? 0);
    $balance = max(0, round($totalInvoiced - $totalPaid, 2));

    $message = $actionType === 'statement'
        ? 'Fee statement for ' . $studentName . ' (' . $schoolId . ')' . ($className !== '' ? ' - ' . $className : '') . ': Total invoiced KES ' . number_format($totalInvoiced, 2) . ', paid KES ' . number_format($totalPaid, 2) . ', balance KES ' . number_format($balance, 2) . '.'
        : 'Fee reminder for ' . $studentName . ' (' . $schoolId . ')' . ($className !== '' ? ' - ' . $className : '') . ': Outstanding balance KES ' . number_format($balance, 2) . '. Please clear the balance with the school accounts office.';
    $subject = $actionType === 'statement' ? 'School Fee Statement' : 'School Fee Reminder';

    $contacts = [];
    if (app_table_exists($conn, 'tbl_parent_students') && app_table_exists($conn, 'tbl_parents')) {
        $stmt = $conn->prepare("SELECT p.phone, p.email
            FROM tbl_parent_students ps
            JOIN tbl_parents p ON p.id = ps.parent_id
            WHERE ps.student_id = ?");
        $stmt->execute([$studentId]);
        $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    if (!$contacts) {
        throw new RuntimeException('No linked parent contact found for this student.');
    }

    $sentCount = 0;
    foreach ($contacts as $contact) {
        if (($channel === 'sms' || $channel === 'both') && !empty($contact['phone'])) {
            app_send_sms($conn, (string)$contact['phone'], $message);
            $stmt = $conn->prepare("INSERT INTO tbl_finance_reminder_logs (student_id, channel, recipient, message, sent_by) VALUES (?,?,?,?,?)");
            $stmt->execute([$studentId, 'sms', (string)$contact['phone'], $message, (int)$account_id]);
            $sentCount++;
        }
        if (($channel === 'email' || $channel === 'both') && !empty($contact['email'])) {
            app_send_email($conn, (string)$contact['email'], $subject, nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')));
            $stmt = $conn->prepare("INSERT INTO tbl_finance_reminder_logs (student_id, channel, recipient, message, sent_by) VALUES (?,?,?,?,?)");
            $stmt->execute([$studentId, 'email', (string)$contact['email'], $message, (int)$account_id]);
            $sentCount++;
        }
    }

    app_audit_log($conn, 'staff', (string)$account_id, 'finance.parent_contact', 'student', $studentId, ['action' => $actionType, 'channel' => $channel, 'sent_count' => $sentCount]);
    app_reply_redirect('success', ucfirst($actionType) . ' sent successfully.', '../fees');
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to send finance message: ' . $e->getMessage(), '../fees');
}
