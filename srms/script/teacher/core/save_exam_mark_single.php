<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
if ($res == "1" && $level == "2") {}else{header("location:../"); exit;}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	echo json_encode(['ok' => false, 'message' => 'Invalid request']);
	exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) { $payload = $_POST; }

$examId = (int)($payload['exam_id'] ?? 0);
$classId = (int)($payload['class_id'] ?? 0);
$termId = (int)($payload['term_id'] ?? 0);
$subjectComb = (int)($payload['subject_combination'] ?? 0);
$studentId = (string)($payload['student_id'] ?? '');
$scoreVal = isset($payload['score']) ? (float)$payload['score'] : null;

if ($examId < 1 || $classId < 1 || $termId < 1 || $subjectComb < 1 || $studentId === '' || $scoreVal === null) {
	echo json_encode(['ok' => false, 'message' => 'Missing data']);
	exit;
}

try {
	$conn = app_db();
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

	if (app_results_locked($conn, $classId, $termId)) {
		echo json_encode(['ok' => false, 'message' => 'Results locked']);
		exit;
	}

	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$stmt = $conn->prepare("SELECT status FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
		$stmt->execute([$examId, $subjectComb]);
		$status = (string)$stmt->fetchColumn();
		if (in_array($status, ['submitted','approved'], true)) {
			echo json_encode(['ok' => false, 'message' => 'Marks submitted']);
			exit;
		}
	}

	$stmt = $conn->prepare("SELECT id, class, teacher FROM tbl_subject_combinations WHERE id = ?");
	$stmt->execute([$subjectComb]);
	$combo = $stmt->fetch(PDO::FETCH_ASSOC);
	if (!$combo || (int)$combo['teacher'] !== (int)$account_id) {
		echo json_encode(['ok' => false, 'message' => 'Not assigned']);
		exit;
	}
	$classList = app_unserialize($combo['class']);
	if (!in_array((string)$classId, array_map('strval', $classList), true)) {
		echo json_encode(['ok' => false, 'message' => 'Invalid class']);
		exit;
	}

	$scoreVal = max(0, min(100, $scoreVal));
	$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');

	if ($useExamId) {
		$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE exam_id = ? AND student = ? AND subject_combination = ? LIMIT 1");
		$stmt->execute([$examId, $studentId, $subjectComb]);
	} else {
		$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
		$stmt->execute([$studentId, $classId, $subjectComb, $termId]);
	}
	$existing = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($existing) {
		$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
		$stmt->execute([$scoreVal, $existing['id']]);
	} else {
		if ($useExamId) {
			$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
			$stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal, $examId]);
		} else {
			$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
			$stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal]);
		}
	}

	echo json_encode(['ok' => true]);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
