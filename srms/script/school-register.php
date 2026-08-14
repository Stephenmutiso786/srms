<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');
require_once('const/public_media.php');

$schoolTitle = (defined('WBName') && WBName !== '') ? WBName : APP_NAME;
$schoolName = trim((string)($_POST['school_name'] ?? ''));
$schoolAdminEmail = trim((string)($_POST['admin_email'] ?? ''));
$schoolAdminPassword = trim((string)($_POST['admin_password'] ?? ''));
$schoolPhone = trim((string)($_POST['phone'] ?? ''));
$schoolAddress = trim((string)($_POST['address'] ?? ''));
$resultSystem = (int)($_POST['result_system'] ?? 1);
$allowResults = (int)($_POST['allow_results'] ?? 1);
$packageTier = trim((string)($_POST['package_tier'] ?? 'elimu_hub'));
$signupReply = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	try {
		$conn = app_db();
		$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		if (!app_table_exists($conn, 'tbl_school')) {
			throw new RuntimeException('School table is not available.');
		}
		if ($schoolName === '' || $schoolAdminEmail === '' || $schoolAdminPassword === '') {
			throw new RuntimeException('School name, admin email, and password are required.');
		}
		$packageTier = in_array($packageTier, ['elimu_hub', 'elimu_hub_pro'], true) ? $packageTier : 'elimu_hub';
		$hash = password_hash($schoolAdminPassword, PASSWORD_DEFAULT);
		$conn->beginTransaction();

		$schoolLogo = 'school_logo.png';
		$stmt = $conn->prepare('INSERT INTO tbl_school (name, logo, result_system, allow_results, package_tier, term_start_date, term_end_date, is_locked, sms_balance, support_plan, mpesa_enabled) VALUES (?, ?, ?, ?, ?, NULL, NULL, 0, 0, ?, 1)');
		$stmt->execute([$schoolName, $schoolLogo, $resultSystem === 1 ? 1 : 0, $allowResults === 1 ? 1 : 0, $packageTier, $packageTier === 'elimu_hub_pro' ? 'pro' : 'basic']);
		$schoolId = (int)$conn->lastInsertId();

		if ($schoolId < 1) {
			throw new RuntimeException('Failed to create school.');
		}

		if (app_table_exists($conn, 'tbl_classes') && !app_column_exists($conn, 'tbl_classes', 'school_id')) {
			$conn->exec("ALTER TABLE tbl_classes ADD COLUMN school_id int DEFAULT NULL");
		}
		if (app_table_exists($conn, 'tbl_subjects') && !app_column_exists($conn, 'tbl_subjects', 'school_id')) {
			$conn->exec("ALTER TABLE tbl_subjects ADD COLUMN school_id int DEFAULT NULL");
		}
		if (app_table_exists($conn, 'tbl_staff') && !app_column_exists($conn, 'tbl_staff', 'school_id')) {
			$conn->exec("ALTER TABLE tbl_staff ADD COLUMN school_id varchar(50) DEFAULT NULL");
		}

		$seed = app_seed_school_workspace($conn, $schoolId, $schoolName, false);
		$ownerEmail = $schoolAdminEmail;
		$ownerName = preg_split('/\s+/', trim($schoolName), 2);
		$firstName = $ownerName[0] ?: 'School';
		$lastName = $ownerName[1] ?? 'Admin';
		$ownerSchoolId = isset($seed['admin_email']) ? (string)$seed['admin_email'] : '';
		$ownerStmt = $conn->prepare('INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id, force_password_change) VALUES (?,?,?,?,?,?,?,?,?)');
		$ownerStmt->execute([$firstName, $lastName, 'Male', $ownerEmail, $hash, 0, 1, $ownerSchoolId !== '' ? $ownerSchoolId : app_generate_school_id($conn, 'ADM', (int)date('Y'), 'tbl_staff'), 1]);

		$conn->commit();
		$_SESSION['reply'] = [['success', 'School account created successfully. Use the portal login with the admin email and password you entered.']];
		header('location:index.php');
		exit;
	} catch (Throwable $e) {
		if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
			$conn->rollBack();
		}
		$signupReply = $e->getMessage();
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo htmlspecialchars($schoolTitle); ?> | Register School</title>
	<link rel="stylesheet" href="css/main.css">
	<link rel="stylesheet" href="cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
	<style>
		body { margin:0; font-family: "Segoe UI", sans-serif; background: linear-gradient(180deg, #f7fbff, #eef5ff); }
		.wrap { min-height:100vh; display:grid; grid-template-columns: 1.1fr .9fr; }
		.panel { padding: 2rem; }
		.hero { background: linear-gradient(160deg, #0b3b5a, #2196c8); color:#fff; display:flex; flex-direction:column; justify-content:center; }
		.card { background:#fff; border-radius:20px; padding:1.2rem; box-shadow:0 18px 40px rgba(15,45,74,.12); max-width:680px; }
		.form-control, select { width:100%; padding:.9rem; border:1px solid #d9e3ee; border-radius:12px; margin:.4rem 0 1rem; font:inherit; }
		.btn { display:inline-flex; align-items:center; justify-content:center; gap:.45rem; padding:.9rem 1.1rem; border:0; border-radius:999px; font-weight:800; cursor:pointer; text-decoration:none; }
		.btn-primary { background:#0ea5e9; color:#fff; }
		.btn-secondary { background:#e9f7ff; color:#0b3b5a; }
		.alert { padding:.8rem 1rem; border-radius:12px; margin-bottom:1rem; }
		.alert-danger { background:#ffecec; color:#8f1f1f; }
		.alert-success { background:#e9f8ef; color:#1d6b39; }
		@media (max-width: 900px) { .wrap { grid-template-columns:1fr; } }
	</style>
</head>
<body>
	<div class="wrap">
		<div class="panel hero">
			<h1 style="font-size:clamp(2rem,4vw,3.5rem); margin:0 0 .75rem;">Create Your School Account</h1>
			<p style="max-width: 36rem; line-height:1.7; font-size:1.05rem;">Register your school, create the first admin account, and start with a real seeded workspace for classes, subjects, timetable setup, exams, and communication.</p>
			<div style="margin-top:1rem; display:flex; gap:.75rem; flex-wrap:wrap;">
				<a class="btn btn-secondary" href="index.php"><i class="bi bi-box-arrow-in-right"></i> Platform Login</a>
				<a class="btn btn-secondary" href="index.php?redirect_to=elearning"><i class="bi bi-mortarboard"></i> E-Learning Login</a>
				<a class="btn btn-secondary" href="school_main_website.php"><i class="bi bi-globe2"></i> Back to Home</a>
			</div>
		</div>
		<div class="panel" style="display:flex; align-items:center; justify-content:center;">
			<form class="card" method="post" action="core/public_school_register.php">
				<h2 style="margin-top:0;">Register School</h2>
				<?php if ($signupReply !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($signupReply); ?></div><?php endif; ?>
				<label>School Name</label>
				<input class="form-control" name="school_name" value="<?php echo htmlspecialchars($schoolName); ?>" required>
				<label>Admin Email</label>
				<input class="form-control" type="email" name="admin_email" value="<?php echo htmlspecialchars($schoolAdminEmail); ?>" required>
				<label>Temporary Password</label>
				<input class="form-control" type="text" name="admin_password" value="<?php echo htmlspecialchars($schoolAdminPassword); ?>" required>
				<label>Phone</label>
				<input class="form-control" name="phone" value="<?php echo htmlspecialchars($schoolPhone); ?>">
				<label>Address</label>
				<input class="form-control" name="address" value="<?php echo htmlspecialchars($schoolAddress); ?>">
				<label>Package</label>
				<select class="form-control" name="package_tier">
					<option value="elimu_hub" <?php echo $packageTier === 'elimu_hub' ? 'selected' : ''; ?>>Elimu Hub</option>
					<option value="elimu_hub_pro" <?php echo $packageTier === 'elimu_hub_pro' ? 'selected' : ''; ?>>Elimu Hub Pro</option>
				</select>
				<label>Result System</label>
				<select class="form-control" name="result_system">
					<option value="1">Division</option>
					<option value="0">Average</option>
				</select>
				<label>Allow Results</label>
				<select class="form-control" name="allow_results">
					<option value="1">Yes</option>
					<option value="0">No</option>
				</select>
				<button class="btn btn-primary" type="submit">Create School</button>
			</form>
		</div>
	</div>
</body>
</html>
