<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res !== "1" || $level !== "0") { header("location:../../"); exit; }
app_require_permission('exams.manage', '../grading_system');

$returnPage = trim((string)($_GET['return'] ?? 'grading_system'));
$returnTarget = $returnPage === 'system' ? '../system' : '../grading_system';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
	header("location:" . $returnTarget);
	exit;
}

$gradingSystemId = (int)($_GET['id'] ?? 0);
if ($gradingSystemId < 1) {
	app_reply_redirect('danger', 'Invalid grading system selected.', $returnTarget);
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	app_ensure_exam_grading_schema($conn);

	if (!app_table_exists($conn, 'tbl_grading_systems')) {
		throw new RuntimeException('Grading system support is not installed.');
	}

	$stmt = $conn->prepare("SELECT name, is_default FROM tbl_grading_systems WHERE id = ? LIMIT 1");
	$stmt->execute([$gradingSystemId]);
	$system = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$system) {
		throw new RuntimeException('Grading system not found.');
	}
	if ((int)($system['is_default'] ?? 0) === 1) {
		throw new RuntimeException('The default grading system cannot be deleted. Set another default first.');
	}

	if (app_table_exists($conn, 'tbl_classes') && app_column_exists($conn, 'tbl_classes', 'grading_system_id')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_classes WHERE grading_system_id = ?");
		$stmt->execute([$gradingSystemId]);
		if ((int)$stmt->fetchColumn() > 0) {
			throw new RuntimeException('This grading system is assigned to one or more classes. Reassign those classes first.');
		}
	}

	if (app_table_exists($conn, 'tbl_exams') && app_column_exists($conn, 'tbl_exams', 'grading_system_id')) {
		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exams WHERE grading_system_id = ?");
		$stmt->execute([$gradingSystemId]);
		if ((int)$stmt->fetchColumn() > 0) {
			throw new RuntimeException('This grading system is already linked to exams. Move those exams first or keep this system.');
		}
	}

	$stmt = $conn->prepare("DELETE FROM tbl_grading_systems WHERE id = ?");
	$stmt->execute([$gradingSystemId]);
	app_reply_redirect('success', 'Grading system deleted successfully.', $returnTarget);
} catch (Throwable $e) {
	app_reply_redirect('danger', 'Failed to delete grading system: ' . $e->getMessage(), $returnTarget);
}
