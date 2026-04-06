<?php
session_start();
require_once('db/config.php');
require_once('const/school.php');

$setupToken = getenv('SETUP_TOKEN') ?: '';
$providedToken = $_GET['token'] ?? '';

function render_page(string $title, string $bodyHtml): void {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" type="text/css" href="css/main.css">
  <link rel="icon" href="images/icon.ico">
  <title><?php echo htmlspecialchars($title); ?></title>
</head>
<body class="app sidebar-mini">
  <section class="login-content" style="min-height:100vh;">
    <div class="login-box" style="max-width:520px;">
      <div class="p-4">
        <?php echo $bodyHtml; ?>
      </div>
    </div>
  </section>
</body>
</html>
<?php
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$staffCount = (int)$conn->query("SELECT COUNT(*) FROM tbl_staff")->fetchColumn();
	if ($staffCount > 0) {
		header("location:./");
		exit;
	}

	if ($setupToken === '' || !hash_equals($setupToken, $providedToken)) {
		http_response_code(403);
		render_page(APP_NAME . " - Setup", "<h3>Setup locked</h3><p>Set <code>SETUP_TOKEN</code> on your server and open <code>/setup?token=YOUR_TOKEN</code>.</p>");
		exit;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$fname = trim($_POST['fname'] ?? '');
		$lname = trim($_POST['lname'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$gender = trim($_POST['gender'] ?? 'Male');
		$password = (string)($_POST['password'] ?? '');
		$schoolName = trim($_POST['school_name'] ?? '');

		if ($fname === '' || $lname === '' || $email === '' || $password === '') {
			render_page(APP_NAME . " - Setup", "<h3>Create Admin</h3><p class=\"text-danger\">All fields are required.</p>");
			exit;
		}

		$hash = password_hash($password, PASSWORD_DEFAULT);

		$stmt = $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?,?,?,?,?,?,?)");
		$stmt->execute([$fname, $lname, $gender, $email, $hash, 0, 1]);

		if ($schoolName !== '') {
			$schoolCount = (int)$conn->query("SELECT COUNT(*) FROM tbl_school")->fetchColumn();
			if ($schoolCount < 1) {
				$stmt = $conn->prepare("INSERT INTO tbl_school (name, logo, result_system, allow_results) VALUES (?,?,?,?)");
				$stmt->execute([$schoolName, 'school_logo1711003619.png', 1, 1]);
			}
		}

		render_page(APP_NAME . " - Setup", "<h3>Setup complete</h3><p>Admin account created. You can now go to the login page.</p><p><a class=\"btn btn-primary\" href=\"./\">Go to Login</a></p>");
		exit;
	}

	$form = '
	<h3 class="mb-3">Initial Setup</h3>
	<p class="text-muted mb-4">Create your first administrator account. This page works only when there are no staff accounts in the database.</p>
	<form method="POST" autocomplete="off">
	  <div class="mb-3">
	    <label class="form-label">School Name (optional)</label>
	    <input class="form-control" name="school_name" type="text" placeholder="Elimu Hub Academy">
	  </div>
	  <div class="mb-3">
	    <label class="form-label">First Name</label>
	    <input class="form-control" name="fname" type="text" required>
	  </div>
	  <div class="mb-3">
	    <label class="form-label">Last Name</label>
	    <input class="form-control" name="lname" type="text" required>
	  </div>
	  <div class="mb-3">
	    <label class="form-label">Email</label>
	    <input class="form-control" name="email" type="email" required>
	  </div>
	  <div class="mb-3">
	    <label class="form-label">Password</label>
	    <input class="form-control" name="password" type="password" required>
	  </div>
	  <div class="mb-3">
	    <label class="form-label">Gender</label>
	    <select class="form-control" name="gender">
	      <option value="Male">Male</option>
	      <option value="Female">Female</option>
	    </select>
	  </div>
	  <div class="d-grid">
	    <button class="btn btn-primary" type="submit">Create Admin</button>
	  </div>
	</form>';

	render_page(APP_NAME . " - Setup", $form);
} catch (PDOException $e) {
	http_response_code(500);
	render_page(APP_NAME . " - Setup", "<h3>Database error</h3><p>" . htmlspecialchars($e->getMessage()) . "</p>");
}

