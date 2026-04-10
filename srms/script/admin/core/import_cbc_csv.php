<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/rbac.php');

if ($res != "1" || $level != "0") { header("location:../"); }
app_require_permission('marks.enter', '../import_export');

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
	app_require_unlocked('exams', '../import_export');

	if (!app_table_exists($conn, 'tbl_cbc_assessments')) {
		throw new RuntimeException("CBC table missing. Run migration 013.");
	}

	$grading = [];
	if (app_table_exists($conn, 'tbl_cbc_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points, sort_order FROM tbl_cbc_grading WHERE active = 1 ORDER BY sort_order, min_mark DESC");
		$stmt->execute();
		$grading = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (count($grading) < 1) {
		$grading = [
			['level' => 'EE', 'min_mark' => 80, 'max_mark' => 100, 'points' => 4, 'sort_order' => 1],
			['level' => 'ME', 'min_mark' => 60, 'max_mark' => 79, 'points' => 3, 'sort_order' => 2],
			['level' => 'AE', 'min_mark' => 40, 'max_mark' => 59, 'points' => 2, 'sort_order' => 3],
			['level' => 'BE', 'min_mark' => 0, 'max_mark' => 39, 'points' => 1, 'sort_order' => 4],
		];
	}
	$validLevels = array_values(array_unique(array_map(function ($row) {
		return strtoupper((string)$row['level']);
	}, $grading)));

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

	while (($row = fgetcsv($handle)) !== false) {
		$total++;
		$studentId = trim((string)($row[$idx('student_id')] ?? ''));
		$classId = (int)($row[$idx('class_id')] ?? 0);
		$termId = (int)($row[$idx('term_id')] ?? 0);
		$learningArea = trim((string)($row[$idx('learning_area')] ?? ''));
		$strand = trim((string)($row[$idx('strand')] ?? ''));
		$level = strtoupper(trim((string)($row[$idx('level')] ?? '')));

		if ($studentId === '' || $classId < 1 || $termId < 1 || $learningArea === '' || !in_array($level, $validLevels, true)) {
			$failed++;
			$details[] = "Row $total invalid data.";
			continue;
		}

		try {
			$stmt = $conn->prepare("INSERT INTO tbl_cbc_assessments (student_id, class_id, term_id, learning_area, strand, level, teacher_id) VALUES (?,?,?,?,?,?,?)");
			$stmt->execute([$studentId, $classId, $termId, $learningArea, $strand, $level, $account_id]);
			$success++;
		} catch (Throwable $e) {
			$failed++;
			$details[] = "Row $total error: ".$e->getMessage();
		}
	}

	fclose($handle);

	if (app_table_exists($conn, 'tbl_import_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_import_logs (import_type, total, success, failed, details, created_by) VALUES (?,?,?,?,?,?)");
		$stmt->execute(['cbc', $total, $success, $failed, implode("\n", $details), $account_id]);
	}

	$_SESSION['reply'] = array (array("success", "Import done. Total: $total, Success: $success, Failed: $failed"));
	header("location:../import_export");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Import failed: ".$e->getMessage()));
	header("location:../import_export");
}
