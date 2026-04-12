<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');

if ($res !== '1' || $level !== '3') { header('location:../'); exit; }

$rows = [];
try {
    $conn = app_db();
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    app_ensure_certificates_table($conn);
    $stmt = $conn->prepare('SELECT id, title, serial_no, issue_date, verification_code, status FROM tbl_certificates WHERE student_id = ? ORDER BY id DESC');
    $stmt->execute([(string)$account_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
<aside class="app-sidebar"><ul class="app-menu"><li><a class="app-menu__item" href="student"><span class="app-menu__label">Dashboard</span></a></li><li><a class="app-menu__item" href="student/report_card"><span class="app-menu__label">Report Card</span></a></li><li><a class="app-menu__item active" href="student/certificates"><span class="app-menu__label">Certificates</span></a></li></ul></aside>
<main class="app-content"><div class="app-title"><div><h1>My Certificates</h1></div></div>
<div class="tile"><div class="table-responsive"><table class="table table-hover"><thead><tr><th>Type</th><th>Serial</th><th>Issue Date</th><th>Status</th><th>Action</th></tr></thead><tbody>
<?php foreach($rows as $row): ?>
<tr><td><?php echo htmlspecialchars((string)$row['title']); ?></td><td><?php echo htmlspecialchars((string)$row['serial_no']); ?></td><td><?php echo htmlspecialchars((string)$row['issue_date']); ?></td><td><?php echo htmlspecialchars((string)$row['status']); ?></td><td><a class="btn btn-sm btn-primary" target="_blank" href="certificate_pdf?id=<?php echo (int)$row['id']; ?>">Download</a> <a class="btn btn-sm btn-outline-secondary" target="_blank" href="verify_certificate?code=<?php echo urlencode((string)$row['verification_code']); ?>">Verify</a></td></tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="5" class="text-center text-muted">No certificates issued yet.</td></tr><?php endif; ?>
</tbody></table></div></div></main>
<script src="js/jquery-3.7.0.min.js"></script><script src="js/bootstrap.min.js"></script><script src="js/main.js"></script>
</body></html>
