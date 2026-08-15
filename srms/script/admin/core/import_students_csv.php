<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('students.manage', '../import_export');

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
$importedClassIds = [];
$defaultClassId = (int)($_POST['class_id'] ?? 0);

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_require_unlocked('students', '../import_export');

	$classesMap = [];
	$stmt = $conn->prepare("SELECT id, name FROM tbl_classes");
	$stmt->execute();
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$classesMap[strtolower((string)$row['name'])] = (int)$row['id'];
	}

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
		$studentId = $get($row, ['student_id', 'admission_number', 'adm_no', 'reg_no', 'id']);
		$fname = $get($row, ['fname', 'first_name', 'firstname', 'first name']);
		$mname = $get($row, ['mname', 'middle_name', 'middlename', 'middle name']);
		$lname = $get($row, ['lname', 'last_name', 'lastname', 'last name', 'surname']);
		$gender = $get($row, ['gender', 'sex'], 'Male');
		$email = $get($row, ['email', 'email_address']);
		$classIdRaw = $get($row, ['class_id']);
		$className = $get($row, ['class_name', 'class']);

		$studentId = trim((string)$studentId);
		$fname = trim((string)$fname);
		$mname = trim((string)$mname);
		$lname = trim((string)$lname);
		$gender = trim((string)$gender);
		$email = trim((string)$email);
		$classIdRaw = trim((string)$classIdRaw);
		$className = trim((string)$className);

		if ($studentId === '') {
			$studentId = app_next_student_registration_number($conn);
		}

		if ($fname === '' || $lname === '') {
			$failed++;
			$details[] = "Row $total missing first name or last name.";
			continue;
		}
		if ($email === '') {
			$email = app_generate_student_login_email($conn, $fname, $lname, (string)$classId);
		}

		$classId = 0;
		if ($classIdRaw !== '') {
			$classId = (int)$classIdRaw;
		} elseif ($className !== '') {
			$classId = $classesMap[strtolower($className)] ?? 0;
		} elseif ($defaultClassId > 0) {
			$classId = $defaultClassId;
		}

		if ($classId < 1) {
			$failed++;
			$details[] = "Row $total has invalid class.";
			continue;
		}

		$pwd = trim((string)(getenv('DEFAULT_STUDENT_PASSWORD') ?: ''));
		if ($pwd === '') {
			$pwd = '12345678';
		}
		$hash = password_hash($pwd, PASSWORD_DEFAULT);

		try {
			$duplicateSql = "SELECT 1 FROM tbl_students WHERE id = ?";
			$params = [$studentId];
			if ($email !== '') {
				$duplicateSql .= " OR email = ?";
				$params[] = $email;
			}
			$duplicateSql .= " LIMIT 1";
			$stmt = $conn->prepare($duplicateSql);
			$stmt->execute($params);
			if ($stmt->fetchColumn()) {
				$failed++;
				$details[] = "Row $total duplicate student ID".($email !== '' ? " or email" : "").".";
				continue;
			}

			if (app_column_exists($conn, 'tbl_students', 'school_id')) {
				$schoolId = app_generate_school_id($conn, 'STD', (int)date('Y'), 'tbl_students');
				$stmt = $conn->prepare("INSERT INTO tbl_students (id, school_id, fname, mname, lname, gender, email, class, password, level, status) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
				$stmt->execute([$studentId, $schoolId, $fname, $mname, $lname, $gender, $email, $classId, $hash, 3, 1]);
			} else {
				$stmt = $conn->prepare("INSERT INTO tbl_students (id, fname, mname, lname, gender, email, class, password, level, status) VALUES (?,?,?,?,?,?,?,?,?,?)");
				$stmt->execute([$studentId, $fname, $mname, $lname, $gender, $email, $classId, $hash, 3, 1]);
			}
			$success++;
			$importedClassIds[(string)$classId] = (string)$classId;
		} catch (Throwable $e) {
			$failed++;
			$details[] = "Row $total error: ".$e->getMessage();
		}
	}

	fclose($handle);

	if (app_table_exists($conn, 'tbl_import_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_import_logs (import_type, total, success, failed, details, created_by) VALUES (?,?,?,?,?,?)");
		$stmt->execute(['students', $total, $success, $failed, implode("\n", $details), $account_id]);
	}

	$message = "Import done. Total: $total, Success: $success, Failed: $failed";
	if ($failed > 0 && !empty($details)) {
		$message .= ". ".implode(' ', array_slice($details, 0, 3));
	}
	if ($success > 0) {
		$_SESSION['student_list'] = array_values($importedClassIds);
	}
	$_SESSION['reply'] = array (array($success > 0 ? "success" : "danger", $message));
	header("location:".($success > 0 ? "../students" : "../import_export"));
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Import failed: ".$e->getMessage()));
	header("location:../import_export");
}
