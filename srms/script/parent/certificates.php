<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '4') { header('location:../'); exit; }

$rows = [];
try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);
    if (app_table_exists($conn, 'tbl_parent_students')) {
        $stmt = $conn->prepare('SELECT cert.id, cert.title, cert.serial_no, cert.issue_date, cert.status, cert.verification_code,
            concat_ws(\' \' , st.fname, st.mname, st.lname) AS student_name
            FROM tbl_certificates cert
            JOIN tbl_parent_students ps ON ps.student_id = cert.student_id
            JOIN tbl_students st ON st.id = cert.student_id
            WHERE ps.parent_id = ?
            ORDER BY cert.id DESC');
        $stmt->execute([(int)$account_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $rows = [];
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<title><?php echo APP_NAME; ?> - Certificates</title>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css"><link rel="icon" href="images/icon.ico">
</head><body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);\"><?php echo APP_NAME; ?></a><a class="app-sidebar__toggle" href="#" data-toggle="sidebar"></a></header>
<aside class="app-sidebar"><ul class="app-menu"><li><a class="app-menu__item" href="parent"><span class="app-menu__label">Dashboard</span></a></li><li><a class="app-menu__item" href="parent/report_card"><span class="app-menu__label">Report Card</span></a></li><li><a class="app-menu__item active" href="parent/certificates"><span class="app-menu__label">Certificates</span></a></li></ul></aside>
<main class="app-content"><div class="app-title"><div><h1>Children Certificates</h1></div></div>
<div class="tile"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Student</th><th>Type</th><th>Serial</th><th>Issue Date</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row): ?>
<tr><td><?php echo htmlspecialchars((string)$row['student_name']); ?></td><td><?php echo htmlspecialchars((string)$row['title']); ?></td><td><?php echo htmlspecialchars((string)$row['serial_no']); ?></td><td><?php echo htmlspecialchars((string)$row['issue_date']); ?></td><td><a class="btn btn-sm btn-primary" target="_blank" href="certificate_pdf?id=<?php echo (int)$row['id']; ?>">Download</a> <a class="btn btn-sm btn-outline-secondary" target="_blank" href="verify_certificate?code=<?php echo urlencode((string)$row['verification_code']); ?>">Verify</a></td></tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="5" class="text-center text-muted">No certificates issued yet.</td></tr><?php endif; ?>
</tbody></table></div></div></main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body></html>
