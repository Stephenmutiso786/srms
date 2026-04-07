<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "2") { header("location:../"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../marks_entry");
	exit;
}

$subjectId = (int)($_POST['subject_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));

if ($subjectId < 1 || $name === '') {
	$_SESSION['reply'] = array (array("error","Provide strand name."));
	header("location:../cbc_entry");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (!app_table_exists($conn, 'tbl_cbc_strands')) {
		throw new RuntimeException("CBC strands table missing. Run migration 014.");
	}

	$stmt = $conn->prepare("INSERT INTO tbl_cbc_strands (subject_id, name, status, created_by) VALUES (?,?,1,?) ON CONFLICT (subject_id, name) DO NOTHING");
	$stmt->execute([$subjectId, $name, $account_id]);

	$_SESSION['reply'] = array (array("success","Strand added."));
	header("location:../cbc_entry");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger","Failed to add strand: ".$e->getMessage()));
	header("location:../cbc_entry");
}
