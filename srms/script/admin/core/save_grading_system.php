<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../../"); exit; }
app_require_permission('exams.manage', '../system');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../system");
	exit;
}

$gradingSystemId = (int)($_POST['grading_system_id'] ?? 0);
$name = trim((string)($_POST['name'] ?? ''));
$type = trim((string)($_POST['type'] ?? 'marks'));
$description = trim((string)($_POST['description'] ?? ''));
$isDefault = (int)($_POST['is_default'] ?? 0) === 1 ? 1 : 0;
$isActive = (int)($_POST['is_active'] ?? 1) === 1 ? 1 : 0;
$grades = $_POST['scale_grade'] ?? [];
$mins = $_POST['scale_min'] ?? [];
$maxs = $_POST['scale_max'] ?? [];
$points = $_POST['scale_points'] ?? [];
$remarks = $_POST['scale_remark'] ?? [];
$orders = $_POST['scale_order'] ?? [];
$activeRows = $_POST['scale_active'] ?? [];

if ($name === '') {
	app_reply_redirect('danger', 'Enter the grading system name.', '../system');
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	if (!app_table_exists($conn, 'tbl_grading_systems') || !app_table_exists($conn, 'tbl_grading_scales')) {
		throw new RuntimeException('Grading system support is not installed. Run migration 030.');
	}

	$scaleRows = [];
	foreach ($grades as $index => $grade) {
		$grade = trim((string)$grade);
		$min = trim((string)($mins[$index] ?? ''));
		$max = trim((string)($maxs[$index] ?? ''));
		if ($grade === '' || $min === '' || $max === '') {
			continue;
		}
		$scaleRows[] = [
			'grade' => $grade,
			'min' => (float)$min,
			'max' => (float)$max,
			'points' => (float)($points[$index] ?? 0),
			'remark' => trim((string)($remarks[$index] ?? '')),
			'order' => (int)($orders[$index] ?? ($index + 1)),
			'active' => isset($activeRows[$index]) ? ((int)$activeRows[$index] === 1 ? 1 : 0) : 1,
		];
	}

	if (count($scaleRows) < 1) {
		throw new RuntimeException('Add at least one grading scale row.');
	}

	$conn->beginTransaction();
	if ($isDefault === 1) {
		$conn->exec("UPDATE tbl_grading_systems SET is_default = 0");
	}

	if ($gradingSystemId > 0) {
		$stmt = $conn->prepare("UPDATE tbl_grading_systems SET name = ?, type = ?, description = ?, is_default = ?, is_active = ? WHERE id = ?");
		$stmt->execute([$name, $type, $description, $isDefault, $isActive, $gradingSystemId]);

		$stmt = $conn->prepare("SELECT COUNT(*) FROM tbl_exams WHERE grading_system_id = ? AND status IN ('finalized','published')");
		$stmt->execute([$gradingSystemId]);
		$lockedUsage = (int)$stmt->fetchColumn() > 0;
		if ($lockedUsage) {
			throw new RuntimeException('This grading system is already used by finalized/published exams. Create a new one instead of changing the scales.');
		}

		$stmt = $conn->prepare("DELETE FROM tbl_grading_scales WHERE grading_system_id = ?");
		$stmt->execute([$gradingSystemId]);
	} else {
		if (DBDriver === 'pgsql') {
			$stmt = $conn->prepare("INSERT INTO tbl_grading_systems (name, type, description, is_default, is_active) VALUES (?,?,?,?,?) RETURNING id");
			$stmt->execute([$name, $type, $description, $isDefault, $isActive]);
			$gradingSystemId = (int)$stmt->fetchColumn();
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_grading_systems (name, type, description, is_default, is_active) VALUES (?,?,?,?,?)");
			$stmt->execute([$name, $type, $description, $isDefault, $isActive]);
			$gradingSystemId = (int)$conn->lastInsertId();
		}
	}

	$stmt = $conn->prepare("INSERT INTO tbl_grading_scales (grading_system_id, min_score, max_score, grade, points, remark, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)");
	foreach ($scaleRows as $row) {
		$stmt->execute([$gradingSystemId, $row['min'], $row['max'], $row['grade'], $row['points'], $row['remark'], $row['order'], $row['active']]);
	}

	$conn->commit();
	app_reply_redirect('success', 'Grading system saved successfully.', '../system');
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	app_reply_redirect('danger', 'Failed to save grading system: '.$e->getMessage(), '../system');
}
