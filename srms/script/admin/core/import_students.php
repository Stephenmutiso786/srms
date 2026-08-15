<?php
session_start();
require_once(__DIR__ . '/../../db/config.php');
require_once(__DIR__ . '/../../const/phpexcel/SimpleXLSX.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$class = trim((string)($_POST['class'] ?? ''));
$pasteInput = trim((string)($_POST['paste_students'] ?? ''));
$hasPaste = $pasteInput !== '';
$hasFile = !empty($_FILES['file']['tmp_name']);

if (!$hasPaste && !$hasFile) {
	$_SESSION['reply'] = array(array("danger", "Paste students or upload an Excel file."));
	header("location:../import_students");
	exit;
}

function app_next_available_student_registration_number(PDO $conn): string
{
	$start = (int)app_setting_get($conn, 'admission_start_number', '1');
	if ($start < 1) {
		$start = 1;
	}

	$existingIds = [];
	foreach (['tbl_students', 'tbl_staff'] as $table) {
		if (!app_table_exists($conn, $table)) {
			continue;
		}
		$stmt = $conn->prepare("SELECT id FROM {$table}");
		$stmt->execute();
		foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $value) {
			$text = trim((string)$value);
			if ($text !== '') {
				$existingIds[$text] = true;
			}
		}
	}

	$next = $start;
	while (isset($existingIds[(string)$next])) {
		$next++;
	}
	return (string)$next;
}

