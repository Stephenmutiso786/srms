<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');
require_once('const/notify.php');

app_require_authentication([], ['students.manage']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header('location:../parents');
	exit;
}

$rowsRaw = trim((string)($_POST['rows'] ?? ''));
$defaultPassword = trim((string)($_POST['default_password'] ?? ''));
$status = (int)($_POST['status'] ?? 1);

if ($rowsRaw === '') {
	$_SESSION['reply'] = array(array('danger', 'Paste at least one parent row.'));
	header('location:../parents');
	exit;
}

$lines = preg_split('/\r\n|\r|\n/', $rowsRaw) ?: [];
$parsedRows = [];
foreach ($lines as $lineNumber => $line) {
	$line = trim($line);
	if ($line === '') {
		continue;
	}
	$parts = array_map('trim', str_getcsv($line));
	if (count($parts) < 5) {
		$_SESSION['reply'] = array(array('danger', 'Row ' . ($lineNumber + 1) . ' is invalid. Use: student_id, first_name, last_name, phone, email[, password]'));
		header('location:../parents');
		exit;
	}
	$parsedRows[] = [
		'student_id' => (string)$parts[0],
		'fname' => ucfirst((string)$parts[1]),
		'lname' => ucfirst((string)$parts[2]),
		'phone' => app_normalize_phone_number((string)$parts[3], (string)(getenv('APP_DEFAULT_COUNTRY_CODE') ?: '254')),
		'email' => strtolower((string)$parts[4]),
		'password' => (string)($parts[5] ?? $defaultPassword),
	];
}

if (!$parsedRows) {
	$_SESSION['reply'] = array(array('danger', 'No valid parent rows found.'));
	header('location:../parents');
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_parents') || !app_table_exists($conn, 'tbl_parent_students')) {
		throw new RuntimeException('Parent tables are not installed.');
	}

	$created = 0;
	$linked = 0;
	$skipped = [];
	$isPgsql = defined('DBDriver') && DBDriver === 'pgsql';

	foreach ($parsedRows as $row) {
		$studentId = trim($row['student_id']);
		$fname = trim($row['fname']);
		$lname = trim($row['lname']);
		$phone = trim($row['phone']);
		$email = trim($row['email']);
		$password = trim($row['password']);

		if ($studentId === '' || $fname === '' || $lname === '' || $phone === '' || $email === '' || $password === '') {
			$skipped[] = $studentId !== '' ? $studentId : 'Unknown row';
			continue;
		}

		$stmt = $conn->prepare('SELECT id FROM tbl_students WHERE id = ? LIMIT 1');
		$stmt->execute([$studentId]);
		if (!$stmt->fetchColumn()) {
			$skipped[] = $studentId . ' (student missing)';
			continue;
		}

		$stmt = $conn->prepare('SELECT id FROM tbl_parents WHERE lower(email) = lower(?) LIMIT 1');
		$stmt->execute([$email]);
		$parentId = (int)$stmt->fetchColumn();

		if ($parentId < 1) {
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$stmt = $conn->prepare('INSERT INTO tbl_parents (fname, lname, phone, email, password, status) VALUES (?,?,?,?,?,?)');
			$stmt->execute([$fname, $lname, $phone, $email, $hash, $status === 1 ? 1 : 0]);
			$parentId = (int)$conn->lastInsertId();
			$created++;
		}

		if ($parentId < 1) {
			$skipped[] = $studentId . ' (parent create failed)';
			continue;
		}

		if ($isPgsql) {
			$stmt = $conn->prepare('INSERT INTO tbl_parent_students (parent_id, student_id) VALUES (?,?) ON CONFLICT DO NOTHING');
		} else {
			$stmt = $conn->prepare('INSERT IGNORE INTO tbl_parent_students (parent_id, student_id) VALUES (?,?)');
		}
		$stmt->execute([$parentId, $studentId]);
		$linked++;

		app_audit_log($conn, 'staff', (string)$account_id, 'parent.bulk_create_link', 'parent', (string)$parentId, ['student_id' => $studentId]);
	}

	$message = 'Bulk parent import complete. Created: ' . $created . ', linked: ' . $linked . '.';
	if ($skipped) {
		$message .= ' Skipped: ' . implode(', ', array_slice($skipped, 0, 10));
		if (count($skipped) > 10) {
			$message .= ' and ' . (count($skipped) - 10) . ' more.';
		}
	}

	$_SESSION['reply'] = array(array('success', $message));
} catch (Throwable $e) {
	error_log('[' . __FILE__ . ':' . __LINE__ . '] ' . $e->getMessage());
	$_SESSION['reply'] = array(array('danger', $e->getMessage() !== '' ? $e->getMessage() : 'Bulk parent import failed.'));
}

header('location:../parents');
exit;
