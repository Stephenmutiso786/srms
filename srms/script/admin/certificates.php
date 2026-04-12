<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/rbac.php');
require_once('const/certificate_engine.php');

if ($res !== '1' || $level !== '0') { header('location:../'); exit; }
app_require_permission('report.generate', 'admin');

$students = [];
$certificates = [];
$types = app_certificate_types();

try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);

    $stmt = $conn->prepare('SELECT st.id, st.school_id, concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name, c.name AS class_name
        FROM tbl_students st
        LEFT JOIN tbl_classes c ON c.id = st.class
        ORDER BY st.fname, st.lname
        LIMIT 500');
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $conn->prepare('SELECT cert.id, cert.certificate_type, cert.title, cert.serial_no, cert.issue_date, cert.status, cert.verification_code,
        concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name, st.school_id, c.name AS class_name
        FROM tbl_certificates cert
        JOIN tbl_students st ON st.id = cert.student_id
        LEFT JOIN tbl_classes c ON c.id = cert.class_id
        ORDER BY cert.id DESC
        LIMIT 300');
    $stmt->execute();
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $_SESSION['reply'] = array(array('danger', 'Failed to load certificates module.'));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - Certificates</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);\"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a></header>
<?php include('admin/partials/sidebar.php'); ?>
<main class="app-content">
<div class="app-title"><div><h1>School Certificates</h1><p>Generate leaving and school certificates with verification QR codes.</p></div></div>

<div class="tile mb-3">
<h3 class="tile-title">Generate Certificate</h3>
<form class="row g-3" method="POST" action="admin/core/generate_certificate">
<div class="col-md-4">
<label class="form-label">Certificate Type</label>
<select class="form-control" name="certificate_type" required>
<?php foreach ($types as $key => $label): ?>
<option value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($label); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-4">
<label class="form-label">Student</label>
<select class="form-control" name="student_id" required>
<option value="" disabled selected>Select student</option>
<?php foreach ($students as $student): ?>
<option value="<?php echo htmlspecialchars((string)$student['id']); ?>"><?php echo htmlspecialchars((string)$student['student_name'] . ' (' . ((string)$student['school_id'] !== '' ? (string)$student['school_id'] : (string)$student['id']) . ')'); ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-2">
<label class="form-label">Issue Date</label>
<input class="form-control" type="date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
</div>
<div class="col-md-12">
<label class="form-label">Notes</label>
<textarea class="form-control" name="notes" rows="2" placeholder="Optional notes/remarks"></textarea>
</div>
<div class="col-md-12 d-grid">
<button class="btn btn-primary" type="submit"><i class="bi bi-award me-1"></i>Generate Certificate</button>
</div>
</form>
</div>

<div class="tile">
<h3 class="tile-title">Issued Certificates</h3>
<div class="table-responsive">
<table class="table table-hover">
<thead><tr><th>#</th><th>Student</th><th>Class</th><th>Type</th><th>Serial</th><th>Issue Date</th><th>Verify Code</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($certificates as $row): ?>
<tr>
<td><?php echo (int)$row['id']; ?></td>
<td><?php echo htmlspecialchars((string)$row['student_name']); ?><div class="small text-muted"><?php echo htmlspecialchars((string)($row['school_id'] ?: '')); ?></div></td>
<td><?php echo htmlspecialchars((string)($row['class_name'] ?? '')); ?></td>
<td><?php echo htmlspecialchars((string)$row['title']); ?></td>
<td><?php echo htmlspecialchars((string)$row['serial_no']); ?></td>
<td><?php echo htmlspecialchars((string)$row['issue_date']); ?></td>
<td class="small text-muted"><?php echo htmlspecialchars((string)$row['verification_code']); ?></td>
<td>
<a class="btn btn-sm btn-primary" target="_blank" href="certificate_pdf?id=<?php echo (int)$row['id']; ?>">Download</a>
<a class="btn btn-sm btn-outline-secondary" target="_blank" href="verify_certificate?code=<?php echo urlencode((string)$row['verification_code']); ?>">Verify</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$certificates): ?>
<tr><td colspan="8" class="text-center text-muted">No certificates issued yet.</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
<?php require_once('const/check-reply.php'); ?>
</body>
</html>
