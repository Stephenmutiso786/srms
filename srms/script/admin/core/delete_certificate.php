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
    app_ensure_data_camp_schema($conn);

    $stmt = $conn->prepare('SELECT id, student_id, class_id, verification_code FROM tbl_certificates WHERE id = ? LIMIT 1');
    $stmt->execute([$certificateId]);
    $certificateRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$certificateRow) {
        app_reply_redirect('warning', 'Certificate was already removed.', '../certificates');
    }

    app_data_camp_store_record($conn, [
        'module_key' => 'certificates',
        'record_type' => 'retention_notice',
        'entity_table' => 'tbl_certificates',
        'entity_id' => (string)$certificateId,
        'title' => 'Certificate retained',
        'description' => 'A delete request was blocked because certificates are now permanently retained for future reference.',
        'class_id' => (int)($certificateRow['class_id'] ?? 0),
        'student_id' => (string)($certificateRow['student_id'] ?? ''),
        'source_url' => trim((string)($certificateRow['verification_code'] ?? '')) !== '' ? 'verify_certificate?code=' . trim((string)$certificateRow['verification_code']) : null,
        'status' => 'retained',
        'source_key' => 'certificate_retention_notice:' . $certificateId,
        'created_by' => isset($account_id) ? (int)$account_id : 0,
    ]);

    app_reply_redirect('warning', 'Certificates are now retained permanently. This record was not deleted and remains available in Data Camp.', '../certificates');
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to delete certificate: ' . $e->getMessage(), '../certificates');
}