function import_student_row(PDO $conn, string $class, array $row, int $total, array &$details, int &$success, int &$failed): void
{
	$fname = ucfirst(trim((string)($row['fname'] ?? '')));
	$mname = ucfirst(trim((string)($row['mname'] ?? '')));
	$lname = ucfirst(trim((string)($row['lname'] ?? '')));
	$gender = trim((string)($row['gender'] ?? 'Male'));
	$email = trim((string)($row['email'] ?? ''));
	$plainPassword = trim((string)($row['password'] ?? '12345678'));

	if ($gender === '') {
		$gender = 'Male';
	}
	if ($plainPassword === '') {
		$plainPassword = '12345678';
	}

	if ($fname === '' || $lname === '' || $class === '') {
		$failed++;
		$details[] = "Row $total missing first name, last name, or class.";
		return;
	}

	$reg_no = app_next_available_student_registration_number($conn);
	if ($email === '') {
		$email = app_generate_student_login_email($conn, $fname, $lname, $class);
	}

	$isPgsql = (defined('DBDriver') && DBDriver === 'pgsql');
	$idExpr = $isPgsql ? 'id::text' : 'id';
	$emailClause = $email !== '' ? " OR email = ?" : "";
	$studentSql = "SELECT 1 FROM tbl_students WHERE {$idExpr} = ?{$emailClause} LIMIT 1";
	$staffSql = "SELECT 1 FROM tbl_staff WHERE {$idExpr} = ?{$emailClause} LIMIT 1";

	$stmt = $conn->prepare($studentSql);
	$stmt->execute($email !== '' ? [$reg_no, $email] : [$reg_no]);
	if ($stmt->fetchColumn()) {
		$failed++;
		$details[] = "Row $total duplicate email or registration number.";
		return;
	}

	$stmt = $conn->prepare($staffSql);
	$stmt->execute($email !== '' ? [$reg_no, $email] : [$reg_no]);
	if ($stmt->fetchColumn()) {
		$failed++;
		$details[] = "Row $total duplicate email or registration number.";
		return;
	}

	if (preg_match('~[0-9]+~', $fname) || preg_match('~[0-9]+~', $mname) || preg_match('~[0-9]+~', $lname)) {
		$failed++;
		$details[] = "Row $total has numbers in a name field.";
		return;
	}

	$pass = password_hash($plainPassword, PASSWORD_DEFAULT);
	$img = 'DEFAULT';

	if (app_column_exists($conn, 'tbl_students', 'school_id')) {
		$schoolId = app_generate_school_id($conn, 'STD', (int)date('Y'), 'tbl_students');
		if (app_column_exists($conn, 'tbl_students', 'tenant_school_id')) {
			$stmt = $conn->prepare("INSERT INTO tbl_students (id, school_id, tenant_school_id, fname, mname, lname, gender, email, class, password, display_image) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
			$stmt->execute([$reg_no, $schoolId, function_exists('app_current_school_id') ? app_current_school_id() : null, $fname, $mname, $lname, $gender, $email, $class, $pass, $img]);
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_students (id, school_id, fname, mname, lname, gender, email, class, password, display_image) VALUES (?,?,?,?,?,?,?,?,?,?)");
			$stmt->execute([$reg_no, $schoolId, $fname, $mname, $lname, $gender, $email, $class, $pass, $img]);
		}
	} else {
		$stmt = $conn->prepare("INSERT INTO tbl_students (id, fname, mname, lname, gender, email, class, password, display_image) VALUES (?,?,?,?,?,?,?,?,?)");
		$stmt->execute([$reg_no, $fname, $mname, $lname, $gender, $email, $class, $pass, $img]);
	}

	$success++;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$total = 0;
	$success = 0;
	$failed = 0;
	$details = [];
	$st_rec = 0;

	if ($hasPaste) {
		$lines = preg_split("/\r\n|\n|\r/", $pasteInput);
		foreach ($lines as $line) {
			$line = trim((string)$line);
			if ($line === '') {
				continue;
			}

			$total++;
			$parts = preg_split('/\s*\|\s*|\s*,\s*/', $line);
			if (!$parts || count($parts) === 1) {
				$parts = preg_split('/\s+/', $line);
			}
			$parts = array_values(array_filter(array_map('trim', $parts), static function ($value) {
				return $value !== '';
			}));

			$row = [
				'fname' => $parts[0] ?? '',
				'mname' => '',
				'lname' => '',
				'gender' => 'Male',
				'email' => '',
				'password' => '12345678',
			];

			if (count($parts) === 2) {
				$row['lname'] = $parts[1] ?? '';
			} elseif (count($parts) === 3) {
				$row['mname'] = $parts[1] ?? '';
				$row['lname'] = $parts[2] ?? '';
			} elseif (count($parts) >= 4) {
				$row['mname'] = $parts[1] ?? '';
				$row['lname'] = $parts[2] ?? '';
				$row['gender'] = $parts[3] ?? 'Male';
				$row['email'] = $parts[4] ?? '';
				$row['password'] = $parts[5] ?? '12345678';
			}

			import_student_row($conn, $class, $row, $total, $details, $success, $failed);
		}
	} else {
		$uploadCheck = app_validate_upload($_FILES['file'], ['xlsx', 'xls', 'csv']);
		if (!$uploadCheck['ok']) {
			$_SESSION['reply'] = array(array("danger", $uploadCheck['message']));
			header("location:../import_students");
			exit;
		}

		$file = $_FILES['file']['tmp_name'];
		$xlsx = SimpleXLSX::parse($file);
		if (!$xlsx) {
			$_SESSION['reply'] = array(array("danger", "Import failed: " . SimpleXLSX::parseError()));
			header("location:../import_students");
			exit;
		}

		foreach ($xlsx->rows() as $r) {
			if ($st_rec === 0) {
				$st_rec++;
				continue;
			}

			$total++;
			$cells = array_pad($r, 6, '');
			$row = [
				'fname' => (string)$cells[0],
				'lname' => (string)$cells[1],
				'mname' => (string)$cells[2],
				'gender' => (string)$cells[3],
				'email' => (string)$cells[4],
				'password' => (string)$cells[5],
			];
			import_student_row($conn, $class, $row, $total, $details, $success, $failed);
			$st_rec++;
		}
	}

	if ($success > 0) {
		$_SESSION['student_list'] = [$class];
	}

	$message = "Import done. Total: $total, Success: $success, Failed: $failed";
	if ($failed > 0 && !empty($details)) {
		$message .= ". " . implode(' ', array_slice($details, 0, 3));
	}

	$_SESSION['reply'] = array(array($success > 0 ? "success" : "danger", $message));
	header("location:" . ($success > 0 ? "../students" : "../import_students"));
} catch (Throwable $e) {
	error_log("[" . __FILE__ . ":" . __LINE__ . " IMPORT] " . $e->getMessage());
	$_SESSION['reply'] = array(array("danger", "Import failed: " . $e->getMessage()));
	header("location:../import_students");
	exit;
}
?>
