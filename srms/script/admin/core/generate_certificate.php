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
$meanScore = !empty($_POST['mean_score']) ? (float)$_POST['mean_score'] : null;
$competencies = (array)($_POST['competencies'] ?? []);

$types = app_certificate_types();
if ($studentId === '' || !isset($types[$type])) {
    app_reply_redirect('danger', 'Missing certificate details.', '../certificates');
}

$category = $type;

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
    
    // Prepare competencies JSON if provided
    $competenciesJson = null;
    if (!empty($competencies)) {
        $normalized = [];
        foreach ($competencies as $key => $level) {
            $levelValue = trim((string)$level);
            if ($levelValue === '') {
                continue;
            }
            $normalized[$key] = [
                'achievement_level' => $levelValue,
                'comment' => '',
            ];
        }
        if (!empty($normalized)) {
            $competenciesJson = json_encode([
                'assessed_at' => date('Y-m-d H:i:s'),
                'competencies' => $normalized,
            ]);
        }
    }
    
    // Determine merit grade from mean score
    $meritGrade = null;
    if ($meanScore !== null) {
        $meritGrade = app_merit_grade_from_score($meanScore);
    }

    $stmt = $conn->prepare('INSERT INTO tbl_certificates (student_id, class_id, certificate_type, certificate_category, title, serial_no, issue_date, status, notes, verification_code, cert_hash, issued_by, mean_score, merit_grade, competencies_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $studentId,
        (int)$student['class'],
        $type,
        $category,
        $title,
        $serial,
        $issueDate,
        'issued',
        $notes,
        $code,
        $hash,
        (int)$account_id,
        $meanScore,
        $meritGrade,
        $competenciesJson,
    ]);

    app_reply_redirect('success', 'Certificate generated successfully.', '../certificates');
} catch (Throwable $e) {
    app_reply_redirect('danger', 'Failed to generate certificate: ' . $e->getMessage(), '../certificates');
}
