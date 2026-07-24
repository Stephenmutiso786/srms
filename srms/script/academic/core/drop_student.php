<?php
chdir('../../');
session_start();
require_once('db/config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:../");
	exit;
}

$id = trim((string)($_GET['id'] ?? ''));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();
	app_delete_students($conn, [$id]);
	$conn->commit();
	app_reply_redirect('success', 'Student deleted successfully.', '../students');
} catch (Throwable $e) {
	if (isset($conn) && $conn->inTransaction()) {
		$conn->rollBack();
	}
	if (isset($conn) && app_table_exists($conn, 'tbl_students') && app_column_exists($conn, 'tbl_students', 'status')) {
		try {
			$studentSnapshot = app_student_archive_payload($conn, $id);
			$stmt = $conn->prepare("UPDATE tbl_students SET status = 0 WHERE id = ?");
			$stmt->execute([$id]);
			if ($studentSnapshot) {
				$studentRow = (array)($studentSnapshot['student'] ?? []);
				app_data_camp_store_event($conn, [
					'module_key' => 'students',
					'record_type' => 'student_blocked',
					'entity_table' => 'tbl_students',
					'entity_id' => $id,
					'title' => trim((string)($studentRow['fname'] ?? '') . ' ' . (string)($studentRow['mname'] ?? '') . ' ' . (string)($studentRow['lname'] ?? '')) ?: ('Student ' . $id),
					'description' => 'Student snapshot retained when account was blocked instead of deleted',
					'class_id' => (int)($studentRow['class'] ?? 0) > 0 ? (int)$studentRow['class'] : null,
					'student_id' => $id,
					'owner_portal' => 'admin,academic',
					'mime_type' => 'application/json',
					'status' => 'retained',
					'payload_json' => $studentSnapshot,
				]);
			}
			app_reply_redirect('warning', 'Student could not be fully deleted because linked history exists. The account has been blocked instead.', '../students');
		} catch (Throwable $ignored) {
		}
	}
	app_reply_redirect('danger', 'Unable to delete student right now.', '../students');
}
