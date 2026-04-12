<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/results_notifications.php');

if ($res != '1' || $level != '0') { header('location:../'); exit; }
app_require_permission('results.approve', '../publish_results');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location:../publish_results');
    exit;
}

$examId = (int)($_POST['exam_id'] ?? 0);
$channel = strtolower(trim((string)($_POST['channel'] ?? '')));
if ($examId < 1 || !in_array($channel, ['sms', 'email', 'both'], true)) {
    $_SESSION['reply'] = array(array('danger', 'Invalid notification request.'));
    header('location:../publish_results');
    exit;
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stats = app_results_send_notifications($conn, $examId, $channel);

    app_audit_log($conn, 'staff', (string)$account_id, 'results.notify.' . $channel, 'exam', (string)$examId, $stats);

    $summary = 'SMS Sent: ' . (int)$stats['sent_sms'] . ', SMS Failed: ' . (int)$stats['failed_sms']
        . ' | Email Sent: ' . (int)$stats['sent_email'] . ', Email Failed: ' . (int)$stats['failed_email']
        . ' | Missing Contacts: ' . (int)$stats['missing_contacts']
        . ' | Fees Not Cleared: ' . (int)$stats['skipped_fees'];

    if ((int)$stats['sent_sms'] > 0 || (int)$stats['sent_email'] > 0) {
        $_SESSION['reply'] = array(array('success', 'Result notifications sent. ' . $summary));
    } else {
        $_SESSION['reply'] = array(array('danger', 'No notifications sent. ' . $summary));
    }

    header('location:../publish_results');
    exit;
} catch (Throwable $e) {
    $_SESSION['reply'] = array(array('danger', 'Failed to send notifications: ' . $e->getMessage()));
    header('location:../publish_results');
    exit;
}
