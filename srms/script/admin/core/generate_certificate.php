<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');

if ($res !== '1' || $level !== '0') { header('location:../../'); exit; }
app_require_permission('report.generate', '../certificates');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('location:../certificates');
    exit;
}

$studentId = trim((string)($_POST['student_id'] ?? ''));
$type = trim((string)($_POST['certificate_type'] ?? 'leaving'));
$issueDate = trim((string)($_POST['issue_date'] ?? date('Y-m-d')));
$notes = trim((string)($_POST['notes'] ?? ''));

$types = app_certificate_types();
if ($studentId === '' || !isset($types[$type])) {
    app_reply_redirect('danger', 'Missing certificate details.', '../certificates');
}

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);

    $stmt = $conn->prepare('SELECT id, class, fname, mname, lname FROM tbl_students WHERE id = ? LIMIT 1');
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) {
        throw new RuntimeException('Student not found.');
    }

    $serial = app_certificate_serial($type, $studentId);
    $code = app_certificate_code($studentId);
    $title = $types[$type];
    $payload = [
        'student_id' => $studentId,
        'certificate_type' => $type,
        'title' => $title,
        'issue_date' => $issueDate,
        'serial_no' => $serial,
    ];
    $hash = app_certificate_hash($payload);

    $stmt = $conn->prepare('INSERT INTO tbl_certificates (student_id, class_id, certificate_type, title, serial_no, issue_date, status, notes, verification_code, cert_hash, issued_by)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $studentId,
        (int)$student['class'],
        $type,
        $title,
        $serial,
        $issueDate,
        'issued',
        $notes,
        $code,
        $hash,
        (int)$account_id,
    ]);

    app_reply_redirect('success', 'Certificate generated successfully.', '../certificates');
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to generate certificate.', '../certificates');
}
