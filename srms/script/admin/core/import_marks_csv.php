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

$classIdForm = (int)($_POST['class_id'] ?? 0);
$termIdForm = (int)($_POST['term_id'] ?? 0);
$subjectIdForm = (int)($_POST['subject_id'] ?? 0);

if ($classIdForm < 1 || $termIdForm < 1 || $subjectIdForm < 1) {
	$_SESSION['reply'] = array (array("danger", "Select class, term, and subject."));
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

	$combos = [];
	$stmt = $conn->prepare("SELECT id, class, subject FROM tbl_subject_combinations WHERE subject = ?");
	$stmt->execute([$subjectIdForm]);
	foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
		$combos[] = $row;
	}

	$comboId = 0;
	foreach ($combos as $combo) {
		$classes = app_unserialize($combo['class']);
		if (in_array((string)$classIdForm, $classes, true)) {
			$comboId = (int)$combo['id'];
			break;
		}
	}

	if ($comboId < 1) {
		throw new RuntimeException("No subject combination found for selected class/subject.");
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

	while (($row = fgetcsv($handle)) !== false) {
		$total++;
		$studentId = trim((string)($row[$idx('student_id')] ?? $row[$idx('student')] ?? ''));
		$score = $row[$idx('score')] ?? '';
		$score = is_numeric($score) ? (float)$score : null;

		if ($studentId === '' || $score === null) {
			$failed++;
			$details[] = "Row $total missing student or score.";
			continue;
		}

		try {
			$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
			$stmt->execute([$studentId, $classIdForm, $comboId, $termIdForm]);
			$existing = $stmt->fetchColumn();

			if ($existing) {
				$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
				$stmt->execute([$score, $existing]);
			} else {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
				$stmt->execute([$studentId, $classIdForm, $comboId, $termIdForm, $score]);
			}
			$success++;
		} catch (Throwable $e) {
			$failed++;
			$details[] = "Row $total error: ".$e->getMessage();
		}
	}

	fclose($handle);

	if (app_table_exists($conn, 'tbl_import_logs')) {
		$stmt = $conn->prepare("INSERT INTO tbl_import_logs (import_type, total, success, failed, details, created_by) VALUES (?,?,?,?,?,?)");
		$stmt->execute(['marks', $total, $success, $failed, implode("\n", $details), $account_id]);
	}

	$_SESSION['reply'] = array (array("success", "Import done. Total: $total, Success: $success, Failed: $failed"));
	header("location:../import_export");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Import failed: ".$e->getMessage()));
	header("location:../import_export");
}
