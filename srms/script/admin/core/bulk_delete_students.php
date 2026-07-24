<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res !== "1" || $level !== "0") {
	header("location:../");
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../");
	exit;
}

$ids = $_POST['student_ids'] ?? [];
$action = $_POST['bulk_action'] ?? 'delete';
if (!is_array($ids)) {
	$ids = [];
}

$ids = array_values(array_unique(array_filter($ids, function ($id) {
	return is_string($id) && preg_match('/^[A-Za-z0-9_-]+$/', $id);
})));

if (count($ids) < 1) {
	$_SESSION['reply'] = array (array("error","Select at least one student to delete"));
	header("location:../students");
	exit;
}

$placeholders = implode(',', array_fill(0, count($ids), '?'));

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$conn->beginTransaction();

	if ($action === 'set_active' || $action === 'set_blocked') {
		$status = $action === 'set_active' ? 1 : 0;
		$stmt = $conn->prepare("SELECT id, fname, mname, lname, class, status FROM tbl_students WHERE id IN ($placeholders)");
		$stmt->execute($ids);
		$studentRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
		$stmt = $conn->prepare("UPDATE tbl_students SET status = ? WHERE id IN ($placeholders)");
		$stmt->execute(array_merge([$status], $ids));
		foreach ($studentRows as $studentRow) {
			$studentId = trim((string)($studentRow['id'] ?? ''));
			if ($studentId === '') {
				continue;
			}
			app_data_camp_store_event($conn, [
				'module_key' => 'students',
				'record_type' => 'student_status_changed',
				'entity_table' => 'tbl_students',
				'entity_id' => $studentId,
				'title' => trim((string)($studentRow['fname'] ?? '') . ' ' . (string)($studentRow['mname'] ?? '') . ' ' . (string)($studentRow['lname'] ?? '')) ?: ('Student ' . $studentId),
				'description' => 'Student status snapshot retained during bulk status update',
				'class_id' => (int)($studentRow['class'] ?? 0) > 0 ? (int)$studentRow['class'] : null,
				'student_id' => $studentId,
				'owner_portal' => 'admin,academic',
				'mime_type' => 'application/json',
				'status' => 'retained',
				'payload_json' => [
					'before_status' => $studentRow['status'] ?? null,
					'after_status' => $status,
					'student_snapshot' => app_student_archive_payload($conn, $studentId),
				],
				'created_by' => (int)($account_id ?? 0),
			]);
		}
		$conn->commit();
		$_SESSION['reply'] = array (array("success","Selected students updated successfully"));
		header("location:../students");
		exit;
	}

	if (app_table_exists($conn, 'tbl_students')) {
		$stmt = $conn->prepare("SELECT id, display_image FROM tbl_students WHERE id IN ($placeholders)");
		$stmt->execute($ids);
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$img = $row['display_image'] ?? '';
			if ($img !== '' && $img !== 'DEFAULT' && $img !== 'Blank') {
				@unlink('images/students/'.$img);
			}
		}
	}

	if (app_table_exists($conn, 'tbl_parent_students')) {
		$stmt = $conn->prepare("DELETE FROM tbl_parent_students WHERE student_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_cbe_assessments')) {
		$stmt = $conn->prepare("DELETE FROM tbl_cbe_assessments WHERE student_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_attendance_records')) {
		$stmt = $conn->prepare("DELETE FROM tbl_attendance_records WHERE student_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_exam_results')) {
		$stmt = $conn->prepare("DELETE FROM tbl_exam_results WHERE student IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_report_cards')) {
		if (app_table_exists($conn, 'tbl_report_card_subjects')) {
			$stmt = $conn->prepare("DELETE FROM tbl_report_card_subjects WHERE report_id IN (SELECT id FROM tbl_report_cards WHERE student_id IN ($placeholders))");
			$stmt->execute($ids);
		}
		$stmt = $conn->prepare("DELETE FROM tbl_report_cards WHERE student_id IN ($placeholders)");
		$stmt->execute($ids);
	}

	if (app_table_exists($conn, 'tbl_login_sessions') && app_column_exists($conn, 'tbl_login_sessions', 'student')) {
		$stmt = $conn->prepare("DELETE FROM tbl_login_sessions WHERE student IN ($placeholders)");
		$stmt->execute($ids);
	}

	$stmt = $conn->prepare("DELETE FROM tbl_students WHERE id IN ($placeholders)");
	$stmt->execute($ids);

	$conn->commit();
	$_SESSION['reply'] = array (array("success","Selected students deleted successfully"));
	header("location:../students");
} catch(PDOException $e) {
	if ($conn && $conn->inTransaction()) {
		$conn->rollBack();
	}
	error_log("[".__FILE__.":".__LINE__." PDO] " . $e->getMessage());
	echo "Connection failed.";
}
?>
