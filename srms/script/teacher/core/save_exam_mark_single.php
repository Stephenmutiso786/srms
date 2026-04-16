<?php
chdir('../../');
session_start();
require_once('db/config.php');
require_once('const/check_session.php');
require_once('const/school.php');
require_once('const/report_engine.php');
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

	if (app_results_locked($conn, $classId, $termId, $examId)) {
		echo json_encode(['ok' => false, 'message' => 'Results locked']);
		exit;
	}

	if (app_table_exists($conn, 'tbl_exam_mark_submissions')) {
		$stmt = $conn->prepare("SELECT status FROM tbl_exam_mark_submissions WHERE exam_id = ? AND subject_combination_id = ? LIMIT 1");
		$stmt->execute([$examId, $subjectComb]);
		$status = (string)$stmt->fetchColumn();
		if (in_array($status, ['submitted','reviewed','finalized'], true)) {
			echo json_encode(['ok' => false, 'message' => 'Marks submitted']);
			exit;
		}
	}

	$stmt = $conn->prepare("SELECT status FROM tbl_exams WHERE id = ? LIMIT 1");
	$stmt->execute([$examId]);
	$examStatus = (string)$stmt->fetchColumn();
	if (!app_exam_can_enter_marks($examStatus)) {
		echo json_encode(['ok' => false, 'message' => 'Exam is not active for mark entry']);
		exit;
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

	if ($scoreVal < 0 || $scoreVal > 100) {
		echo json_encode(['ok' => false, 'message' => 'Marks must be between 0 and 100']);
		exit;
	}
	$useExamId = app_column_exists($conn, 'tbl_exam_results', 'exam_id');
	$useGradeColumns = app_column_exists($conn, 'tbl_exam_results', 'grade_label') && app_column_exists($conn, 'tbl_exam_results', 'grade_points');
	$gradingSystemId = report_exam_grading_system_id($conn, $examId);
	list($gradeLabel, , $gradePoints) = report_grade_for_score($conn, $scoreVal, $gradingSystemId);

	if ($useExamId) {
		$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE exam_id = ? AND student = ? AND subject_combination = ? LIMIT 1");
		$stmt->execute([$examId, $studentId, $subjectComb]);
	} else {
		$stmt = $conn->prepare("SELECT id FROM tbl_exam_results WHERE student = ? AND class = ? AND subject_combination = ? AND term = ? LIMIT 1");
		$stmt->execute([$studentId, $classId, $subjectComb, $termId]);
	}
	$existing = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($existing) {
		if ($useGradeColumns) {
			$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ?, grade_label = ?, grade_points = ? WHERE id = ?");
			$stmt->execute([$scoreVal, $gradeLabel, $gradePoints, $existing['id']]);
		} else {
			$stmt = $conn->prepare("UPDATE tbl_exam_results SET score = ? WHERE id = ?");
			$stmt->execute([$scoreVal, $existing['id']]);
		}
	} else {
		if ($useExamId) {
			if ($useGradeColumns) {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id, grade_label, grade_points) VALUES (?,?,?,?,?,?,?,?)");
				$stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal, $examId, $gradeLabel, $gradePoints]);
			} else {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, exam_id) VALUES (?,?,?,?,?,?)");
				$stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal, $examId]);
			}
		} else {
			if ($useGradeColumns) {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score, grade_label, grade_points) VALUES (?,?,?,?,?,?,?)");
				$stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal, $gradeLabel, $gradePoints]);
			} else {
				$stmt = $conn->prepare("INSERT INTO tbl_exam_results (student, class, subject_combination, term, score) VALUES (?,?,?,?,?)");
				$stmt->execute([$studentId, $classId, $subjectComb, $termId, $scoreVal]);
			}
		}
	}

	echo json_encode(['ok' => true, 'grade' => $gradeLabel, 'points' => $gradePoints]);
} catch (Throwable $e) {
	echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
