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

if (!in_array($type, ['marks', 'cbc'], true)) {
	app_reply_redirect('danger', 'Invalid grading system type selected.', '../system');
}

function app_grading_stmt(PDO $conn, string $sql, array $params = [], string $context = 'query'): PDOStatement
{
	try {
		$stmt = $conn->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	} catch (Throwable $e) {
		throw new RuntimeException("Failed while saving {$context}: ".$e->getMessage());
	}
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

	foreach ($scaleRows as $row) {
		if ($row['min'] > $row['max']) {
			throw new RuntimeException('Each grading scale must have Min less than or equal to Max.');
		}
	}
	$gradeNames = [];
	foreach ($scaleRows as $row) {
		$normalized = strtoupper(trim((string)$row['grade']));
		if (isset($gradeNames[$normalized])) {
			throw new RuntimeException("Duplicate grading scale '{$row['grade']}' detected. Use each grade label once.");
		}
		$gradeNames[$normalized] = true;
	}
	usort($scaleRows, function (array $left, array $right): int {
		if ($left['min'] === $right['min']) {
			return $left['order'] <=> $right['order'];
		}
		return $left['min'] <=> $right['min'];
	});
	for ($index = 1; $index < count($scaleRows); $index++) {
		$previous = $scaleRows[$index - 1];
		$current = $scaleRows[$index];
		if ($current['min'] <= $previous['max']) {
			throw new RuntimeException("Grading scales '{$previous['grade']}' and '{$current['grade']}' overlap. Adjust the score ranges so they do not collide.");
		}
	}

	$conn->beginTransaction();

	if ($gradingSystemId > 0) {
		$stmt = app_grading_stmt($conn, "SELECT COUNT(*) FROM tbl_exams WHERE grading_system_id = ? AND status IN ('finalized','published')", [$gradingSystemId], 'locked exam usage check');
		$lockedUsage = (int)$stmt->fetchColumn() > 0;
		if ($lockedUsage) {
			throw new RuntimeException('This grading system is already used by finalized/published exams. Create a new one instead of changing the scales.');
		}

		if ($isDefault === 1) {
			app_grading_stmt($conn, "UPDATE tbl_grading_systems SET is_default = 0", [], 'default system reset');
		}
		app_grading_stmt($conn, "UPDATE tbl_grading_systems SET name = ?, type = ?, description = ?, is_default = ?, is_active = ? WHERE id = ?", [$name, $type, $description, $isDefault, $isActive, $gradingSystemId], 'grading system update');
		app_grading_stmt($conn, "DELETE FROM tbl_grading_scales WHERE grading_system_id = ?", [$gradingSystemId], 'existing scale cleanup');
	} else {
		if ($isDefault === 1) {
			app_grading_stmt($conn, "UPDATE tbl_grading_systems SET is_default = 0", [], 'default system reset');
		}
		if (DBDriver === 'pgsql') {
			$stmt = app_grading_stmt($conn, "INSERT INTO tbl_grading_systems (name, type, description, is_default, is_active) VALUES (?,?,?,?,?) RETURNING id", [$name, $type, $description, $isDefault, $isActive], 'grading system creation');
			$gradingSystemId = (int)$stmt->fetchColumn();
		} else {
			app_grading_stmt($conn, "INSERT INTO tbl_grading_systems (name, type, description, is_default, is_active) VALUES (?,?,?,?,?)", [$name, $type, $description, $isDefault, $isActive], 'grading system creation');
			$gradingSystemId = (int)$conn->lastInsertId();
		}
	}

	foreach ($scaleRows as $row) {
		app_grading_stmt(
			$conn,
			"INSERT INTO tbl_grading_scales (grading_system_id, min_score, max_score, grade, points, remark, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?)",
			[$gradingSystemId, $row['min'], $row['max'], $row['grade'], $row['points'], $row['remark'], $row['order'], $row['active']],
			"grading scale '{$row['grade']}'"
		);
	}

	$conn->commit();
	app_reply_redirect('success', 'Grading system saved successfully.', '../system');
} catch (Throwable $e) {
	if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
		$conn->rollBack();
	}
	app_reply_redirect('danger', 'Failed to save grading system: '.$e->getMessage(), '../system');
}
