<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/certificate_engine.php');

$code = isset($_GET['code']) ? trim((string)$_GET['code']) : '';
$status = 'Invalid';
$data = null;

if ($code !== '') {
    try {
        $conn = app_db();
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        app_ensure_certificates_table($conn);

        $stmt = $conn->prepare('SELECT cert.*, concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
            FROM tbl_certificates cert
            JOIN tbl_students st ON st.id = cert.student_id
            LEFT JOIN tbl_classes c ON c.id = cert.class_id
            WHERE cert.verification_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $hash = app_certificate_hash([
                'student_id' => (string)$row['student_id'],
                'certificate_type' => (string)$row['certificate_type'],
                'title' => (string)$row['title'],
                'issue_date' => (string)$row['issue_date'],
                'serial_no' => (string)$row['serial_no'],
            ]);
            if (hash_equals((string)$row['cert_hash'], $hash)) {
                $status = 'Verified';
                $data = $row;
            }
        }
    } catch (Throwable $e) {
        $status = 'Invalid';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Verify Certificate</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body style="background:#f4f7f6;">
<div class="container py-5">
<div class="report-card">
<div class="report-header">
<div><h2><?php echo APP_NAME; ?> Certificate Verification</h2><div class="report-meta"><span>Verification Code: <?php echo htmlspecialchars($code); ?></span></div></div>
<div class="text-end"><span class="report-badge"><i class="bi bi-shield-check"></i> <?php echo htmlspecialchars($status); ?></span></div>
</div>
<?php if ($status === 'Verified' && $data): ?>
<div class="report-grid">
<div class="report-stat"><div class="label">Student</div><div class="value"><?php echo htmlspecialchars((string)$data['student_name']); ?></div></div>
<div class="report-stat"><div class="label">Class</div><div class="value"><?php echo htmlspecialchars((string)($data['class_name'] ?? '')); ?></div></div>
<div class="report-stat"><div class="label">Certificate</div><div class="value"><?php echo htmlspecialchars((string)$data['title']); ?></div></div>
<div class="report-stat"><div class="label">Issue Date</div><div class="value"><?php echo htmlspecialchars((string)$data['issue_date']); ?></div></div>
<div class="report-stat"><div class="label">Serial No</div><div class="value"><?php echo htmlspecialchars((string)$data['serial_no']); ?></div></div>
</div>
<p class="text-muted">This certificate is authentic and matches the original record in the school system.</p>
<?php else: ?>
<p class="text-danger">Certificate verification failed. The document may be invalid or altered.</p>
<?php endif; ?>
</div>
</div>
</body>
</html>
