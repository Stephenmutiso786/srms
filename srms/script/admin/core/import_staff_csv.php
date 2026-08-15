<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('staff.manage', '../import_export');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../import_export");
	exit;
}

if (empty($_FILES['file']['tmp_name'])) {
	$_SESSION['reply'] = array (array("danger", "Upload a CSV file."));
	header("location:../import_export");
	exit;
}
$uploadCheck = app_validate_upload($_FILES['file'], ['csv']);
if (!$uploadCheck['ok']) {
	$_SESSION['reply'] = array (array("danger", $uploadCheck['message']));
	header("location:../import_export");
	exit;
}

$total = 0;
$success = 0;
$failed = 0;
$details = [];

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_staff_password_policy_columns($conn);
	app_require_unlocked('staff', '../import_export');

	$handle = fopen($_FILES['file']['tmp_name'], 'r');
	if (!$handle) {
		throw new RuntimeException("Failed to read file.");
	}

	$headers = fgetcsv($handle);
	if (!$headers) {
		throw new RuntimeException("Missing CSV headers.");
	}
	$headers = array_map(function ($h) { return strtolower(trim((string)$h)); }, $headers);

	$idx = function ($key) use ($headers) {
		$pos = array_search($key, $headers, true);
		return $pos === false ? -1 : $pos;
	};
	$get = function (array $row, array $keys, string $default = '') use ($idx) {
		foreach ($keys as $key) {
			$pos = $idx($key);
			if ($pos >= 0 && array_key_exists($pos, $row)) {
				return (string)$row[$pos];
			}
		}
		return $default;
	};

	while (($row = fgetcsv($handle)) !== false) {
		$total++;
		$fname = trim($get($row, ['fname', 'first_name', 'firstname', 'first name']));
		$lname = trim($get($row, ['lname', 'last_name', 'lastname', 'last name', 'surname']));
		$gender = trim($get($row, ['gender', 'sex'], 'Male'));
		$email = trim($get($row, ['email', 'email_address']));
		$password = trim($get($row, ['password']));

		if ($fname === '' || $lname === '') {
			$failed++;
			$details[] = "Row $total missing first name or last name.";
			continue;
		}
		if ($email === '') {
			$email = strtolower(preg_replace('/[^a-z0-9]+/', '.', $fname.'.'.$lname.'.'.$total)).'@teachers.local';
		}
		$email = trim($email, '.');

		$pwd = $password !== '' ? $password : (getenv('DEFAULT_STAFF_PASSWORD') ?: 'Password123');
		$hash = password_hash($pwd, PASSWORD_DEFAULT);

		try {
			$stmt = $conn->prepare("SELECT 1 FROM tbl_staff WHERE email = ? LIMIT 1");
			$stmt->execute([$email]);
			if ($stmt->fetchColumn()) {
				$failed++;
				$details[] = "Row $total duplicate email.";
				continue;
			}

			if (app_column_exists($conn, 'tbl_staff', 'school_id')) {
				$schoolId = app_generate_school_id($conn, 'TCH', (int)date('Y'), 'tbl_staff');
				$stmt = app_column_exists($conn, 'tbl_staff', 'force_password_change')
					? $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id, force_password_change) VALUES (?,?,?,?,?,?,?,?,?)")
					: $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, school_id) VALUES (?,?,?,?,?,?,?,?)");
				$params = [$fname, $lname, $gender, $email, $hash, 2, 1, $schoolId];
				if (app_column_exists($conn, 'tbl_staff', 'force_password_change')) { $params[] = 1; }
				$stmt->execute($params);
			} else {
				$stmt = app_column_exists($conn, 'tbl_staff', 'force_password_change')
					? $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status, force_password_change) VALUES (?,?,?,?,?,?,?,?)")
					: $conn->prepare("INSERT INTO tbl_staff (fname, lname, gender, email, password, level, status) VALUES (?,?,?,?,?,?,?)");
				$params = [$fname, $lname, $gender, $email, $hash, 2, 1];
				if (app_column_exists($conn, 'tbl_staff', 'force_password_change')) { $params[] = 1; }
				$stmt->execute($params);
			}
			$success++;
		} catch (Throwable $e) {
			$failed++;
			$details[] = "Row $total error: ".$e->getMessage();
		}
	}

	fclose($handle);

	if (app_table_exists($conn, 'tbl_import_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_import_logs (import_type, total, success, failed, details, created_by) VALUES (?,?,?,?,?,?)");
		$stmt->execute(['teachers', $total, $success, $failed, implode("\n", $details), $account_id]);
	}

	$_SESSION['reply'] = array (array("success", "Import done. Total: $total, Success: $success, Failed: $failed"));
	header("location:../import_export");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Import failed: ".$e->getMessage()));
	header("location:../import_export");
}
