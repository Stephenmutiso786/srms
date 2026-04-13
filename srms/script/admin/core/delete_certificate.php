<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== '1' || $level !== '0') {
    header('location:../../');
    exit;
}
app_require_permission('report.generate', '../certificates');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location:../certificates');
    exit;
}

$certificateId = (int)($_POST['certificate_id'] ?? 0);
if ($certificateId < 1) {
    app_reply_redirect('danger', 'Invalid certificate ID.', '../certificates');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $conn->prepare('SELECT id FROM tbl_certificates WHERE id = ? LIMIT 1');
    $stmt->execute([$certificateId]);
    if (!$stmt->fetchColumn()) {
        app_reply_redirect('warning', 'Certificate was already removed.', '../certificates');
    }

    $stmt = $conn->prepare('DELETE FROM tbl_certificates WHERE id = ?');
    $stmt->execute([$certificateId]);

    app_reply_redirect('success', 'Certificate deleted successfully.', '../certificates');
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to delete certificate: ' . $e->getMessage(), '../certificates');
}
