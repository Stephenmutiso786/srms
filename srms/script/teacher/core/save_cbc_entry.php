<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');

header('Content-Type: application/json');

if ($res != "1" || $level != "2") {
	echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['ok' => false, 'message' => 'Invalid request']);
	exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
	$payload = $_POST;
}

$termId = (int)($payload['term_id'] ?? 0);
$classId = (int)($payload['class_id'] ?? 0);
$subjectId = (int)($payload['subject_id'] ?? 0);
$combinationId = (int)($payload['combination_id'] ?? 0);
$studentId = trim((string)($payload['student_id'] ?? ''));
$strand = trim((string)($payload['strand'] ?? ''));
$levelVal = strtoupper(trim((string)($payload['level'] ?? '')));
$marksVal = isset($payload['marks']) && $payload['marks'] !== '' ? (float)$payload['marks'] : null;
$pointsVal = (int)($payload['points'] ?? 0);
$learningArea = trim((string)($payload['learning_area'] ?? ''));
$mode = ($payload['mode'] ?? 'cbc') === 'marks' ? 'marks' : 'cbc';

if ($termId < 1 || $classId < 1 || $subjectId < 1 || $studentId === '' || $strand === '') {
	echo json_encode(['ok' => false, 'message' => 'Missing data']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	$grading = [];
	if (app_table_exists($conn, 'tbl_cbc_grading')) {
		$stmt = $conn->prepare("SELECT level, min_mark, max_mark, points, sort_order FROM tbl_cbc_grading WHERE active = 1 ORDER BY sort_order, min_mark DESC");
		$stmt->execute();
		$grading = $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	if (count($grading) < 1) {
		$grading = [
			['level' => 'EE', 'min_mark' => 90, 'max_mark' => 100, 'points' => 4, 'sort_order' => 1],
			['level' => 'ME', 'min_mark' => 75, 'max_mark' => 89, 'points' => 3, 'sort_order' => 2],
			['level' => 'AE', 'min_mark' => 50, 'max_mark' => 74, 'points' => 2, 'sort_order' => 3],
			['level' => 'BE', 'min_mark' => 0, 'max_mark' => 49, 'points' => 1, 'sort_order' => 4],
		];
	}
	$validLevels = array_values(array_unique(array_map(function ($row) {
		return strtoupper((string)$row['level']);
	}, $grading)));

	if ($mode === 'marks') {
		if ($marksVal === null) {
			echo json_encode(['ok' => false, 'message' => 'Marks required']);
			exit;
		}
		if ($marksVal < 0 || $marksVal > 100) {
			echo json_encode(['ok' => false, 'message' => 'Marks must be between 0 and 100']);
			exit;
		}
		foreach ($grading as $band) {
			$min = (float)$band['min_mark'];
			$max = (float)$band['max_mark'];
			if ($marksVal >= $min && $marksVal <= $max) {
				$levelVal = strtoupper((string)$band['level']);
				$pointsVal = (int)$band['points'];
				break;
			}
		}
	}

	if (!in_array($levelVal, $validLevels, true)) {
		echo json_encode(['ok' => false, 'message' => 'Invalid level']);
		exit;
	}

	if (app_results_locked($conn, $classId, $termId)) {
		echo json_encode(['ok' => false, 'message' => 'Results are locked for this class/term']);
		exit;
	}

	// Validate teacher assignment
	$stmt = $conn->prepare("SELECT class, teacher FROM tbl_subject_combinations WHERE id = ?");
	$stmt->execute([$combinationId]);
	$combo = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$combo || (int)$combo['teacher'] !== (int)$account_id) {
		echo json_encode(['ok' => false, 'message' => 'Not assigned to this subject']);
		exit;
	}
	$classList = app_unserialize($combo['class']);
	if (!in_array((string)$classId, array_map('strval', $classList), true)) {
		echo json_encode(['ok' => false, 'message' => 'Subject not assigned to selected class']);
		exit;
	}

	$useSubjectId = app_column_exists($conn, 'tbl_cbc_assessments', 'subject_id');
	$useMarks = app_column_exists($conn, 'tbl_cbc_assessments', 'marks');
	$usePoints = app_column_exists($conn, 'tbl_cbc_assessments', 'points');
	$useUpdated = app_column_exists($conn, 'tbl_cbc_assessments', 'updated_at');

	if (app_table_exists($conn, 'tbl_cbc_mark_submissions')) {
		$stmt = $conn->prepare("SELECT status FROM tbl_cbc_mark_submissions WHERE term_id = ? AND class_id = ? AND subject_combination_id = ? LIMIT 1");
		$stmt->execute([$termId, $classId, $combinationId]);
		$submissionStatus = (string)$stmt->fetchColumn();
		if (in_array($submissionStatus, ['submitted','approved'], true)) {
			echo json_encode(['ok' => false, 'message' => 'Marks are submitted and locked']);
			exit;
		}
	}

	$where = $useSubjectId ? "class_id = ? AND term_id = ? AND subject_id = ? AND student_id = ? AND strand = ?" : "class_id = ? AND term_id = ? AND learning_area = ? AND student_id = ? AND strand = ?";
	$args = $useSubjectId ? [$classId, $termId, $subjectId, $studentId, $strand] : [$classId, $termId, $learningArea, $studentId, $strand];

	$stmt = $conn->prepare("SELECT id, level FROM tbl_cbc_assessments WHERE $where LIMIT 1");
	$stmt->execute($args);
	$existing = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($existing) {
		$fields = "level = ?";
		$vals = [$levelVal];
		if ($useMarks) { $fields .= ", marks = ?"; $vals[] = $marksVal; }
		if ($usePoints) { $fields .= ", points = ?"; $vals[] = $pointsVal; }
		if ($useUpdated) { $fields .= ", updated_at = CURRENT_TIMESTAMP"; }
		$vals[] = $existing['id'];
		$stmt = $conn->prepare("UPDATE tbl_cbc_assessments SET $fields WHERE id = ?");
		$stmt->execute($vals);
		app_audit_log($conn, 'staff', (string)$account_id, 'cbc.update', 'cbc_assessment', (string)$existing['id']);
	} else {
		$cols = "student_id, class_id, term_id, learning_area, strand, level, teacher_id";
		$placeholders = "?,?,?,?,?,?,?";
		$vals = [$studentId, $classId, $termId, $learningArea, $strand, $levelVal, $account_id];
		if ($useSubjectId) { $cols .= ", subject_id"; $placeholders .= ",?"; $vals[] = $subjectId; }
		if ($useMarks) { $cols .= ", marks"; $placeholders .= ",?"; $vals[] = $marksVal; }
		if ($usePoints) { $cols .= ", points"; $placeholders .= ",?"; $vals[] = $pointsVal; }
		if ($useUpdated) { $cols .= ", updated_at"; $placeholders .= ",CURRENT_TIMESTAMP"; }

		$stmt = $conn->prepare("INSERT INTO tbl_cbc_assessments ($cols) VALUES ($placeholders)");
		$stmt->execute($vals);
	}

	echo json_encode(['ok' => true]);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
