<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/report_engine.php');
if (!isset($res) || $res !== "1" || !isset($level) || $level !== "2") { header("location:../../"); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("location:../import_results");
  exit;
}

$term = (int)($_POST['term'] ?? 0);
$class = (int)($_POST['class'] ?? 0);
$subject = (int)($_POST['subject'] ?? 0);
$examId = (int)($_POST['exam'] ?? 0);
$pasteResults = trim((string)($_POST['paste_results'] ?? ''));
$hasPasteResults = $pasteResults !== '';
$hasUpload = !empty($_FILES['file']['tmp_name']);

if (!$hasPasteResults && !$hasUpload) {
	$_SESSION['reply'] = array(array("error", "Paste results or upload a CSV file."));
	header("location:../import_results");
	exit;
}

if ($term < 1 || $class < 1 || $subject < 1 || $examId < 1) {
	$_SESSION['reply'] = array(array("error", "Select term, class, subject, and exam."));
	header("location:../import_results");
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

	if (app_results_locked($conn, $class, $term)) {
		$_SESSION['reply'] = array(array("error","Results are locked for this class/term. Contact admin."));
		header("location:../import_results");
		exit;
	}
	if ($useExamId) {
		$stmt = $conn->prepare("SELECT COALESCE(status, 'draft') AS status FROM tbl_exams WHERE id = ? AND class_id = ? AND term_id = ? LIMIT 1");
		$stmt->execute([$examId, $class, $term]);
		if (strtolower(trim((string)$stmt->fetchColumn())) === 'published') {
			throw new RuntimeException("Published exams are view-only.");
		}
	}

	$stmt = $conn->prepare("SELECT id, fname, mname, lname FROM tbl_students WHERE class = ? ORDER BY id");
	$stmt->execute([$class]);
	$studentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$studentOrder = [];
	foreach ($studentRows as $studentRow) {
		$fullName = trim(implode(' ', array_filter([
			(string)($studentRow['fname'] ?? ''),
			(string)($studentRow['mname'] ?? ''),
			(string)($studentRow['lname'] ?? ''),
		], static fn($value) => trim((string)$value) !== '')));
		$studentOrder[] = [
			'id' => (string)($studentRow['id'] ?? ''),
			'name' => $fullName,
			'key' => strtolower(preg_replace('/\s+/', ' ', trim($fullName))),
		];
	}

	$findStudentByName = function (string $name) use ($studentOrder): ?array {
		$key = strtolower(preg_replace('/\s+/', ' ', trim($name)));
		foreach ($studentOrder as $student) {
			if ($student['key'] === $key) {
				return $student;
			}
		}
		foreach ($studentOrder as $student) {
			if ($key !== '' && str_contains($student['key'], $key)) {
				return $student;
			}
		}
		return null;
	};

	$rows = [];
	if ($hasPasteResults) {
		foreach (preg_split('/\r\n|\r|\n/', $pasteResults) as $line) {
			$line = trim((string)$line);
			if ($line !== '') {
				$rows[] = $line;
			}
		}
		if (count($rows) !== count($studentOrder)) {
			throw new RuntimeException('Pasted row count must match the class list exactly.');
		}
	} else {
		$uploadCheck = app_validate_upload($_FILES['file'], ['csv']);
		if (!$uploadCheck['ok']) {
			throw new RuntimeException($uploadCheck['message']);
		}
		$handle = fopen($_FILES['file']['tmp_name'], 'r');
		if (!$handle) {
			throw new RuntimeException("Failed to open CSV file.");
		}
		$st_rec = 0;
		while (($r = fgetcsv($handle, 10000, ",")) !== false) {
			if ($st_rec === 0) { $st_rec++; continue; }
			$rows[] = $r;
		}
		fclose($handle);
	}

	$hadExistingResults = false;
	foreach ($rows as $index => $rawRow) {
		$studentId = '';
		$score = null;
		if ($hasPasteResults) {
			$line = trim((string)$rawRow);
			if (preg_match('/^\s*(.+?)\s*(?:,|\||\t|\s{2,})\s*(-?\d+(?:\.\d+)?)\s*$/', $line, $m)) {
				$match = $findStudentByName((string)$m[1]);
				if (!$match) {
					throw new RuntimeException('Unmatched student name on pasted row '.($index + 1).'.');
				}
				$studentId = (string)$match['id'];
				$score = (float)$m[2];
			} elseif (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*$/', $line, $m)) {
				if (!isset($studentOrder[$index])) {
					throw new RuntimeException('Pasted row count does not match the class list.');
				}
				$studentId = (string)$studentOrder[$index]['id'];
				$score = (float)$m[1];
			} else {
				throw new RuntimeException('Unrecognized pasted format on row '.($index + 1).'.');
			}
		} else {
			$cells = array_pad((array)$rawRow, 3, '');
			$studentId = trim((string)$cells[0]);
			$scoreRaw = trim((string)$cells[2]);
			if ($studentId === '' || $scoreRaw === '' || !is_numeric($scoreRaw)) {
				continue;
			}
			$score = (float)$scoreRaw;
		}

		if ($studentId === '' || $score === null) {
			continue;
		}
		if ($score < 0 || $score > 100) {
			throw new RuntimeException('Scores must be between 0 and 100.');
		}

		if ($useExamId) {
			$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? AND exam_id = ? LIMIT 1");
			$stmt->execute([$studentId, $class, $subject, $term, $examId]);
			$existingId = $stmt->fetchColumn();
			if (!$existingId) {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
				$stmt->execute([$studentId, $class, $subject, $term, $score, $examId]);
			} else {
				$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
				$stmt->execute([$score, $existingId]);
				$hadExistingResults = true;
			}
		} else {
			$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
			$stmt->execute([$studentId, $class, $subject, $term]);
			$existingId = $stmt->fetchColumn();
			if (!$existingId) {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
				$stmt->execute([$studentId, $class, $subject, $term, $score]);
			} else {
				$hadExistingResults = true;
			}
		}
	}

	$_SESSION['reply'] = array(array("success", $hadExistingResults ? 'Results import completed, previous results were not changed' : 'Results import completed'));
	header("location:../import_results");
	exit;
} catch (Throwable $e) {
	error_log("[".__FILE__.":".__LINE__."] ".$e->getMessage());
	$_SESSION['reply'] = array(array("error", "Import failed: ".$e->getMessage()));
	header("location:../import_results");
	exit;
}
