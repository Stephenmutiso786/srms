<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

if ($res != "1" || $level != "2") { header("location:../"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	header("location:../cbc_entry");
	exit;
}

if (empty($_FILES['file']['tmp_name'])) {
	$_SESSION['reply'] = array (array("danger", "Upload a CSV file."));
	header("location:../cbc_entry");
	exit;
}

$termId = (int)($_POST['term_id'] ?? 0);
$classId = (int)($_POST['class_id'] ?? 0);
$subjectId = (int)($_POST['subject_id'] ?? 0);
$learningArea = trim((string)($_POST['learning_area'] ?? ''));
$mode = ($_POST['mode'] ?? 'cbc') === 'marks' ? 'marks' : 'cbc';

if ($termId < 1 || $classId < 1 || $subjectId < 1) {
	$_SESSION['reply'] = array (array("danger", "Missing term/class/subject."));
	header("location:../cbc_entry");
	exit;
}

$validLevels = ['EE', 'ME', 'AE', 'BE'];
$total = 0;
$success = 0;
$failed = 0;
$details = [];

function mapMarksToLevel($mark): array {
	if ($mark >= 90) return ['level' => 'EE', 'points' => 8];
	if ($mark >= 75) return ['level' => 'EE', 'points' => 7];
	if ($mark >= 58) return ['level' => 'ME', 'points' => 6];
	if ($mark >= 41) return ['level' => 'ME', 'points' => 5];
	if ($mark >= 31) return ['level' => 'AE', 'points' => 4];
	if ($mark >= 21) return ['level' => 'AE', 'points' => 3];
	if ($mark >= 11) return ['level' => 'BE', 'points' => 2];
	if ($mark >= 1) return ['level' => 'BE', 'points' => 1];
	return ['level' => 'BE', 'points' => 0];
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_results_locked($conn, $classId, $termId)) {
		throw new RuntimeException("Results are locked for this class/term.");
	}

	// Validate teacher assignment to subject + class
	$stmt = $conn->prepare("SELECT class FROM tbl_subject_combinations WHERE teacher = ? AND subject = ?");
	$stmt->execute([$account_id, $subjectId]);
	$combo = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$combo) {
		throw new RuntimeException("Not assigned to this subject.");
	}
	$classList = app_unserialize($combo['class']);
	if (!in_array((string)$classId, array_map('strval', $classList), true)) {
		throw new RuntimeException("Subject not assigned to selected class.");
	}

	if (!app_table_exists($conn, 'tbl_cbc_assessments')) {
		throw new RuntimeException("CBC table missing. Run migration 013.");
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

	$useSubjectId = app_column_exists($conn, 'tbl_cbc_assessments', 'subject_id');
	$useMarks = app_column_exists($conn, 'tbl_cbc_assessments', 'marks');
	$usePoints = app_column_exists($conn, 'tbl_cbc_assessments', 'points');

	while (($row = fgetcsv($handle)) !== false) {
		$total++;
		$studentId = trim((string)($row[$idx('student_id')] ?? ''));
		$strand = trim((string)($row[$idx('strand')] ?? ''));
		$level = strtoupper(trim((string)($row[$idx('level')] ?? '')));
		$marks = $idx('marks') >= 0 ? (float)($row[$idx('marks')] ?? 0) : null;

		if ($studentId === '' || $strand === '') {
			$failed++;
			$details[] = "Row $total missing student/strand.";
			continue;
		}

		$points = 0;
		if ($mode === 'marks') {
			$mapped = mapMarksToLevel($marks);
			$level = $mapped['level'];
			$points = $mapped['points'];
		}

		if (!in_array($level, $validLevels, true)) {
			$failed++;
			$details[] = "Row $total invalid level.";
			continue;
		}

		$stmt = $conn->prepare("SELECT id FROM tbl_cbc_assessments WHERE class_id = ? AND term_id = ? AND student_id = ? AND strand = ?".($useSubjectId ? " AND subject_id = ?" : " AND learning_area = ?")." LIMIT 1");
		$args = $useSubjectId ? [$classId, $termId, $studentId, $strand, $subjectId] : [$classId, $termId, $studentId, $strand, $learningArea];
		$stmt->execute($args);
		$existingId = $stmt->fetchColumn();

		if ($existingId) {
			$fields = "level = ?";
			$vals = [$level];
			if ($useMarks) { $fields .= ", marks = ?"; $vals[] = $marks; }
			if ($usePoints) { $fields .= ", points = ?"; $vals[] = $points; }
			$vals[] = $existingId;
			$stmt = $conn->prepare("UPDATE tbl_cbc_assessments SET $fields WHERE id = ?");
			$stmt->execute($vals);
		} else {
			$cols = "student_id, class_id, term_id, learning_area, strand, level, teacher_id";
			$placeholders = "?,?,?,?,?,?,?";
			$vals = [$studentId, $classId, $termId, $learningArea, $strand, $level, $account_id];
			if ($useSubjectId) { $cols .= ", subject_id"; $placeholders .= ",?"; $vals[] = $subjectId; }
			if ($useMarks) { $cols .= ", marks"; $placeholders .= ",?"; $vals[] = $marks; }
			if ($usePoints) { $cols .= ", points"; $placeholders .= ",?"; $vals[] = $points; }
			$stmt = $conn->prepare("INSERT INTO tbl_cbc_assessments ($cols) VALUES ($placeholders)");
			$stmt->execute($vals);
		}

		$success++;
	}

	fclose($handle);

	$_SESSION['reply'] = array (array("success", "Import done. Total: $total, Success: $success, Failed: $failed"));
	header("location:../cbc_entry");
} catch (Throwable $e) {
	$_SESSION['reply'] = array (array("danger", "Import failed: ".$e->getMessage()));
	header("location:../cbc_entry");
}
