<?php
chdir('../');
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/check_session.php');
require_once('const/id_card_engine.php');

if ($res !== "1" || $level !== "3") { header("location:../"); }

$payload = null;
$school = ['name' => WBName, 'logo' => WBLogo];
try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$payload = idcard_student_payload($conn, (string)$account_id);
	$school = idcard_school_meta($conn);
} catch (Throwable $e) {
	$payload = null;
}

$verifyUrl = $payload ? idcard_verify_url($payload['school_id']) : '#';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title><?php echo APP_NAME; ?> - My ID Card</title>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<base href="../">
<link rel="stylesheet" type="text/css" href="css/main.css">
<link rel="icon" href="images/icon.ico">
<link rel="stylesheet" type="text/css" href="cdn.jsdelivr.net/npm/bootstrap-icons%401.10.5/font/bootstrap-icons.css">
<style>
.id-shell{max-width:980px;margin:0 auto}
.id-card-wrap{display:grid;grid-template-columns:minmax(320px,430px) 1fr;gap:24px;align-items:start}
.smart-id-card{position:relative;overflow:hidden;border-radius:24px;background:linear-gradient(135deg,#0f5fa8 0%,#1784c7 24%,#f7fbff 24.2%,#f7fbff 100%);box-shadow:0 20px 45px rgba(7,33,66,.18);padding:18px;min-height:280px;color:#11304a}
.smart-id-card:before,.smart-id-card:after{content:"";position:absolute;border-radius:50%;background:rgba(15,95,168,.10)}
.smart-id-card:before{width:220px;height:220px;right:-80px;bottom:-110px}
.smart-id-card:after{width:180px;height:180px;left:-90px;bottom:-120px}
.id-header{display:flex;justify-content:space-between;align-items:flex-start;color:#fff;margin-bottom:18px}
.id-header-left{display:flex;gap:12px;align-items:center}
.id-logo{width:48px;height:48px;border-radius:50%;background:#fff;display:flex;align-items:center;justify-content:center;overflow:hidden}
.id-logo img{width:100%;height:100%;object-fit:cover}
.id-title{line-height:1.15}
.id-title .school{font-size:1.05rem;font-weight:700;letter-spacing:.03em}
.id-title .meta{font-size:.78rem;opacity:.95}
.id-badge{font-size:1.5rem;font-weight:800;letter-spacing:.04em}
.id-body{display:grid;grid-template-columns:110px 1fr;gap:16px;position:relative;z-index:1}
.id-photo{width:110px;height:128px;border-radius:18px;background:#dfe8ef;overflow:hidden;border:4px solid #fff;box-shadow:0 8px 18px rgba(0,0,0,.1)}
.id-photo img{width:100%;height:100%;object-fit:cover}
.id-photo-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f5fa8,#28a0d8);color:#fff;font-size:2rem;font-weight:700}
.id-info h3{margin:2px 0 2px;font-size:1.25rem;font-weight:700}
.id-info .sub{font-size:.9rem;color:#4f6476;margin-bottom:10px}
.id-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 14px}
.id-field{background:rgba(255,255,255,.74);border:1px solid rgba(17,48,74,.08);border-radius:14px;padding:10px 12px}
.id-field .label{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#587187;margin-bottom:4px}
.id-field .value{font-size:.95rem;font-weight:700;word-break:break-word}
.id-footer{position:relative;z-index:1;display:flex;justify-content:space-between;align-items:flex-end;margin-top:16px}
.id-code{font-size:1.1rem;font-weight:800;color:#0f5fa8;letter-spacing:.04em}
.id-year{font-size:.85rem;font-weight:700;color:#5a6f82}
.id-panel{background:#fff;border-radius:18px;padding:22px;box-shadow:0 12px 28px rgba(15,54,86,.08)}
.id-panel h4{margin-bottom:12px}
.id-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.id-note{color:#62798b;margin-bottom:0}
@media (max-width: 900px){.id-card-wrap{grid-template-columns:1fr}}
@media print{
	body.app.sidebar-mini{background:#fff}
	.app-header,.app-sidebar,.app-title,.id-panel,.app-nav{display:none!important}
	.app-content{margin-left:0;padding:0}
	.smart-id-card{box-shadow:none;margin:0 auto}
}
</style>
</head>
<body class="app sidebar-mini">
<header class="app-header"><a class="app-header__logo" href="javascript:void(0);"><?php echo APP_NAME; ?></a>
<a class="app-sidebar__toggle" href="#" data-toggle="sidebar" aria-label="Hide Sidebar"></a>
<ul class="app-nav">
<li class="dropdown"><a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Open Profile Menu"><i class="bi bi-person fs-4"></i></a>
<ul class="dropdown-menu settings-menu dropdown-menu-right">
<li><a class="dropdown-item" href="student/view"><i class="bi bi-person me-2 fs-5"></i> Profile</a></li>
<li><a class="dropdown-item" href="logout"><i class="bi bi-box-arrow-right me-2 fs-5"></i> Logout</a></li>
</ul>
</li>
</ul>
</header>
<div class="app-sidebar__overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
<div class="app-sidebar__user"><div><p class="app-sidebar__user-name"><?php echo $fname.' '.$lname; ?></p><p class="app-sidebar__user-designation">Student</p></div></div>
<ul class="app-menu">
<li><a class="app-menu__item" href="student"><i class="app-menu__icon feather icon-monitor"></i><span class="app-menu__label">Dashboard</span></a></li>
<li><a class="app-menu__item" href="student/elearning"><i class="app-menu__icon feather icon-book-open"></i><span class="app-menu__label">E-Learning</span></a></li>
<li><a class="app-menu__item" href="student/view"><i class="app-menu__icon feather icon-user"></i><span class="app-menu__label">My Profile</span></a></li>
<li><a class="app-menu__item active" href="student/id_card"><i class="app-menu__icon feather icon-credit-card"></i><span class="app-menu__label">My ID Card</span></a></li>
<li><a class="app-menu__item" href="student/report_card"><i class="app-menu__icon feather icon-file-text"></i><span class="app-menu__label">Report Card</span></a></li>
</ul>
</aside>
<main class="app-content">
<div class="app-title"><div><h1>My ID Card</h1><p class="mb-0 text-muted">Digital student identification card with print and PDF download.</p></div></div>
<div class="id-shell">
<?php if (!$payload): ?>
<div class="tile"><div class="tile-body"><p class="mb-0 text-muted">ID card information is not available right now.</p></div></div>
<?php else: ?>
<div class="id-card-wrap">
<section class="smart-id-card" id="student-id-card">
<div class="id-header">
<div class="id-header-left">
<div class="id-logo"><?php if (!empty($school['logo']) && file_exists('images/logo/' . $school['logo'])) { ?><img src="images/logo/<?php echo htmlspecialchars($school['logo']); ?>" alt="logo"><?php } else { ?><span class="fw-bold text-primary">L</span><?php } ?></div>
<div class="id-title">
<div class="school"><?php echo htmlspecialchars($school['name']); ?></div>
<div class="meta">Official Learner Identification</div>
</div>
</div>
<div class="id-badge">STUDENT ID</div>
</div>
<div class="id-body">
<div class="id-photo">
<?php if ($payload['photo_exists']) { ?><img src="<?php echo htmlspecialchars($payload['photo_path']); ?>" alt="student photo"><?php } else { ?><div class="id-photo-fallback"><?php echo htmlspecialchars($payload['initials']); ?></div><?php } ?>
</div>
<div class="id-info">
<h3><?php echo htmlspecialchars($payload['name']); ?></h3>
<div class="sub"><?php echo htmlspecialchars($payload['subtitle']); ?></div>
<div class="id-grid">
<div class="id-field"><div class="label">School ID</div><div class="value"><?php echo htmlspecialchars($payload['school_id']); ?></div></div>
<div class="id-field"><div class="label">Class</div><div class="value"><?php echo htmlspecialchars($payload['class_name']); ?></div></div>
<div class="id-field"><div class="label"><?php echo htmlspecialchars($payload['aux_label']); ?></div><div class="value"><?php echo htmlspecialchars($payload['aux_value']); ?></div></div>
<div class="id-field"><div class="label">Email</div><div class="value"><?php echo htmlspecialchars($payload['email']); ?></div></div>
</div>
</div>
</div>
<div class="id-footer">
<div>
<div class="id-code"><?php echo htmlspecialchars($payload['school_id']); ?></div>
<div class="id-year">Issued <?php echo date('Y'); ?></div>
</div>
<div class="text-end">
<div class="small text-muted">Scan / verify</div>
<div class="small"><?php echo htmlspecialchars(parse_url($verifyUrl, PHP_URL_HOST) ?: 'secure portal'); ?></div>
</div>
</div>
</section>
<aside class="id-panel">
<h4>Card Actions</h4>
<p class="id-note">This design is styled to resemble a professional printed school ID. You can print it directly or download the PDF version.</p>
<div class="id-actions">
<button class="btn btn-primary" onclick="window.print();"><i class="bi bi-printer me-2"></i>Print Card</button>
<a class="btn btn-outline-primary" href="student/id_card_pdf" target="_blank"><i class="bi bi-download me-2"></i>Download PDF</a>
<a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($verifyUrl); ?>" target="_blank"><i class="bi bi-qr-code-scan me-2"></i>Verify ID</a>
</div>
</aside>
</div>
<?php endif; ?>
</div>
</main>
<script src="js/jquery-3.7.0.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/main.js"></script>
</body>
</html>
